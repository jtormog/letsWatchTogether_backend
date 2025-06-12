<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Platform extends Model
{
    protected $fillable = [
        'name',
        'code',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}
