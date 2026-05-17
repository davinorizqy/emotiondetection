<?php

use App\Http\Controllers\EmotionController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('login');
});

Route::post('/save-emotion', [EmotionController::class, 'store']);

Route::get('/dashboard', [EmotionController::class, 'index']);

Route::get('/analytics', [EmotionController::class, 'analytics']);

Route::get('/login', function () {
    return view('login');
});

Route::post('/login', [AuthController::class, 'login']);