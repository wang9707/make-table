<?php

namespace Wang9707\MakeTable\Tests\Fixtures;

use Wang9707\MakeTable\Attributes\Filter;
use Wang9707\MakeTable\Eloquent\QueryFilter;

class UserOperatorFilter extends QueryFilter
{
    #[Filter('status', 'in')]
    public ?string $statuses = null;

    #[Filter('created_at', 'between', param: 'date_range')]
    public ?string $range = null;

    #[Filter('created_at', 'not_null', param: 'has_date')]
    public ?string $hasDate = null;

    #[Filter('deleted_at', 'null', param: 'only_deleted')]
    public ?string $onlyDeleted = null;
}
