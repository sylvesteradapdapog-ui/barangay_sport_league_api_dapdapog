<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\GameResult;
use App\Models\Season;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameResultController extends Controller
{
    public function index(Request $request, Season $season): JsonResponse
    {
        $this->authorizeSeason($request, $season);

        $results = GameResult::whereHas('game', function ($query) use ($season) {
            $query->where('season_id', $season->id);
        })->with(['game', 'playerStats'])->get();

        return response()->json($results);
    }

    public function store(Request $request, Game $game): JsonResponse
    {
        $this->authorizeGame($request, $game);

        $validated = $request->validate([
            'home_score' => ['required', 'integer', 'min:0'],
            'away_score' => ['required', 'integer', 'min:0'],
        ]);

        $result = $game->gameResult()->updateOrCreate([], $validated);
        $game->update(['status' => 'done']);

        return response()->json($result, 201);
    }

    public function show(Request $request, GameResult $result): JsonResponse
    {
        $this->authorizeResult($request, $result);

        return response()->json($result->load(['game.homeTeam', 'game.awayTeam', 'playerStats']));
    }

    public function update(Request $request, GameResult $result): JsonResponse
    {
        $this->authorizeResult($request, $result);

        $validated = $request->validate([
            'home_score' => ['sometimes', 'required', 'integer', 'min:0'],
            'away_score' => ['sometimes', 'required', 'integer', 'min:0'],
        ]);

        $result->update($validated);

        return response()->json($result);
    }

    public function destroy(Request $request, GameResult $result): JsonResponse
    {
        $this->authorizeResult($request, $result);

        $result->delete();

        return response()->json(['message' => 'Result deleted successfully.']);
    }

    private function authorizeSeason(Request $request, Season $season): void
    {
        abort_unless($season->league && $season->league->user_id === $request->user()->id, 404);
    }

    private function authorizeGame(Request $request, Game $game): void
    {
        abort_unless($game->season && $game->season->league && $game->season->league->user_id === $request->user()->id, 404);
    }

    private function authorizeResult(Request $request, GameResult $result): void
    {
        abort_unless(
            $result->game && $result->game->season && $result->game->season->league
            && $result->game->season->league->user_id === $request->user()->id,
            404
        );
    }
}
