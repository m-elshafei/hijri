<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read Property|null $property
 */
class PropertyTotal extends Model
{
    protected $fillable = [
        'legacy_id',
        'property_id',
        'profit_percentage',
        'profit_amount',
        'depreciation_type',
        'calc_amount',
        'depreciation_amount',
        'forced_sale_percentage',
        'forced_sale_amount',
        'total_amount',
        'total_amount_manual',
        'hide_comparisons_info_table',
        'hide_evaluation_info_table',
        'total_area',
        'base_area',
        'base_amount',
        'movables',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'legacy_id' => 'integer',
            'property_id' => 'integer',
            'profit_percentage' => 'float',
            'profit_amount' => 'float',
            'depreciation_type' => 'integer',
            'calc_amount' => 'float',
            'depreciation_amount' => 'float',
            'forced_sale_percentage' => 'integer',
            'forced_sale_amount' => 'float',
            'total_amount' => 'float',
            'total_amount_manual' => 'float',
            'hide_comparisons_info_table' => 'boolean',
            'hide_evaluation_info_table' => 'boolean',
            'total_area' => 'float',
            'base_area' => 'float',
            'base_amount' => 'float',
            'movables' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Final reportable amount: manual override wins when present and non-zero
     * (matches legacy EvaluationRequestController PDF selection).
     */
    public function finalAmount(): ?float
    {
        if ($this->total_amount_manual !== null && (float) $this->total_amount_manual != 0.0) {
            return (float) $this->total_amount_manual;
        }

        if ($this->total_amount === null) {
            return null;
        }

        return (float) $this->total_amount;
    }

    public function isManualOverride(): bool
    {
        return $this->total_amount_manual !== null && (float) $this->total_amount_manual != 0.0;
    }
}
