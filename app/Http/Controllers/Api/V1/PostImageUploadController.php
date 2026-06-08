<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\MediaOptimizer;
use App\Support\UploadStorage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class PostImageUploadController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private MediaOptimizer $mediaOptimizer) {}

    public function __invoke(Request $request)
    {
        $this->authorize('create', Post::class);

        $data = $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:51200'],
        ]);

        $stored = $this->mediaOptimizer->store($data['image'], 'posts');

        return response()->json([
            'data' => [
                'path' => $stored['path'],
                'url' => UploadStorage::url($stored['path']),
                'type' => $stored['type'] ?? null,
                'size' => $stored['size'] ?? null,
            ],
        ], 201);
    }
}
