<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GameActionController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\PlayController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'portal.home')->name('home');

// Play without an account: a guest account is created on the fly.
Route::post('/play', [PlayController::class, 'start'])->middleware('throttle:10,1')->name('play');

// Open to visitors and to guest accounts upgrading themselves; the
// controller bounces fully registered users to their games.
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/games', [GameController::class, 'index'])->name('games.index');
    Route::post('/games', [GameController::class, 'store'])->name('games.store');
    Route::get('/games/{game}', [GameController::class, 'show'])->name('games.show');
    Route::get('/games/{game}/panel', [GameController::class, 'panel'])->name('games.panel');
    Route::delete('/games/{game}', [GameController::class, 'destroy'])->name('games.destroy');

    Route::post('/games/{game}/end-turn', [GameActionController::class, 'endTurn'])->name('games.end-turn');
    Route::post('/games/{game}/found', [GameActionController::class, 'found'])->name('games.found');
    Route::post('/games/{game}/research', [GameActionController::class, 'research'])->name('games.research');
    Route::post('/games/{game}/cities/{city}/build', [GameActionController::class, 'build'])->name('games.build');
    Route::post('/games/{game}/cities/{city}/buy', [GameActionController::class, 'buy'])->name('games.buy');
    Route::post('/games/{game}/cities/{city}/attack', [GameActionController::class, 'attack'])->name('games.attack');
    Route::post('/games/{game}/cities/{city}/focus', [GameActionController::class, 'focus'])->name('games.focus');
    Route::post('/games/{game}/empires/{empire}/war', [GameActionController::class, 'war'])->name('games.war');
    Route::post('/games/{game}/empires/{empire}/peace', [GameActionController::class, 'peace'])->name('games.peace');
    Route::post('/games/{game}/clear-camp', [GameActionController::class, 'clearCamp'])->name('games.clear-camp');
});
