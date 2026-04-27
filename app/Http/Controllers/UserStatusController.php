<?php

namespace App\Http\Controllers;

use App\Events\UserStatusUpdated;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserStatusController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['online', 'offline', 'training', 'work'])],
        ]);

        $request->user()->forceFill([
            'status' => $data['status'],
        ])->save();

        broadcast(new UserStatusUpdated($request->user()))->toOthers();

        return response()->json([
            'status' => $request->user()->status,
        ]);
    }
}
