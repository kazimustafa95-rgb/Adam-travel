<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AppSettingController;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\CmsPageController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\SupportTicketController;
use App\Http\Controllers\Admin\TripController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/', fn () => redirect()->route(auth('admin')->check() ? 'admin.dashboard' : 'admin.login'));

        Route::middleware('guest:admin')->group(function (): void {
            Route::get('/login', [LoginController::class, 'create'])->name('login');
            Route::post('/login', [LoginController::class, 'store'])
                ->middleware('throttle:6,1')
                ->name('login.store');
        });

        Route::middleware('auth.admin')->group(function (): void {
            Route::get('/dashboard', DashboardController::class)->name('dashboard');
            Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
            Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
            Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');

            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
            Route::patch('/users/{user}/status', [UserController::class, 'updateStatus'])->name('users.status.update');

            Route::get('/imports', [ImportController::class, 'index'])->name('imports.index');
            Route::get('/imports/{import}', [ImportController::class, 'show'])->name('imports.show');
            Route::post('/imports/{import}/retry', [ImportController::class, 'retry'])->name('imports.retry');

            Route::get('/locations', [LocationController::class, 'index'])->name('locations.index');
            Route::patch('/locations/{location}', [LocationController::class, 'update'])->name('locations.update');

            Route::get('/trips', [TripController::class, 'index'])->name('trips.index');
            Route::get('/trips/{trip}', [TripController::class, 'show'])->name('trips.show');
            Route::patch('/trips/{trip}', [TripController::class, 'update'])->name('trips.update');

            Route::get('/support-tickets', [SupportTicketController::class, 'index'])->name('support.index');
            Route::get('/support-tickets/{supportTicket}', [SupportTicketController::class, 'show'])->name('support.show');
            Route::patch('/support-tickets/{supportTicket}', [SupportTicketController::class, 'update'])->name('support.update');

            Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity.index');

            Route::get('/cms-pages', [CmsPageController::class, 'index'])->name('cms-pages.index');
            Route::get('/cms-pages/{cmsPage}/edit', [CmsPageController::class, 'edit'])->name('cms-pages.edit');
            Route::patch('/cms-pages/{cmsPage}', [CmsPageController::class, 'update'])->name('cms-pages.update');

            Route::get('/app-settings', [AppSettingController::class, 'index'])->name('app-settings.index');
            Route::patch('/app-settings/{appSetting}', [AppSettingController::class, 'update'])->name('app-settings.update');

            Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
        });
    });
