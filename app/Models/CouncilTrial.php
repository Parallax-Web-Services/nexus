<?php
namespace App\Models;

class CouncilTrial extends Model
{
    protected $casts = ['services' => 'array', 'started_at' => 'datetime', 'expires_at' => 'datetime'];
}
