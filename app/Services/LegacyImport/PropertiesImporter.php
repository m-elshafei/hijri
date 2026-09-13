<?php

declare(strict_types=1);

namespace App\Services\LegacyImport;

use App\Models\ImportRun;
use App\Models\Property;
use App\Models\ValuationRequest;
use App\Services\LegacyImport\Concerns\ResolvesImportedEntities;
use App\Services\LegacyImport\Concerns\ResumableImporter;
use App\Support\Legacy\RequestPropertyLinker;
use App\Support\Legacy\YesNoNormalizer;
use Illuminate\Database\Query\Builder;
use stdClass;

final class PropertiesImporter extends ResumableImporter
{
    use ResolvesImportedEntities;

    /** @var array{certain:int,ambiguous:int,orphan:int} */
    private array $linkStats = ['certain' => 0, 'ambiguous' => 0, 'orphan' => 0];

    public function __construct(
        ImportRun $run,
        bool $dryRun = false,
        bool $resume = false,
        private readonly ?RequestPropertyLinker $linker = null,
    ) {
        parent::__construct($run, $dryRun, $resume);
    }

    public static function key(): string
    {
        return 'properties';
    }

    /**
     * @return array{certain:int,ambiguous:int,orphan:int}
     */
    public function linkStats(): array
    {
        return $this->linkStats;
    }

    protected function legacyIdColumn(): string
    {
        return 'idReal_Estate_Information';
    }

    protected function legacyQuery(): Builder
    {
        return $this->legacy()->table('real_estate_information');
    }

    protected function importRow(stdClass $row): void
    {
        $legacyReiId = (int) $row->idReal_Estate_Information;
        $locationId = isset($row->Location) ? (int) $row->Location : 0;

        $valuationRequestId = null;
        $requestDeposit = null;

        if ($locationId <= 0) {
            $this->linkStats['orphan']++;
            $this->quarantine('property', $legacyReiId, (array) $row, 'rei_missing_location');
        } else {
            $requests = $this->legacy()->table('request')
                ->where('Location', $locationId)
                ->orderBy('idRequest')
                ->get();

            if ($requests->isEmpty()) {
                $this->linkStats['orphan']++;
                $this->quarantine('property', $legacyReiId, [
                    'rei_id' => $legacyReiId,
                    'location_id' => $locationId,
                ], 'orphan_rei_no_request');
            } elseif ($requests->count() > 1) {
                $this->linkStats['ambiguous']++;
                $this->quarantine('property', $legacyReiId, [
                    'rei_id' => $legacyReiId,
                    'location_id' => $locationId,
                    'request_ids' => $requests->pluck('idRequest')->all(),
                ], 'ambiguous_multiple_requests_for_location');
                foreach ($requests as $req) {
                    $this->quarantine('valuation_request', (int) $req->idRequest, [
                        'location_id' => $locationId,
                        'rei_id' => $legacyReiId,
                    ], 'ambiguous_shared_location');
                }
            } else {
                $request = $requests->first();
                $linker = $this->linker ?? app(RequestPropertyLinker::class);
                $result = $linker->resolve($request, (string) config('legacy_import.legacy_connection', 'legacy'));

                if ($result['resolution'] === RequestPropertyLinker::RESOLUTION_CERTAIN
                    && (int) ($result['rei_id'] ?? 0) === $legacyReiId) {
                    $this->linkStats['certain']++;
                    $valuationRequestId = $this->valuationRequestIdByLegacy((int) $request->idRequest);
                    $requestDeposit = $this->nullableString($request->deposit_number ?? null);

                    if ($valuationRequestId === null) {
                        $this->quarantine('property', $legacyReiId, (array) $row, 'certain_but_request_not_imported');
                    }
                } elseif ($result['resolution'] === RequestPropertyLinker::RESOLUTION_AMBIGUOUS) {
                    $this->linkStats['ambiguous']++;
                    $this->quarantine('property', $legacyReiId, $result, $result['reason']);
                    $this->quarantine('valuation_request', (int) $request->idRequest, $result, $result['reason']);
                } else {
                    $this->linkStats['orphan']++;
                    $this->quarantine('property', $legacyReiId, $result, $result['reason']);
                    $this->quarantine('valuation_request', (int) $request->idRequest, $result, $result['reason']);
                }
            }
        }

        $reiDeposit = $this->nullableString($this->attrTatweel($row, 'deposit_number'));
        $propertyDeposit = $reiDeposit ?? $requestDeposit;

        if ($valuationRequestId !== null && $requestDeposit !== null && ! $this->dryRun) {
            ValuationRequest::query()->where('id', $valuationRequestId)->update([
                'deposit_number' => $requestDeposit,
            ]);
        }

        if ($valuationRequestId !== null && $requestDeposit === null && $reiDeposit !== null && ! $this->dryRun) {
            ValuationRequest::query()->where('id', $valuationRequestId)->whereNull('deposit_number')->update([
                'deposit_number' => $reiDeposit,
            ]);
        }

        // Avoid unique FK collision: clear any other property already linked to this request.
        if ($valuationRequestId !== null && ! $this->dryRun) {
            Property::query()
                ->where('valuation_request_id', $valuationRequestId)
                ->where('legacy_id', '!=', $legacyReiId)
                ->update(['valuation_request_id' => null]);
        }

        $otherUsersRaw = $row->other_users ?? null;
        $otherUsersBool = is_string($otherUsersRaw) || is_numeric($otherUsersRaw)
            ? YesNoNormalizer::toBool($otherUsersRaw)
            : null;

        $this->upsertByLegacyId(Property::class, $legacyReiId, [
            'valuation_request_id' => $valuationRequestId,
            'legacy_location_id' => $locationId > 0 ? $locationId : null,
            'customer_name' => $this->nullableString($row->customer_name ?? null),
            'owner_name' => $this->nullableString($row->owner_name ?? null),
            'customer_name_en' => $this->nullableString($row->customer_name_en ?? null),
            'owner_name_en' => $this->nullableString($row->owner_name_en ?? null),
            'property_kind' => $this->nullableString($row->type ?? null),
            'property_type' => $this->nullableString($row->property_type ?? null),
            'notes_real_estate' => $this->nullableString($row->note_real_estate ?? null),
            'notes_property' => $this->nullableString($row->note_property ?? null),
            'instrument_no' => $this->nullableString($row->Instrument_No ?? null),
            'instrument_date' => $this->nullableString($row->Instrument_date ?? null),
            'instrument_gregorian_date' => $this->nullableString($row->instrument_gregorian_date ?? null),
            'license_no' => $this->nullableString($row->license_No ?? null),
            'license_date' => $this->nullableString($row->license_date ?? null),
            'issued_by' => $this->nullableString($row->issued_by ?? null),
            'commissioning_date' => $this->nullableString($row->commissioning_date ?? null),
            'retail_no' => $this->nullableString($row->retail_No ?? null),
            'undergo_reasons' => $this->nullableString($row->undergo_reasons ?? null),
            'valuation_type' => isset($row->valuation_type) ? (int) $row->valuation_type : null,
            'valuation_type_desc' => $this->nullableString($row->valuation_type_desc ?? null),
            'base_value' => $this->nullableString($row->base_value ?? null),
            'note_base_value' => $this->nullableString($row->note_base_value ?? null),
            'valuation_way_type' => $this->nullableString($row->valuation_way_type ?? null),
            'note_valuation_way_type' => $this->nullableString($row->note_valuation_way_type ?? null),
            'print_valuation_certificate' => isset($row->print_valuation_certificat)
                ? YesNoNormalizer::toBool($row->print_valuation_certificat)
                : null,
            'requested_papers' => $this->nullableString($row->requested_papers ?? null),
            'assumptions' => $this->nullableString($row->assumptions ?? null),
            'area_of_search' => $this->nullableString($row->area_of_search ?? null),
            'area_approval_way' => $this->nullableString($row->area_approval_way ?? null),
            'informations_source' => $this->nullableString($row->informations_source ?? null),
            'important_assumptions' => $this->nullableString($row->important_assumptions ?? null),
            'conclusion_value' => $this->nullableString($row->conclusion_value ?? null),
            'type_of_getings_value' => $this->nullableString($row->type_of_getings_value ?? null),
            'value_assumption' => isset($row->value_assumption) ? (int) $row->value_assumption : null,
            'other_users_raw' => is_scalar($otherUsersRaw) ? (string) $otherUsersRaw : null,
            'other_users' => $otherUsersBool,
            'valuation_usage' => isset($row->valuation_usage) ? (int) $row->valuation_usage : null,
            'valuation_usage_old' => isset($row->valuation_usage_old) ? (int) $row->valuation_usage_old : null,
            'valuation_usage_desc' => $this->nullableString($row->valuation_usage_desc ?? null),
            'deposit_number' => $propertyDeposit,
            'market_valuation_way_main' => isset($row->market_valuation_way_main) ? (int) $row->market_valuation_way_main : null,
            'income_valuation_way_main' => isset($row->income_valuation_way_main) ? (int) $row->income_valuation_way_main : null,
            'cost_valuation_way_main' => isset($row->cost_valuation_way_main) ? (int) $row->cost_valuation_way_main : null,
            'market_valuation_way_sub' => isset($row->market_valuation_way_sub) ? (int) $row->market_valuation_way_sub : null,
            'income_valuation_way_sub' => isset($row->income_valuation_way_sub) ? (int) $row->income_valuation_way_sub : null,
            'cost_valuation_way_sub' => isset($row->cost_valuation_way_sub) ? (int) $row->cost_valuation_way_sub : null,
            'forced_sale_percentage' => isset($row->forced_sale_percentage) ? (int) $row->forced_sale_percentage : null,
            'notes' => $this->nullableString($row->notes ?? null),
            'evaluation_date' => $row->evaluation_date ?? null,
            'evaluation_date_hijri' => $this->nullableString($row->evaluation_date_hijri ?? null),
            'according_instrument' => isset($row->according_instrument) ? (bool) $row->according_instrument : null,
        ]);
    }

    /**
     * Quarantine orphan requests that have no REI (not covered by the REI walk).
     */
    public function quarantineOrphanRequests(): void
    {
        $linker = $this->linker ?? app(RequestPropertyLinker::class);
        $connection = (string) config('legacy_import.legacy_connection', 'legacy');

        $this->legacy()->table('request')->orderBy('idRequest')->chunkById(500, function ($rows) use ($linker, $connection): void {
            foreach ($rows as $request) {
                $result = $linker->resolve($request, $connection);
                if ($result['resolution'] !== RequestPropertyLinker::RESOLUTION_ORPHAN) {
                    continue;
                }
                if ($result['rei_ids'] !== []) {
                    continue;
                }

                $this->quarantine(
                    'valuation_request',
                    (int) $request->idRequest,
                    $result,
                    $result['reason']
                );
            }
        }, 'idRequest');
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
