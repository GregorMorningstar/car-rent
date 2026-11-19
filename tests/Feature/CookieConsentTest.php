<?php

use App\Models\TrustedDevice;
use App\Models\User;
use App\Notifications\TwoFactorCode;
use App\Services\TwoFactorService;
use App\Support\CookieConsent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function consentCookie(bool $functional): string
{
    return CookieConsent::encode(['functional' => $functional, 'analytics' => false, 'marketing' => false]);
}

function consentChoices(bool $all): array
{
    return ['functional' => $all, 'analytics' => $all, 'marketing' => $all];
}

function trustedConsentDevice(User $user): string
{
    $token = Str::random(64);

    TrustedDevice::query()->create(['user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'name' => 'Chrome', 'last_used_at' => now(), 'expires_at' => now()->addDays(30)]);

    return $token;
}

test('nothing is decided on a new device', function () {
    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('cookieConsent', null));
});

test('choice is stored in a cookie for a year', function (bool $all) {
    $response = $this->post(route('cookies.consent'), consentChoices($all))->assertRedirect();

    $cookie = collect($response->headers->getCookies())->first(fn ($item) => $item->getName() === CookieConsent::COOKIE);

    expect($cookie)->not->toBeNull()
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getExpiresTime())->toBeGreaterThan(now()->addDays(364)->getTimestamp());
})->with([true, false]);

test('accepted and rejected choices come back from the same device', function (bool $functional) {
    $this->withCookie(CookieConsent::COOKIE, consentCookie($functional))
        ->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('cookieConsent.functional', $functional)
            ->where('cookieConsent.analytics', false));
})->with([true, false]);

test('cookie from an older version asks again', function () {
    $this->withCookie(CookieConsent::COOKIE, json_encode(['functional' => true, 'version' => 0]))
        ->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('cookieConsent', null));
});

test('consent needs every category as a boolean', function (array $data) {
    $this->post(route('cookies.consent'), $data)->assertSessionHasErrors();
})->with([
    'brak' => [[]],
    'zła wartość' => [['functional' => 'może', 'analytics' => 0, 'marketing' => 0]],
]);

test('remember me works only with consent', function (bool $functional, bool $remembered) {
    $user = User::factory()->create();

    $response = $this->withCookie(CookieConsent::COOKIE, consentCookie($functional))
        ->post(route('login.store'), ['email' => $user->email, 'password' => 'password', 'remember' => '1']);

    $recaller = collect($response->headers->getCookies())->first(fn ($item) => $item->getName() === Auth::guard('web')->getRecallerName());

    $this->assertAuthenticatedAs($user);
    expect($recaller !== null)->toBe($remembered);
})->with([
    'zgoda' => [true, true],
    'odrzucone' => [false, false],
]);

test('remember me is ignored without any decision', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password', 'remember' => '1']);

    expect(collect($response->headers->getCookies())->contains(fn ($item) => $item->getName() === Auth::guard('web')->getRecallerName()))->toBeFalse();
});

test('device is not added after rejecting cookies', function () {
    Notification::fake();
    $user = User::factory()->create();
    $user->forceFill(['two_factor_enabled_at' => now()])->save();

    $this->withCookie(CookieConsent::COOKIE, consentCookie(false));
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    $notification = Notification::sent($user, TwoFactorCode::class)->last();
    $code = (new ReflectionProperty($notification, 'code'))->getValue($notification);

    $this->post(route('login.code.verify'), ['code' => $code, 'trust' => true])->assertCookieMissing(TwoFactorService::COOKIE);

    $this->assertAuthenticatedAs($user);
    expect(TrustedDevice::query()->count())->toBe(0);
});

test('trusted device does not skip the code after rejecting cookies', function () {
    Notification::fake();
    $user = User::factory()->create();
    $user->forceFill(['two_factor_enabled_at' => now()])->save();
    $token = trustedConsentDevice($user);

    $this->withCookie(CookieConsent::COOKIE, consentCookie(false))
        ->withCookie(TwoFactorService::COOKIE, $token)
        ->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('login.code'));

    $this->assertGuest();
});

test('rejecting removes the trusted device and the remembered login', function () {
    $user = User::factory()->create();
    $other = trustedConsentDevice(User::factory()->create());
    $token = trustedConsentDevice($user);

    $response = $this->withCookie(TwoFactorService::COOKIE, $token)
        ->post(route('cookies.consent'), consentChoices(false));

    $forgotten = collect($response->headers->getCookies())->filter(fn ($item) => $item->getExpiresTime() > 0 && $item->getExpiresTime() < time())->map->getName()->all();

    expect(TrustedDevice::query()->where('user_id', $user->id)->count())->toBe(0)
        ->and(TrustedDevice::query()->count())->toBe(1)
        ->and($forgotten)->toContain(TwoFactorService::COOKIE, Auth::guard('web')->getRecallerName())
        ->and($other)->not->toBe($token);
});

test('accepting keeps the trusted device', function () {
    $user = User::factory()->create();
    $token = trustedConsentDevice($user);

    $this->withCookie(TwoFactorService::COOKIE, $token)->post(route('cookies.consent'), consentChoices(true));

    expect(TrustedDevice::query()->count())->toBe(1);
});

test('required features work after rejecting cookies', function () {
    $user = User::factory()->create();

    $this->withCookie(CookieConsent::COOKIE, consentCookie(false))
        ->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect();

    $this->assertAuthenticatedAs($user);
    $this->get(route('user.dashboard'))->assertOk();
});
