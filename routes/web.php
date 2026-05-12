<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin/login');
Route::redirect('/login', '/admin/login')->name('login');

Route::get('/reset-password/{token}', function (Request $request, string $token) {
    return view('auth.reset-password', [
        'token' => $token,
        'email' => (string) $request->query('email', ''),
    ]);
})->middleware('guest')->name('password.reset');

require __DIR__.'/admin.php';
