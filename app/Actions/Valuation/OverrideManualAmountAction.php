<?php

declare(strict_types=1);

namespace App\Actions\Valuation;

use App\Models\PropertyTotal;
use App\Models\User;
use App\Models\ValuationRequest;
use App\Support\Valuation\ValuationActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

final class OverrideManualAmountAction
{
    public function execute(
        User $actor,
        ValuationRequest $request,
        float $manualAmount,
        bool $hideComparisons = false,
        bool $hideEvaluation = false,
    ): PropertyTotal {
        Gate::forUser($actor)->authorize('overrideAmount', $request);

        if ($request->isFinallyApproved()) {
            throw new RuntimeException(__('Cannot override amount after final approval.'));
        }

        $property = $request->property;
        if ($property === null) {
            throw new RuntimeException(__('Valuation has no linked property.'));
        }

        $previous = $property->total?->total_amount_manual;

        $total = DB::transaction(function () use ($property, $manualAmount, $hideComparisons, $hideEvaluation): PropertyTotal {
            $existingTotal = $property->total;
            $existingLegacyId = $existingTotal !== null ? $existingTotal->legacy_id : null;

            /** @var PropertyTotal $total */
            $total = PropertyTotal::query()->updateOrCreate(
                ['property_id' => $property->id],
                [
                    'legacy_id' => $existingLegacyId ?? (900000000 + $property->id),
                    'total_amount_manual' => $manualAmount,
                    'hide_comparisons_info_table' => $hideComparisons,
                    'hide_evaluation_info_table' => $hideEvaluation,
                ]
            );

            return $total;
        });

        ValuationActivity::log($actor, $request, 'overrode_manual_amount', 'Manual valuation amount overridden', [
            'previous_total_amount_manual' => $previous,
            'total_amount_manual' => $manualAmount,
            'hide_comparisons_info_table' => $hideComparisons,
            'hide_evaluation_info_table' => $hideEvaluation,
        ]);

        return $total->refresh();
    }
}
