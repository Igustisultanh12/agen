<?php

namespace App\Services\AI;

use App\Models\AiModel;
use App\Models\ModelPricing;

class CostCalculatorService
{
    /**
     * Calculate estimated costs based on configurable model pricings table.
     *
     * @return array{
     *     input_cost: float,
     *     output_cost: float,
     *     cached_cost: float,
     *     reasoning_cost: float,
     *     total_cost: float,
     *     currency: string,
     *     is_free: bool
     * }
     */
    public function calculateCost(
        AiModel $model,
        int $inputTokens,
        int $outputTokens,
        int $cachedTokens = 0,
        int $reasoningTokens = 0
    ): array {
        // If local model or explicitly marked as free category
        if ($model->category === 'local' || $model->category === 'free') {
            return [
                'input_cost' => 0.0,
                'output_cost' => 0.0,
                'cached_cost' => 0.0,
                'reasoning_cost' => 0.0,
                'total_cost' => 0.0,
                'currency' => 'USD',
                'is_free' => true,
            ];
        }

        $pricing = ModelPricing::where('model_id', $model->id)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('effective_from')->orWhere('effective_from', '<=', now()->toDateString());
            })
            ->where(function ($q) {
                $q->whereNull('effective_until')->orWhere('effective_until', '>=', now()->toDateString());
            })
            ->latest('id')
            ->first();

        if (!$pricing) {
            return [
                'input_cost' => 0.0,
                'output_cost' => 0.0,
                'cached_cost' => 0.0,
                'reasoning_cost' => 0.0,
                'total_cost' => 0.0,
                'currency' => 'USD',
                'is_free' => true,
            ];
        }

        $inputPricePer1m = (float) $pricing->input_price_per_1m;
        $outputPricePer1m = (float) $pricing->output_price_per_1m;
        $cachedPricePer1m = (float) $pricing->cached_input_price_per_1m;
        $reasoningPricePer1m = (float) $pricing->reasoning_price_per_1m;
        $currency = $pricing->currency ?: 'USD';

        $inputCost = ($inputTokens / 1000000.0) * $inputPricePer1m;
        $outputCost = ($outputTokens / 1000000.0) * $outputPricePer1m;
        $cachedCost = ($cachedTokens / 1000000.0) * $cachedPricePer1m;
        $reasoningCost = ($reasoningTokens / 1000000.0) * $reasoningPricePer1m;

        $totalCost = round($inputCost + $outputCost + $cachedCost + $reasoningCost, 6);

        return [
            'input_cost' => round($inputCost, 6),
            'output_cost' => round($outputCost, 6),
            'cached_cost' => round($cachedCost, 6),
            'reasoning_cost' => round($reasoningCost, 6),
            'total_cost' => $totalCost,
            'currency' => $currency,
            'is_free' => false,
        ];
    }
}
