<?php

namespace App\Services;

use App\Models\User;
use App\Models\WebsiteRequest;
use App\Models\WebsiteRequestAttachment;
use App\Models\WebsiteRequestMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class WebsiteProjectFiles
{
    public function eraseForUser(User $user): int
    {
        return DB::transaction(function () use ($user) {
            $count = WebsiteRequest::query()->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('guest_email', $user->email))
                ->get()->each(fn ($project) => $project->delete())->count();
            $messages = WebsiteRequestMessage::where('user_id', $user->id)->pluck('id');
            $this->scheduleDeletion($messages);
            WebsiteRequestMessage::whereIn('id', $messages)->delete();

            return $count;
        });
    }

    public function scheduleDeletion($messageIds): void
    {
        $files = WebsiteRequestAttachment::query()->whereIn('website_request_message_id', $messageIds)->get(['disk', 'path']);
        DB::afterCommit(function () use ($files) {
            foreach ($files as $file) {
                Storage::disk($file->disk)->delete($file->path);
            }
        });
    }
}
