<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\File;
use App\Models\Post;
use Illuminate\Http\UploadedFile;

class PostService
{
    public function create($user, $data)
    {
        $post = Post::create([
            'user_id' => $user->id,
            'club_id' => $data['club_id'] ?? null,
            'team_id' => $data['team_id'] ?? null,
            'visibility' => $data['visibility'] ?? 'organization',
            'content' => $data['content'],
            'image' => $data['image'] ?? null,
        ]);

        $this->attachFiles($post, $user, $data['attachments'] ?? []);
        $this->recordActivity($post, 'post.created', $user);

        return $post;
    }

    public function attachFiles(Post $post, $user, array $attachments): void
    {
        collect($attachments)
            ->filter(fn ($attachment) => $attachment instanceof UploadedFile)
            ->each(function (UploadedFile $attachment) use ($post, $user) {
                $file = File::create([
                    'club_id' => $post->club_id,
                    'team_id' => $post->team_id,
                    'user_id' => $user->id,
                    'path' => $attachment->store($this->directoryFor($post, $user->id), 'public'),
                    'type' => $attachment->getClientMimeType(),
                    'size' => $attachment->getSize(),
                ]);

                $post->attachments()->create([
                    'file_id' => $file->id,
                ]);
            });
    }

    public function recordActivity(Post $post, string $type, $user, array $data = []): void
    {
        Activity::create([
            'user_id' => $user->id,
            'club_id' => $post->club_id,
            'team_id' => $post->team_id,
            'type' => $type,
            'subject_type' => Post::class,
            'subject_id' => $post->id,
            'data' => array_merge([
                'visibility' => $post->visibility,
            ], $data),
        ]);
    }

    private function directoryFor(Post $post, int $userId): string
    {
        if ($post->team_id) {
            return 'teams/'.$post->team_id.'/posts';
        }

        if ($post->club_id) {
            return 'clubs/'.$post->club_id.'/posts';
        }

        return 'users/'.$userId.'/posts';
    }
}
