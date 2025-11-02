<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AnalyticsController;

// Rutas para obtener información de usuarios.
Route::get('/analytics/user', [AnalyticsController::class, 'getUser']);

// Ruta para obtener los streams en vivo.
Route::get('/analytics/streams', [AnalyticsController::class, 'getStreams']);
