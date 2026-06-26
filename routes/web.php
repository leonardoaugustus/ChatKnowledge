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

        Route::livewire('agents/{agent}/edit', 'pages::agent.edit')->name('agents.edit');
        Route::livewire('agents/{agent}/training', 'pages::training.upload')->name('training.upload');
        Route::livewire('agents/{agent}/curation', 'pages::curation.queue')->name('curation.queue');
    });

Route::middleware(['auth'])->group(function () {
    Route::livewire('invitations/{invitation}/accept', 'pages::organizations.accept-invitation')->name('invitations.accept');
});

require __DIR__.'/settings.php';
