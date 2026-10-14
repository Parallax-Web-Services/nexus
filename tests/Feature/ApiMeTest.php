<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Tests\PassportTokenTestCase;

class ApiMeTest extends PassportTokenTestCase
{
    private const BILLING = ['stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at', 'two_factor_confirmed_at'];

    public function test_teammates_are_reduced_to_who_they_are()
    {
        $owner = $this->billedUser('Owner', 'owner@example.test');
        $member = $this->billedUser('Member', 'member@example.test');
        $me = $this->billedUser('Me', 'me@example.test');

        $team = Team::forceCreate(['user_id' => $owner->id, 'name' => 'Shared', 'personal_team' => false]);
        $team->users()->attach($member, ['role' => 'editor']);
        $team->users()->attach($me, ['role' => 'admin']);

        $payload = $this->getWithToken($me->createToken('Client')->accessToken)->assertOk()->json();

        $shared = collect($payload['all_teams'])->firstWhere('id', $team->id);
        $people = array_merge($shared['users'], $shared['all_users'], [$shared['owner']]);

        // Two members, the same two plus the owner, and the owner again.
        $this->assertCount(6, $people);

        foreach ($people as $person) {
            $this->assertEqualsCanonicalizing(
                array_values(array_intersect(User::TEAMMATE_FIELDS, array_keys($person))),
                array_keys($person),
                'A teammate carried fields beyond who they are.',
            );
            $this->assertArrayHasKey('email', $person);
            $this->assertArrayHasKey('profile_photo_url', $person);
        }

        $this->assertSame('editor', collect($shared['users'])->firstWhere('id', $member->id)['membership']['role']);
        $this->assertSame($owner->id, $shared['owner']['id']);
        $this->assertStringNotContainsString('cus_', json_encode($payload));
    }

    public function test_the_signed_in_account_keeps_identity_but_not_billing()
    {
        $me = $this->billedUser('Me', 'me@example.test');
        Team::forceCreate(['user_id' => $me->id, 'name' => 'Mine', 'personal_team' => true]);

        $payload = $this->getWithToken($me->createToken('Client')->accessToken)->assertOk()->json();

        foreach (['id', 'name', 'email', 'email_verified_at', 'current_team_id', 'profile_photo_path', 'profile_photo_url', 'all_teams', 'service_entitlements'] as $key) {
            $this->assertArrayHasKey($key, $payload);
        }

        foreach ([...self::BILLING, 'owned_teams', 'teams'] as $key) {
            $this->assertArrayNotHasKey($key, $payload);
        }
    }

    private function billedUser(string $name, string $email): User
    {
        $user = User::factory()->create(['name' => $name, 'email' => $email]);

        $user->forceFill([
            'stripe_id' => 'cus_'.$user->id,
            'pm_type' => 'visa',
            'pm_last_four' => '4242',
            'trial_ends_at' => now()->addWeek(),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $user;
    }
}
