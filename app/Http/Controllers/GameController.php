<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Season;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GameController extends Controller
{
    public function index(Request $request, Season $season): JsonResponse
    {
        $this->authorizeSeason($request, $season);

        return response()->json($season->games()->with(['homeTeam', 'awayTeam', 'gameResult'])->get());
    }

    public function store(Request $request, Season $season): JsonResponse
    {
        $this->authorizeSeason($request, $season);

        $validated = $request->validate([
            'home_team_id' => ['required', 'integer', 'exists:teams,id'],
            'away_team_id' => ['required', 'integer', 'exists:teams,id', 'different:home_team_id'],
            'scheduled_at' => ['required', 'date'],
            'venue' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:scheduled,done'],
        ]);

        $this->validateGameRules($season, $validated);

        $game = $season->games()->create($validated);

        return response()->json($game, 201);
    }

    public function show(Request $request, Game $game): JsonResponse
    {
        $this->authorizeGame($request, $game);

        return response()->json($game->load(['season', 'homeTeam', 'awayTeam', 'gameResult']));
    }

    public function update(Request $request, Game $game): JsonResponse
    {
        $this->authorizeGame($request, $game);

        $validated = $request->validate([
            'home_team_id' => ['sometimes', 'required', 'integer', 'exists:teams,id'],
            'away_team_id' => ['sometimes', 'required', 'integer', 'exists:teams,id', 'different:home_team_id'],
            'scheduled_at' => ['sometimes', 'required', 'date'],
            'venue' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'required', 'in:scheduled,done'],
        ]);

        $payload = [
            'home_team_id' => $validated['home_team_id'] ?? $game->home_team_id,
            'away_team_id' => $validated['away_team_id'] ?? $game->away_team_id,
            'scheduled_at' => $validated['scheduled_at'] ?? $game->scheduled_at,
            'venue' => $validated['venue'] ?? $game->venue,
            'status' => $validated['status'] ?? $game->status,
        ];

        $this->validateGameRules($game->season, $payload, $game->id);

        $game->update($payload);

        return response()->json($game);
    }

    public function destroy(Request $request, Game $game): JsonResponse
    {
        $this->authorizeGame($request, $game);

        $game->delete();

        return response()->json(['message' => 'Game deleted successfully.']);
    }

    private function validateGameRules(Season $season, array $data, ?int $ignoreGameId = null): void
    {
        if ((int) $data['home_team_id'] === (int) $data['away_team_id']) {
            throw ValidationException::withMessages([
                'away_team_id' => ['Home and away teams cannot be the same.'],
            ]);
        }

        $teamCount = $season->teams()
            ->whereIn('id', [(int) $data['home_team_id'], (int) $data['away_team_id']])
            ->count();

        if ($teamCount !== 2) {
            throw ValidationException::withMessages([
                'home_team_id' => ['Both teams must belong to the selected season.'],
            ]);
        }

        $scheduledDate = Carbon::parse($data['scheduled_at'])->toDateString();

        $duplicateMatchup = $season->games()
            ->when($ignoreGameId, fn ($query) => $query->where('id', '!=', $ignoreGameId))
            ->whereDate('scheduled_at', $scheduledDate)
            ->where(function ($query) use ($data) {
                $query
                    ->where(function ($sub) use ($data) {
                        $sub->where('home_team_id', $data['home_team_id'])
                            ->where('away_team_id', $data['away_team_id']);
                    })
                    ->orWhere(function ($sub) use ($data) {
                        $sub->where('home_team_id', $data['away_team_id'])
                            ->where('away_team_id', $data['home_team_id']);
                    });
            })
            ->exists();

        if ($duplicateMatchup) {
            throw ValidationException::withMessages([
                'scheduled_at' => ['Duplicate matchup conflict on this date.'],
            ]);
        }
    }

    private function authorizeSeason(Request $request, Season $season): void
    {
        abort_unless($season->league && $season->league->user_id === $request->user()->id, 404);
    }

    private function authorizeGame(Request $request, Game $game): void
    {
        abort_unless($game->season && $game->season->league && $game->season->league->user_id === $request->user()->id, 404);
    }
}
