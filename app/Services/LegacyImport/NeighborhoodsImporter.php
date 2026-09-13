<?php

declare(strict_types=1);

namespace App\Services\LegacyImport;

use App\Models\GeoCity;
use App\Models\GeoNeighborhood;
use App\Services\LegacyImport\Concerns\ResumableImporter;
use Illuminate\Database\Query\Builder;
use stdClass;

final class NeighborhoodsImporter extends ResumableImporter
{
    public static function key(): string
    {
        return 'neighborhoods';
    }

    protected function legacyIdColumn(): string
    {
        return 'idNeighborhood';
    }

    protected function legacyQuery(): Builder
    {
        return $this->legacy()->table('neighborhood');
    }

    protected function importRow(stdClass $row): void
    {
        $legacyCityId = (int) ($row->City ?? 0);
        $cityId = $legacyCityId > 0
            ? GeoCity::query()->where('legacy_id', $legacyCityId)->value('id')
            : null;

        if ($cityId === null) {
            $this->quarantine(self::key(), (int) $row->idNeighborhood, (array) $row, 'city_not_imported');

            return;
        }

        $this->upsertByLegacyId(GeoNeighborhood::class, (int) $row->idNeighborhood, [
            'city_id' => $cityId,
            'name_ar' => (string) ($row->neighborhood ?? ''),
            'name_en' => null,
        ]);
    }
}
