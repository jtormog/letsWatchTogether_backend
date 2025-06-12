<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WatchInvitation extends Model
{
    protected $fillable = [
        'friendship_id',
        'sender_id',
        'tmdb_id',
        'type',
        'status',
    ];

    public function friendship()
    {
        return $this->belongsTo(Friendship::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
