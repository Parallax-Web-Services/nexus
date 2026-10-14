<?php

namespace Tests\Feature;

use App\Models\CouncilSubscription;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class CouncilBoardroomTest extends TestCase
{
    use RefreshDatabase;

    private User $chair;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chair = User::factory()->create();
        $this->team = Team::factory()->create(['user_id' => $this->chair->id]);
        $this->chair->forceFill(['current_team_id' => $this->team->id])->save();
        CouncilSubscription::create(['user_id' => $this->chair->id, 'billing_account' => 'parallax', 'stripe_subscription_id' => 'sub_fixture', 'stripe_customer_id' => 'cus_fixture', 'status' => 'active', 'price_ids' => [config('council.catalog.plans.full.prices.monthly.id')], 'event_created' => 1]);
        Passport::actingAs($this->chair, ['identity:read', 'council:read', 'council:write']);
    }

    private function proposal(): string
    {
        return $this->postJson('/api/council/proposals', ['team_id' => $this->team->id, 'title' => 'Enter a new market', 'objective' => 'Prepare a market entry plan.', 'target' => 'continuum'])->assertCreated()->json('id');
    }

    private function vote(string $id, string $choice = 'yes')
    {
        return $this->postJson('/api/council/proposals/'.$id.'/votes', ['choice' => $choice, 'reason' => 'Recorded rationale']);
    }

    public function test_saved_approved_decision_is_immutable_and_handoff_requires_chair(): void
    {
        $id = $this->proposal();
        $this->vote($id)->assertOk();
        $closed = $this->postJson('/api/council/proposals/'.$id.'/close')->assertOk()->assertJsonPath('status', 'approved')->json();
        $this->assertSame(hash('sha256', json_encode($closed['decision'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), $closed['decision_hash']);
        $this->vote($id, 'no')->assertStatus(409);
        $this->postJson('/api/council/proposals/'.$id.'/close')->assertOk()->assertJsonPath('decision_hash', $closed['decision_hash']);
        $this->getJson('/api/council/proposals/'.$id.'/handoff')->assertOk();
        $this->getJson('/api/council/boardroom')->assertOk()->assertJsonPath('proposals.0.id', $id);
        $member = User::factory()->create();
        $this->team->users()->attach($member, ['role' => 'admin']);
        Passport::actingAs($member, ['council:read', 'council:write']);
        $this->getJson('/api/council/proposals/'.$id.'/handoff')->assertForbidden();
    }

    public function test_ballots_are_unique_and_quorum_and_chair_vote_are_required(): void
    {
        $members = User::factory()->count(2)->create();
        foreach ($members as $member) {
            $this->team->users()->attach($member, ['role' => 'editor']);
        }
        $id = $this->proposal();
        $this->vote($id)->assertOk();
        $this->vote($id)->assertOk();
        $this->assertDatabaseCount('council_votes', 1);
        $this->postJson('/api/council/proposals/'.$id.'/close')->assertStatus(409);
        Passport::actingAs($members[0], ['council:write']);
        $this->vote($id, 'no')->assertOk();
        $this->postJson('/api/council/proposals/'.$id.'/close')->assertForbidden();
        Passport::actingAs($this->chair, ['council:write']);
        $this->postJson('/api/council/proposals/'.$id.'/close')->assertOk()->assertJsonPath('status', 'rejected');
    }

    public function test_chair_cannot_close_without_a_decisive_ballot(): void
    {
        $id = $this->proposal();
        $this->vote($id, 'abstain')->assertOk();
        $this->postJson('/api/council/proposals/'.$id.'/close')->assertStatus(409);
    }

    public function test_outsiders_and_tokens_without_scopes_cannot_read_or_vote(): void
    {
        $id = $this->proposal();
        Passport::actingAs(User::factory()->create(), ['council:read', 'council:write']);
        $this->getJson('/api/council/boardroom?team_id='.$this->team->id)->assertNotFound();
        $this->vote($id)->assertNotFound();
        Passport::actingAs($this->chair, ['identity:read']);
        $this->getJson('/api/council/boardroom')->assertForbidden();
        $this->vote($id)->assertForbidden();
    }

    public function test_new_members_cannot_vote_and_removed_members_are_excluded_from_close(): void
    {
        $removed = User::factory()->create();
        $this->team->users()->attach($removed, ['role' => 'editor']);
        $id = $this->proposal();
        Passport::actingAs($removed, ['council:write']);
        $this->vote($id, 'no')->assertOk();
        $this->team->users()->detach($removed);
        $new = User::factory()->create();
        $this->team->users()->attach($new, ['role' => 'editor']);
        Passport::actingAs($new, ['council:write']);
        $this->vote($id)->assertForbidden();
        Passport::actingAs($this->chair, ['council:write']);
        $this->vote($id)->assertOk();
        $this->postJson('/api/council/proposals/'.$id.'/close')->assertOk()->assertJsonPath('decision.quorum', 1)->assertJsonCount(1, 'decision.votes')->assertJsonCount(1, 'votes');
    }

    public function test_closed_ballot_names_are_preserved_when_the_identity_changes(): void
    {
        $id = $this->proposal();
        $name = $this->chair->name;
        $this->vote($id)->assertOk();
        $this->postJson('/api/council/proposals/'.$id.'/close')->assertOk();
        $this->chair->update(['name' => 'Updated Name']);
        $this->getJson('/api/council/boardroom')->assertOk()->assertJsonPath('proposals.0.votes.0.name', $name);
    }

    public function test_cancelled_license_preserves_history_but_blocks_writes_and_handoffs(): void
    {
        $id = $this->proposal();
        $this->vote($id)->assertOk();
        $this->postJson('/api/council/proposals/'.$id.'/close')->assertOk();
        CouncilSubscription::query()->update(['status' => 'canceled']);
        $this->getJson('/api/council/boardroom')->assertOk()->assertJsonCount(0, 'executives');
        $this->getJson('/api/council/proposals/'.$id.'/handoff')->assertForbidden();
        $this->vote($id)->assertForbidden();
        $this->postJson('/api/council/proposals', ['team_id' => $this->team->id, 'title' => 'Next decision', 'objective' => 'New work'])->assertForbidden();
    }

    public function test_executive_request_keys_prevent_duplicate_and_changed_proposals(): void {
        $key=hash('sha256','fixture-quark-v1');
        $input=['team_id'=>$this->team->id,'title'=>'Finance brief','objective'=>'Review the cash assumptions.','target'=>'parity','request_key'=>$key];
        $id=$this->postJson('/api/council/proposals',$input)->assertCreated()->json('id');
        $this->postJson('/api/council/proposals',$input)->assertCreated()->assertJsonPath('id',$id);
        $this->assertDatabaseCount('council_proposals',1);
        $this->postJson('/api/council/proposals',[...$input,'objective'=>'Changed proposal'])->assertConflict();
    }
    public function test_quark_and_pulsar_decisions_can_be_handed_to_the_licensed_app(): void {
        foreach(['parity','pulsar'] as $target){
            $id=$this->postJson('/api/council/proposals',['team_id'=>$this->team->id,'title'=>'Executive decision','objective'=>'Review this work.','target'=>$target])->assertCreated()->json('id');
            $this->vote($id)->assertOk();$this->postJson('/api/council/proposals/'.$id.'/close')->assertOk();
            $this->getJson('/api/council/proposals/'.$id.'/handoff')->assertOk()->assertJsonPath('target',$target);
        }
    }
}
