<?php

namespace Wang9707\MakeTable\Tests\Fixtures;

use Wang9707\MakeTable\Contracts\AttributeFilter;

class UppercaseNameFilter implements AttributeFilter
{
    public function filter(array $attributes): array
    {
        if (isset($attributes['name'])) {
            $attributes['name'] = strtoupper($attributes['name']);
        }

        return $attributes;
    }
}
