<?php

namespace App\Services;

use App\Exceptions\FontAwesomeException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Ikony Font Awesome: wyszukiwanie jak na fontawesome.com i pobieranie kształtu ikony.
 * Z tokenem (FONT_AWESOME_API_TOKEN) kształty podaje API, także ikon z planu Pro.
 * Bez tokena API zna tylko nazwy i licencje, więc kształty darmowych ikon pochodzą z paczki fontawesome-free.
 */
class FontAwesomeService
{
    private const API_URL = 'https://api.fontawesome.com';

    private const API_VERSION = '7.x';

    private const TOKEN_URL = 'https://api.fontawesome.com/token';

    /** Pola kształtu - bez tokena API odrzuca je jako "unauthorized". */
    private const SVG_FIELDS = 'svgs(filter: { familyStyles: [
        { family: CLASSIC, style: SOLID },
        { family: CLASSIC, style: BRANDS }
    ] }) { width height pathData }';

    private const CDN_URL = 'https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@7.3.1/svgs';

    /** Darmowe style w kolejności wyboru. */
    private const STYLES = ['solid', 'brands', 'regular'];

    private const API_RESULTS = 60;

    private const MAX_RESULTS = 30;

    private const CACHE_SECONDS = 2592000;

    /**
     * Wyszukuje ikony. Pomija te, których kształtu nie można pobrać - bez tokena to ikony płatne.
     *
     * @return array<int, array{name: string, label: string, svg: array{viewBox: string, path: string}|null, preview_url: string|null}>
     *
     * @throws ValidationException
     * @throws FontAwesomeException
     */
    public function search(mixed $query): array
    {
        $validated = Validator::make(
            ['q' => $query],
            ['q' => ['required', 'string', 'min:2', 'max:50']],
            [],
            ['q' => 'szukana ikona'],
        )->validate();

        $query = mb_strtolower(trim($validated['q']));

        return Cache::remember($this->cacheKey('search', md5($query)), self::CACHE_SECONDS, function () use ($query) {
            $data = $this->graphql(
                'query ($version: String!, $query: String!, $first: Int!) {
                    search(version: $version, query: $query, first: $first) {
                        id label familyStylesByLicense { free { family style } } '.$this->svgFields().'
                    }
                }',
                ['query' => $query, 'first' => self::API_RESULTS],
            );

            $icons = [];

            foreach ($data['search'] ?? [] as $icon) {
                $svg = $this->apiSvg($icon);
                $style = $this->freeStyle($icon);

                if ($svg !== null || $style !== null) {
                    $icons[] = [
                        'name' => $icon['id'],
                        'label' => $icon['label'],
                        'svg' => $svg,
                        'preview_url' => $style === null ? null : $this->svgUrl($style, $icon['id']),
                    ];
                }
            }

            return array_slice($icons, 0, self::MAX_RESULTS);
        });
    }

    /**
     * Kształt ikony do zapisania w bazie albo null, gdy nie ma takiej dostępnej ikony.
     *
     * @return array{viewBox: string, path: string}|null
     *
     * @throws FontAwesomeException
     */
    public function find(string $name): ?array
    {
        return Cache::remember($this->cacheKey('icon', $name), self::CACHE_SECONDS, function () use ($name) {
            $data = $this->graphql(
                'query ($version: String!, $name: String!) {
                    release(version: $version) {
                        icon(name: $name) { id label familyStylesByLicense { free { family style } } '.$this->svgFields().' }
                    }
                }',
                ['name' => $name],
            );

            $icon = $data['release']['icon'] ?? null;

            if ($icon === null) {
                return null;
            }

            $style = $this->freeStyle($icon);

            return $this->apiSvg($icon) ?? ($style === null ? null : $this->parseSvg($this->request(
                fn () => $this->http()->get($this->svgUrl($style, $name)),
            )->body()));
        });
    }

    private function token(): ?string
    {
        return config('services.font_awesome.api_token') ?: null;
    }

    private function svgFields(): string
    {
        return $this->token() === null ? '' : self::SVG_FIELDS;
    }

    /** Wyniki z tokenem i bez niego się różnią, a dodanie tokena nie może zwracać starych. */
    private function cacheKey(string $type, string $key): string
    {
        return 'font-awesome:'.($this->token() === null ? 'free' : 'token').":{$type}:{$key}";
    }

    /**
     * Token z konta wymienia się na tymczasowy token dostępu, ważny zwykle godzinę.
     *
     * @throws FontAwesomeException
     */
    private function accessToken(string $token): string
    {
        $key = 'font-awesome:access-token:'.md5($token);
        $cached = Cache::get($key);

        if (is_string($cached)) {
            return $cached;
        }

        $response = $this->request(fn () => $this->http()->withToken($token)->post(self::TOKEN_URL));
        $accessToken = $response->json('access_token');

        if (! is_string($accessToken)) {
            throw new FontAwesomeException('Nieprawidłowa odpowiedź przy wymianie tokena.');
        }

        Cache::put($key, $accessToken, max((int) $response->json('expires_in', 3600) - 60, 60));

        return $accessToken;
    }

    /**
     * Kształt z odpowiedzi API - tylko gdy był dostępny dla tego tokena.
     *
     * @param  array<string, mixed>  $icon
     * @return array{viewBox: string, path: string}|null
     */
    private function apiSvg(array $icon): ?array
    {
        $svg = $icon['svgs'][0] ?? null;

        if ($svg === null) {
            return null;
        }

        $path = implode(' ', array_filter($svg['pathData'] ?? []));

        return $this->validSvg("0 0 {$svg['width']} {$svg['height']}", $path);
    }

    /**
     * @return array{viewBox: string, path: string}
     *
     * @throws FontAwesomeException
     */
    private function validSvg(string $viewBox, string $path): array
    {
        if (preg_match('/^[0-9. ]+$/', $viewBox) !== 1 || preg_match('/^[MmLlHhVvCcSsQqTtAaZz0-9 ,.\-eE]+$/', $path) !== 1) {
            throw new FontAwesomeException('Nieczytelny kształt ikony.');
        }

        return ['viewBox' => $viewBox, 'path' => $path];
    }

    /**
     * @param  array<string, mixed>  $icon
     */
    private function freeStyle(array $icon): ?string
    {
        $free = array_column($icon['familyStylesByLicense']['free'] ?? [], 'style');

        foreach (self::STYLES as $style) {
            if (in_array($style, $free, true)) {
                return $style;
            }
        }

        return null;
    }

    private function svgUrl(string $style, string $name): string
    {
        return self::CDN_URL."/{$style}/{$name}.svg";
    }

    /**
     * Z pliku zostają tylko dwie wartości i ich format jest sprawdzany - do bazy nie trafia kod SVG.
     *
     * @return array{viewBox: string, path: string}
     */
    private function parseSvg(string $svg): array
    {
        $found = preg_match('/viewBox="([^"]+)"/', $svg, $viewBox) === 1
            && preg_match('/<path[^>]*\sd="([^"]+)"/', $svg, $path) === 1;

        if (! $found) {
            throw new FontAwesomeException('Nieczytelny plik ikony.');
        }

        return $this->validSvg($viewBox[1], $path[1]);
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    private function graphql(string $query, array $variables): array
    {
        $token = $this->token();
        $request = $this->http();

        if ($token !== null) {
            $request = $request->withToken($this->accessToken($token));
        }

        $response = $this->request(fn () => $request->post(self::API_URL, [
            'query' => $query,
            'variables' => ['version' => self::API_VERSION, ...$variables],
        ]));

        $data = $response->json('data');
        $errors = array_column($response->json('errors') ?? [], 'message');

        // "unauthorized" dotyczy pojedynczych kształtów spoza planu - reszta wyników jest poprawna.
        if (! is_array($data) || array_diff($errors, ['unauthorized']) !== []) {
            throw new FontAwesomeException('Nieprawidłowa odpowiedź Font Awesome.');
        }

        return $data;
    }

    private function http(): PendingRequest
    {
        return Http::timeout(5)->withOptions(['verify' => config('services.font_awesome.ca_bundle') ?: true]);
    }

    /**
     * @param  callable(): Response  $send
     */
    private function request(callable $send): Response
    {
        try {
            return $send()->throw();
        } catch (ConnectionException|RequestException $exception) {
            throw new FontAwesomeException('Font Awesome jest niedostępny.', previous: $exception);
        }
    }
}
