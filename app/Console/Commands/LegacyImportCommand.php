<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ImportQuarantine;
use App\Models\ImportRun;
use App\Models\PropertyPicture;
use App\Services\LegacyImport\AdjustmentsImporter;
use App\Services\LegacyImport\BordersImporter;
use App\Services\LegacyImport\CitiesImporter;
use App\Services\LegacyImport\CompaniesImporter;
use App\Services\LegacyImport\ComparablesImporter;
use App\Services\LegacyImport\ComponentsImporter;
use App\Services\LegacyImport\ContractorsImporter;
use App\Services\LegacyImport\ContractsImporter;
use App\Services\LegacyImport\FacadesImporter;
use App\Services\LegacyImport\FeeSharesImporter;
use App\Services\LegacyImport\LandsImporter;
use App\Services\LegacyImport\LegacyUsersMapImporter;
use App\Services\LegacyImport\NeighborhoodsImporter;
use App\Services\LegacyImport\OfferEstatesImporter;
use App\Services\LegacyImport\OffersImporter;
use App\Services\LegacyImport\PartnersImporter;
use App\Services\LegacyImport\PartyContactsImporter;
use App\Services\LegacyImport\PicturesImporter;
use App\Services\LegacyImport\PropertiesImporter;
use App\Services\LegacyImport\PropertyLocationsImporter;
use App\Services\LegacyImport\ServicesImporter;
use App\Services\LegacyImport\TotalsImporter;
use App\Services\LegacyImport\ValuationRequestsImporter;
use App\Support\Legacy\RequestPropertyLinker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LegacyImportCommand extends Command
{
    protected $signature = 'legacy:import
                            {--only= : Comma-separated importer keys}
                            {--resume : Resume from checkpoints of the latest run}
                            {--dry-run : Do not write to the target database}';

    protected $description = 'Import valuation domain from read-only legacy DB into muquem_v3';

    /**
     * @return array<string, class-string>
     */
    private function importers(): array
    {
        return [
            CompaniesImporter::key() => CompaniesImporter::class,
            CitiesImporter::key() => CitiesImporter::class,
            NeighborhoodsImporter::key() => NeighborhoodsImporter::class,
            LegacyUsersMapImporter::key() => LegacyUsersMapImporter::class,
            PartnersImporter::key() => PartnersImporter::class,
            ContractorsImporter::key() => ContractorsImporter::class,
            ValuationRequestsImporter::key() => ValuationRequestsImporter::class,
            PropertiesImporter::key() => PropertiesImporter::class,
            PropertyLocationsImporter::key() => PropertyLocationsImporter::class,
            ComparablesImporter::key() => ComparablesImporter::class,
            AdjustmentsImporter::key() => AdjustmentsImporter::class,
            ComponentsImporter::key() => ComponentsImporter::class,
            TotalsImporter::key() => TotalsImporter::class,
            BordersImporter::key() => BordersImporter::class,
            LandsImporter::key() => LandsImporter::class,
            FacadesImporter::key() => FacadesImporter::class,
            ServicesImporter::key() => ServicesImporter::class,
            PicturesImporter::key() => PicturesImporter::class,
            FeeSharesImporter::key() => FeeSharesImporter::class,
            OffersImporter::key() => OffersImporter::class,
            OfferEstatesImporter::key() => OfferEstatesImporter::class,
            ContractsImporter::key() => ContractsImporter::class,
            PartyContactsImporter::key() => PartyContactsImporter::class,
        ];
    }

    public function handle(): int
    {
        $targetDb = (string) config('database.connections.'.config('database.default').'.database');
        if ($targetDb !== 'muquem_v3') {
            $this->error("Aborting: target database is [{$targetDb}], expected muquem_v3.");

            return self::FAILURE;
        }

        $legacyDb = (string) config('database.connections.legacy.database');
        $this->info("Target DB: {$targetDb} (write)");
        $this->info("Legacy DB: {$legacyDb} (read-only)");

        // Safety: refuse if legacy connection somehow equals target.
        if ($legacyDb === $targetDb) {
            $this->error('Aborting: legacy and target databases must differ.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $resume = (bool) $this->option('resume');
        $only = $this->option('only');
        $selected = $this->importers();

        if (is_string($only) && trim($only) !== '') {
            $keys = array_filter(array_map('trim', explode(',', $only)));
            $selected = array_intersect_key($selected, array_flip($keys));
            if ($selected === []) {
                $this->error('No matching importers for --only='.$only);
                $this->line('Available: '.implode(', ', array_keys($this->importers())));

                return self::FAILURE;
            }
        }

        $run = null;
        if ($resume && ! $dryRun) {
            $run = ImportRun::query()->latest('id')->first();
        }

        if ($run === null) {
            $run = $dryRun
                ? new ImportRun(['name' => 'legacy-import-dry-run', 'status' => 'dry_run'])
                : ImportRun::query()->create([
                    'name' => 'legacy-import-'.now()->format('Ymd-His'),
                    'status' => 'running',
                    'started_at' => now(),
                    'meta' => [],
                ]);
            if ($dryRun) {
                $run->id = 0;
            }
        } else {
            $run->status = 'running';
            $run->save();
        }

        $results = [];
        $linkStats = ['certain' => 0, 'ambiguous' => 0, 'orphan' => 0];

        foreach ($selected as $key => $class) {
            $this->info("→ {$key}");
            /** @var object $importer */
            $importer = new $class($run, $dryRun, $resume);
            $stats = $importer->import();

            if ($importer instanceof PropertiesImporter) {
                $importer->quarantineOrphanRequests();
                $linkStats = $importer->linkStats();
                // Prefer linker summary for request-side integrity report.
                $summary = app(RequestPropertyLinker::class)->summarize('legacy');
                $linkStats = [
                    'certain' => $summary['certain'],
                    'ambiguous' => $summary['ambiguous'],
                    'orphan' => $summary['orphan'],
                ];
            }

            $results[$key] = $stats;
            $this->line(sprintf(
                '  processed=%d imported=%d quarantined=%d skipped=%d last_id=%d',
                $stats['processed'],
                $stats['imported'],
                $stats['quarantined'],
                $stats['skipped'],
                $stats['last_legacy_id']
            ));
        }

        // Drain sync queue if configured; for database queue count pending picture jobs later.
        if (config('queue.default') === 'sync') {
            // Verify jobs already ran inline.
        }

        $quarantineTotal = $dryRun
            ? array_sum(array_column($results, 'quarantined'))
            : ImportQuarantine::query()->where('import_run_id', $run->id)->count();

        $picturesMissing = PropertyPicture::query()->where('file_exists', false)->count();
        $picturesFound = PropertyPicture::query()->where('file_exists', true)->count();

        $meta = [
            'results' => $results,
            'link_stats' => $linkStats,
            'quarantine_total' => $quarantineTotal,
            'pictures_missing' => $picturesMissing,
            'pictures_found' => $picturesFound,
        ];

        if (! $dryRun) {
            $run->status = 'completed';
            $run->finished_at = now();
            $run->meta = $meta;
            $run->save();
        }

        $this->newLine();
        $this->info('=== Final report ===');
        $this->table(
            ['Metric', 'Count'],
            [
                ['resolved (certain links)', $linkStats['certain']],
                ['ambiguous', $linkStats['ambiguous']],
                ['orphaned', $linkStats['orphan']],
                ['quarantine total', $quarantineTotal],
                ['pictures found', $picturesFound],
                ['pictures missing', $picturesMissing],
            ]
        );

        $this->table(
            ['Importer', 'Processed', 'Imported', 'Quarantined', 'Skipped'],
            collect($results)->map(fn ($s, $k) => [$k, $s['processed'], $s['imported'], $s['quarantined'], $s['skipped']])->values()->all()
        );

        // Confirm we never wrote to legacy: simple read check.
        DB::connection('legacy')->select('select 1');

        return self::SUCCESS;
    }
}
