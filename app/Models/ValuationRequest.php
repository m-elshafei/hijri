<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Valuation\RequestState;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $started_at
 * @property Carbon|null $under_evaluation_at
 * @property Carbon|null $evaluated_at
 * @property Carbon|null $ended_at
 * @property Carbon|null $qima_locked_at
 * @property-read Property|null $property
 * @property-read Company|null $company
 * @property-read User|null $coordinator
 * @property-read User|null $evaluator
 * @property-read Collection<int, RequestFeeShare> $feeShares
 * @property-read Collection<int, Contract> $contracts
 * @property-read Collection<int, Offer> $offers
 */
class ValuationRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'legacy_id',
        'reference',
        'number',
        'deposit_number',
        'company_id',
        'coordinator_user_id',
        'evaluator_user_id',
        'sub_user_id',
        'fellow_user_id',
        'legacy_coordinator_id',
        'legacy_evaluator_id',
        'legacy_sub_user_id',
        'legacy_fellow_id',
        'legacy_location_id',
        'state',
        'approve',
        'started_at',
        'under_evaluation_at',
        'evaluated_at',
        'ended_at',
        'uploaded_on_qima',
        'official_report_path',
        'qima_locked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'legacy_id' => 'integer',
            'reference' => 'integer',
            'company_id' => 'integer',
            'coordinator_user_id' => 'integer',
            'evaluator_user_id' => 'integer',
            'sub_user_id' => 'integer',
            'fellow_user_id' => 'integer',
            'legacy_coordinator_id' => 'integer',
            'legacy_evaluator_id' => 'integer',
            'legacy_sub_user_id' => 'integer',
            'legacy_fellow_id' => 'integer',
            'legacy_location_id' => 'integer',
            'approve' => 'integer',
            'started_at' => 'datetime',
            'under_evaluation_at' => 'datetime',
            'evaluated_at' => 'datetime',
            'ended_at' => 'datetime',
            'uploaded_on_qima' => 'boolean',
            'qima_locked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return HasOne<Property, $this>
     */
    public function property(): HasOne
    {
        return $this->hasOne(Property::class);
    }

    /**
     * @return HasMany<RequestFeeShare, $this>
     */
    public function feeShares(): HasMany
    {
        return $this->hasMany(RequestFeeShare::class);
    }

    /**
     * @return HasMany<Contract, $this>
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * @return HasMany<Offer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_user_id');
    }

    public function isQimaLocked(): bool
    {
        return $this->qima_locked_at !== null || $this->uploaded_on_qima === true;
    }

    public function isFinallyApproved(): bool
    {
        $mapped = RequestState::tryFromLegacy($this->state);

        return ($mapped === RequestState::Approve)
            || $this->approve === 1;
    }

    public function stateLabel(): string
    {
        return RequestState::labelFor($this->state);
    }
}
