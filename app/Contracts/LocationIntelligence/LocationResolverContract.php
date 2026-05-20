<?php

namespace App\Contracts\LocationIntelligence;

interface LocationResolverContract
{
    /**
     * Resolve a location from the given input and return a structured result.
     *
     * @return array<string, mixed>
     */
    public function resolve(string $input): array;
}
