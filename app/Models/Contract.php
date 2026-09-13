<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property-read Contractor|null $contractor
 * @property-read ValuationRequest|null $valuationRequest
 */
class Contract extends Model
{
    use LogsActivity;
    use SoftDeletes;

    public const STATE_UNPAID = 0;

    public const STATE_PAID = 1;

    protected $fillable = [
        'legacy_id',
        'contractor_id',
        'valuation_request_id',
        'legacy_contractor_id',
        'legacy_request_id',
        'state',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'legacy_id' => 'integer',
            'contractor_id' => 'integer',
            'valuation_request_id' => 'integer',
            'legacy_contractor_id' => 'integer',
            'legacy_request_id' => 'integer',
            'state' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('Contract')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->logOnly(['state', 'contractor_id', 'valuation_request_id']);
    }

    /**
     * @return BelongsTo<Contractor, $this>
     */
    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    /**
     * @return BelongsTo<ValuationRequest, $this>
     */
    public function valuationRequest(): BelongsTo
    {
        return $this->belongsTo(ValuationRequest::class);
    }

    public function isPaid(): bool
    {
        return $this->state === self::STATE_PAID;
    }
}
