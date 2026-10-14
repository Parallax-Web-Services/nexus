<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class ServiceSubscription extends Model
{
    use HasFactory;

    /**
     * @var array
     */
    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function isActive(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
