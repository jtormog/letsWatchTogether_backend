<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserMedia extends Model
{
    use HasFactory;

    protected $table = 'user_media';

    protected $fillable = [
        'user_id',
        'tmdb_id',
        'recommended',
        'type',
        'status',
        'episode',
        'watching_with',
        'invitation_accepted',
    ];

    protected $casts = [
        'recommended' => 'boolean',
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
