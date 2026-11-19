<?php

namespace App\Http\Controllers;

use App\Services\TwoFactorService;
use App\Support\CookieConsent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

/**
 * Zapisuje wybór cookies na tym urządzeniu. Odrzucenie od razu kasuje to, na co nie ma już zgody.
 */
class CookieConsentController extends Controller
{
    public function store(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $validated = $request->validate(array_fill_keys(CookieConsent::CATEGORIES, ['required', 'boolean']));

        $choices = array_map(fn (mixed $value) => filter_var($value, FILTER_VALIDATE_BOOL), $validated);

        if (! $choices['functional']) {
            $twoFactor->forgetCurrentDevice($request);
            Cookie::queue(Cookie::forget(Auth::guard('web')->getRecallerName()));
        }

        Cookie::queue(CookieConsent::COOKIE, CookieConsent::encode($choices), CookieConsent::MINUTES);

        return back(fallback: route('home'));
    }
}
