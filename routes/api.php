<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\KolController;

// Routes untuk Master Campaign
Route::get('/campaigns', [CampaignController::class, 'index']);
Route::post('/campaigns', [CampaignController::class, 'store']);
Route::put('/campaigns/{id}', [CampaignController::class, 'update']);
// Ditambahkan optional jika dibutuhkan fitur delete campaign
Route::delete('/campaigns/{id}', [CampaignController::class, 'destroy']);

// Routes untuk Master KOL
Route::get('/kols', [KolController::class, 'index']);
Route::post('/kols', [KolController::class, 'store']);
Route::put('/kols/{id}', [KolController::class, 'update']); // Ditambahkan agar fitur Edit KOL dapat berjalan
Route::delete('/kols/{id}', [KolController::class, 'destroy']);