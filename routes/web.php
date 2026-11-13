<?php

declare(strict_types=1);

use App\Livewire\Pmo\AiOmnichannelHub;
use App\Livewire\Pmo\Dashboard;
use App\Livewire\Pmo\KanbanBoard;
use App\Livewire\Pmo\RosterProjectIndex;
use Illuminate\Support\Facades\Route;

// Landing page (the starter-kit welcome.blade.php is kept but no longer routed).
Route::view('/', 'landing')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', Dashboard::class)->name('dashboard');

    Route::livewire('pmo/board', KanbanBoard::class)->name('pmo.board');
    Route::livewire('pmo/roster', RosterProjectIndex::class)->name('pmo.roster');
    Route::livewire('pmo/hub', AiOmnichannelHub::class)->name('pmo.hub');
});

require __DIR__.'/settings.php';
