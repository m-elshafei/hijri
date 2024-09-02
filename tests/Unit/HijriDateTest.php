<?php

namespace Tests\Unit;

use Tests\TestCase;
use Mockery;
use MElshafei\HijriDate\HijriDate;
use MElshafei\HijriDate\DateConverter;

class HijriDateTest extends TestCase
{
    public function testGetCurrentDateWithMock()
    {
        $mock = Mockery::mock(DateConverter::class);
        $mock->shouldReceive('convertToHijri')->andReturn('Mocked Hijri Date');

        $hijriDate = new HijriDate($mock);
        $result = $hijriDate->getCurrentDate();

        $this->assertEquals('Mocked Hijri Date', $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
