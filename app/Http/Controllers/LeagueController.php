<?php

namespace App\Http\Controllers;

use App\Models\League;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeagueController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($request->user()->leagues);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sport' => ['required', 'string', 'max:255'],
        ]);

        $league = $request->user()->leagues()->create($validated);

        return response()->json($league, 201);
    }

    public function show(Request $request, League $league): JsonResponse
    {
        $this->authorizeLeague($request, $league);

        return response()->json($league->load('seasons'));
    }

    public function update(Request $request, League $league): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'sport' => ['sometimes', 'required', 'string', 'max:255'],
        ]);

        $this->authorizeLeague($request, $league);

        $league->update([
            'name' => $validated['name'] ?? $league->name,
            'sport' => $validated['sport'] ?? $league->sport,
        ]);

        return response()->json($league);
    }

    public function destroy(Request $request, League $league): JsonResponse
    {
        $this->authorizeLeague($request, $league);
        $league->delete();

        return response()->json(['message' => 'League deleted successfully.']);
    }

    private function authorizeLeague(Request $request, League $league): void
    {
        abort_unless($league->user_id === $request->user()->id, 404);
    }
}
