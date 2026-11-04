<?php

use App\Enums\EquipmentIcon;
use App\Models\Car;
use App\Models\Color;
use App\Models\Equipment;
use App\Models\User;
use App\Repositories\Contracts\ColorRepositoryInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

// Żaden test nie może wyjść do prawdziwego Font Awesome; lokalny SSR Inertii działa po http.
beforeEach(function () {
    Http::preventStrayRequests();
    Http::allowStrayRequests(['http://*']);
    config(['services.font_awesome.api_token' => null]);
});

/** Dane formularza - kolory wymagają też wzoru. */
function dictionaryData(string $route, string $name): array
{
    return str_contains($route, 'colors')
        ? ['name' => $name, 'hex' => '#'.substr(md5($name), 0, 6)]
        : ['name' => $name];
}

/** Odpowiedzi Font Awesome: "snowflake" jest darmowa, "cloud-snow" płatna. */
function fakeFontAwesome(): void
{
    $free = ['id' => 'snowflake', 'label' => 'Snowflake', 'familyStylesByLicense' => ['free' => [['family' => 'classic', 'style' => 'solid']]]];
    $pro = ['id' => 'cloud-snow', 'label' => 'Cloud Snow', 'familyStylesByLicense' => ['free' => []]];

    Http::fake([
        'api.fontawesome.com' => function (Request $request) use ($free, $pro) {
            $name = $request['variables']['name'] ?? null;

            return Http::response(['data' => [
                'search' => [$pro, $free],
                'release' => ['icon' => match ($name) {
                    'snowflake' => $free,
                    'cloud-snow' => $pro,
                    default => null,
                }],
            ]]);
        },
        'cdn.jsdelivr.net/*' => Http::response('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path d="M224 0c17 0 32 14 32 32z"/></svg>'),
    ]);
}

dataset('dictionaries', [
    'wyposażenie' => ['admin.dictionary.equipment', Equipment::class],
    'kolory' => ['admin.dictionary.colors', Color::class],
]);

test('administrator can add an option', function (string $route, string $model) {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route($route.'.store'), dictionaryData($route, 'Nowa opcja'))
        ->assertRedirect(route('admin.dictionary.index'));

    expect($model::query()->sole()->name)->toBe('Nowa opcja');
})->with('dictionaries');

test('option name is validated', function (string $route, string $model, mixed $name) {
    $model::query()->create(['name' => 'Zajęta']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route($route.'.store'), dictionaryData($route, $name))
        ->assertSessionHasErrors('name');

    expect($model::query()->count())->toBe(1);
})->with('dictionaries')->with([
    'pusta' => [''],
    'duplikat' => ['Zajęta'],
    'za długa' => [str_repeat('a', 101)],
]);

test('administrator can hide and restore an option', function (string $route, string $model) {
    $option = $model::query()->create(['name' => 'Opcja']);
    $this->actingAs(User::factory()->admin()->create());

    $this->patch(route($route.'.toggle', $option->id))->assertRedirect(route('admin.dictionary.index'));
    expect($option->refresh()->is_active)->toBeFalse();

    $this->patch(route($route.'.toggle', $option->id));
    expect($option->refresh()->is_active)->toBeTrue();
})->with('dictionaries');

test('user cannot change options', function (string $route, string $model) {
    $option = $model::query()->create(['name' => 'Opcja']);
    $this->actingAs(User::factory()->create());

    $this->post(route($route.'.store'), dictionaryData($route, 'Nowa'))->assertForbidden();
    $this->patch(route($route.'.toggle', $option->id))->assertForbidden();

    expect($option->refresh()->is_active)->toBeTrue()
        ->and($model::query()->count())->toBe(1);
})->with('dictionaries');

test('administrator can edit an option', function (string $route, string $model) {
    $option = $model::query()->create(['name' => 'Stara nazwa']);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route($route.'.update', $option->id), dictionaryData($route, 'Nowa nazwa'))
        ->assertRedirect(route('admin.dictionary.index'));

    expect($option->refresh()->name)->toBe('Nowa nazwa');
})->with('dictionaries');

test('edited option keeps its own name but cannot take another one', function (string $route, string $model) {
    $option = $model::query()->create(['name' => 'Opcja']);
    $model::query()->create(['name' => 'Zajęta']);
    $this->actingAs(User::factory()->admin()->create());

    $this->put(route($route.'.update', $option->id), dictionaryData($route, 'Opcja'))->assertSessionHasNoErrors();
    $this->put(route($route.'.update', $option->id), dictionaryData($route, 'Zajęta'))->assertSessionHasErrors('name');

    expect($option->refresh()->name)->toBe('Opcja');
})->with('dictionaries');

test('user cannot edit an option', function (string $route, string $model) {
    $option = $model::query()->create(['name' => 'Opcja']);

    $this->actingAs(User::factory()->create())
        ->put(route($route.'.update', $option->id), dictionaryData($route, 'Nowa'))
        ->assertForbidden();

    expect($option->refresh()->name)->toBe('Opcja');
})->with('dictionaries');

test('editing unknown option returns 404', function (string $route, string $model) {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route($route.'.update', 999), dictionaryData($route, 'Nowa'))
        ->assertNotFound();
})->with('dictionaries');

test('equipment icon can be changed and removed', function () {
    $equipment = Equipment::query()->create(['name' => 'GPS', 'icon' => 'location-dot']);
    $this->actingAs(User::factory()->admin()->create());

    $this->put(route('admin.dictionary.equipment.update', $equipment->id), ['name' => 'GPS', 'icon' => 'road']);
    expect($equipment->refresh()->icon)->toBe('road');

    $this->put(route('admin.dictionary.equipment.update', $equipment->id), ['name' => 'GPS', 'icon' => '']);
    expect($equipment->refresh()->icon)->toBeNull();
});

test('renaming a color updates cars using it', function () {
    $color = Color::query()->create(['name' => 'Biały']);
    $car = Car::query()->create([
        'brand' => 'Toyota',
        'model' => 'Corolla',
        'registration_number' => 'RKR 12345',
        'vin' => 'JTDBR32E720123456',
        'production_year' => 2022,
        'color' => 'Biały',
        'fuel_type' => 'hybrid',
        'transmission' => 'automatic',
        'doors' => 5,
        'seats' => 5,
        'mileage_km' => 15000,
        'daily_rate' => '189.99',
        'deposit_amount' => '1000',
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.dictionary.colors.update', $color->id), ['name' => 'Perłowy', 'hex' => '#eeeeee']);

    expect($car->refresh()->color)->toBe('Perłowy');
});

test('equipment can be added with a built-in icon', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.dictionary.equipment.store'), ['name' => 'Klimatyzacja', 'icon' => 'snowflake'])
        ->assertRedirect(route('admin.dictionary.index'));

    expect(Equipment::query()->sole()->icon)->toBe('snowflake')
        ->and(Equipment::query()->sole()->icon_svg)->toBeNull();
});

test('equipment can be added without an icon', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.dictionary.equipment.store'), ['name' => 'Klimatyzacja', 'icon' => ''])
        ->assertRedirect(route('admin.dictionary.index'));

    expect(Equipment::query()->sole()->icon)->toBeNull();
});

test('equipment icon must exist', function () {
    fakeFontAwesome();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.dictionary.equipment.store'), ['name' => 'Klimatyzacja', 'icon' => 'fa-hacker'])
        ->assertSessionHasErrors('icon');

    expect(Equipment::query()->count())->toBe(0);
});

test('built-in icons match the frontend list', function () {
    $source = file_get_contents(resource_path('js/components/ui/equipment-icon.tsx'));
    preg_match_all("/^    '([a-z-]+)': \\{ icon:/m", $source, $matches);

    expect($matches[1])->toEqualCanonicalizing(EquipmentIcon::values());
});

test('car form gets equipment icons', function () {
    Equipment::query()->create(['name' => 'GPS', 'icon' => 'location-dot']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.cars.create'))
        ->assertInertia(fn (Assert $page) => $page->where('equipment.0.icon', 'location-dot'));
});

test('car form gets only active options', function () {
    Equipment::query()->create(['name' => 'GPS']);
    Equipment::query()->create(['name' => 'Ukryte', 'is_active' => false]);
    Color::query()->create(['name' => 'Biały']);
    Color::query()->create(['name' => 'Ukryty', 'is_active' => false]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.cars.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('equipment', 1)
            ->where('equipment.0.name', 'GPS')
            ->has('colors', 1)
            ->where('colors.0.name', 'Biały'));
});

// Duplikaty

test('option name cannot differ only by case, spaces or diacritics', function (string $route, string $model, string $name) {
    $model::query()->create(['name' => 'Żółty pas']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route($route.'.store'), dictionaryData($route, $name))
        ->assertSessionHasErrors('name');

    expect($model::query()->count())->toBe(1);
})->with('dictionaries')->with([
    'wielkie litery' => ['ŻÓŁTY PAS'],
    'spacje' => ['  Żółty    pas '],
    'bez ogonków' => ['Zolty pas'],
]);

test('name is saved without extra spaces', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.dictionary.equipment.store'), ['name' => '  Czujniki   parkowania ']);

    expect(Equipment::query()->sole()->name)->toBe('Czujniki parkowania');
});

test('concurrent duplicate is reported as validation error', function () {
    $repository = $this->mock(ColorRepositoryInterface::class);
    $repository->shouldReceive('colorNames')->andReturn([]);
    $repository->shouldReceive('createColor')
        ->andThrow(new UniqueConstraintViolationException('sqlite', 'insert', [], new Exception('duplicate')));

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.dictionary.colors.store'), ['name' => 'Biały', 'hex' => '#ffffff'])
        ->assertSessionHasErrors('name');
});

// Wzór koloru

test('color is saved with lowercase swatch', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.dictionary.colors.store'), ['name' => 'Biały', 'hex' => '#FFFFFF']);

    expect(Color::query()->sole()->hex)->toBe('#ffffff');
});

test('color swatch is validated', function (mixed $hex) {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.dictionary.colors.store'), ['name' => 'Biały', 'hex' => $hex])
        ->assertSessionHasErrors('hex');
})->with([
    'brak' => [''],
    'nazwa' => ['red'],
    'skrót' => ['#fff'],
    'wstrzyknięcie' => ['#fff;background:url(x)'],
]);

test('two colors cannot share a swatch', function () {
    $color = Color::query()->create(['name' => 'Biały', 'hex' => '#ffffff']);
    $this->actingAs(User::factory()->admin()->create());

    $this->post(route('admin.dictionary.colors.store'), ['name' => 'Śnieżny', 'hex' => '#FFFFFF'])
        ->assertSessionHasErrors('hex');

    $this->put(route('admin.dictionary.colors.update', $color->id), ['name' => 'Biel', 'hex' => '#ffffff'])
        ->assertSessionHasNoErrors();
});

// Paginacja i wyszukiwanie

test('lists are paginated separately', function () {
    foreach (range(1, 12) as $number) {
        Equipment::query()->create(['name' => sprintf('Pozycja %02d', $number)]);
    }

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dictionary.index', ['equipment_page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('equipment.data', 2)
            ->where('equipment.data.0.name', 'Pozycja 11')
            ->where('equipment.total', 12)
            ->where('equipment.last_page', 2)
            ->where('colors.current_page', 1));
});

test('lists can be searched', function () {
    Equipment::query()->create(['name' => 'Klimatyzacja']);
    Equipment::query()->create(['name' => 'GPS']);
    Color::query()->create(['name' => 'Biały']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dictionary.index', ['equipment_search' => 'klima']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('equipment.data', 1)
            ->where('equipment.data.0.name', 'Klimatyzacja')
            ->has('colors.data', 1)
            ->where('filters.equipment_search', 'klima'));
});

test('search treats percent as text and ignores non-text values', function () {
    Equipment::query()->create(['name' => 'GPS']);
    $this->actingAs(User::factory()->admin()->create());

    $this->get(route('admin.dictionary.index', ['equipment_search' => '%']))
        ->assertInertia(fn (Assert $page) => $page->has('equipment.data', 0));

    $this->get(route('admin.dictionary.index', ['equipment_search' => ['a']]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('equipment.data', 1));
});

test('saving returns to the same page of the list', function () {
    $option = Equipment::query()->create(['name' => 'GPS']);
    $list = route('admin.dictionary.index', ['equipment_page' => 2, 'equipment_search' => 'g']);

    $this->actingAs(User::factory()->admin()->create())
        ->from($list)
        ->patch(route('admin.dictionary.equipment.toggle', $option->id))
        ->assertRedirect($list);
});

// Ikony Font Awesome

test('icon search returns only free icons', function () {
    fakeFontAwesome();

    $this->actingAs(User::factory()->admin()->create())
        ->getJson(route('admin.dictionary.icons', ['q' => 'snow']))
        ->assertOk()
        ->assertJsonCount(1, 'icons')
        ->assertJsonPath('icons.0.name', 'snowflake')
        ->assertJsonPath('icons.0.preview_url', fn (string $url) => str_contains($url, '/solid/snowflake.svg'));
});

test('icon search validates query', function (mixed $query) {
    $this->actingAs(User::factory()->admin()->create())
        ->getJson(route('admin.dictionary.icons', ['q' => $query]))
        ->assertUnprocessable();
})->with(['za krótkie' => ['a'], 'puste' => [''], 'tablica' => [['snow']]]);

test('icon search reports unavailable service', function () {
    Http::fake(['api.fontawesome.com' => Http::response('', 500)]);

    $this->actingAs(User::factory()->admin()->create())
        ->getJson(route('admin.dictionary.icons', ['q' => 'snow']))
        ->assertStatus(503);
});

test('only administrator can search icons', function () {
    $this->actingAs(User::factory()->create())
        ->getJson(route('admin.dictionary.icons', ['q' => 'snow']))
        ->assertForbidden();
});

test('equipment stores shape of free icon from outside the built-in set', function () {
    Http::fake([
        'api.fontawesome.com' => Http::response(['data' => ['release' => ['icon' => [
            'id' => 'tire',
            'label' => 'Tire',
            'familyStylesByLicense' => ['free' => [['family' => 'classic', 'style' => 'solid']]],
        ]]]]),
        'cdn.jsdelivr.net/*' => Http::response('<svg viewBox="0 0 512 512"><!-- licencja --><path d="M0 0h512v512z"/></svg>'),
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.dictionary.equipment.store'), ['name' => 'Opony', 'icon' => 'tire'])
        ->assertSessionHasNoErrors();

    expect(Equipment::query()->sole()->icon_svg)->toBe(['viewBox' => '0 0 512 512', 'path' => 'M0 0h512v512z']);
});

test('paid and unknown icons are rejected', function (string $icon) {
    fakeFontAwesome();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.dictionary.equipment.store'), ['name' => 'Opony', 'icon' => $icon])
        ->assertSessionHasErrors('icon');

    expect(Equipment::query()->count())->toBe(0);
})->with(['płatna' => ['cloud-snow'], 'nieznana' => ['nie-ma-takiej']]);

test('icon name cannot leave the expected format', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.dictionary.equipment.store'), ['name' => 'Opony', 'icon' => '../../etc/passwd'])
        ->assertSessionHasErrors('icon');

    Http::assertNothingSent();
});

test('built-in icon does not call font awesome', function () {
    Http::fake();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.dictionary.equipment.store'), ['name' => 'GPS', 'icon' => 'location-dot'])
        ->assertSessionHasNoErrors();

    Http::assertNothingSent();
});

test('editing keeps stored icon shape without calling font awesome', function () {
    Http::fake();
    $svg = ['viewBox' => '0 0 512 512', 'path' => 'M0 0z'];
    $equipment = Equipment::query()->create(['name' => 'Opony', 'icon' => 'tire', 'icon_svg' => $svg]);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.dictionary.equipment.update', $equipment->id), ['name' => 'Opony zimowe', 'icon' => 'tire'])
        ->assertSessionHasNoErrors();

    expect($equipment->refresh()->icon_svg)->toBe($svg);
    Http::assertNothingSent();
});

test('unavailable font awesome blocks saving a new icon with a message', function () {
    Http::fake(['*' => Http::response('', 500)]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.dictionary.equipment.store'), ['name' => 'Opony', 'icon' => 'tire'])
        ->assertSessionHasErrors('icon');
});

test('car form gets swatches and car list gets color of a car', function () {
    Color::query()->create(['name' => 'Biały', 'hex' => '#ffffff']);
    Car::query()->create([
        'brand' => 'Toyota',
        'model' => 'Corolla',
        'registration_number' => 'RKR 12345',
        'vin' => 'JTDBR32E720123456',
        'production_year' => 2022,
        'color' => 'Biały',
        'fuel_type' => 'hybrid',
        'transmission' => 'automatic',
        'doors' => 5,
        'seats' => 5,
        'mileage_km' => 15000,
        'daily_rate' => '189.99',
        'deposit_amount' => '1000',
    ]);
    $this->actingAs(User::factory()->admin()->create());

    $this->get(route('admin.cars.create'))
        ->assertInertia(fn (Assert $page) => $page->where('colors.0.hex', '#ffffff'));

    $this->get(route('admin.cars.index'))
        ->assertInertia(fn (Assert $page) => $page->where('cars.0.color_option.hex', '#ffffff'));
});

// Token Font Awesome

/** Odpowiedzi API dla tokena: "seat-airline" to ikona z planu Pro, a kształt podaje API. */
function fakeFontAwesomeWithToken(): void
{
    $icon = [
        'id' => 'seat-airline',
        'label' => 'Seat Airline',
        'familyStylesByLicense' => ['free' => []],
        'svgs' => [['width' => 448, 'height' => 512, 'pathData' => ['M0 0h448v512z']]],
    ];

    Http::fake([
        'api.fontawesome.com/token' => Http::response(['access_token' => 'dostep-123', 'expires_in' => 3600]),
        'api.fontawesome.com' => Http::response(['data' => ['search' => [$icon], 'release' => ['icon' => $icon]]]),
    ]);
}

test('token is exchanged for access token used in queries', function () {
    config(['services.font_awesome.api_token' => 'token-z-konta']);
    fakeFontAwesomeWithToken();

    $this->actingAs(User::factory()->admin()->create())
        ->getJson(route('admin.dictionary.icons', ['q' => 'seat']))
        ->assertOk()
        ->assertJsonPath('icons.0.name', 'seat-airline')
        ->assertJsonPath('icons.0.svg', ['viewBox' => '0 0 448 512', 'path' => 'M0 0h448v512z'])
        ->assertJsonPath('icons.0.preview_url', null);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.fontawesome.com/token'
        && $request->hasHeader('Authorization', 'Bearer token-z-konta'));

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.fontawesome.com'
        && $request->hasHeader('Authorization', 'Bearer dostep-123')
        && str_contains($request['query'], 'pathData'));
});

test('access token is reused between searches', function () {
    config(['services.font_awesome.api_token' => 'token-z-konta']);
    fakeFontAwesomeWithToken();
    $this->actingAs(User::factory()->admin()->create());

    $this->getJson(route('admin.dictionary.icons', ['q' => 'seat']))->assertOk();
    $this->getJson(route('admin.dictionary.icons', ['q' => 'chair']))->assertOk();

    Http::assertSentCount(3);
});

test('without token queries are public and ask for no shapes', function () {
    fakeFontAwesome();

    $this->actingAs(User::factory()->admin()->create())
        ->getJson(route('admin.dictionary.icons', ['q' => 'snow']))
        ->assertOk();

    Http::assertSent(fn (Request $request) => ! $request->hasHeader('Authorization')
        && ! str_contains($request['query'], 'pathData'));
});

test('equipment stores shape from api when token is set', function () {
    config(['services.font_awesome.api_token' => 'token-z-konta']);
    fakeFontAwesomeWithToken();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.dictionary.equipment.store'), ['name' => 'Fotele', 'icon' => 'seat-airline'])
        ->assertSessionHasNoErrors();

    expect(Equipment::query()->sole()->icon_svg)->toBe(['viewBox' => '0 0 448 512', 'path' => 'M0 0h448v512z']);

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'cdn.jsdelivr.net'));
});

test('icon without shape for this token is skipped but free one stays', function () {
    config(['services.font_awesome.api_token' => 'token-z-konta']);

    Http::fake([
        'api.fontawesome.com/token' => Http::response(['access_token' => 'dostep-123', 'expires_in' => 3600]),
        'api.fontawesome.com' => Http::response([
            'data' => ['search' => [
                ['id' => 'pro-only', 'label' => 'Pro', 'familyStylesByLicense' => ['free' => []], 'svgs' => null],
                ['id' => 'snowflake', 'label' => 'Snowflake', 'familyStylesByLicense' => ['free' => [['family' => 'classic', 'style' => 'solid']]], 'svgs' => null],
            ]],
            'errors' => [['message' => 'unauthorized']],
        ]),
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->getJson(route('admin.dictionary.icons', ['q' => 'snow']))
        ->assertOk()
        ->assertJsonCount(1, 'icons')
        ->assertJsonPath('icons.0.name', 'snowflake');
});

test('wrong token reports unavailable service', function () {
    config(['services.font_awesome.api_token' => 'zly-token']);
    Http::fake(['api.fontawesome.com/token' => Http::response('', 401)]);

    $this->actingAs(User::factory()->admin()->create())
        ->getJson(route('admin.dictionary.icons', ['q' => 'snow']))
        ->assertStatus(503);
});

test('invalid shape from api is rejected', function () {
    config(['services.font_awesome.api_token' => 'token-z-konta']);

    Http::fake([
        'api.fontawesome.com/token' => Http::response(['access_token' => 'dostep-123', 'expires_in' => 3600]),
        'api.fontawesome.com' => Http::response(['data' => ['release' => ['icon' => [
            'id' => 'zla',
            'label' => 'Zła',
            'familyStylesByLicense' => ['free' => []],
            'svgs' => [['width' => 512, 'height' => 512, 'pathData' => ['"/><script>alert(1)</script>']]],
        ]]]]),
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.dictionary.equipment.store'), ['name' => 'Zła', 'icon' => 'zla'])
        ->assertSessionHasErrors('icon');

    expect(Equipment::query()->count())->toBe(0);
});
