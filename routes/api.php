<?php

use App\Http\Controllers\UserController;
use App\Http\Controllers\EventController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TeamScoreController;
use App\Http\Controllers\StandingsController;

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



Route::post('/recent-scores', [TeamScoreController::class, 'store']);
Route::get('/recent-scores', [TeamScoreController::class, 'index']);
Route::put('/recent-scores/{id}', [TeamScoreController::class, 'update']);
Route::delete('/recent-scores/{id}', [TeamScoreController::class, 'destroy']);


Route::prefix('standings')->group(function () {
    Route::get('/', [StandingsController::class, 'index']);
    Route::post('/', [StandingsController::class, 'store']);
    Route::get('{team}', [StandingsController::class, 'show']);
    Route::put('{team}', [StandingsController::class, 'update']);
    Route::delete('{team}', [StandingsController::class, 'destroy']);
});



Route::middleware('cors')->group(function () {
    Route::get('/endpoint', [UserController::class, 'index']);
    // Add other API routes here
});
Route::middleware('cors')->group(function () {
    Route::get('/endpoint', [EventController::class, 'index']);
    // Add other API routes here
});
Route::get('/players', [UserController::class, 'getPlayersByTeam']);
Route::get('/teams', [UserController::class, 'getAllTeams']);

