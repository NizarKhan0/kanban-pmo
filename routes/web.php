<?php

declare(strict_types=1);

use App\Livewire\Pmo\RosterProjectIndex;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('pmo/roster', RosterProjectIndex::class)->name('pmo.roster');
});

require __DIR__.'/settings.php';
