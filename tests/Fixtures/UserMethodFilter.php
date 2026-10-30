<?php

namespace Wang9707\MakeTable\Tests\Fixtures;

use Wang9707\MakeTable\Eloquent\QueryFilter;

class UserMethodFilter extends QueryFilter
{
    public function name($name)
    {
        $this->builder->where('name', 'like', "%{$name}%");
    }
}
