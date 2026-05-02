<?php

namespace App\Http\Controllers;

use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $players = Player::whereHas('teams.season.league', function ($query) use ($request) {
            $query->where('user_id', $request->user()->id);
        })->with('teams')->get();

        return response()->json($players);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'birthdate' => ['required', 'date'],
            'position' => ['required', 'string', 'max:255'],
        ]);

        $player = Player::create($validated);

        return response()->json($player, 201);
    }

    public function show(Request $request, Player $player): JsonResponse
    {
        $this->authorizePlayer($request, $player);

        return response()->json($player->load('teams'));
    }

    public function update(Request $request, Player $player): JsonResponse
    {
        $this->authorizePlayer($request, $player);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'birthdate' => ['sometimes', 'required', 'date'],
            'position' => ['sometimes', 'required', 'string', 'max:255'],
        ]);

        $player->update($validated);

        return response()->json($player);
    }

    public function destroy(Request $request, Player $player): JsonResponse
    {
        $this->authorizePlayer($request, $player);

        $player->delete();

        return response()->json(['message' => 'Player deleted successfully.']);
    }

    private function authorizePlayer(Request $request, Player $player): void
    {
        $belongsToUser = $player->teams()->whereHas('season.league', function ($query) use ($request) {
            $query->where('user_id', $request->user()->id);
        })->exists();

        abort_unless($belongsToUser, 404);
    }
}
