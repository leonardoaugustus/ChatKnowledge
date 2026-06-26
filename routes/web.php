<?php

use App\Http\Middleware\EnsureOrganizationMembership;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

if (app()->environment('local')) {
    Route::view('/_dev/ui-kit', 'dev.ui-kit')->name('dev.ui-kit');
}

Route::prefix('{current_organization}')
    ->middleware(['auth', 'verified', EnsureOrganizationMembership::class])
    ->group(function () {
        Route::view('dashboard', 'dashboard')->name('dashboard');
    });

Route::middleware(['auth'])->group(function () {
    Route::livewire('invitations/{invitation}/accept', 'pages::organizations.accept-invitation')->name('invitations.accept');
});

require __DIR__.'/settings.php';
