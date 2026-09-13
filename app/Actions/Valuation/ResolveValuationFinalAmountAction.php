<?php

declare(strict_types=1);

namespace App\Actions\Valuation;

use App\Models\Property;
use App\Models\PropertyTotal;
use App\Models\ValuationRequest;

/**
 * Resolves the reportable final valuation amount using the same precedence
 * as the legacy PDF path: total_amount_manual (when non-zero) → total_amount.
 */
final class ResolveValuationFinalAmountAction
{
    /**
     * @return array{
     *     amount: float|null,
     *     is_manual: bool,
     *     calculated_amount: float|null,
     *     manual_amount: float|null,
     *     forced_sale_percentage: int|null,
     *     forced_sale_amount: float|null,
     *     total_area: float|null,
     *     property_total: PropertyTotal|null
     * }
     */
    public function execute(ValuationRequest|Property|PropertyTotal $source): array
    {
        $total = match (true) {
            $source instanceof PropertyTotal => $source,
            $source instanceof Property => $source->total,
            default => $source->property?->total,
        };

        if (! $total instanceof PropertyTotal) {
            return [
                'amount' => null,
                'is_manual' => false,
                'calculated_amount' => null,
                'manual_amount' => null,
                'forced_sale_percentage' => null,
                'forced_sale_amount' => null,
                'total_area' => null,
                'property_total' => null,
            ];
        }

        return [
            'amount' => $total->finalAmount(),
            'is_manual' => $total->isManualOverride(),
            'calculated_amount' => $total->total_amount !== null ? (float) $total->total_amount : null,
            'manual_amount' => $total->total_amount_manual !== null ? (float) $total->total_amount_manual : null,
            'forced_sale_percentage' => $total->forced_sale_percentage,
            'forced_sale_amount' => $total->forced_sale_amount !== null ? (float) $total->forced_sale_amount : null,
            'total_area' => $total->total_area !== null ? (float) $total->total_area : null,
            'property_total' => $total,
        ];
    }
}
