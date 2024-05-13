<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\TransactionController;


Route::get('/', function () {
    return view('welcome');
});

Route::get('/users', function () {
    return view('auth.register');
})->name('users');

Auth::routes();

Route::get('/home', [HomeController::class, 'index'])->name('home');
Route::get('/deposit', [TransactionController::class, 'deposit'])->name('deposit');
Route::post('/deposit', [TransactionController::class, 'deposit_store'])->name('deposit_store');
Route::get('/withdrawal', [TransactionController::class, 'withdrawal'])->name('withdrawal');
Route::post('/withdrawal', [TransactionController::class, 'withdrawal_store'])->name('withdrawal_store');
