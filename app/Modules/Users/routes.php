<?php

// Routes for the Users module (users, user groups, roles/permissions).
// Registered by UsersServiceProvider under the CMS prefix (config('cms.route')),
// 'web' + 'auth' + 'verified' middleware, and the 'users.' route name prefix.
// Do NOT add ->prefix()/->middleware()/->name() again in this file.

use App\Modules\Users\Livewire\RoleForm;
use App\Modules\Users\Livewire\RoleManager;
use App\Modules\Users\Livewire\UserForm;
use App\Modules\Users\Livewire\UserGroupManager;
use App\Modules\Users\Livewire\UserManager;
use Illuminate\Support\Facades\Route;

Route::get('users', UserManager::class)->name('index');
Route::get('users/create', UserForm::class)->name('create');
Route::get('users/{user}', UserForm::class)->name('edit');

Route::get('user-groups', UserGroupManager::class)->name('groups.index');

Route::get('roles', RoleManager::class)->name('roles.index');
Route::get('roles/create', RoleForm::class)->name('roles.create');
Route::get('roles/{role}', RoleForm::class)->name('roles.edit');
