<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Platform extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}
