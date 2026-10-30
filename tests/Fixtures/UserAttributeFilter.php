<?php

namespace Wang9707\MakeTable\Tests\Fixtures;

use Wang9707\MakeTable\Attributes\Filter;
use Wang9707\MakeTable\Eloquent\QueryFilter;

class UserAttributeFilter extends QueryFilter
{
    #[Filter('name', 'like')]
    public ?string $name = null;

    #[Filter('status')]
    public ?int $status = null;

    #[Filter('name', 'like_left', param: 'q')]
    public ?string $keyword = null;
}
