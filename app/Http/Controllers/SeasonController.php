<?php

namespace App\Http\Controllers;

use App\Models\League;
use App\Models\Season;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeasonController extends Controller
{
    public function index(Request $request, League $league): JsonResponse
    {
        $this->authorizeLeague($request, $league);

        return response()->json($league->seasons);
    }

    public function store(Request $request, League $league): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', 'in:active,done'],
        ]);

        $this->authorizeLeague($request, $league);
        $season = $league->seasons()->create($validated);

        return response()->json($season, 201);
    }

    public function show(Request $request, League $league, Season $season): JsonResponse
    {
        $this->authorizeSeason($request, $league, $season);

        return response()->json($season->load(['teams', 'games']));
    }

    public function update(Request $request, League $league, Season $season): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['sometimes', 'required', 'in:active,done'],
        ]);

        $this->authorizeSeason($request, $league, $season);

        $season->update($validated);

        return response()->json($season);
    }

    public function destroy(Request $request, League $league, Season $season): JsonResponse
    {
        $this->authorizeSeason($request, $league, $season);

        $season->delete();

        return response()->json(['message' => 'Season deleted successfully.']);
    }

    private function authorizeLeague(Request $request, League $league): void
    {
        abort_unless($league->user_id === $request->user()->id, 404);
    }

    private function authorizeSeason(Request $request, League $league, Season $season): void
    {
        $this->authorizeLeague($request, $league);
        abort_unless($season->league_id === $league->id, 404);
    }
}
