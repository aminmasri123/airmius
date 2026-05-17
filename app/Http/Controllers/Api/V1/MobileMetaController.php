<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;

class MobileMetaController extends Controller
{
    public function __invoke()
    {
        return response()->json([
            'data' => [
                'api_version' => 'v1',
                'auth' => 'sanctum_bearer_token',
                'supported_locales' => ['de', 'en', 'fr', 'ar'],
                'rtl_locales' => ['ar'],
                'capabilities' => [
                    'profile' => ['user_card'],
                    'feed' => ['list', 'create', 'stories', 'story_upload', 'story_reactions'],
                    'clubs' => ['list', 'show', 'members', 'billing', 'membership_requests'],
                    'teams' => ['list', 'show'],
                    'chat' => ['conversations', 'messages', 'send_message', 'typing'],
                    'events' => ['list', 'show'],
                    'training' => ['plans', 'logs'],
                    'sport_map' => [
                        'route_planning',
                        'route_duplication',
                        'route_navigation_cues',
                        'track_recording',
                        'track_points_append',
                        'community_sport_places',
                        'nearby_place_search',
                    ],
                    'subscriptions' => ['plans', 'overview', 'bank_transfer_checkout', 'stripe_redirect_checkout', 'paypal_redirect_checkout', 'cancel', 'renew'],
                    'commerce' => [
                        'products',
                        'orders',
                        'admin_dashboard',
                        'admin_catalog',
                        'admin_product_status',
                        'admin_shipping',
                        'admin_refunds',
                        'admin_documents',
                        'admin_export',
                        'admin_payouts',
                        'admin_tax_shipping',
                        'admin_seller_applications',
                        'admin_ads_campaigns',
                        'admin_website_requests',
                    ],
                    'settings' => ['read', 'update'],
                    'uploads' => ['list', 'create', 'rename', 'delete'],
                    'notifications' => ['list', 'unread_count', 'mark_read', 'mark_all_read', 'delete', 'realtime'],
                ],
                'catalogs' => [
                    'sport_map' => [
                        'sport_types' => config('sport_map.sport_types', []),
                        'place_types' => config('sport_map.place_types', []),
                    ],
                ],
                'broadcasting' => [
                    'private_channels' => [
                        'chat.conversation.{conversation}',
                        'chat.user.{userId}',
                        'notifications.user.{userId}',
                        'event.{event}.team',
                        'event.{event}.club',
                    ],
                    'events' => [
                        'chat.typing',
                        'message.sent',
                        'chat.conversation.updated',
                    ],
                ],
            ],
        ]);
    }
}
