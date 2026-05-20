<?php

namespace App\Contracts\LocationIntelligence;

use App\Enums\LocationIntelligence\LocationInputType;

interface InputTypeDetectorContract
{
    public function detect(string $input): LocationInputType;
}
