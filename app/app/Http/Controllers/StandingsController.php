<?php

namespace App\Http\Controllers;

use App\Models\Season;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StandingsController extends Controller
{
    public function standings(Request $request, Season $season): JsonResponse
    {
        $this->authorizeSeason($request, $season);

        return response()->json($this->buildStandings($season));
    }

    public function leaderboard(Request $request): JsonResponse
    {
        $games = Game::whereHas('season.league', function ($query) use ($request) {
            $query->where('user_id', $request->user()->id);
        })->with(['gameResult', 'homeTeam', 'awayTeam'])->where('status', 'done')->get();

        $records = collect();

        foreach ($games as $game) {
            if (! $game->gameResult || ! $game->homeTeam || ! $game->awayTeam) {
                continue;
            }

            foreach ([$game->homeTeam, $game->awayTeam] as $team) {
                if (! isset($records[$team->id])) {
                    $records[$team->id] = [
                        'team_id' => $team->id,
                        'team_name' => $team->name,
                        'wins' => 0,
                        'losses' => 0,
                        'games_played' => 0,
                        'points_for' => 0,
                        'points_against' => 0,
                        'win_pct' => 0,
                    ];
                }
            }

            $home = &$records[$game->home_team_id];
            $away = &$records[$game->away_team_id];

            $home['games_played']++;
            $away['games_played']++;
            $home['points_for'] += $game->gameResult->home_score;
            $home['points_against'] += $game->gameResult->away_score;
            $away['points_for'] += $game->gameResult->away_score;
            $away['points_against'] += $game->gameResult->home_score;

            if ($game->gameResult->home_score > $game->gameResult->away_score) {
                $home['wins']++;
                $away['losses']++;
            } elseif ($game->gameResult->away_score > $game->gameResult->home_score) {
                $away['wins']++;
                $home['losses']++;
            }
        }

        $leaderboard = $records->map(function (array $record) {
            $record['win_pct'] = $record['games_played'] > 0
                ? round($record['wins'] / $record['games_played'], 3)
                : 0;

            return $record;
        })->sort(function (array $a, array $b) {
            if ($a['wins'] !== $b['wins']) {
                return $b['wins'] <=> $a['wins'];
            }

            if ($a['losses'] !== $b['losses']) {
                return $a['losses'] <=> $b['losses'];
            }

            return $b['win_pct'] <=> $a['win_pct'];
        })->values()->map(function (array $record, int $index) {
            $record['rank'] = $index + 1;

            return $record;
        });

        return response()->json($leaderboard);
    }

    private function buildStandings(Season $season)
    {
        $teams = $season->teams()->get()->keyBy('id');
        $records = collect();

        foreach ($teams as $team) {
            $records[$team->id] = [
                'team_id' => $team->id,
                'team_name' => $team->name,
                'wins' => 0,
                'losses' => 0,
                'games_played' => 0,
                'points_for' => 0,
                'points_against' => 0,
                'win_pct' => 0,
            ];
        }

        $games = $season->games()->with('gameResult')->where('status', 'done')->get();

        foreach ($games as $game) {
            if (! $game->gameResult) {
                continue;
            }

            $homeId = $game->home_team_id;
            $awayId = $game->away_team_id;
            $homeScore = $game->gameResult->home_score;
            $awayScore = $game->gameResult->away_score;

            if (! isset($records[$homeId], $records[$awayId])) {
                continue;
            }

            $records[$homeId]['games_played']++;
            $records[$awayId]['games_played']++;

            $records[$homeId]['points_for'] += $homeScore;
            $records[$homeId]['points_against'] += $awayScore;
            $records[$awayId]['points_for'] += $awayScore;
            $records[$awayId]['points_against'] += $homeScore;

            if ($homeScore > $awayScore) {
                $records[$homeId]['wins']++;
                $records[$awayId]['losses']++;
            } elseif ($awayScore > $homeScore) {
                $records[$awayId]['wins']++;
                $records[$homeId]['losses']++;
            }
        }

        $standings = $records->map(function (array $record) {
            $record['win_pct'] = $record['games_played'] > 0
                ? round($record['wins'] / $record['games_played'], 3)
                : 0;

            return $record;
        })->sort(function (array $a, array $b) {
            if ($a['wins'] !== $b['wins']) {
                return $b['wins'] <=> $a['wins'];
            }

            if ($a['losses'] !== $b['losses']) {
                return $a['losses'] <=> $b['losses'];
            }

            return $b['win_pct'] <=> $a['win_pct'];
        })->values();

        return $standings->map(function (array $record, int $index) {
            $record['rank'] = $index + 1;

            return $record;
        });
    }

    private function authorizeSeason(Request $request, Season $season): void
    {
        abort_unless($season->league && $season->league->user_id === $request->user()->id, 404);
    }
}
