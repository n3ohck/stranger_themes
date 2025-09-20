<?php

namespace App\Traits;

use Carbon\Carbon;

trait DateTrait
{
    public function makeDate($date)
    {
        return (isset($date)) ? Carbon::parse(str_replace('T', ' ', $date)) : null;
    }
}
