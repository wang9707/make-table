<?php

namespace Wang9707\MakeTable\Tests\Fixtures;

use Wang9707\MakeTable\Eloquent\Model;

class Post extends Model
{
    protected $table = 'posts';

    protected $guarded = [];

    public $timestamps = false;
}
