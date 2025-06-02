<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WatchInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'friendship_id',
        'tmdb_id',
        'status',
    ];

    public function friendship()
    {
        return $this->belongsTo(Friendship::class);
    }
}
