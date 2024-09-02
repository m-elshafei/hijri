<?php

namespace MElshafei\HijriDate;

class HijriDate
{
    protected $dateConverter;

    public function __construct(DateConverter $dateConverter)
    {
        $this->dateConverter = $dateConverter;
    }

    public function getCurrentDate()
    {
        return $this->dateConverter->convertToHijri(date('Y-m-d'));
    }
}
