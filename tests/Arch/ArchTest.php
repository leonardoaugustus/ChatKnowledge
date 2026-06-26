<?php

// PHP enums are implicitly final (they cannot be extended), satisfying the
// "Enums are final" rule by language.
arch('domain enums live under App\Enums')
    ->expect('App\Enums')
    ->toBeEnums();

arch('services live under App\Services and do not read session, auth or request')
    ->expect('App\Services')
    ->not->toUse([
        'auth',
        'session',
        'request',
        'Illuminate\Support\Facades\Auth',
        'Illuminate\Support\Facades\Session',
        'Illuminate\Support\Facades\Request',
    ]);

arch('livewire form objects extend the base form')
    ->expect('App\Livewire\Forms')
    ->toExtend('Livewire\Form');

arch('jobs are queueable')
    ->expect('App\Jobs')
    ->toImplement('Illuminate\Contracts\Queue\ShouldQueue')
    ->ignoring('App\Jobs\Concerns');

arch('policies live under App\Policies and are suffixed Policy')
    ->expect('App\Policies')
    ->toHaveSuffix('Policy');

arch('actions live under App\Actions')
    ->expect('App\Actions')
    ->toBeClasses();

arch('no debugging helpers are left behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'ddd'])
    ->not->toBeUsed();
