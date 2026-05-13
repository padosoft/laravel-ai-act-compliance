<?php

use Illuminate\Support\Facades\Route;
use Padosoft\AiActCompliance\Http\Controllers\ComplianceOverviewController;

Route::get('/overview', ComplianceOverviewController::class);
