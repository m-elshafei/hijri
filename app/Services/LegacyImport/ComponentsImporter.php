<?php

declare(strict_types=1);

namespace App\Services\LegacyImport;

use App\Models\ImportRun;
use App\Models\Property;
use App\Services\LegacyImport\Concerns\ResumableImporter;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

final class ComponentsImporter extends ResumableImporter
{
    /**
     * @var array<string, array{0:string,1:string}>
     */
    private const MAP = [
        'land_area' => ['price_meter', 'land_area'],
        'floorG' => ['pfloorG', 'floor_g'],
        'floorA' => ['pfloorA', 'floor_a'],
        'floorB' => ['pfloorB', 'floor_b'],
        'floorU' => ['pfloorU', 'floor_u'],
        'repeated_floors' => ['prepeated_floors', 'repeated_floors'],
        'repeated_No' => ['prepeated_No', 'repeated_no'],
        'flat_area' => ['pflat_area', 'flat_area'],
        'share_land' => ['pshare_land', 'share_land'],
        'fence' => ['pfence', 'fence'],
        'pool' => ['ppool', 'pool'],
        'annexe' => ['pannexe', 'annexe'],
        'parking' => ['pparking', 'parking'],
        'land_garden' => ['pland_garden', 'land_garden'],
        'basement' => ['pbasement', 'basement'],
        'shop' => ['pshop', 'shop'],
        'apartment' => ['papartment', 'apartment'],
        'office' => ['poffice', 'office'],
        'exhibition' => ['pexhibition', 'exhibition'],
        'warehouse' => ['pwarehouse', 'warehouse'],
        'pond' => ['ppond', 'pond'],
        'silo' => ['psilo', 'silo'],
        'vertical_tank' => ['pvertical_tank', 'vertical_tank'],
        'chalets' => ['pchalets', 'chalets'],
        'children_space' => ['pchildren_space', 'children_space'],
        'supplies' => ['psupplies', 'supplies'],
        'restaurant' => ['prestaurant', 'restaurant'],
        'mosque' => ['pmosque', 'mosque'],
        'buffet' => ['pbuffet', 'buffet'],
        'workers_places' => ['pworkers_places', 'workers_places'],
        'fuel_tank' => ['pfuel_tank', 'fuel_tank'],
        'break' => ['pbreak', 'break'],
        'water_tank' => ['pwater_tank', 'water_tank'],
        'electric_and_mechanical' => ['pelectric_and_mechanical', 'electric_and_mechanical'],
        'oil' => ['poil', 'oil'],
        'tire' => ['ptire', 'tire'],
        'share_loft' => ['pshare_loft', 'share_loft'],
        'loft_area' => ['ploft_area', 'loft_area'],
        'earnings' => ['pearnings', 'earnings'],
        'depreciation' => ['pdepreciation', 'depreciation'],
        'discount' => ['pdiscount', 'discount'],
        'cost' => ['pcost', 'cost'],
        'share_floor' => ['pshare_floor', 'share_floor'],
        'floor_area' => ['pfloor_area', 'floor_area'],
        'grass' => ['pgrass', 'grass'],
        'upper' => ['pupper', 'upper'],
        'ground' => ['pground', 'ground'],
        'base_area' => ['base_area', 'base_area'],
        'ground_commercial' => ['pground_commercial', 'ground_commercial'],
        'first_commercial' => ['pfirst_commercial', 'first_commercial'],
        'ground_floor' => ['pground_floor', 'ground_floor'],
        'first_office' => ['pfirst_office', 'first_office'],
        'first_basement' => ['pfirst_basement', 'first_basement'],
        'second_basement' => ['psecond_basement', 'second_basement'],
        'electricity_room' => ['pelectricity_room', 'electricity_room'],
        'kiosk' => ['pkiosk', 'kiosk'],
        'ground_office' => ['pground_office', 'ground_office'],
    ];

    /** @var array<int, int> legacy REI id => property id */
    private array $propertyMap = [];

    /** @var array<int, stdClass> legacy REI id => price row */
    private array $priceMap = [];

    /** @var list<array<string, mixed>> */
    private array $buffer = [];

    public function __construct(
        ImportRun $run,
        bool $dryRun = false,
        bool $resume = false,
    ) {
        parent::__construct($run, $dryRun, $resume);
        if (! $dryRun) {
            $this->propertyMap = Property::query()->pluck('id', 'legacy_id')->map(fn ($id) => (int) $id)->all();
            foreach ($this->legacy()->table('building_price')->cursor() as $price) {
                $this->priceMap[(int) $price->Real_Estate_Information] = $price;
            }
        }
    }

    public static function key(): string
    {
        return 'components';
    }

    protected function legacyIdColumn(): string
    {
        return 'id';
    }

    protected function legacyQuery(): Builder
    {
        return $this->legacy()->table('building_areas');
    }

    public function import(): array
    {
        $result = parent::import();
        $this->flushBuffer();

        return $result;
    }

    protected function importRow(stdClass $row): void
    {
        $legacyReiId = (int) ($row->Real_Estate_Information ?? 0);
        $propertyId = $this->propertyMap[$legacyReiId] ?? null;
        if ($propertyId === null) {
            $this->quarantine(self::key(), (int) $row->id, ['rei' => $legacyReiId], 'property_not_imported');

            return;
        }

        $priceRow = $this->priceMap[$legacyReiId] ?? null;
        $now = now()->toDateTimeString();

        foreach (self::MAP as $areaCol => [$priceCol, $key]) {
            $areaRaw = $row->{$areaCol} ?? null;
            $priceRaw = $priceRow->{$priceCol} ?? null;
            if ($key === 'base_area' && $priceRow !== null) {
                $priceRaw = $priceRow->base_amount ?? $priceRaw;
            }

            $area = $this->toDecimal($areaRaw);
            $price = $this->toDecimal($priceRaw);
            if ($area === null && $price === null) {
                continue;
            }

            if ($this->dryRun) {
                $this->imported++;

                continue;
            }

            $this->buffer[] = [
                'property_id' => $propertyId,
                'component_key' => $key,
                'area_value' => $area,
                'price_value' => $price,
                'meta' => json_encode([
                    'legacy_area_column' => $areaCol,
                    'legacy_price_column' => $priceCol,
                ], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $this->imported++;

            if (count($this->buffer) >= 500) {
                $this->flushBuffer();
            }
        }
    }

    private function flushBuffer(): void
    {
        if ($this->buffer === [] || $this->dryRun) {
            return;
        }

        DB::table('property_components')->upsert(
            $this->buffer,
            ['property_id', 'component_key'],
            ['area_value', 'price_value', 'meta', 'updated_at']
        );
        $this->buffer = [];
    }

    private function toDecimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 4, '.', '');
    }
}
