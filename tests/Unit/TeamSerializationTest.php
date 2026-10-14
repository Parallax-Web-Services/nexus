<?php

namespace Tests\Unit;

use App\Models\Membership;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class TeamSerializationTest extends TestCase
{
    public function test_team_serialization_keeps_the_owner_profile_photo_url()
    {
        [$user, $team] = $this->ownerWithTeam();

        $serializedTeam = $team->toArray();

        $this->assertSame(
            $user->profile_photo_url,
            $serializedTeam['owner']['profile_photo_url']
        );
    }

    public function test_team_serialization_keeps_member_avatars_and_membership()
    {
        [$user, $team] = $this->ownerWithTeam();

        $member = new User([
            'name' => 'Member',
            'email' => 'member@example.com',
        ]);
        $member->id = 2;
        $member->setRelation('membership', new Membership([
            'team_id' => $team->id,
            'user_id' => $member->id,
            'role' => 'editor',
        ]));
        $team->setRelation('users', new Collection([$member]));

        $serializedTeam = $team->toArray();

        $this->assertSame(
            $member->profile_photo_url,
            $serializedTeam['users'][0]['profile_photo_url']
        );
        $this->assertSame('editor', $serializedTeam['users'][0]['membership']['role']);
    }

    public function test_serializing_a_user_who_owns_a_team_terminates()
    {
        [$user, $team] = $this->ownerWithTeam();
        $user->setRelation('ownedTeams', new Collection([$team]));

        // The cycle behind the GA-night login outage: user → all_teams →
        // team → owner (this same user) → all_teams → ...
        $serialized = $user->toArray();

        $this->assertArrayNotHasKey('all_teams', $serialized['all_teams'][0]['owner']);
        $this->assertSame($user->profile_photo_url, $serialized['profile_photo_url']);

        // The shared instance must come out unstripped — mutating it is
        // what dropped owner avatars elsewhere in the payload before.
        $this->assertContains('all_teams', $user->getAppends());
    }

    private function ownerWithTeam(): array
    {
        $user = new User([
            'name' => 'Ada Okafor',
            'email' => 'ada@example.com',
        ]);
        $user->id = 1;
        $user->setRelation('ownedTeams', new Collection());
        $user->setRelation('teams', new Collection());

        $team = new Team([
            'name' => 'Acme',
        ]);
        $team->id = 10;
        $team->setRelation('owner', $user);
        $team->setRelation('users', new Collection());

        return [$user, $team];
    }
}
