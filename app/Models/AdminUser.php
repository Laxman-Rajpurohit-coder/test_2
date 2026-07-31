<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class AdminUser extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Defines attribute casting for the admin user.
     *
     * @return array<string, string> The configured attribute casts.
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
