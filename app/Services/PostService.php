<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\File;
use App\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PostService
{
    public function __construct(
        private MediaOptimizer $mediaOptimizer,
        private DomainEventPublisher $domainEvents,
    ) {}

    public function create($user, $data)
    {
        return DB::transaction(function () use ($user, $data) {
            $post = Post::create([
                'user_id' => $user->id,
                'club_id' => $data['club_id'] ?? null,
                'team_id' => $data['team_id'] ?? null,
                'sport_id' => $data['sport_id'] ?? null,
                'post_type' => $data['post_type'] ?? 'normal',
                'content_origin' => $data['content_origin'] ?? 'self',
                'visibility' => $data['visibility'] ?? 'organization',
                'content' => $data['content'],
                'image' => $data['image'] ?? null,
            ]);

            $post->sportSkills()->sync($data['sport_skill_ids'] ?? []);

            $this->attachFiles($post, $user, $data['attachments'] ?? []);
            $this->recordActivity($post, 'post.created', $user);
            $this->domainEvents->record(
                'community.post.created.v1',
                $post,
                payload: [
                    'visibility' => $post->visibility,
                    'post_type' => $post->post_type,
                    'sport_id' => $post->sport_id,
                    'attachment_count' => count($data['attachments'] ?? []),
                ],
                audience: array_filter([
                    'users' => [$user->id],
                    'teams' => $post->team_id ? [$post->team_id] : null,
                    'clubs' => $post->club_id ? [$post->club_id] : null,
                ]),
            );

            return $post;
        });
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
                    'display_name' => $this->displayNameForUpload($attachment),
                    ...$this->mediaOptimizer->store($attachment, $this->directoryFor($post, $user->id)),
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

    private function displayNameForUpload(UploadedFile $file): string
    {
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]+/', '', $name));

        return Str::limit($name !== '' ? $name : 'Datei', 180, '');
    }
}
