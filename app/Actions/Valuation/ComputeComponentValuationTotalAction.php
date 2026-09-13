<?php

declare(strict_types=1);

namespace App\Actions\Valuation;

use App\Models\Property;

/**
 * Recalculates the component-based valuation subtotal using the same type
 * branching as the legacy EvaluationRequestController PDF path.
 *
 * Component keys match ComponentsImporter storage names (e.g. floor_g).
 */
final class ComputeComponentValuationTotalAction
{
    public function execute(Property $property): float
    {
        if (! $property->relationLoaded('components')) {
            $property->load('components');
        }
        $map = $property->components->keyBy('component_key');

        $area = static function (string $key) use ($map): float {
            $component = $map->get($key);
            if ($component === null) {
                return 0.0;
            }

            return (float) ($component->area_value ?? 0);
        };
        $price = static function (string $key) use ($map): float {
            $component = $map->get($key);
            if ($component === null) {
                return 0.0;
            }

            return (float) ($component->price_value ?? 0);
        };

        $kind = (string) ($property->property_kind ?? '');
        $type = (string) ($property->property_type ?? '');

        if ($kind === 'سكني' && $type === 'شقة') {
            return $area('share_land') * $price('share_land')
                + $area('flat_area') * $price('flat_area');
        }

        if ($kind === 'سكني') {
            return $area('land_area') * $price('land_area')
                + $area('basement') * $price('basement')
                + $area('floor_g') * $price('floor_g')
                + $area('floor_a') * $price('floor_a')
                + $area('floor_u') * $price('floor_u')
                + $area('annexe') * $price('annexe')
                + $area('fence') * $price('fence')
                + $area('parking') * $price('parking')
                + $area('land_garden') * $price('land_garden')
                + $area('pool') * $price('pool');
        }

        if ($kind === 'تجاري') {
            return $area('land_area') * $price('land_area')
                + $area('basement') * $price('basement')
                + $area('parking') * $price('parking')
                + $area('ground') * $price('ground')
                + $area('upper') * $price('upper')
                + $area('shop') * $price('shop')
                + $area('apartment') * $price('apartment')
                + $area('office') * $price('office')
                + $area('exhibition') * $price('exhibition')
                + $area('fence') * $price('fence');
        }

        // أرض / زراعي / صناعي / مرفق — land meter price
        return $area('land_area') * $price('land_area');
    }
}
