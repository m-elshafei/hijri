<?php

declare(strict_types=1);

namespace App\Services\LegacyImport;

use App\Enums\Commercial\PartyContactOwnerType;
use App\Enums\Commercial\PartyContactStatus;
use App\Models\Contractor;
use App\Models\ImportRun;
use App\Models\Partner;
use App\Models\PartyContact;
use App\Services\LegacyImport\Concerns\ResumableImporter;
use Illuminate\Database\Query\Builder;
use stdClass;

/**
 * Legacy fellow_emails serves both partners and contractors (type + fellow_id).
 */
final class PartyContactsImporter extends ResumableImporter
{
    /** @var array<int, int> */
    private array $partnerMap = [];

    /** @var array<int, int> */
    private array $contractorMap = [];

    public function __construct(
        ImportRun $run,
        bool $dryRun = false,
        bool $resume = false,
    ) {
        parent::__construct($run, $dryRun, $resume);
        if (! $dryRun) {
            $this->partnerMap = Partner::query()->pluck('id', 'legacy_id')->map(fn ($id) => (int) $id)->all();
            $this->contractorMap = Contractor::query()->pluck('id', 'legacy_id')->map(fn ($id) => (int) $id)->all();
        }
    }

    public static function key(): string
    {
        return 'party_contacts';
    }

    protected function legacyIdColumn(): string
    {
        return 'id';
    }

    protected function legacyQuery(): Builder
    {
        return $this->legacy()->table('fellow_emails');
    }

    protected function importRow(stdClass $row): void
    {
        $legacyId = (int) $row->id;
        $fellowId = (int) ($row->fellow_id ?? 0);
        $type = strtolower(trim((string) ($row->type ?? '')));

        $partnerId = $this->partnerMap[$fellowId] ?? null;
        $contractorId = $this->contractorMap[$fellowId] ?? null;

        $ownerType = null;
        $resolvedPartnerId = null;
        $resolvedContractorId = null;

        if ($type === 'partner_info' && $partnerId !== null) {
            $ownerType = PartyContactOwnerType::Partner;
            $resolvedPartnerId = $partnerId;
        } elseif ($type === 'contractor_info' && $contractorId !== null) {
            $ownerType = PartyContactOwnerType::Contractor;
            $resolvedContractorId = $contractorId;
        } elseif ($type === 'partner_info' && $partnerId === null && $contractorId !== null) {
            // Mis-tagged but resolvable to contractor
            $ownerType = PartyContactOwnerType::Contractor;
            $resolvedContractorId = $contractorId;
        } elseif ($type === 'contractor_info' && $contractorId === null && $partnerId !== null) {
            $ownerType = PartyContactOwnerType::Partner;
            $resolvedPartnerId = $partnerId;
        } elseif ($contractorId !== null && $partnerId === null) {
            $ownerType = PartyContactOwnerType::Contractor;
            $resolvedContractorId = $contractorId;
        } elseif ($partnerId !== null && $contractorId === null) {
            $ownerType = PartyContactOwnerType::Partner;
            $resolvedPartnerId = $partnerId;
        } elseif ($partnerId !== null && $contractorId !== null) {
            // Ambiguous shared id with empty type — prefer partner (majority of legacy rows)
            $ownerType = PartyContactOwnerType::Partner;
            $resolvedPartnerId = $partnerId;
        }

        if ($ownerType === null) {
            $this->quarantine(self::key(), $legacyId, (array) $row, 'owner_not_imported');

            return;
        }

        $state = (int) ($row->state ?? 1);
        $status = match ($state) {
            -1 => PartyContactStatus::Inactive->value,
            2 => PartyContactStatus::Active->value,
            default => PartyContactStatus::Pending->value,
        };

        $this->upsertByLegacyId(PartyContact::class, $legacyId, [
            'partner_id' => $resolvedPartnerId,
            'contractor_id' => $resolvedContractorId,
            'owner_type' => $ownerType->value,
            'name' => trim((string) ($row->name ?? '')) ?: __('Unknown'),
            'email' => $this->nullableString($row->email ?? null),
            'phone_number' => $this->nullableString($row->phone_number ?? null),
            'status' => $status,
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
