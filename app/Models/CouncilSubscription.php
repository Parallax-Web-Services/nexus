<?php

namespace App\Models;

class CouncilSubscription extends Model
{
    protected $casts = ['price_ids' => 'array', 'expires_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
