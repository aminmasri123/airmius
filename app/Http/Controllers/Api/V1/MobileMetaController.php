<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\AirmiusRoleMatrix;
use App\Support\AiGovernanceCatalog;
use App\Support\AiSafetyReadinessReport;
use App\Support\Api\V1\ApiContract;
use App\Support\BulkChangeSafetyContract;
use App\Support\ClubContextSwitchContract;
use App\Support\ClubContinuityReadinessCatalog;
use App\Support\ClubFinanceFacilitiesReadinessCatalog;
use App\Support\ClubMemberFamilyCalendarReadinessCatalog;
use App\Support\ClubModuleSettingsContract;
use App\Support\ClubOperationsReadinessCatalog;
use App\Support\ClubPublicNetworkReadinessContract;
use App\Support\ClubSoftwareTariffLifecycleContract;
use App\Support\ClubSportWorkforceReadinessCatalog;
use App\Support\ClubSupportAccessContract;
use App\Support\CriticalJourneyRegistry;
use App\Support\FinanceImplementationInventory;
use App\Support\IntegrationCatalog;
use App\Support\MultiClubPlatformIsolationReport;
use App\Support\PlatformModuleRegistry;
use App\Support\ReleaseSeparationReadinessReport;
use App\Support\ReportingReadinessContract;
use App\Support\WorkManagementCatalog;

class MobileMetaController extends Controller
{
    public function __invoke()
    {
        return response()->json([
            'data' => [
                'api_version' => ApiContract::VERSION,
                'contract_version' => ApiContract::CONTRACT_VERSION,
                'minimum_client_version' => ApiContract::MIN_CLIENT_VERSION,
                'minimum_app_version' => ApiContract::MIN_CLIENT_VERSION,
                'auth' => 'sanctum_bearer_token',
                'feature_flags' => [
                    'mvp_surface' => true,
                    'flutter_dev_suites' => false,
                    'offline_queue' => true,
                    'upload_retry' => true,
                    'push_devices' => true,
                    'deep_links' => true,
                    'chat_realtime_polling' => true,
                    'secure_token_storage' => true,
                    'member_card_checkin' => true,
                    'member_card_qr' => true,
                    'training_exercise_library' => true,
                    'training_availability' => true,
                    'training_plan_templates' => true,
                    'sport_integrations' => true,
                    'global_search_typed' => true,
                ],
                'supported_locales' => ['de', 'en', 'fr', 'ar'],
                'rtl_locales' => ['ar'],
                'role_matrix' => AirmiusRoleMatrix::forClient(),
                'modules' => PlatformModuleRegistry::forClient(),
                'critical_journeys' => CriticalJourneyRegistry::forClient(),
                'capabilities' => [
                    'profile' => ['user_card'],
                    'search' => [
                        'people',
                        'clubs',
                        'teams',
                        'events',
                        'courses',
                        'products',
                        'files',
                    ],
                    'feed' => ['list', 'create', 'stories', 'story_upload', 'story_reactions'],
                    'clubs' => [
                        'list',
                        'show',
                        'membership_requests',
                        'manager_members',
                        'manager_billing',
                        'member_card',
                        'member_card_rotate',
                        'member_card_verify',
                        'member_card_qr_scan',
                    ],
                    'teams' => ['list', 'show'],
                    'chat' => ['conversations', 'create_conversation', 'messages', 'send_message', 'typing', 'read', 'reactions'],
                    'events' => ['list', 'show', 'create', 'update', 'cancel', 'delete', 'participation', 'attendance'],
                    'training' => [
                        'plans',
                        'logs',
                        'log_create_stepper',
                        'compact_training_type_picker',
                        'gym_exercise_set_stepper',
                        'exercise_library',
                        'training_analytics',
                        'availability_status',
                        'plan_templates',
                    ],
                    'nutrition' => [
                        'meal_logging',
                        'macro_summary',
                        'goal_targets',
                        'recipe_suggestions',
                        'food_search_open_food_facts',
                        'water_tracking',
                        'water_quick_add',
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
                    'sport_integrations' => [
                        'provider_status',
                        'connected_accounts',
                        'request_provider_connection',
                        'sync_account',
                        'disconnect_account',
                        'normalized_activity_import',
                        'gps_samples',
                        'gpx_contract',
                    ],
                    'subscriptions' => ['plans', 'overview', 'bank_transfer_checkout', 'stripe_redirect_checkout', 'paypal_redirect_checkout', 'cancel', 'renew'],
                    'recruiting' => ['public_jobs', 'submit_interest', 'pipeline', 'status_update', 'data_erasure'],
                    'agency' => ['public_request', 'privacy_consent', 'retention_lifecycle', 'self_service_erasure'],
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
                    'notifications' => ['list', 'unread_count', 'mark_read', 'mark_unread', 'mark_all_read', 'delete', 'realtime'],
                    'support' => ['contact', 'tickets', 'contextual_help', 'privacy_safe_diagnostics'],
                ],
                'support' => [
                    'contextual_help' => [
                        [
                            'key' => 'club_membership_member_view',
                            'module' => 'clubs',
                            'audience' => ['member'],
                            'article_key' => 'support.articles.club_membership_member_view',
                            'route_hint' => '/clubs/{club}',
                        ],
                        [
                            'key' => 'club_membership_management',
                            'module' => 'clubs',
                            'audience' => ['owner', 'admin', 'membership_manager'],
                            'article_key' => 'support.articles.club_membership_management',
                            'route_hint' => '/clubs/{club}/membership',
                            'required_capability' => 'manager_members',
                        ],
                        [
                            'key' => 'billing_and_reports',
                            'module' => 'clubs',
                            'audience' => ['owner', 'treasurer'],
                            'article_key' => 'support.articles.billing_and_reports',
                            'route_hint' => '/clubs/{club}/reports',
                            'required_capability' => 'manager_billing',
                        ],
                    ],
                    'diagnostics' => [
                        'endpoint' => '/api/v1/support/contact',
                        'allowed_fields' => [
                            'app_version',
                            'build_number',
                            'platform',
                            'locale',
                            'timezone',
                            'module',
                            'screen_key',
                            'feature_flags',
                            'capability_keys',
                            'last_error_code',
                        ],
                        'forbidden_fields' => [
                            'name',
                            'email',
                            'phone',
                            'token',
                            'password',
                            'authorization',
                            'raw_url',
                            'request_body',
                            'response_body',
                            'device_id',
                            'precise_location',
                            'free_text_dump',
                        ],
                        'stores_device_identifiers' => false,
                        'stores_credentials' => false,
                        'requires_user_submitted_message' => true,
                    ],
                ],
                'catalogs' => [
                    'finance_inventory' => FinanceImplementationInventory::forClient(),
                    'reporting_readiness' => ReportingReadinessContract::forClient(),
                    'integrations' => IntegrationCatalog::forClient(),
                    'work_management' => WorkManagementCatalog::forClient(),
                    'bulk_change_safety' => BulkChangeSafetyContract::forClient(),
                    'ai_governance' => AiGovernanceCatalog::forClient(),
                    'ai_safety_readiness' => AiSafetyReadinessReport::make(),
                    'club_module_settings' => ClubModuleSettingsContract::forClient(),
                    'club_continuity_readiness' => ClubContinuityReadinessCatalog::forClient(),
                    'club_finance_facilities_readiness' => ClubFinanceFacilitiesReadinessCatalog::forClient(),
                    'club_member_family_calendar_readiness' => ClubMemberFamilyCalendarReadinessCatalog::forClient(),
                    'club_operations_readiness' => ClubOperationsReadinessCatalog::forClient(),
                    'club_context_switch' => ClubContextSwitchContract::forClient(),
                    'club_software_tariff_lifecycle' => ClubSoftwareTariffLifecycleContract::forClient(),
                    'club_sport_workforce_readiness' => ClubSportWorkforceReadinessCatalog::forClient(),
                    'club_public_network_readiness' => ClubPublicNetworkReadinessContract::forClient(),
                    'club_support_access' => ClubSupportAccessContract::forClient(),
                    'multi_club_isolation' => MultiClubPlatformIsolationReport::make(),
                    'release_separation' => ReleaseSeparationReadinessReport::make(),
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
                                'rate_limit_note' => 'Search nur gezielt auslösen, nicht bei jedem Tastendruck.',
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
