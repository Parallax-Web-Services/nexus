<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Jetstream\Events\TeamCreated;
use Laravel\Jetstream\Events\TeamDeleted;
use Laravel\Jetstream\Events\TeamUpdated;
use Laravel\Jetstream\Team as JetstreamTeam;

class Team extends JetstreamTeam
{
    use HasFactory;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'personal_team' => 'boolean',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'name',
        'personal_team',
    ];

    /**
     * The event map for the model.
     *
     * @var array
     */
    protected $dispatchesEvents = [
        'created' => TeamCreated::class,
        'updated' => TeamUpdated::class,
        'deleted' => TeamDeleted::class,
    ];

    protected $appends = [
        'all_users'
    ];

    public function getAllUsersAttribute()
    {
        // Serialize clones so the shared instances keep their appends for
        // the rest of the payload. Each clone keeps only the avatar and the
        // membership pivot — all_teams or any loaded team relation would
        // re-enter all_users and recurse without bound.
        //
        // Teammates are other people: expose only who they are, never their
        // billing, two-factor or account columns. This payload reaches every
        // OAuth client through /api/me and every member through team pages.
        $safe = function ($user) {
            $clone = clone $user;
            $clone->setAppends(['profile_photo_url']);
            $clone->setRelations(array_intersect_key($clone->getRelations(), ['membership' => true]));
            $clone->setVisible(User::TEAMMATE_FIELDS);

            return $clone;
        };

        $users = $this->users->map($safe);
        $owner = $this->owner ? $safe($this->owner) : null;

        // toArray() also serializes the users/owner relations loaded above;
        // swap in the safe clones or the cycle comes back through there.
        $this->setRelation('users', $users);

        if ($owner) {
            $this->setRelation('owner', $owner);
        }

        return $owner ? $users->merge([$owner]) : $users;
    }
}
