<?php

namespace Wang9707\MakeTable\Tests\Fixtures;

use Wang9707\MakeTable\Eloquent\Model;

class Member extends Model
{
    protected $connection = 'other';

    protected $table = 'members';

    protected $guarded = [];

    public $timestamps = false;
}
