<?php

namespace Tests\Unit;

use Tests\TestCase;
use MElshafei\HijriDate\DateConverter;

class DateConverterTest extends TestCase
{
    public function testConvertToHijri()
    {
        $converter = new DateConverter();
        $result = $converter->convertToHijri('2024-09-02');

        // Replace 'Expected Hijri Date' with the actual expected result
        $this->assertEquals('Expected Hijri Date', $result);
    }
}
