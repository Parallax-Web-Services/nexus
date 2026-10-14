<?php

namespace App\Http\Controllers\Api;

use App\Models\CouncilProposal;
use App\Models\Team;
use App\Support\CouncilBoardroom;
use App\Support\CouncilCatalog;
use App\Support\CouncilEntitlements;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CouncilBoardroomController extends Controller
{
    public function __construct()
    {
        $this->middleware(function (Request $request, $next) {
            abort_unless($request->user()->tokenCan($request->isMethod('GET') ? 'council:read' : 'council:write'), 403, 'Council permission is required.');

            return $next($request);
        });
    }

    public function index(Request $request, CouncilBoardroom $room, CouncilEntitlements $entitlements)
    {
        $teams = $request->user()->allTeams();
        $id = $request->validate(['team_id' => ['sometimes', 'integer']])['team_id'] ?? $request->user()->current_team_id ?? $teams->first()?->id;
        $team = $teams->firstWhere('id', $id);
        abort_unless($team, 404, 'Choose one of your Nexus teams.');
        $room->member($request->user(), $team);

        $executives = array_values(array_intersect($entitlements->services($team->owner), ['continuum', 'slipstream', 'parity', 'plexus', 'positronic', 'pulsar']));
        $members = $team->users()->pluck('users.id')->push($team->user_id)->unique()->map(fn ($id) => (int) $id)->values()->all();

        return response()->json([
            'teams' => $teams->map(fn ($item) => $item->only('id', 'name'))->values(),
            'team' => $team->only('id', 'name', 'user_id'),
            'executives' => $executives,
            'executive_names' => collect(['continuum', 'slipstream', 'parity', 'plexus', 'positronic', 'pulsar'])->mapWithKeys(fn ($key) => [$key => app(CouncilCatalog::class)->executiveName($key)])->all(),
            'proposals' => CouncilProposal::where('team_id', $team->id)->latest()->limit(50)->get()->map(function ($proposal) use ($room, $request, $team, $members, $executives) {
                $proposal->setRelation('team', $team);
                $payload = $room->payload($proposal);

                return $payload + ['workflow' => $room->workflow($request->user(), $proposal, $payload['votes'], $members, $executives)];
            }),
        ]);
    }

    public function store(Request $request, CouncilBoardroom $room)
    {
        $input = $request->validate(['team_id' => 'required|integer', 'title' => 'required|string|max:160', 'objective' => 'required|string|max:20000', 'target' => 'nullable|in:continuum,parity,pulsar', 'request_key' => 'nullable|string|regex:/^[a-f0-9]{64}$/']);
        $proposal = $room->create($request->user(), Team::findOrFail($input['team_id']), $input);

        return response()->json($room->payload($proposal), 201);
    }

    public function vote(Request $request, CouncilProposal $proposal, CouncilBoardroom $room)
    {
        $input = $request->validate(['choice' => 'required|in:yes,no,abstain', 'reason' => 'nullable|string|max:2000']);
        $room->vote($request->user(), $proposal, $input);

        return response()->json($room->payload($proposal));
    }

    public function close(Request $request, CouncilProposal $proposal, CouncilBoardroom $room)
    {
        return response()->json($room->payload($room->close($request->user(), $proposal)));
    }

    public function handoff(Request $request, CouncilProposal $proposal, CouncilBoardroom $room, CouncilEntitlements $entitlements)
    {
        $room->member($request->user(), $proposal->team);
        abort_unless($proposal->status === 'approved' && $proposal->target, 409, 'Only an approved decision with a Mission target can be handed off.');
        abort_unless($proposal->chair_id === $request->user()->id && $proposal->team->user_id === $request->user()->id, 403);
        abort_unless(in_array($proposal->target, $entitlements->services($request->user()), true), 403, 'The target executive subscription is no longer active.');

        return response()->json($room->payload($proposal));
    }
}
