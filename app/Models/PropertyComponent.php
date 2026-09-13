<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read Property|null $property
 */
class PropertyComponent extends Model
{
    protected $fillable = [
        'property_id',
        'component_key',
        'area_value',
        'price_value',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'property_id' => 'integer',
            'area_value' => 'decimal:4',
            'price_value' => 'decimal:4',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
