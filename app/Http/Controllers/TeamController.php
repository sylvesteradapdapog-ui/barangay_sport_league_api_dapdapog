<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function index(Request $request, Season $season): JsonResponse
    {
        $this->authorizeSeason($request, $season);

        return response()->json($season->teams()->with('players')->get());
    }

    public function store(Request $request, Season $season): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'coach' => ['required', 'string', 'max:255'],
        ]);

        $this->authorizeSeason($request, $season);

        $team = $season->teams()->create($validated);

        return response()->json($team->load('players'), 201);
    }

    public function show(Request $request, Team $team): JsonResponse
    {
        $this->authorizeTeam($request, $team);

        return response()->json($team->load('players'));
    }

    public function update(Request $request, Team $team): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'coach' => ['sometimes', 'required', 'string', 'max:255'],
        ]);

        $this->authorizeTeam($request, $team);

        $team->update($validated);

        return response()->json($team);
    }

    public function destroy(Request $request, Team $team): JsonResponse
    {
        $this->authorizeTeam($request, $team);

        $team->delete();

        return response()->json(['message' => 'Team deleted successfully.']);
    }

    public function addPlayer(Request $request, Team $team): JsonResponse
    {
        $validated = $request->validate([
            'player_id' => ['required', 'integer', 'exists:players,id'],
            'jersey_number' => ['required', 'string', 'max:255'],
        ]);

        $this->authorizeTeam($request, $team);

        $team->players()->syncWithoutDetaching([
            $validated['player_id'] => ['jersey_number' => $validated['jersey_number']],
        ]);

        return response()->json($team->load('players'));
    }

    public function removePlayer(Request $request, Team $team, Player $player): JsonResponse
    {
        $this->authorizeTeam($request, $team);

        $team->players()->detach($player->id);

        return response()->json($team->load('players'));
    }

    private function authorizeSeason(Request $request, Season $season): void
    {
        $season->loadMissing('league');
        abort_unless($season->league && $season->league->user_id === $request->user()->id, 404);
    }

    private function authorizeTeam(Request $request, Team $team): void
    {
        $team->loadMissing('season.league');
        abort_unless($team->season && $team->season->league && $team->season->league->user_id === $request->user()->id, 404);
    }
}
