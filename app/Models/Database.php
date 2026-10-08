<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Database extends Model
{
    protected $guarded = ['id'];

    public function sites()
    {
        return $this->hasMany(Site::class);
    }
}
