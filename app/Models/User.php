<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Club;
use App\Models\FriendInvitation;
use App\Models\Friendship;
use App\Models\Follow;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Post;
use App\Models\Ride;
use App\Models\Team;
use App\Notifications\MyCustomResetPassword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use HasRoles;

    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'theme',
        'status',
        'birth_date',
        'guardian_email',
        'guardian_user_id',
        'guardian_consent_requested_at',
        'guardian_consent_at',
        'guardian_consent_token',
        'profile_visibility',
        'bio',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'birth_date' => 'date',
            'guardian_consent_requested_at' => 'datetime',
            'guardian_consent_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function guardian()
    {
        return $this->belongsTo(User::class, 'guardian_user_id');
    }

    public function supervisedChildren()
    {
        return $this->hasMany(User::class, 'guardian_user_id');
    }

    public function clubs()
    {
        return $this->belongsToMany(Club::class)->withPivot('role');
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class)->withPivot('role');
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function files()
    {
        return $this->hasMany(File::class);
    }

    public function folders()
    {
        return $this->hasMany(Folder::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function conversations()
    {
        return $this->belongsToMany(Conversation::class, 'conversation_users');
    }

    public function rides()
    {
        return $this->hasMany(Ride::class, 'driver_id');
    }

    public function appNotifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function sentFriendInvitations()
    {
        return $this->hasMany(FriendInvitation::class, 'sender_id');
    }

    public function receivedFriendInvitations()
    {
        return $this->hasMany(FriendInvitation::class, 'recipient_id');
    }

    public function friendships()
    {
        return $this->hasMany(Friendship::class);
    }

    public function receivedFriendships()
    {
        return $this->hasMany(Friendship::class, 'friend_id');
    }

    public function following()
    {
        return $this->hasMany(Follow::class, 'follower_id');
    }

    public function followers()
    {
        return $this->hasMany(Follow::class, 'followed_id');
    }

    public function isFollowedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->followers()
            ->where('follower_id', $user->id)
            ->exists();
    }

    public function isProfileVisibleTo(?User $user): bool
    {
        return $this->profile_visibility !== 'private'
            || $user?->id === $this->id
            || $user?->can('user.manage')
            || $this->isFollowedBy($user);
    }

    public function sendPasswordResetNotification($token)
    {
   // Erstelle zuerst die Instanz der Notification
    $notification = new MyCustomResetPassword($token);

    // Setze die Sprache auf der Notification-Instanz
    $notification->locale('en');

    // Sende die fertig konfigurierte Notification
    $this->notify($notification);    }
}
