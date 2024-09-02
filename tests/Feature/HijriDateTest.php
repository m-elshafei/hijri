<?php

namespace Tests\Feature;

use Tests\TestCase;
use MElshafei\HijriDate\HijriDate;
use MElshafei\HijriDate\DateConverter;

class HijriDateTest extends TestCase
{
    public function testGetCurrentDate()
    {
        $dateConverter = new DateConverter();
        $hijriDate = new HijriDate($dateConverter);

        // Replace 'Expected Hijri Date' with the actual expected result
        $result = $hijriDate->getCurrentDate();
        $this->assertEquals('Expected Hijri Date', $result);
    }
}
