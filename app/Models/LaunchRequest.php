<?php
namespace App\Models;

class LaunchRequest extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $casts = ['brief' => 'array', 'created_user' => 'boolean', 'claimed_at' => 'datetime', 'email_sent_at' => 'datetime', 'preview_expires_at' => 'datetime'];
    protected $hidden = ['claim_hash', 'preview_token_id'];
    public function user() { return $this->belongsTo(User::class); }
}
