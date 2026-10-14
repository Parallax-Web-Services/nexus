<?php

namespace Tests\Unit;

use App\Models\CouncilProposal;
use App\Models\Team;
use App\Models\User;
use App\Support\CouncilBoardroom;
use App\Support\CouncilEntitlements;
use Tests\TestCase;

class CouncilWorkflowTest extends TestCase
{
    public function test_readiness_uses_active_members_and_requires_the_chairs_decisive_vote(): void
    {
        [$room, $viewer, $proposal] = $this->room();
        $votes = [['user_id' => 1, 'choice' => 'abstain'], ['user_id' => 2, 'choice' => 'yes'], ['user_id' => 4, 'choice' => 'yes']];
        $workflow = $room->workflow($viewer, $proposal, $votes, [1, 2, 3], ['continuum']);
        $this->assertSame(2, $workflow['votes_cast']);
        $this->assertSame(2, $workflow['quorum']);
        $this->assertFalse($workflow['can_close']);
        $votes[0]['choice'] = 'yes';
        $this->assertTrue($room->workflow($viewer, $proposal, $votes, [1, 2, 3], ['continuum'])['can_close']);
        $this->assertFalse($room->workflow($viewer, $proposal, [$votes[0], $votes[2]], [1, 2, 3], ['continuum'])['can_close']);
        $this->assertFalse($room->workflow($viewer, $proposal, $votes, [1, 2, 3], [])['can_close']);
        $viewer->id = 3;
        $memberWorkflow = $room->workflow($viewer, $proposal, $votes, [1, 2, 3], ['continuum']);
        $this->assertTrue($memberWorkflow['needs_vote']);
        $this->assertFalse($memberWorkflow['can_close']);
    }

    public function test_recorded_decisions_preserve_ballots_and_handoff_requires_current_ownership_and_access(): void
    {
        [$room, $viewer, $proposal] = $this->room();
        $proposal->status = 'approved';
        $proposal->decision = ['electorate' => [1, 2, 4], 'quorum' => 2];
        $votes = [['user_id' => 1, 'choice' => 'yes'], ['user_id' => 4, 'choice' => 'yes']];
        $workflow = $room->workflow($viewer, $proposal, $votes, [1, 2, 3], ['continuum']);
        $this->assertSame(2, $workflow['votes_cast']);
        $this->assertSame(3, $workflow['electorate_count']);
        $this->assertFalse($workflow['can_vote']);
        $this->assertFalse($workflow['can_close']);
        $this->assertTrue($workflow['can_handoff']);
        $this->assertFalse($room->workflow($viewer, $proposal, $votes, [1, 2, 3], [])['can_handoff']);
        $proposal->team->user_id = 2;
        $this->assertFalse($room->workflow($viewer, $proposal, $votes, [1, 2, 3], ['continuum'])['can_handoff']);
    }

    private function room(): array
    {
        $viewer = (new User)->forceFill(['id' => 1]);
        $team = (new Team)->forceFill(['id' => 10, 'user_id' => 1]);
        $proposal = (new CouncilProposal)->forceFill(['status' => 'open', 'chair_id' => 1, 'target' => 'continuum', 'electorate' => [1, 2, 3, 4]]);
        $proposal->setRelation('team', $team);

        return [new CouncilBoardroom($this->createStub(CouncilEntitlements::class)), $viewer, $proposal];
    }
}
