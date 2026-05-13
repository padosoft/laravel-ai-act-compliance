<?php

namespace Padosoft\AiActCompliance\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Padosoft\AiActCompliance\Tests\TestCase;

class OverviewRouteTest extends TestCase
{
    public function test_overview_endpoint_responds(): void
    {
        Route::middleware('api')->get('/api/admin/ai-act-compliance/overview', fn () => response()->json(['ok' => true]));

        $response = $this->getJson('/api/admin/ai-act-compliance/overview');

        $response->assertOk()->assertJson(['ok' => true]);
    }
}
