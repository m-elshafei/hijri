<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read ValuationRequest|null $valuationRequest
 * @property-read PropertyLocation|null $location
 * @property-read PropertyTotal|null $total
 * @property-read Collection<int, PropertyComparable> $comparables
 * @property-read Collection<int, PropertyAdjustment> $adjustments
 * @property-read Collection<int, PropertyComponent> $components
 * @property-read Collection<int, PropertyPicture> $pictures
 * @property-read Collection<int, PropertyBorder> $borders
 * @property-read Collection<int, PropertyLand> $lands
 * @property-read Collection<int, PropertyFacade> $facades
 * @property-read Collection<int, PropertyService> $services
 */
class Property extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'legacy_id',
        'valuation_request_id',
        'legacy_location_id',
        'customer_name',
        'owner_name',
        'customer_name_en',
        'owner_name_en',
        'property_kind',
        'property_type',
        'notes_real_estate',
        'notes_property',
        'instrument_no',
        'instrument_date',
        'instrument_gregorian_date',
        'license_no',
        'license_date',
        'issued_by',
        'commissioning_date',
        'retail_no',
        'undergo_reasons',
        'valuation_type',
        'valuation_type_desc',
        'base_value',
        'note_base_value',
        'valuation_way_type',
        'note_valuation_way_type',
        'print_valuation_certificate',
        'requested_papers',
        'assumptions',
        'area_of_search',
        'area_approval_way',
        'informations_source',
        'important_assumptions',
        'conclusion_value',
        'type_of_getings_value',
        'value_assumption',
        'other_users',
        'other_users_yes',
        'valuation_usage',
        'valuation_usage_old',
        'valuation_usage_desc',
        'deposit_number',
        'market_valuation_way_main',
        'income_valuation_way_main',
        'cost_valuation_way_main',
        'market_valuation_way_sub',
        'income_valuation_way_sub',
        'cost_valuation_way_sub',
        'forced_sale_percentage',
        'notes',
        'evaluation_date',
        'evaluation_date_hijri',
        'according_instrument',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'legacy_id' => 'integer',
            'valuation_request_id' => 'integer',
            'legacy_location_id' => 'integer',
            'valuation_type' => 'integer',
            'print_valuation_certificate' => 'boolean',
            'value_assumption' => 'integer',
            'other_users_yes' => 'boolean',
            'valuation_usage' => 'integer',
            'valuation_usage_old' => 'integer',
            'market_valuation_way_main' => 'integer',
            'income_valuation_way_main' => 'integer',
            'cost_valuation_way_main' => 'integer',
            'market_valuation_way_sub' => 'integer',
            'income_valuation_way_sub' => 'integer',
            'cost_valuation_way_sub' => 'integer',
            'forced_sale_percentage' => 'integer',
            'evaluation_date' => 'datetime',
            'according_instrument' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ValuationRequest, $this>
     */
    public function valuationRequest(): BelongsTo
    {
        return $this->belongsTo(ValuationRequest::class);
    }

    /**
     * @return HasOne<PropertyLocation, $this>
     */
    public function location(): HasOne
    {
        return $this->hasOne(PropertyLocation::class);
    }

    /**
     * @return HasMany<PropertyComparable, $this>
     */
    public function comparables(): HasMany
    {
        return $this->hasMany(PropertyComparable::class);
    }

    /**
     * @return HasMany<PropertyAdjustment, $this>
     */
    public function adjustments(): HasMany
    {
        return $this->hasMany(PropertyAdjustment::class);
    }

    /**
     * @return HasMany<PropertyComponent, $this>
     */
    public function components(): HasMany
    {
        return $this->hasMany(PropertyComponent::class);
    }

    /**
     * @return HasMany<PropertyPicture, $this>
     */
    public function pictures(): HasMany
    {
        return $this->hasMany(PropertyPicture::class);
    }

    /**
     * @return HasMany<PropertyBorder, $this>
     */
    public function borders(): HasMany
    {
        return $this->hasMany(PropertyBorder::class);
    }

    /**
     * @return HasMany<PropertyLand, $this>
     */
    public function lands(): HasMany
    {
        return $this->hasMany(PropertyLand::class);
    }

    /**
     * @return HasMany<PropertyFacade, $this>
     */
    public function facades(): HasMany
    {
        return $this->hasMany(PropertyFacade::class);
    }

    /**
     * @return HasMany<PropertyService, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(PropertyService::class);
    }

    /**
     * @return HasOne<PropertyTotal, $this>
     */
    public function total(): HasOne
    {
        return $this->hasOne(PropertyTotal::class);
    }
}
