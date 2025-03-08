<?php

use Illuminate\Support\Facades\Route;
use App\Filament\Pages\Auth\Login;

Route::post('/admin/login', [Login::class, 'authenticate'])->name('filament.admin.auth.login');
Route::redirect('/', '/admin/login');
