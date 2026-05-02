<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\GameResultController;
use App\Http\Controllers\LeagueController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\PlayerStatController;
use App\Http\Controllers\SeasonController;
use App\Http\Controllers\StandingsController;
use App\Http\Controllers\TeamController;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $routes = collect(Route::getRoutes())
        ->filter(function (RoutingRoute $route) {
            return str_starts_with($route->uri(), 'api');
        })
        ->map(function (RoutingRoute $route) {
            return [
                'method' => implode('|', $route->methods()),
                'uri' => $route->uri(),
                'action' => $route->getActionName(),
            ];
        });

    return response()->json([
        'api' => config('app.name') . ' API',
        'total_api_routes' => $routes->count(),
        'routes' => $routes,
    ]);
});

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);

    Route::apiResource('leagues', LeagueController::class);
    Route::apiResource('leagues.seasons', SeasonController::class);

    Route::get('seasons/{season}/teams', [TeamController::class, 'index']);
    Route::post('seasons/{season}/teams', [TeamController::class, 'store']);
    Route::get('teams/{team}', [TeamController::class, 'show']);
    Route::match(['put', 'patch'], 'teams/{team}', [TeamController::class, 'update']);
    Route::delete('teams/{team}', [TeamController::class, 'destroy']);
    Route::post('teams/{team}/players', [TeamController::class, 'addPlayer']);
    Route::delete('teams/{team}/players/{player}', [TeamController::class, 'removePlayer']);

    Route::apiResource('players', PlayerController::class);

    Route::get('seasons/{season}/games', [GameController::class, 'index']);
    Route::post('seasons/{season}/games', [GameController::class, 'store']);
    Route::get('games/{game}', [GameController::class, 'show']);
    Route::match(['put', 'patch'], 'games/{game}', [GameController::class, 'update']);
    Route::delete('games/{game}', [GameController::class, 'destroy']);

    Route::get('seasons/{season}/results', [GameResultController::class, 'index']);
    Route::post('games/{game}/results', [GameResultController::class, 'store']);
    Route::get('results/{result}', [GameResultController::class, 'show']);
    Route::match(['put', 'patch'], 'results/{result}', [GameResultController::class, 'update']);
    Route::delete('results/{result}', [GameResultController::class, 'destroy']);

    Route::get('results/{result}/stats', [PlayerStatController::class, 'index']);
    Route::post('results/{result}/stats', [PlayerStatController::class, 'store']);
    Route::get('stats/{stat}', [PlayerStatController::class, 'show']);
    Route::match(['put', 'patch'], 'stats/{stat}', [PlayerStatController::class, 'update']);
    Route::delete('stats/{stat}', [PlayerStatController::class, 'destroy']);

    Route::get('seasons/{season}/standings', [StandingsController::class, 'standings']);
    Route::get('leaderboard', [StandingsController::class, 'leaderboard']);
});

