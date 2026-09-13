<?php

declare(strict_types=1);

namespace App\Services\LegacyImport;

use App\Enums\Commercial\OfferEstatePaymentStatus;
use App\Enums\Commercial\OfferEstateStatus;
use App\Models\ImportRun;
use App\Models\Offer;
use App\Models\OfferEstate;
use App\Services\LegacyImport\Concerns\ResumableImporter;
use Illuminate\Database\Query\Builder;
use stdClass;

final class OfferEstatesImporter extends ResumableImporter
{
    /** @var array<int, int> */
    private array $offerMap = [];

    public function __construct(
        ImportRun $run,
        bool $dryRun = false,
        bool $resume = false,
    ) {
        parent::__construct($run, $dryRun, $resume);
        if (! $dryRun) {
            $this->offerMap = Offer::query()->pluck('id', 'legacy_id')->map(fn ($id) => (int) $id)->all();
        }
    }

    public static function key(): string
    {
        return 'offer_estates';
    }

    protected function legacyIdColumn(): string
    {
        return 'id';
    }

    protected function legacyQuery(): Builder
    {
        return $this->legacy()->table('multi_estate');
    }

    protected function importRow(stdClass $row): void
    {
        $legacyId = (int) $row->id;
        $legacyOfferId = (int) ($row->offer_price ?? 0);
        $offerId = $this->offerMap[$legacyOfferId] ?? null;

        if ($offerId === null) {
            $this->quarantine(self::key(), $legacyId, (array) $row, 'offer_not_imported');

            return;
        }

        $paid = (int) ($row->paid_estate ?? 0);
        $state = (int) ($row->state_estate ?? 0);

        $this->upsertByLegacyId(OfferEstate::class, $legacyId, [
            'offer_id' => $offerId,
            'estate_kind' => $this->nullableString($row->estate ?? null),
            'estate_type' => $this->nullableString($row->estate_type ?? null),
            'instrument_no' => $this->nullableString($row->Instrument_No ?? null),
            'area' => isset($row->area) ? (int) $row->area : null,
            'neighborhood' => $this->nullableString($row->neighborhood ?? null),
            'fees' => isset($row->fees) ? (int) $row->fees : null,
            'payment_status' => $paid === 1
                ? OfferEstatePaymentStatus::Paid->value
                : OfferEstatePaymentStatus::Unpaid->value,
            'status' => match ($state) {
                2 => OfferEstateStatus::Active->value,
                1 => OfferEstateStatus::Inactive->value,
                default => OfferEstateStatus::Draft->value,
            },
        ]);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }
}
