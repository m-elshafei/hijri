<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read ValuationRequest|null $valuationRequest
 */
class RequestFeeShare extends Model
{
    protected $fillable = [
        'legacy_id',
        'valuation_request_id',
        'coordinator_share',
        'evaluator_share',
        'manager_share',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'legacy_id' => 'integer',
            'valuation_request_id' => 'integer',
            'coordinator_share' => 'integer',
            'evaluator_share' => 'integer',
            'manager_share' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ValuationRequest, $this>
     */
    public function valuationRequest(): BelongsTo
    {
        return $this->belongsTo(ValuationRequest::class);
    }
}
