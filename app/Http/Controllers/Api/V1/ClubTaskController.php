<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ClubTaskController extends Controller
{
    public function index(Club $club)
    {
        Gate::authorize('update', $club);

        return response()->json(['data' => ClubTask::query()
            ->where('club_id', $club->id)
            ->orderByRaw('completed_at IS NOT NULL')
            ->orderByDesc('id')
            ->get()]);
    }

    public function store(Request $request, Club $club)
    {
        Gate::authorize('update', $club);
        $data = $request->validate(['title' => ['required', 'string', 'max:255']]);
        $task = ClubTask::create([
            'club_id' => $club->id,
            'created_by' => $request->user()->id,
            'title' => trim($data['title']),
        ]);

        return response()->json(['data' => $task], 201);
    }

    public function update(Request $request, Club $club, ClubTask $task)
    {
        Gate::authorize('update', $club);
        abort_unless((int) $task->club_id === (int) $club->id, 404);
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'completed' => ['sometimes', 'required', 'boolean'],
        ]);
        if (array_key_exists('title', $data)) {
            $task->title = trim($data['title']);
        }
        if (array_key_exists('completed', $data)) {
            $task->completed_at = $data['completed'] ? ($task->completed_at ?? now()) : null;
        }
        $task->save();

        return response()->json(['data' => $task]);
    }

    public function destroy(Club $club, ClubTask $task)
    {
        Gate::authorize('update', $club);
        abort_unless((int) $task->club_id === (int) $club->id, 404);
        $task->delete();

        return response()->noContent();
    }
}
