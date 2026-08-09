<?php

namespace App\Services;

use App\Models\CommerceOrder;
use App\Models\OrganizationJobInterest;
use App\Models\User;
use App\Models\WebsiteRequest;

class UserPrivacyExportService
{
    public function export(User $user): array
    {
        $recruitingInterests = OrganizationJobInterest::query()
            ->with(['job:id,club_id,title', 'job.club:id,name'])
            ->where(fn ($query) => $query->where('user_id', $user->id)->orWhere('email', $user->email))
            ->latest('id')
            ->get();
        $agencyRequests = WebsiteRequest::query()
            ->with('club:id,name')
            ->where(fn ($query) => $query->where('user_id', $user->id)->orWhere('guest_email', $user->email))
            ->latest('id')
            ->get();
        $commerceOrders = CommerceOrder::query()
            ->with(['refunds', 'returnRequests'])
            ->where(fn ($query) => $query->where('user_id', $user->id)->orWhere('guest_email', $user->email))
            ->latest('id')
            ->get();

        $user->loadMissing([
            'roles:id,name',
            'permissions:id,name',
            'guardian:id,name,email',
            'supervisedChildren:id,name,email,guardian_user_id,guardian_consent_at,guardian_consent_revoked_at',
            'clubs:id,name',
            'teams:id,name,club_id,sport_type',
            'teams.club:id,name',
            'sportProfiles.sport:id,name,slug,category',
            'posts:id,user_id,club_id,team_id,visibility,content,image,moderation_status,created_at,updated_at',
            'files:id,user_id,club_id,team_id,event_id,folder_id,display_name,type,size,created_at,updated_at',
            'folders:id,user_id,club_id,team_id,event_id,parent_id,name,created_at,updated_at',
            'appNotifications:id,user_id,type,data,read,created_at,updated_at',
            'socialAccounts:id,user_id,provider,email,name,created_at,updated_at',
            'connectedSportAccounts:id,user_id,provider,display_name,status,last_synced_at,created_at,updated_at',
            'connectedSportActivities:id,user_id,provider,activity_type,title,started_at,duration_seconds,distance_meters,calories,created_at,updated_at',
            'invoices:id,user_id,club_id,number,title,amount,status,source,issued_at,due_date,paid_at,created_at,updated_at',
            'payments:id,user_id,club_id,invoice_id,amount,status,provider,reference,paid_at,created_at,updated_at',
            'subscriptionInvoices:id,user_id,club_id,number,title,amount_cents,currency,status,payment_method,payment_reference,issued_at,due_at,paid_at,created_at,updated_at',
            'subscriptions:id,user_id,subscription_plan_id,status,payment_provider,trial_ends_at,current_period_ends_at,cancel_at_period_end,cancels_at,created_at,updated_at',
        ]);

        return [
            'schema' => 'airmius.privacy-export.v1',
            'generated_at' => now()->toJSON(),
            'account' => [
                'id' => $user->id,
                'created_at' => $user->created_at?->toJSON(),
                'updated_at' => $user->updated_at?->toJSON(),
                'email_verified_at' => $user->email_verified_at?->toJSON(),
                'privacy_status' => $user->privacy_status ?: 'active',
                'account_status' => $user->account_status,
                'deletion_scheduled_at' => $user->deletion_scheduled_at?->toJSON(),
                'anonymized_at' => $user->anonymized_at?->toJSON(),
            ],
            'profile' => [
                'name' => $user->name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'country' => $user->country,
                'birth_date' => $user->birth_date?->toDateString(),
                'gender' => $user->gender,
                'athlete_license_number' => $user->athlete_license_number,
                'bio' => $user->bio,
                'address' => [
                    'street' => $user->street,
                    'house_number' => $user->house_number,
                    'postal_code' => $user->postal_code,
                    'city' => $user->city,
                    'state' => $user->state,
                    'country' => $user->country,
                ],
            ],
            'privacy_settings' => [
                'profile_visibility' => $user->profile_visibility ?? 'public',
                'direct_message_privacy' => $user->direct_message_privacy ?? 'everyone',
                'friend_request_privacy' => $user->friend_request_privacy ?? 'everyone',
                'ads_personalization_consent' => (bool) $user->ads_personalization_consent,
                'ads_measurement_consent' => (bool) $user->ads_measurement_consent,
                'product_analytics_consent' => (bool) $user->product_analytics_consent,
                'product_analytics_consented_at' => $user->product_analytics_consented_at?->toJSON(),
                'product_analytics_consent_version' => $user->product_analytics_consent_version,
            ],
            'guardian' => [
                'guardian_email' => $user->guardian_email,
                'guardian_user' => $user->guardian ? [
                    'id' => $user->guardian->id,
                    'name' => $user->guardian->name,
                    'email' => $user->guardian->email,
                ] : null,
                'guardian_consent_requested_at' => $user->guardian_consent_requested_at?->toJSON(),
                'guardian_consent_at' => $user->guardian_consent_at?->toJSON(),
                'guardian_consent_rejected_at' => $user->guardian_consent_rejected_at?->toJSON(),
                'guardian_consent_revoked_at' => $user->guardian_consent_revoked_at?->toJSON(),
                'supervised_children' => $user->supervisedChildren
                    ->map(fn (User $child) => [
                        'id' => $child->id,
                        'name' => $child->name,
                        'email' => $child->email,
                        'guardian_consent_at' => $child->guardian_consent_at?->toJSON(),
                        'guardian_consent_revoked_at' => $child->guardian_consent_revoked_at?->toJSON(),
                    ])
                    ->values(),
            ],
            'roles' => $user->roles->pluck('name')->values(),
            'permissions' => $user->permissions->pluck('name')->values(),
            'clubs' => $user->clubs
                ->map(fn ($club) => [
                    'id' => $club->id,
                    'name' => $club->name,
                    'membership' => [
                        'role' => $club->pivot?->role,
                        'roles' => $club->pivot?->roles,
                        'membership_status' => $club->pivot?->membership_status,
                        'member_number' => $club->pivot?->member_number,
                        'joined_on' => $club->pivot?->joined_on,
                        'membership_ends_on' => $club->pivot?->membership_ends_on,
                    ],
                ])
                ->values(),
            'teams' => $user->teams
                ->map(fn ($team) => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'sport_type' => $team->sport_type,
                    'club' => $team->club ? [
                        'id' => $team->club->id,
                        'name' => $team->club->name,
                    ] : null,
                    'role' => $team->pivot?->role,
                ])
                ->values(),
            'sport_profiles' => $user->sportProfiles
                ->map(fn ($profile) => [
                    'id' => $profile->id,
                    'status' => $profile->status,
                    'experience_level' => $profile->experience_level,
                    'visibility' => $profile->visibility,
                    'metrics' => $profile->performance_metrics ?? [],
                    'sport' => $profile->sport ? [
                        'id' => $profile->sport->id,
                        'name' => $profile->sport->name,
                        'slug' => $profile->sport->slug,
                        'category' => $profile->sport->category,
                    ] : null,
                ])
                ->values(),
            'content' => [
                'posts' => $user->posts
                    ->map(fn ($post) => [
                        'id' => $post->id,
                        'club_id' => $post->club_id,
                        'team_id' => $post->team_id,
                        'visibility' => $post->visibility,
                        'content' => $post->content,
                        'image' => $post->image,
                        'moderation_status' => $post->moderation_status,
                        'created_at' => $post->created_at?->toJSON(),
                        'updated_at' => $post->updated_at?->toJSON(),
                    ])
                    ->values(),
            ],
            'files' => [
                'folders' => $user->folders
                    ->map(fn ($folder) => [
                        'id' => $folder->id,
                        'name' => $folder->name,
                        'parent_id' => $folder->parent_id,
                        'club_id' => $folder->club_id,
                        'team_id' => $folder->team_id,
                        'event_id' => $folder->event_id,
                        'created_at' => $folder->created_at?->toJSON(),
                        'updated_at' => $folder->updated_at?->toJSON(),
                    ])
                    ->values(),
                'items' => $user->files
                    ->map(fn ($file) => [
                        'id' => $file->id,
                        'display_name' => $file->display_name,
                        'type' => $file->type,
                        'size' => $file->size,
                        'folder_id' => $file->folder_id,
                        'club_id' => $file->club_id,
                        'team_id' => $file->team_id,
                        'event_id' => $file->event_id,
                        'created_at' => $file->created_at?->toJSON(),
                        'updated_at' => $file->updated_at?->toJSON(),
                    ])
                    ->values(),
            ],
            'notifications' => $user->appNotifications
                ->map(fn ($notification) => [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'data' => $notification->data,
                    'read' => (bool) $notification->read,
                    'created_at' => $notification->created_at?->toJSON(),
                    'updated_at' => $notification->updated_at?->toJSON(),
                ])
                ->values(),
            'integrations' => [
                'social_accounts' => $user->socialAccounts
                    ->map(fn ($account) => [
                        'id' => $account->id,
                        'provider' => $account->provider,
                        'email' => $account->email,
                        'name' => $account->name,
                        'created_at' => $account->created_at?->toJSON(),
                        'updated_at' => $account->updated_at?->toJSON(),
                    ])
                    ->values(),
                'sport_accounts' => $user->connectedSportAccounts
                    ->map(fn ($account) => [
                        'id' => $account->id,
                        'provider' => $account->provider,
                        'display_name' => $account->display_name,
                        'status' => $account->status,
                        'last_synced_at' => $account->last_synced_at?->toJSON(),
                        'created_at' => $account->created_at?->toJSON(),
                        'updated_at' => $account->updated_at?->toJSON(),
                    ])
                    ->values(),
                'sport_activities' => $user->connectedSportActivities
                    ->map(fn ($activity) => [
                        'id' => $activity->id,
                        'provider' => $activity->provider,
                        'activity_type' => $activity->activity_type,
                        'title' => $activity->title,
                        'started_at' => $activity->started_at?->toJSON(),
                        'duration_seconds' => $activity->duration_seconds,
                        'distance_meters' => $activity->distance_meters,
                        'calories' => $activity->calories,
                        'created_at' => $activity->created_at?->toJSON(),
                        'updated_at' => $activity->updated_at?->toJSON(),
                    ])
                    ->values(),
            ],
            'billing' => [
                'club_invoices' => $user->invoices->values(),
                'payments' => $user->payments->values(),
                'subscription_invoices' => $user->subscriptionInvoices->values(),
                'subscriptions' => $user->subscriptions->values(),
            ],
            'commerce' => [
                'orders' => $commerceOrders->map(fn (CommerceOrder $order) => [
                    'id' => $order->id,
                    'type' => $order->type,
                    'provider' => $order->provider,
                    'amount_cents' => $order->amount_cents,
                    'currency' => $order->currency,
                    'status' => $order->status,
                    'invoice_number' => $order->invoice_number,
                    'credit_note_number' => $order->credit_note_number,
                    'issue_status' => $order->issue_status,
                    'issue_note' => $order->issue_note,
                    'refunded_cents' => $order->refunded_cents,
                    'refunds' => $order->refunds->map(fn ($refund) => [
                        'id' => $refund->id,
                        'amount_cents' => $refund->amount_cents,
                        'currency' => $refund->currency,
                        'provider' => $refund->provider,
                        'provider_refund_id' => $refund->provider_refund_id,
                        'status' => $refund->status,
                        'reason' => $refund->reason,
                        'processed_at' => $refund->processed_at?->toJSON(),
                    ])->values(),
                    'return_requests' => $order->returnRequests->map(fn ($returnRequest) => [
                        'id' => $returnRequest->id,
                        'status' => $returnRequest->status,
                        'reason' => $returnRequest->reason,
                        'resolution_note' => $returnRequest->resolution_note,
                        'requested_amount_cents' => $returnRequest->requested_amount_cents,
                        'approved_amount_cents' => $returnRequest->approved_amount_cents,
                        'requested_at' => $returnRequest->requested_at?->toJSON(),
                        'refunded_at' => $returnRequest->refunded_at?->toJSON(),
                    ])->values(),
                    'created_at' => $order->created_at?->toJSON(),
                    'updated_at' => $order->updated_at?->toJSON(),
                ])->values(),
            ],
            'recruiting' => [
                'applications' => $recruitingInterests->map(fn (OrganizationJobInterest $interest) => [
                    'id' => $interest->id,
                    'job' => $interest->job ? [
                        'id' => $interest->job->id,
                        'title' => $interest->job->title,
                        'club' => $interest->job->club?->only(['id', 'name']),
                    ] : null,
                    'name' => $interest->name,
                    'email' => $interest->email,
                    'phone' => $interest->phone,
                    'message' => $interest->message,
                    'status' => $interest->status,
                    'internal_note' => $interest->internal_note,
                    'consent_at' => $interest->consent_at?->toJSON(),
                    'shared_profile_fields' => $interest->shared_profile_fields,
                    'profile_consent_at' => $interest->profile_consent_at?->toJSON(),
                    'allow_in_app_contact' => (bool) $interest->allow_in_app_contact,
                    'retention_expires_at' => $interest->retention_expires_at?->toJSON(),
                    'created_at' => $interest->created_at?->toJSON(),
                    'updated_at' => $interest->updated_at?->toJSON(),
                ])->values(),
            ],
            'agency' => [
                'requests' => $agencyRequests->map(fn (WebsiteRequest $agencyRequest) => [
                    'id' => $agencyRequest->id,
                    'club' => $agencyRequest->club?->only(['id', 'name']),
                    'club_name' => $agencyRequest->club_name,
                    'guest_name' => $agencyRequest->guest_name,
                    'guest_email' => $agencyRequest->guest_email,
                    'guest_phone' => $agencyRequest->guest_phone,
                    'domain' => $agencyRequest->domain,
                    'goals' => $agencyRequest->goals,
                    'notes' => $agencyRequest->notes,
                    'status' => $agencyRequest->status,
                    'consent_at' => $agencyRequest->consent_at?->toJSON(),
                    'retention_expires_at' => $agencyRequest->retention_expires_at?->toJSON(),
                    'created_at' => $agencyRequest->created_at?->toJSON(),
                    'updated_at' => $agencyRequest->updated_at?->toJSON(),
                ])->values(),
            ],
            'rights' => [
                'export' => [
                    'web_route' => 'auth.settings.privacy.export',
                    'api_route' => 'api.v1.privacy.export',
                ],
                'correction' => [
                    'web_route' => 'auth.settings.privacy.correct',
                    'api_route' => 'api.v1.privacy.correct',
                    'existing_profile_routes' => ['user-profile-information.update', 'auth.settings.update', 'api.v1.me.profile.update', 'api.v1.settings.update'],
                ],
                'deletion' => [
                    'web_routes' => ['current-user.deletion-code', 'current-user.destroy'],
                    'api_routes' => ['api.v1.account.deletion-code', 'api.v1.account.destroy'],
                    'manual_account_deletion' => 'hard_delete_after_email_code',
                    'inactive_account_retention' => 'anonymize_via_UserPrivacyRetentionService',
                ],
                'partial_data_erasure' => [
                    'web_routes' => ['auth.settings.privacy.erasure', 'auth.settings.privacy.erasure.code', 'auth.settings.privacy.erasure.destroy'],
                    'api_routes' => ['api.v1.privacy.data-erasure.code', 'api.v1.privacy.data-erasure.destroy'],
                    'public_information_route' => 'legal.data-erasure',
                    'confirmation' => 'email_code_bound_to_selected_categories',
                    'retained_data' => ['account_credentials', 'legal_and_contractual_records', 'club_team_and_permission_assignments'],
                ],
                'consent_withdrawal' => [
                    'web_route' => 'auth.settings.privacy.withdraw-consents',
                    'api_route' => 'api.v1.privacy.withdraw-consents',
                    'supported_consents' => ['ads_personalization', 'ads_measurement', 'product_analytics', 'recruiting_profile_sharing'],
                ],
            ],
        ];
    }
}
