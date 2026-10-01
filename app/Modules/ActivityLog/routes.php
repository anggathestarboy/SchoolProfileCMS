<?php

use App\Modules\ActivityLog\Http\Controllers\ConfirmDeletionController;
use App\Modules\ActivityLog\Livewire\ActivityLogManager;
use Illuminate\Support\Facades\Route;

Route::get('activity-log', ActivityLogManager::class)->name('activity-log.index');
Route::get('activity-log/confirm-delete/{token}', ConfirmDeletionController::class)->name('confirm-delete');
