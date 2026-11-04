<?php

use App\Http\Controllers\Admin\CarController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DictionaryController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\ReservationController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');

    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');
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

    // Panel użytkownika
    Route::middleware('role:user')
        ->prefix('panel')
        ->name('user.')
        ->controller(UserController::class)
        ->group(function () {
            Route::get('/', 'dashboard')->name('dashboard');
            Route::get('/wynajmy', 'rentals')->name('rentals');
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
    Route::middleware(['verified', 'role:administrator'])
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
                    Route::get('/{id}/edit', 'edit')->whereNumber('id')->name('edit');
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
