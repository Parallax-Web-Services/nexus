<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Jetstream\HasTeams;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable implements OAuthenticatable, MustVerifyEmail
{
    use Billable;
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use HasTeams;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * What a teammate may see about another member or owner of a team.
     *
     * @var string[]
     */
    public const TEAMMATE_FIELDS = ['id', 'name', 'email', 'profile_photo_url', 'membership'];

    /**
     * Account columns kept out of /api/me. Client apps get identity and
     * teams; billing and two-factor state stay in Nexus.
     *
     * @var string[]
     */
    public const HIDDEN_FROM_CLIENTS = [
        'stripe_id',
        'pm_type',
        'pm_last_four',
        'trial_ends_at',
        'two_factor_confirmed_at',
        'ownedTeams',
        'teams',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'name', 'email', 'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'all_teams',
        'profile_photo_url',
    ];

    public function getAllTeamsAttribute()
    {
        return $this->allTeams();
    }

    public function billingCustomers()
    {
        return $this->hasMany(BillingCustomer::class);
    }

    public function serviceSubscriptions()
    {
        return $this->hasMany(ServiceSubscription::class);
    }
}
