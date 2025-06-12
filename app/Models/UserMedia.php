<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserMedia extends Model
{
    protected $table = 'user_media';

    protected $fillable = [
        'user_id',
        'tmdb_id',
        'recommended',
        'liked',
        'type',
        'status',
        'episode',
        'watching_with',
        'invitation_accepted',
    ];

    protected $casts = [
        'recommended' => 'boolean',
        'liked' => 'boolean',
        'invitation_accepted' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function watchingWithUser()
    {
        return $this->belongsTo(User::class, 'watching_with');
    }
}
