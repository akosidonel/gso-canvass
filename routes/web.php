<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PriceMonitoringController;
use App\Http\Controllers\PriceArchiveController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::view('/signin', 'pages.auth.signin')->name('login');
    Route::post('/signin', [AuthController::class, 'store'])->middleware('throttle:login')->name('login.store');
});
Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'active', 'auth.session'])->group(function () {
    Route::post('/presence', fn () => response()->noContent())->name('presence');
    Route::resource('users', UserController::class)
        ->except('show')->middleware('can:manage-users');
    Route::prefix('price-monitoring')->name('price-monitoring.')->middleware('can:view-data')->group(function () {
        Route::get('/', [PriceMonitoringController::class, 'index'])->name('index');
        Route::get('/export', [PriceMonitoringController::class, 'export'])->name('export');
        Route::middleware('can:manage-archives')->group(function () {
            Route::get('/archives', [PriceArchiveController::class, 'index'])->name('archives.index');
            Route::post('/archives', [PriceArchiveController::class, 'store'])->name('archives.store');
            Route::post('/archives/{batch}/restore', [PriceArchiveController::class, 'restore'])->name('archives.restore');
            Route::post('/archives/{batch}/retry', [PriceArchiveController::class, 'retry'])->name('archives.retry');
        });
        Route::middleware('can:edit-data')->group(function () {
            Route::get('/create', [PriceMonitoringController::class, 'create'])->name('create');
            Route::post('/preview', [PriceMonitoringController::class, 'preparePreview'])->name('preview.prepare');
            Route::get('/preview', [PriceMonitoringController::class, 'preview'])->name('preview');
            Route::post('/', [PriceMonitoringController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [PriceMonitoringController::class, 'edit'])->whereNumber('id')->name('edit');
            Route::put('/{id}', [PriceMonitoringController::class, 'update'])->whereNumber('id')->name('update');
        });
        Route::delete('/{id}', [PriceMonitoringController::class, 'destroy'])->whereNumber('id')->middleware('can:delete-data')->name('destroy');
    });
    // Locale Switch Route
    Route::get('/locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

    Route::redirect('/', '/price-monitoring')->name('home');

    // form pages
    Route::get('/form-elements', function () {
        return view('pages.form.form-elements', ['title' => 'Form Elements']);
    })->name('form-elements');

    // tables pages
    Route::get('/basic-tables', function () {
        return view('pages.tables.basic-tables', ['title' => 'Basic Tables']);
    })->name('basic-tables');

    // pages

    Route::get('/blank', function () {
        return view('pages.blank', ['title' => 'Blank']);
    })->name('blank');

    // error pages
    Route::get('/error-404', function () {
        return view('pages.errors.error-404', ['title' => 'Error 404']);
    })->name('error-404');

    // chart pages
    Route::get('/line-chart', function () {
        return view('pages.chart.line-chart', ['title' => 'Line Chart']);
    })->name('line-chart');

    Route::get('/bar-chart', function () {
        return view('pages.chart.bar-chart', ['title' => 'Bar Chart']);
    })->name('bar-chart');

    // ui elements pages
    Route::get('/alerts', function () {
        return view('pages.ui-elements.alerts', ['title' => 'Alerts']);
    })->name('alerts');

    Route::get('/avatars', function () {
        return view('pages.ui-elements.avatars', ['title' => 'Avatars']);
    })->name('avatars');

    Route::get('/badge', function () {
        return view('pages.ui-elements.badges', ['title' => 'Badges']);
    })->name('badges');

    Route::get('/buttons', function () {
        return view('pages.ui-elements.buttons', ['title' => 'Buttons']);
    })->name('buttons');

    Route::get('/image', function () {
        return view('pages.ui-elements.images', ['title' => 'Images']);
    })->name('images');
});
