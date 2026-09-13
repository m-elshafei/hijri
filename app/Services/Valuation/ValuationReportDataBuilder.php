<?php

declare(strict_types=1);

namespace App\Services\Valuation;

use App\Actions\Valuation\ResolveValuationFinalAmountAction;
use App\Enums\Valuation\ReportExportVariant;
use App\Models\Property;
use App\Models\PropertyBorder;
use App\Models\ValuationRequest;
use App\Support\Valuation\ArabicAmountInWords;
use App\Support\Valuation\PropertyPictureMedia;
use App\Support\Valuation\ValuationReportPictureHtml;

/**
 * Assembles Blade data for valuation PDF variants from imported domain models.
 */
final class ValuationReportDataBuilder
{
    public function __construct(
        private readonly ResolveValuationFinalAmountAction $resolveFinalAmount = new ResolveValuationFinalAmountAction,
        private readonly ArabicAmountInWords $amountInWords = new ArabicAmountInWords,
        private readonly PropertyPictureMedia $pictureMedia = new PropertyPictureMedia,
        private readonly ValuationReportPictureHtml $pictureHtml = new ValuationReportPictureHtml,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(ValuationRequest $request, ReportExportVariant $variant): array
    {
        $request->loadMissing([
            'company',
            'coordinator',
            'evaluator',
            'property.location.city',
            'property.location.neighborhood',
            'property.total',
            'property.pictures',
            'property.borders',
            'property.lands',
            'property.facades',
            'property.services',
            'property.components',
            'property.comparables',
            'property.adjustments',
        ]);

        $property = $request->property;
        $totals = $this->resolveFinalAmount->execute($request);
        $amount = $totals['amount'];
        $finalFormatted = $amount !== null ? number_format($amount, 2) : '—';
        $words = $amount !== null
            ? trim($this->amountInWords->convert($amount).' '.__('Saudi riyal'))
            : '';

        $border = $property?->borders->first();
        $land = $property?->lands->first();
        $facade = $property?->facades->first();
        $service = $property?->services->first();
        $location = $property?->location;

        $picturesHtml = $property instanceof Property
            ? $this->pictureHtml->render($property)
            : '';

        $reportImages = $property instanceof Property
            ? $this->pictureMedia->forPdfReport($property->pictures)
            : ['images' => [], 'missing' => [], 'notes' => []];

        $streetCount = $this->streetCount($facade, $border);

        return [
            'variant' => $variant,
            'request' => $request,
            'property' => $property,
            'company' => $request->company,
            'coordinator' => $request->coordinator,
            'evaluator' => $request->evaluator,
            'location' => $location,
            'border' => $border,
            'land' => $land,
            'facade' => $facade,
            'service' => $service,
            'components' => $property === null ? collect() : $property->components,
            'comparables' => $property === null ? collect() : $property->comparables,
            'adjustments' => $property === null ? collect() : $property->adjustments,
            'totals' => $totals,
            'propertyTotal' => $totals['property_total'],
            'final_amount' => $finalFormatted,
            'final_amount_raw' => $amount,
            'value_in_words' => $words,
            'is_manual_amount' => $totals['is_manual'],
            'forced_sale_percentage' => $totals['forced_sale_percentage'],
            'forced_sale_amount' => $totals['forced_sale_amount'],
            'total_area' => $totals['total_area'],
            'street_count' => $streetCount,
            'issue_date' => now()->format('d-m-Y'),
            'report_title' => $variant->label(),
            'watermark' => $variant->watermark(),
            'pictures_html' => $picturesHtml,
            'report_images' => $reportImages,
            'hide_comparisons' => (bool) ($totals['property_total']?->hide_comparisons_info_table),
            'hide_evaluation' => (bool) ($totals['property_total']?->hide_evaluation_info_table),
            'city_name' => $location?->city?->name_ar,
            'neighborhood_name' => $location?->neighborhood?->name_ar,
            'reference' => $request->reference ?? $request->number ?? $request->id,
        ];
    }

    private function streetCount(mixed $facade, ?PropertyBorder $border): int
    {
        $count = 0;
        if ($facade !== null) {
            foreach (['facade_type_n', 'facade_type_s', 'facade_type_e', 'facade_type_w'] as $field) {
                if (! empty($facade->{$field})) {
                    $count++;
                }
            }
        }

        if ($count > 0 || $border === null) {
            return $count;
        }

        foreach (['north', 'south', 'east', 'west'] as $dir) {
            $value = (string) ($border->{$dir} ?? '');
            if ($value !== '' && (str_contains($value, 'شارع') || str_contains($value, 'Street'))) {
                $count++;
            }
        }

        return $count;
    }
}
