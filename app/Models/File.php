<?php

namespace App\Models;

use App\Support\UploadStorage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id', 'team_id', 'event_id', 'user_id', 'folder_id',
        'display_name', 'path', 'thumbnail_path', 'type', 'size',
    ];

    protected $appends = ['url', 'thumbnail_url'];

    public function getUrlAttribute(): ?string
    {
        return UploadStorage::url($this->path);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        return UploadStorage::url($this->thumbnail_path);
    }

    public function getDisplayNameAttribute($value): string
    {
        $name = trim((string) $value);

        if ($name !== '') {
            return $name;
        }

        return basename(str_replace('\\', '/', (string) $this->path)) ?: 'Datei';
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function folder()
    {
        return $this->belongsTo(Folder::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function posts()
    {
        return $this->belongsToMany(Post::class, 'post_attachments');
    }

    public function messages()
    {
        return $this->belongsToMany(Message::class, 'message_attachments');
    }

    public function externalShares()
    {
        return $this->hasMany(FileShare::class);
    }

    public function policyDocuments()
    {
        return $this->hasMany(ClubPolicyDocument::class);
    }
}
