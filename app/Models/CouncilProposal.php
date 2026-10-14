<?php

namespace App\Models;

class CouncilProposal extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $casts = ['executives' => 'array', 'electorate' => 'array', 'decision' => 'array', 'closed_at' => 'datetime'];

    public function votes()
    {
        return $this->hasMany(CouncilVote::class, 'proposal_id');
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function chair()
    {
        return $this->belongsTo(User::class, 'chair_id');
    }
}
