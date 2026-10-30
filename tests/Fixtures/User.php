<?php

namespace Wang9707\MakeTable\Tests\Fixtures;

use Wang9707\MakeTable\Eloquent\Model;

class User extends Model
{
    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = false;

    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}
