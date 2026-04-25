<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LikeController extends Controller
{
    public function toggle(Request $request)
    {
        $model = $request->model::findOrFail($request->id);

        $like = $model->likes()->where('user_id', auth()->id())->first();

        if ($like) {
            $like->delete();
        } else {
            $model->likes()->create([
                'user_id' => auth()->id()
            ]);
        }

        return back();
    }
}
