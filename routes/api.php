<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\KolController;
use App\Http\Controllers\DashboardController;

// Routes untuk Master Campaign
Route::get('/campaigns', [CampaignController::class, 'index']);
Route::post('/campaigns', [CampaignController::class, 'store']);
Route::put('/campaigns/{id}', [CampaignController::class, 'update']);
Route::delete('/campaigns/{id}', [CampaignController::class, 'destroy']);

// Routes untuk Master KOL
Route::get('/kols', [KolController::class, 'index']);
Route::post('/kols', [KolController::class, 'store']);
Route::put('/kols/{id}', [KolController::class, 'update']);
Route::delete('/kols/{id}', [KolController::class, 'destroy']);

// Routes untuk Dashboard (Reguler & Event / Campaign Summary)
Route::get('/dashboard/summary', [DashboardController::class, 'index']);