<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\GamificationService;
use Illuminate\Http\Request;

class PostHelpfulController extends Controller
{
    public function __construct(private GamificationService $gamification) {}

    public function toggle(Request $request, Post $post)
    {
        abort_if($request->user()->is($post->user), 403);

        $helpful = $post->helpfuls()
            ->where('user_id', $request->user()->id)
            ->first();

        if ($helpful) {
            $helpful->delete();

            return back()->with('message', 'Hilfreich-Markierung entfernt.');
        }

        $helpful = $post->helpfuls()->create([
            'user_id' => $request->user()->id,
            'context' => 'helpful',
        ]);

        if (in_array($post->post_type, ['knowledge', 'training_drill', 'tactic', 'analysis'], true)) {
            $this->gamification->grant($post->user, 'knowledge_marked_helpful', $helpful, [
                'post_id' => $post->id,
                'post_type' => $post->post_type,
                'sport_id' => $post->sport_id,
                'marked_by' => $request->user()->id,
            ]);
        }

        return back()->with('message', 'Beitrag als hilfreich markiert.');
    }
}
