<?php

declare(strict_types=1);

namespace App\Support\Valuation;

/**
 * Converts a positive amount into Arabic words (legacy GetArabicValue subset).
 */
final class ArabicAmountInWords
{
    public function convert(float|int|null $number): string
    {
        if ($number === null) {
            return '';
        }

        $number = (float) $number;

        if ($number < 0 || $number > 999_999_999_999) {
            return '';
        }

        $integer = (int) round($number);
        $englishFormat = number_format($integer);
        $parts = explode(',', $englishFormat);
        $return = '';

        foreach ($parts as $i => $part) {
            $place = count($parts) - $i;
            $return .= $this->convertHundred((string) (int) $part, $place);
            if (isset($parts[$i + 1]) && (int) $parts[$i + 1] > 0) {
                $return .= ' و';
            }
        }

        return trim($return);
    }

    private function convertHundred(string $number, int $place): string
    {
        $words = [
            '0' => '', '1' => 'واحد', '2' => 'اثنان', '3' => 'ثلاثة', '4' => 'أربعة', '5' => 'خمسة',
            '6' => 'ستة', '7' => 'سبعة', '8' => 'ثمانية', '9' => 'تسعة', '10' => 'عشرة',
            '11' => 'أحد عشر', '12' => 'اثنا عشر', '13' => 'ثلاثة عشر', '14' => 'أربعة عشر', '15' => 'خمسة عشر',
            '16' => 'ستة عشر', '17' => 'سبعة عشر', '18' => 'ثمانية عشر', '19' => 'تسعة عشر', '20' => 'عشرون',
            '30' => 'ثلاثون', '40' => 'أربعون', '50' => 'خمسون', '60' => 'ستون', '70' => 'سبعون',
            '80' => 'ثمانون', '90' => 'تسعون', '100' => 'مئة', '200' => 'مئتان', '300' => 'ثلاثمئة',
            '400' => 'أربعمئة', '500' => 'خمسمئة', '600' => 'ستمئة', '700' => 'سبعمئة', '800' => 'ثمانمئة', '900' => 'تسعمئة',
        ];

        $mil = [
            2 => ['1' => 'ألف', '2' => 'ألفان', '3' => 'آلاف'],
            3 => ['1' => 'مليون', '2' => 'مليونان', '3' => 'ملايين'],
            4 => ['1' => 'مليار', '2' => 'ملياران', '3' => 'مليارات'],
        ];

        $number = ltrim($number, '0');
        if ($number === '') {
            return '';
        }

        $value = (int) $number;
        $length = strlen((string) $value);
        $result = '';

        if ($length === 1) {
            $result = $words[(string) $value] ?? '';
        } elseif ($length === 2) {
            if ($value <= 20) {
                $result = $words[(string) $value] ?? '';
            } else {
                $ones = $value % 10;
                $tens = $value - $ones;
                $result = ($ones > 0 ? $words[(string) $ones].' و' : '').($words[(string) $tens] ?? '');
            }
        } else {
            $hundreds = (int) floor($value / 100) * 100;
            $rest = $value % 100;
            $result = ($words[(string) $hundreds] ?? '');
            if ($rest > 0) {
                $result .= ($result !== '' ? ' و' : '').$this->convertHundred((string) $rest, 1);
            }
        }

        if ($place > 1 && isset($mil[$place]) && $result !== '') {
            if ($value === 1) {
                $result = $mil[$place]['1'];
            } elseif ($value === 2) {
                $result = $mil[$place]['2'];
            } elseif ($value >= 3 && $value <= 10) {
                $result .= ' '.$mil[$place]['3'];
            } else {
                $result .= ' '.$mil[$place]['1'];
            }
        }

        return $result;
    }
}
