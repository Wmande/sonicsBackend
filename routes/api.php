<?php

use App\Http\Controllers\UserController;
use App\Http\Controllers\EventController;
use Illuminate\Support\Facades\Route;

Route::post('/submit-user', [UserController::class, 'store']);
Route::get('/submissions', [UserController::class, 'index']);
Route::delete('/submissions/{id}', [UserController::class, 'destroy']);
Route::get('/submissions/{id}', [UserController::class, 'show']);
Route::put('/submissions/{id}', [UserController::class, 'update']);

Route::post('/submit-event', [EventController::class, 'store']);
Route::get('/events', [EventController::class, 'index']);
Route::delete('/events/{id}', [EventController::class, 'destroy']);
Route::get('/events/{id}', [EventController::class, 'show']);
Route::put('/events/{id}', [EventController::class, 'update']);


