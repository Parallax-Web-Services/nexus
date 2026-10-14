<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Plan extends Model
{
    use HasFactory;

    public function services()
    {
        return $this->belongsToMany(Service::class)->withTimestamps();
    }

    public function subscriptions()
    {
        return $this->hasMany(ServiceSubscription::class);
    }

    public function subscribedTeams()
    {
        return $this->hasManyThrough(Team::class,ServiceSubscription::class);
    }

    public function subscribedUsers()
    {
        return $this->hasManyThrough(User::class,ServiceSubscription::class);
    }
}
