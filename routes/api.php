<?php

use App\Http\Controllers\Api\ActivityApiController;
use App\Http\Controllers\Api\BuyerApiController;
use App\Http\Controllers\Api\DealApiController;
use App\Http\Controllers\Api\DealAnalysisApiController;
use App\Http\Controllers\Api\DealComparableSalesApiController;
use App\Http\Controllers\Api\DealComplianceApiController;
use App\Http\Controllers\Api\DealOfferDecisionApiController;
use App\Http\Controllers\Api\DealRankingApiController;
use App\Http\Controllers\Api\DealBuyerMatchApiController;
use App\Http\Controllers\Api\DealEvidenceApiController;
use App\Http\Controllers\Api\LeadIngestController;
use App\Http\Controllers\Api\PropertyApiController;
use App\Http\Controllers\Api\PropertyIntelligenceApiController;
use App\Http\Controllers\Api\StatsApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['api.key', 'throttle:api', 'api.log'])->group(function () {
    Route::get('/leads', [LeadIngestController::class, 'index']);
    Route::post('/leads', [LeadIngestController::class, 'store']);
    Route::get('/leads/{id}', [LeadIngestController::class, 'show']);
    Route::put('/leads/{id}', [LeadIngestController::class, 'update']);

    Route::get('/deals', [DealApiController::class, 'index']);
    Route::post('/deals', [DealApiController::class, 'store']);
    Route::get('/deals/stages', [DealApiController::class, 'stages']);
    Route::get('/deals/{id}', [DealApiController::class, 'show']);
    Route::put('/deals/{id}', [DealApiController::class, 'update']);
    Route::get('/deals/{dealId}/analysis', [DealAnalysisApiController::class, 'show']);
    Route::post('/deals/{dealId}/analysis', [DealAnalysisApiController::class, 'store']);
    Route::put('/deals/{dealId}/analysis', [DealAnalysisApiController::class, 'update']);
    Route::get('/deals/{dealId}/comps', [DealComparableSalesApiController::class, 'index']);
    Route::post('/deals/{dealId}/comps/recalculate', [DealComparableSalesApiController::class, 'recalculate']);
    Route::post('/deals/{dealId}/offer-decision/calculate', [DealOfferDecisionApiController::class, 'calculate']);
    Route::post('/deals/{dealId}/rank', [DealRankingApiController::class, 'calculate']);
    Route::post('/deals/{dealId}/compliance/check', [DealComplianceApiController::class, 'check']);
    Route::get('/deals/{dealId}/evidence', [DealEvidenceApiController::class, 'index']);
    Route::post('/deals/{dealId}/property-intelligence/reconcile', [PropertyIntelligenceApiController::class, 'reconcile']);
    Route::post('/deals/{dealId}/evidence', [DealEvidenceApiController::class, 'store']);
    Route::get('/deals/{dealId}/matches', [DealBuyerMatchApiController::class, 'index']);
    Route::post('/deals/{dealId}/matches', [DealBuyerMatchApiController::class, 'store']);
    Route::post('/deals/{dealId}/matches/recalculate', [DealBuyerMatchApiController::class, 'match']);
    Route::put('/deals/{dealId}/matches/{matchId}', [DealBuyerMatchApiController::class, 'update']);

    Route::get('/buyers', [BuyerApiController::class, 'index']);
    Route::post('/buyers', [BuyerApiController::class, 'store']);
    Route::get('/buyers/{id}', [BuyerApiController::class, 'show']);
    Route::put('/buyers/{id}', [BuyerApiController::class, 'update']);

    Route::get('/properties', [PropertyApiController::class, 'index']);
    Route::post('/properties', [PropertyApiController::class, 'store']);
    Route::get('/properties/{id}', [PropertyApiController::class, 'show']);
    Route::put('/properties/{id}', [PropertyApiController::class, 'update']);

    Route::get('/activities', [ActivityApiController::class, 'index']);
    Route::post('/activities', [ActivityApiController::class, 'store']);
    Route::get('/stats', [StatsApiController::class, 'index']);
});
