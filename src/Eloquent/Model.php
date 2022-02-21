<?php

namespace Wang9707\MakeTable\Eloquent;


use Wang9707\MakeTable\Eloquent\Traits\QueryFilterTrait;
use Illuminate\Database\Eloquent\Model as BaseModel;

class Model extends BaseModel
{
    use QueryFilterTrait;

    protected $hidden = [
        'deleted_at',
    ];
}
