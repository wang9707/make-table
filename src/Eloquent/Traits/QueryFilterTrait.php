<?php

namespace Wang9707\MakeTable\Eloquent\Traits;

use Wang9707\MakeTable\Eloquent\QueryFilter;

trait QueryFilterTrait
{
    public function scopeFilter($query, QueryFilter $filter)
    {
        return $filter->apply($query);
    }
}
