<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\AI\TokenUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsageController extends Controller
{
    public function __construct(
        protected TokenUsageService $tokenUsageService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $summary = $this->tokenUsageService->getUserUsageSummary($request->user());

        return response()->json($summary);
    }
}
