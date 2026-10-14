<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Service extends Model
{
    use HasFactory;

    public function plans()
    {
        return $this->belongsToMany(Plan::class)->withTimestamps();
    }

    public function subscriptions()
    {
        return $this->hasManyThrough(ServiceSubscription::class, Plan::class);
    }
}
