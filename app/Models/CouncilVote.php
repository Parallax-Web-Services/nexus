<?php

namespace App\Models;

class CouncilVote extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
