<?php

use App\Http\Controllers\Api\V1\CompetitionPlanningController;
use App\Http\Controllers\Api\V1\FamilyCalendarController;
use App\Http\Controllers\Api\V1\SeasonPlanningController;
use Illuminate\Support\Facades\Route;

Route::get('/planning/workspace', [CompetitionPlanningController::class, 'workspace'])->name('planning.workspace');
Route::get('/clubs/{club}/competitions', [CompetitionPlanningController::class, 'index'])->name('competitions.index');
Route::post('/clubs/{club}/competitions', [CompetitionPlanningController::class, 'store'])->name('competitions.store');
Route::get('/competitions/{competition}', [CompetitionPlanningController::class, 'show'])->name('competitions.show');
Route::put('/competitions/{competition}', [CompetitionPlanningController::class, 'update'])->name('competitions.update');
Route::delete('/competitions/{competition}', [CompetitionPlanningController::class, 'destroy'])->name('competitions.destroy');
Route::post('/competitions/{competition}/components/{kind}', [CompetitionPlanningController::class, 'add'])->name('competitions.components.store');
Route::delete('/competitions/{competition}/components/{kind}/{entry}', [CompetitionPlanningController::class, 'remove'])->whereNumber('entry')->name('competitions.components.destroy');
Route::post('/competitions/{competition}/registrations', [CompetitionPlanningController::class, 'nominate'])->name('competitions.registrations.store');
Route::post('/competitions/{competition}/registrations/{registration}/{action}', [CompetitionPlanningController::class, 'registrationAction'])->name('competitions.registrations.action');
Route::post('/competitions/{competition}/tournament', [CompetitionPlanningController::class, 'tournament'])->name('competitions.tournament');
Route::put('/competitions/{competition}/matches/{match}', [CompetitionPlanningController::class, 'matchUpdate'])->name('competitions.matches.update');
Route::post('/competitions/{competition}/lineups', [CompetitionPlanningController::class, 'lineup'])->name('competitions.lineups.store');
Route::post('/competitions/{competition}/games', [CompetitionPlanningController::class, 'gameStore'])->name('competitions.games.store');
Route::put('/competitions/{competition}/games/{game}', [CompetitionPlanningController::class, 'gameUpdate'])->name('competitions.games.update');
Route::put('/competitions/{competition}/games/{game}/lineup', [CompetitionPlanningController::class, 'gameLineup'])->name('competitions.games.lineup');
Route::get('/teams/{team}/season-plans', [SeasonPlanningController::class, 'index'])->name('season-plans.index');
Route::post('/teams/{team}/season-plans', [SeasonPlanningController::class, 'store'])->name('season-plans.store');
Route::put('/teams/{team}/season-plans/{seasonPlan}', [SeasonPlanningController::class, 'update'])->name('season-plans.update');
Route::delete('/teams/{team}/season-plans/{seasonPlan}', [SeasonPlanningController::class, 'destroy'])->name('season-plans.destroy');
Route::get('/family-calendar', [FamilyCalendarController::class, 'index'])->name('family-calendar.index');
Route::post('/family-calendar/entries', [FamilyCalendarController::class, 'store'])->name('family-calendar.entries.store');
Route::put('/family-calendar/entries/{entry}', [FamilyCalendarController::class, 'update'])->name('family-calendar.entries.update');
Route::delete('/family-calendar/entries/{entry}', [FamilyCalendarController::class, 'destroy'])->name('family-calendar.entries.destroy');
Route::post('/family-calendar/events/{event}/people/{person}/response', [FamilyCalendarController::class, 'respond'])->whereNumber('person')->name('family-calendar.respond');
