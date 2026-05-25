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
                    'clubs' => [
                        'list',
                        'show',
                        'membership_requests',
                        'manager_members',
                        'manager_billing',
                    ],
                    'teams' => ['list', 'show'],
                    'chat' => ['conversations', 'messages', 'send_message', 'typing'],
                    'events' => ['list', 'show'],
                    'training' => [
                        'plans',
                        'logs',
                        'log_create_stepper',
                        'compact_training_type_picker',
                        'gym_exercise_set_stepper',
                    ],
                    'nutrition' => [
                        'meal_logging',
                        'macro_summary',
                        'goal_targets',
                        'water_tracking',
                        'water_quick_add',
                        'recipe_suggestions',
                        'food_search_open_food_facts',
                        'training_context',
                        'barcode_ready',
                        'photo_estimate_ai',
                    ],
                    'sport_map' => [
                        'route_planning',
                        'route_routing_osrm_optional',
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
                    'training' => [
                        'log_create_flow' => [
                            'steps' => [
                                ['key' => 'select_training', 'label_key' => 'training.steps.select_training'],
                                ['key' => 'document', 'label_key' => 'training.steps.document'],
                                ['key' => 'finish', 'label_key' => 'training.steps.finish'],
                            ],
                            'finish_fields' => ['intensity', 'calories', 'wellness'],
                        ],
                        'training_types' => [
                            [
                                'key' => 'gym',
                                'label_key' => 'training.types.gym',
                                'sport_type' => 'gym',
                                'mode' => 'sets',
                                'document_flow' => 'exercise_set_stepper',
                                'fields' => ['sets', 'reps', 'weight_kg', 'duration_minutes', 'notes'],
                                'theme' => 'sky',
                            ],
                            [
                                'key' => 'run_interval',
                                'label_key' => 'training.types.run_interval',
                                'sport_type' => 'running',
                                'mode' => 'rows',
                                'document_flow' => 'rows',
                                'fields' => ['reps', 'distance_km', 'duration_minutes', 'notes'],
                                'theme' => 'amber',
                            ],
                            [
                                'key' => 'long_run',
                                'label_key' => 'training.types.long_run',
                                'sport_type' => 'running',
                                'mode' => 'rows',
                                'document_flow' => 'rows',
                                'fields' => ['distance_km', 'duration_minutes', 'notes'],
                                'theme' => 'emerald',
                            ],
                            [
                                'key' => 'swim',
                                'label_key' => 'training.types.swim',
                                'sport_type' => 'swimming',
                                'mode' => 'rows',
                                'document_flow' => 'rows',
                                'fields' => ['sets', 'reps', 'distance_km', 'duration_minutes', 'notes'],
                                'theme' => 'cyan',
                            ],
                            [
                                'key' => 'football',
                                'label_key' => 'training.types.football',
                                'sport_type' => 'football',
                                'mode' => 'rows',
                                'document_flow' => 'rows',
                                'fields' => ['duration_minutes', 'distance_km', 'notes'],
                                'theme' => 'lime',
                            ],
                            [
                                'key' => 'cycling',
                                'label_key' => 'training.types.cycling',
                                'sport_type' => 'cycling',
                                'mode' => 'rows',
                                'document_flow' => 'rows',
                                'fields' => ['distance_km', 'duration_minutes', 'notes'],
                                'theme' => 'fuchsia',
                            ],
                            [
                                'key' => 'generic',
                                'label_key' => 'training.types.generic',
                                'sport_type' => 'generic',
                                'mode' => 'rows',
                                'document_flow' => 'rows',
                                'fields' => ['sets', 'reps', 'weight_kg', 'duration_minutes', 'distance_km', 'notes'],
                                'theme' => 'indigo',
                            ],
                        ],
                    ],
                    'nutrition' => [
                        'meal_types' => config('nutrition.meal_types', []),
                        'goal_types' => config('nutrition.goal_types', []),
                        'diet_styles' => config('nutrition.diet_styles', []),
                        'source_types' => config('nutrition.source_types', []),
                        'water_goal_modes' => ['manual', 'auto'],
                        'water_features' => [
                            'daily_target_ml' => 2500,
                            'weight_factor_ml_per_kg' => 33,
                            'adaptive_training_bonus' => true,
                            'quick_amounts_ml' => [150, 250, 350, 500, 750],
                        ],
                        'ai_features' => [
                            'nutrition_image_analysis' => [
                                'endpoint' => '/api/v1/nutrition/ai/meal-image',
                                'requires_consent' => true,
                                'requires_user_confirmation' => true,
                                'privacy' => [
                                    'exif_removed' => true,
                                    'image_resized' => true,
                                    'store_uploads' => (bool) config('airmius_ai.privacy.store_uploads', false),
                                ],
                            ],
                        ],
                        'recipes' => config('nutrition.recipes', []),
                        'external_sources' => [
                            [
                                'key' => 'open_food_facts',
                                'label' => 'Open Food Facts',
                                'read_only' => true,
                                'requires_api_key' => false,
                                'rate_limit_note' => 'Search nur gezielt ausloesen, nicht bei jedem Tastendruck.',
                            ],
                        ],
                    ],
                    'sport_map' => [
                        'sport_types' => config('sport_map.sport_types', []),
                        'place_types' => config('sport_map.place_types', []),
                        'map' => config('sport_map.map', []),
                        'routing' => [
                            'provider' => config('sport_map.routing.provider', 'local'),
                            'profiles' => config('sport_map.routing.profiles', []),
                        ],
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
