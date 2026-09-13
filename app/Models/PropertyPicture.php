<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read Property|null $property
 */
class PropertyPicture extends Model
{
    protected $fillable = [
        'legacy_id',
        'property_id',
        'filename',
        'description',
        'orientation',
        'sort_order',
        'relative_path',
        'file_exists',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'legacy_id' => 'integer',
            'property_id' => 'integer',
            'orientation' => 'integer',
            'sort_order' => 'integer',
            'file_exists' => 'boolean',
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
