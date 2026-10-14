<?php

namespace App\Support;

use App\Models\CouncilProposal;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CouncilBoardroom
{
    public function __construct(protected CouncilEntitlements $entitlements) {}

    public function member(User $user, Team $team): void
    {
        abort_unless($team->user_id === $user->id || $team->users()->whereKey($user->id)->exists(), 404);
    }

    private function licensed(Team $team): array
    {
        $services = $this->entitlements->services($team->owner);
        $services = array_values(array_intersect($services, ['continuum', 'slipstream', 'parity', 'plexus', 'positronic', 'pulsar']));
        abort_unless($services, 403, 'The council owner needs an active executive subscription.');

        return $services;
    }

    private function memberIds(Team $team): array
    {
        return $team->users()->pluck('users.id')->push($team->user_id)->unique()->map(fn ($id) => (int) $id)->values()->all();
    }

    public function create(User $user, Team $team, array $input): CouncilProposal
    {
        $this->member($user, $team);
        $services = $this->licensed($team);
        abort_if(! empty($input['target']) && ! in_array($input['target'], $services, true), 403, 'The target executive is not in this council.');

        return DB::transaction(function () use ($user, $team, $input, $services) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (!empty($input['request_key'])) {
                $existing = CouncilProposal::where('team_id',$team->id)->where('created_by',$user->id)->where('request_key',$input['request_key'])->first();
                if ($existing) {
                    abort_unless($existing->title === $input['title'] && $existing->objective === $input['objective'] && $existing->target === ($input['target'] ?? null),409,'This request key belongs to a different proposal.');
                    return $existing;
                }
            }
            return CouncilProposal::create([
                'id' => (string) Str::uuid(), 'team_id' => $team->id,
                'created_by' => $user->id, 'chair_id' => $team->user_id,
                'title' => $input['title'], 'objective' => $input['objective'],
                'target' => $input['target'] ?? null, 'request_key' => $input['request_key'] ?? null,
                'executives' => $services, 'electorate' => $this->memberIds($team), 'status' => 'open',
            ]);
        });
    }

    public function vote(User $user, CouncilProposal $proposal, array $input): void
    {
        $this->member($user, $proposal->team);
        $this->licensed($proposal->team);
        DB::transaction(function () use ($user, $proposal, $input) {
            $locked = CouncilProposal::whereKey($proposal->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'open', 409, 'Voting is closed.');
            abort_unless(in_array($user->id, $locked->electorate, true), 403, 'You were not in the electorate when this proposal was created.');
            $locked->votes()->updateOrCreate(['user_id' => $user->id], ['choice' => $input['choice'], 'reason' => $input['reason'] ?? null]);
        });
    }

    public function close(User $user, CouncilProposal $proposal): CouncilProposal
    {
        $this->member($user, $proposal->team);
        $this->licensed($proposal->team);
        abort_unless($user->id === $proposal->chair_id && $proposal->team->user_id === $user->id, 403, 'Only the council chair can close a decision.');

        return DB::transaction(function () use ($user, $proposal) {
            $locked = CouncilProposal::whereKey($proposal->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'open') {
                return $locked;
            }
            $electorate = array_values(array_intersect($locked->electorate, $this->memberIds($locked->team)));
            $votes = $locked->votes()->whereIn('user_id', $electorate)->with('user:id,name')->orderBy('user_id')->get();
            $quorum = intdiv(count($electorate), 2) + 1;
            abort_unless($votes->count() >= $quorum, 409, 'A majority of the electorate must vote before closing.');
            $chairVote = $votes->firstWhere('user_id', $user->id);
            abort_unless($chairVote && $chairVote->choice !== 'abstain', 409, 'The chair must cast a yes or no vote before closing.');
            $yes = $votes->where('choice', 'yes')->count();
            $no = $votes->where('choice', 'no')->count();
            $approved = $yes > $no && $chairVote->choice === 'yes';
            $decision = [
                'proposal_id' => $locked->id, 'team_id' => $locked->team_id, 'chair_id' => $locked->chair_id,
                'title' => $locked->title, 'objective' => $locked->objective, 'target' => $locked->target,
                'executives' => $locked->executives, 'electorate' => $electorate, 'quorum' => $quorum,
                'result' => $approved ? 'approved' : 'rejected', 'closed_at' => now()->toISOString(),
                'votes' => $votes->map(fn ($vote) => $vote->only('user_id', 'choice', 'reason') + ['name' => $vote->user?->name])->values()->all(),
            ];
            $locked->update(['status' => $decision['result'], 'decision' => $decision,
                'decision_hash' => hash('sha256', json_encode($decision, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), 'closed_at' => now()]);

            return $locked;
        });
    }

    public function workflow(User $viewer, CouncilProposal $proposal, array $votes, array $members, array $services): array
    {
        $open = $proposal->status === 'open';
        $electorate = $open
            ? array_values(array_intersect($proposal->electorate, $members))
            : ($proposal->decision['electorate'] ?? $proposal->electorate);
        $eligibleVotes = collect($votes)->filter(fn ($vote) => in_array($vote['user_id'], $electorate, true))->unique('user_id');
        $ownVote = $eligibleVotes->firstWhere('user_id', $viewer->id);
        $quorum = $open ? intdiv(count($electorate), 2) + 1 : ($proposal->decision['quorum'] ?? intdiv(count($electorate), 2) + 1);
        $chair = $proposal->chair_id === $viewer->id && $proposal->team->user_id === $viewer->id;
        $canVote = $open && $services && in_array($viewer->id, $electorate, true);

        return [
            'can_vote' => (bool) $canVote,
            'needs_vote' => $canVote && ! $ownVote,
            'can_close' => $open && $services && $chair && $eligibleVotes->count() >= $quorum
                && in_array($ownVote['choice'] ?? null, ['yes', 'no'], true),
            'can_handoff' => $proposal->status === 'approved' && $chair && $proposal->target
                && in_array($proposal->target, $services, true),
            'own_vote' => $ownVote,
            'votes_cast' => $eligibleVotes->count(),
            'electorate_count' => count($electorate),
            'quorum' => $quorum,
        ];
    }

    public function payload(CouncilProposal $proposal): array
    {
        return $proposal->only('id', 'team_id', 'created_by', 'chair_id', 'title', 'objective', 'target', 'status', 'executives', 'electorate', 'decision', 'decision_hash', 'created_at', 'closed_at') + [
            'votes' => $proposal->status !== 'open' ? ($proposal->decision['votes'] ?? []) : $proposal->votes()->with('user:id,name')->orderBy('user_id')->get()->map(fn ($vote) => $vote->only('user_id', 'choice', 'reason') + ['name' => $vote->user?->name])->all(),
        ];
    }
}
