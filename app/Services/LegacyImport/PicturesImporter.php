<?php

declare(strict_types=1);

namespace App\Services\LegacyImport;

use App\Jobs\LegacyImport\VerifyPropertyPictureJob;
use App\Models\PropertyPicture;
use App\Services\LegacyImport\Concerns\ResolvesImportedEntities;
use App\Services\LegacyImport\Concerns\ResumableImporter;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Log;
use stdClass;

final class PicturesImporter extends ResumableImporter
{
    use ResolvesImportedEntities;

    /** @var list<int> */
    private array $pendingVerifyIds = [];

    private bool $hasSearchPaths = false;

    public static function key(): string
    {
        return 'pictures';
    }

    protected function legacyIdColumn(): string
    {
        return 'idPicture';
    }

    protected function legacyQuery(): Builder
    {
        return $this->legacy()->table('picture');
    }

    public function import(): array
    {
        $paths = config('legacy_import.picture_search_paths', []);
        $this->hasSearchPaths = is_array($paths) && $paths !== [];

        $result = parent::import();
        $this->flushVerifyJobs();

        if (! $this->dryRun && ! $this->hasSearchPaths) {
            PropertyPicture::query()
                ->where('file_exists', true)
                ->orWhereNotNull('relative_path')
                ->update(['file_exists' => false, 'relative_path' => null]);

            Log::info('legacy_import.pictures_unverified', [
                'reason' => 'no_search_paths_configured',
                'count' => PropertyPicture::query()->count(),
            ]);
        }

        return $result;
    }

    protected function importRow(stdClass $row): void
    {
        $legacyId = (int) $row->idPicture;
        $legacyReiId = (int) ($row->Real_Estate_Information ?? 0);
        $propertyId = $this->propertyIdByLegacyRei($legacyReiId);
        if ($propertyId === null) {
            $this->quarantine(self::key(), $legacyId, (array) $row, 'property_not_imported');

            return;
        }

        $filename = trim((string) ($row->Picture ?? ''));
        if ($filename === '') {
            $this->quarantine(self::key(), $legacyId, (array) $row, 'missing_filename');

            return;
        }

        $picture = $this->upsertByLegacyId(PropertyPicture::class, $legacyId, [
            'property_id' => $propertyId,
            'filename' => $filename,
            'description' => $this->nullableString($row->description ?? null),
            'orientation' => isset($row->orientation) ? (int) $row->orientation : null,
            'sort_order' => isset($row->Image_ordering) ? (int) $row->Image_ordering : 0,
            'file_exists' => false,
            'relative_path' => null,
        ]);

        if ($picture instanceof PropertyPicture && $this->hasSearchPaths) {
            $this->pendingVerifyIds[] = (int) $picture->id;
            if (count($this->pendingVerifyIds) >= $this->chunkSize) {
                $this->flushVerifyJobs();
            }
        }
    }

    private function flushVerifyJobs(): void
    {
        if ($this->dryRun || $this->pendingVerifyIds === [] || ! $this->hasSearchPaths) {
            $this->pendingVerifyIds = [];

            return;
        }

        foreach ($this->pendingVerifyIds as $id) {
            VerifyPropertyPictureJob::dispatch($id);
        }
        $this->pendingVerifyIds = [];
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
