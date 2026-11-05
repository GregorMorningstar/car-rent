<?php

use App\Http\Controllers\Admin\CarController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DictionaryController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\MessageController;
use App\Http\Controllers\Admin\MessengerSettingsController;
use App\Http\Controllers\Admin\OptionsController;
use App\Http\Controllers\Admin\ReservationController;
use App\Http\Controllers\Admin\SecurityController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserReservationController;
use App\Http\Middleware\AuditAdminActions;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    // LoginRequest limituje parę e-mail + IP, a to chroni przed zgadywaniem wielu kont z jednego adresu.
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:20,1')->name('login.store');

    Route::get('/login/kod', [TwoFactorChallengeController::class, 'create'])->name('login.code');
    Route::post('/login/kod', [TwoFactorChallengeController::class, 'store'])->middleware('throttle:10,1')->name('login.code.verify');

    Route::get('/haslo/przypomnij', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('/haslo/przypomnij', [PasswordResetController::class, 'store'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/haslo/nowe/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/haslo/nowe', [PasswordResetController::class, 'update'])->middleware('throttle:6,1')->name('password.update');

    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:6,1')->name('register.store');
});

// Strona startowa i podstrony serwisu (dostępne także dla gości)
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/samochody', [PageController::class, 'cars'])->middleware('throttle:60,1')->name('cars');
Route::get('/samochody/szukaj', [PageController::class, 'searchCars'])->middleware('throttle:30,1')->name('cars.search');
Route::get('/samochody/{id}', [PageController::class, 'car'])->whereNumber('id')->middleware('throttle:60,1')->name('cars.show');
Route::get('/kontakt', [PageController::class, 'contact'])->name('contact');
Route::get('/o-nas', [PageController::class, 'about'])->name('about');
Route::get('/rezerwacja', [PageController::class, 'reservation'])->name('reservation');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Rezerwacja samochodu przez klienta
    Route::middleware(['verified', 'role:user'])
        ->controller(BookingController::class)
        ->prefix('samochody/{id}/rezerwuj')
        ->whereNumber('id')
        ->name('cars.book')
        ->group(function () {
            Route::get('/', 'create');
            Route::post('/', 'store')->middleware('throttle:10,1')->name('.store');
        });

    // Rezerwacje właściciela: edycja i usuwanie
    Route::middleware('role:user')
        ->controller(UserReservationController::class)
        ->prefix('panel/rezerwacje/{id}')
        ->whereNumber('id')
        ->name('user.reservations.')
        ->group(function () {
            Route::get('/edytuj', 'edit')->name('edit');
            Route::put('/', 'update')->middleware('throttle:10,1')->name('update');
            Route::delete('/', 'destroy')->middleware('throttle:10,1')->name('destroy');
        });

    // Panel użytkownika
    Route::middleware('role:user')
        ->prefix('panel')
        ->name('user.')
        ->controller(UserController::class)
        ->group(function () {
            Route::get('/', 'dashboard')->name('dashboard');
            Route::get('/wynajmy', 'rentals')->name('rentals');
            Route::get('/wiadomosci', 'messages')->name('messages.index');
            // Bez potwierdzonego adresu konto mogłoby wysyłać spam, który trafia też na Messengera.
            Route::post('/wiadomosci', 'sendMessage')->middleware(['verified', 'throttle:20,1'])->name('messages.store');
            Route::get('/platnosci', 'payments')->name('payments');
            Route::get('/profil', 'profile')->name('profile');
        });

    // Weryfikacja adresu e-mail
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])
        ->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    // Panel administratora
    Route::middleware(['verified', 'role:administrator', AuditAdminActions::class])
        ->prefix('admin')
        ->name('admin.')
        ->group(function () {
            Route::get('/dashboard', DashboardController::class)->name('dashboard');

            // Rezerwacje
            Route::controller(ReservationController::class)
                ->prefix('rezerwacje')
                ->name('reservations.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/history', 'history')->name('history');
                    Route::get('/completed', 'completed')->name('completed');
                    Route::post('/seen', 'markSeen')->name('seen');
                    Route::post('/{id}/confirm', 'confirm')->whereNumber('id')->name('confirm');
                    Route::post('/{id}/wplaty', 'storePayment')->whereNumber('id')->name('payments.store');
                    Route::post('/{id}/wplaty/{payment}/zwrot', 'refundPayment')->whereNumber(['id', 'payment'])->name('payments.refund');
                    Route::post('/{id}/reject', 'reject')->whereNumber('id')->name('reject');
                    Route::get('/{id}/edit', 'edit')->whereNumber('id')->name('edit');
                    Route::post('/{id}/edit', 'update')->whereNumber('id')->name('update');
                    Route::post('/{id}/odbior', 'handOver')->whereNumber('id')->name('handover');
                    Route::post('/{id}/zwrot', 'takeBack')->whereNumber('id')->name('takeback');
                });

            // Klienci
            Route::controller(ClientController::class)
                ->prefix('klienci')
                ->name('clients.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/{id}', 'show')->whereNumber('id')->name('show');
                });

            // Samochody
            Route::controller(CarController::class)
                ->prefix('cars')
                ->name('cars.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/add', 'create')->name('create');
                    Route::post('/add', 'store')->name('store');
                    Route::get('/{id}/edit', 'edit')->whereNumber('id')->name('edit');
                    // POST, bo PHP nie odczytuje plików z formularza wysłanego przez PUT.
                    Route::post('/{id}/edit', 'update')->whereNumber('id')->name('update');
                    Route::get('/history', 'history')->name('history');
                });

            // Rozmowy z klientami
            Route::controller(MessageController::class)
                ->prefix('wiadomosci')
                ->name('messages.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/otworz', 'open')->name('open');
                    Route::post('/{conversation}', 'store')->whereNumber('conversation')->middleware('throttle:60,1')->name('store');
                });

            // Bezpieczeństwo konta i dziennik działań
            Route::controller(SecurityController::class)
                ->prefix('bezpieczenstwo')
                ->name('security.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/start', 'start')->name('start');
                    Route::post('/potwierdz', 'confirm')->middleware('throttle:10,1')->name('confirm');
                    Route::delete('/', 'disable')->middleware('throttle:10,1')->name('disable');
                    Route::get('/dziennik', 'audit')->name('audit');
                });

            // Opcje rezerwacji
            Route::controller(OptionsController::class)
                ->prefix('opcje')
                ->name('options.')
                ->group(function () {
                    Route::get('/', 'edit')->name('edit');
                    Route::put('/', 'update')->name('update');
                });

            // Ustawienia Messengera
            Route::controller(MessengerSettingsController::class)
                ->prefix('ustawienia/messenger')
                ->name('settings.messenger.')
                ->group(function () {
                    Route::get('/', 'edit')->name('edit');
                    Route::put('/', 'update')->name('update');
                    Route::post('/test', 'test')->middleware('throttle:5,1')->name('test');
                });

            // Miejsca odbioru i dostawy
            Route::controller(LocationController::class)
                ->prefix('miejsca')
                ->name('locations.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->name('store');
                    Route::put('/{id}', 'update')->whereNumber('id')->name('update');
                    Route::patch('/{id}', 'toggle')->whereNumber('id')->name('toggle');
                });

            // Słowniki: wyposażenie i kolory
            Route::controller(DictionaryController::class)
                ->prefix('slowniki')
                ->name('dictionary.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/ikony', 'icons')->name('icons');
                    Route::post('/wyposazenie', 'storeEquipment')->name('equipment.store');
                    Route::put('/wyposazenie/{id}', 'updateEquipment')->whereNumber('id')->name('equipment.update');
                    Route::patch('/wyposazenie/{id}', 'toggleEquipment')->whereNumber('id')->name('equipment.toggle');
                    Route::post('/kolory', 'storeColor')->name('colors.store');
                    Route::put('/kolory/{id}', 'updateColor')->whereNumber('id')->name('colors.update');
                    Route::patch('/kolory/{id}', 'toggleColor')->whereNumber('id')->name('colors.toggle');
                });
        });
});
