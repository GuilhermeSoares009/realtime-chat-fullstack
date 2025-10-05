<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'bio',
        'last_seen_at',
        'is_online',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'is_online' => 'boolean',
        'password' => 'hashed',
    ];

    public function chats()
    {
        return $this->belongsToMany(Chat::class)
            ->withPivot('joined_at', 'last_read_at')
            ->withTimestamps();
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function updateOnlineStatus(bool $isOnline): void
    {
        $this->update([
            'is_online' => $isOnline,
            'last_seen_at' => now(),
        ]);
    }
}