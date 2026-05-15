<?php

use Illuminate\Support\Facades\Route;
use Padosoft\AiActCompliance\Http\Controllers\BiasController;
use Padosoft\AiActCompliance\Http\Controllers\ComplianceAttestationController;
use Padosoft\AiActCompliance\Http\Controllers\ComplianceOverviewController;
use Padosoft\AiActCompliance\Http\Controllers\ConsentController;
use Padosoft\AiActCompliance\Http\Controllers\DsarController;
use Padosoft\AiActCompliance\Http\Controllers\HumanReviewController;
use Padosoft\AiActCompliance\Http\Controllers\IncidentController;
use Padosoft\AiActCompliance\Http\Controllers\RegulatoryAmendmentController;
use Padosoft\AiActCompliance\Http\Controllers\RiskRegisterController;
use Padosoft\AiActCompliance\Http\Controllers\SettingsController;

Route::get('/overview', ComplianceOverviewController::class);
Route::get('/settings', SettingsController::class);

Route::get('/dsar', [DsarController::class, 'index']);
Route::post('/dsar', [DsarController::class, 'store']);
Route::get('/dsar/{id}', [DsarController::class, 'show'])->whereNumber('id');
Route::post('/dsar/{id}/execute', [DsarController::class, 'execute'])->whereNumber('id');

Route::get('/consent', [ConsentController::class, 'index']);
Route::post('/consent/grant', [ConsentController::class, 'grant']);
Route::post('/consent/revoke', [ConsentController::class, 'revoke']);

Route::get('/risks', [RiskRegisterController::class, 'index']);
Route::post('/risks', [RiskRegisterController::class, 'store']);
Route::get('/risks/{id}', [RiskRegisterController::class, 'show'])->whereNumber('id');
Route::patch('/risks/{id}', [RiskRegisterController::class, 'update'])->whereNumber('id');

Route::get('/incidents', [IncidentController::class, 'index']);
Route::post('/incidents', [IncidentController::class, 'store']);
Route::get('/incidents/{id}', [IncidentController::class, 'show'])->whereNumber('id');
Route::post('/incidents/{id}/transition', [IncidentController::class, 'transition'])->whereNumber('id');

Route::get('/bias', [BiasController::class, 'index']);
Route::post('/bias/capture', [BiasController::class, 'capture']);

Route::get('/human-reviews', [HumanReviewController::class, 'index']);
Route::post('/human-reviews', [HumanReviewController::class, 'store']);
Route::post('/human-reviews/{id}/transition', [HumanReviewController::class, 'transition'])->whereNumber('id');

Route::get('/attestations', [ComplianceAttestationController::class, 'index']);
Route::post('/attestations', [ComplianceAttestationController::class, 'store']);
Route::get('/attestations/{id}', [ComplianceAttestationController::class, 'show'])->whereNumber('id');

// v1.4 — regulatory feed / amendments dashboard
Route::get('/regulatory-amendments', [RegulatoryAmendmentController::class, 'index']);
Route::get('/regulatory-amendments/{id}', [RegulatoryAmendmentController::class, 'show'])->whereNumber('id');
Route::patch('/regulatory-amendments/{id}', [RegulatoryAmendmentController::class, 'update'])->whereNumber('id');
Route::post('/regulatory-amendments/poll', [RegulatoryAmendmentController::class, 'poll']);
