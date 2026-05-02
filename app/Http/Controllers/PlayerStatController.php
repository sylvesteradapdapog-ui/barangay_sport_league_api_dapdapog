<?php

namespace App\Http\Controllers;

use App\Models\GameResult;
use App\Models\PlayerStat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PlayerStatController extends Controller
{
    public function index(Request $request, GameResult $result): JsonResponse
    {
        $this->authorizeResult($request, $result);

        return response()->json($result->playerStats()->with('player')->get());
    }

    public function store(Request $request, GameResult $result): JsonResponse
    {
        $this->authorizeResult($request, $result);

        $validated = $request->validate([
            'player_id' => ['required', 'integer', 'exists:players,id'],
            'points' => ['required', 'integer', 'min:0'],
            'assists' => ['required', 'integer', 'min:0'],
            'rebounds' => ['required', 'integer', 'min:0'],
            'fouls' => ['required', 'integer', 'min:0'],
        ]);

        $result->loadMissing('game.homeTeam.players', 'game.awayTeam.players');

        $homeTeam = $result->game->homeTeam ?? null;
        $awayTeam = $result->game->awayTeam ?? null;

        $inHome = $homeTeam ? $homeTeam->players()->where('players.id', $validated['player_id'])->exists() : false;
        $inAway = $awayTeam ? $awayTeam->players()->where('players.id', $validated['player_id'])->exists() : false;

        if (! $inHome && ! $inAway) {
            throw ValidationException::withMessages([
                'player_id' => ['Player is not part of either game team.'],
            ]);
        }

        $stat = $result->playerStats()->create($validated);

        return response()->json($stat, 201);
    }

    public function show(Request $request, PlayerStat $stat): JsonResponse
    {
        $this->authorizeStat($request, $stat);

        return response()->json($stat->load(['player', 'gameResult.game']));
    }

    public function update(Request $request, PlayerStat $stat): JsonResponse
    {
        $this->authorizeStat($request, $stat);

        $validated = $request->validate([
            'points' => ['sometimes', 'required', 'integer', 'min:0'],
            'assists' => ['sometimes', 'required', 'integer', 'min:0'],
            'rebounds' => ['sometimes', 'required', 'integer', 'min:0'],
            'fouls' => ['sometimes', 'required', 'integer', 'min:0'],
        ]);

        $stat->update($validated);

        return response()->json($stat);
    }

    public function destroy(Request $request, PlayerStat $stat): JsonResponse
    {
        $this->authorizeStat($request, $stat);

        $stat->delete();

        return response()->json(['message' => 'Player stat deleted successfully.']);
    }

    private function authorizeResult(Request $request, GameResult $result): void
    {
        abort_unless(
            $result->game && $result->game->season && $result->game->season->league
            && $result->game->season->league->user_id === $request->user()->id,
            404
        );
    }

    private function authorizeStat(Request $request, PlayerStat $stat): void
    {
        abort_unless(
            $stat->gameResult && $stat->gameResult->game && $stat->gameResult->game->season
            && $stat->gameResult->game->season->league
            && $stat->gameResult->game->season->league->user_id === $request->user()->id,
            404
        );
    }
}
