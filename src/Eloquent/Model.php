<?php

namespace Wang9707\MakeTable\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as BaseModel;
use Wang9707\MakeTable\Eloquent\Traits\EloquentDate;
use Wang9707\MakeTable\Eloquent\Traits\QueryFilterTrait;

/**
 * 基础模型
 *
 * filter 是 QueryFilterTrait 提供的查询作用域：
 *
 * User::query()->filter($filter)->get();
 * User::filter($filter)->paginate();
 *
 * @method static Builder<static> filter(QueryFilter $filter)
 */
class Model extends BaseModel
{
    use QueryFilterTrait, EloquentDate;

    protected $hidden = [
        'deleted_at',
    ];
}
