<?php

namespace App\Support;

final class CommunicationInteractionReadinessRegistry
{
    public const CONTRACT = 'communication-interaction-readiness.v1';

    /** @return array<string, mixed> */
    public static function definitions(): array
    {
        return [
            'contract' => self::CONTRACT,
            'task' => 'T037c',
            'scope' => [
                'direct_chat',
                'group_chat',
                'chat_attachments',
                'club_surveys',
                'event_decisions',
                'required_confirmations',
            ],
            'capabilities' => [
                'direct_chat' => self::capability(
                    routes: ['api.v1.chat.conversations.store', 'auth.conversations.store'],
                    artifacts: [
                        'app/Http/Controllers/Api/V1/ChatController.php',
                        'app/Http/Controllers/ConversationController.php',
                        'app/Models/Conversation.php',
                        'tests/Feature/MobileChatMessageApiTest.php',
                    ],
                    moderation: ['message_auto_flagging', 'removed_message_hidden_from_payloads', 'report_visible_messages_only'],
                    retention: ['participant_hide', 'delete_unread_own_message', 'account_erasure_replaces_personal_chat_content'],
                ),
                'group_chat' => self::capability(
                    routes: [
                        'api.v1.chat.conversations.members.store',
                        'api.v1.chat.conversation-invitations.accept',
                        'api.v1.chat.conversations.owner.update',
                    ],
                    artifacts: [
                        'app/Http/Controllers/Api/V1/ChatController.php',
                        'app/Http/Controllers/ConversationController.php',
                        'tests/Feature/ChatSecurityTest.php',
                    ],
                    moderation: ['owner_profile_management', 'invitation_audit_system_messages', 'historical_join_boundary'],
                    retention: ['joined_at_visibility_boundary', 'removed_member_access_revocation'],
                ),
                'chat_attachments' => self::capability(
                    routes: ['api.v1.chat.messages.store', 'auth.messages.store'],
                    artifacts: [
                        'app/Services/ChatService.php',
                        'app/Http/Resources/Api/V1/MessageResource.php',
                        'app/Models/MessageAttachment.php',
                        'mobile/airmius_mobile/lib/core/airmius_chat_attachment_service.dart',
                    ],
                    moderation: ['attachment_message_flag_context', 'preview_requires_visible_membership'],
                    retention: ['delete_unread_message_removes_attachment_file', 'file_preview_policy_bound_to_chat_visibility'],
                ),
                'club_surveys' => self::capability(
                    routes: [
                        'api.v1.clubs.surveys.index',
                        'api.v1.clubs.surveys.store',
                        'api.v1.clubs.surveys.vote',
                        'api.v1.clubs.surveys.close',
                    ],
                    artifacts: [
                        'app/Http/Controllers/Api/V1/ClubSurveyController.php',
                        'tests/Feature/ClubSurveyApiTest.php',
                        'mobile/airmius_mobile/lib/screens/club_survey_screen.dart',
                    ],
                    moderation: ['club_permission_guarded_create_update_close_delete'],
                    retention: ['votes_preserved_after_close', 'voted_surveys_not_mutated_or_deleted_by_editor'],
                ),
                'event_decisions' => self::capability(
                    routes: [
                        'api.v1.events.decisions.index',
                        'api.v1.events.decisions.store',
                        'api.v1.events.decisions.vote',
                        'api.v1.events.decisions.close',
                    ],
                    artifacts: [
                        'app/Http/Controllers/Api/V1/EventCompetitivenessController.php',
                        'tests/Feature/EventParticipationLifecycleTest.php',
                        'mobile/airmius_mobile/lib/screens/training_event_detail_screen.dart',
                    ],
                    moderation: ['event_visibility_and_manager_policy_before_vote_or_close'],
                    retention: ['decision_votes_bound_to_event_record', 'closed_decisions_remain_readable_for_event_audit'],
                ),
                'required_confirmations' => self::capability(
                    routes: [
                        'api.v1.clubs.policy-documents.index',
                        'api.v1.events.update',
                        'api.v1.guardian.consent.show',
                    ],
                    artifacts: [
                        'app/Http/Controllers/Api/V1/ClubPolicyDocumentController.php',
                        'app/Http/Controllers/Api/V1/EventCompetitivenessController.php',
                        'app/Http/Controllers/Api/V1/GuardianController.php',
                        'tests/Feature/DashboardDailyFlowTest.php',
                    ],
                    moderation: ['guardian_consent_blocks_minor_communication_until_resolved'],
                    retention: ['document_version_confirmation_snapshot', 'participant_response_deadline_audit'],
                ),
            ],
            'safety_controls' => [
                'minor_pending_consent_blocks_chat_index_and_creation' => true,
                'direct_messages_honor_recipient_privacy' => true,
                'club_context_requires_all_participants_active_in_same_club' => true,
                'group_participants_require_friendship_and_dm_permission' => true,
                'historical_group_messages_hidden_before_joined_at' => true,
                'removed_messages_are_not_returned_or_deep_linked' => true,
            ],
            'data_policy' => [
                'registry_contains_personal_data' => false,
                'registry_contains_free_text_messages' => false,
                'registry_contains_file_paths' => false,
                'notifications_avoid_recipient_lists' => true,
                'external_legal_retention_approval_required' => true,
                'productive_erasure_approval_required' => true,
            ],
            'open_gates' => [
                'external_delivery_provider_acceptance',
                'legal_retention_schedule_approval',
                'productive_erasure_runbook_approval',
                'human_moderation_sla_acceptance',
            ],
            'decision' => 'local-contract-ready-external-gates-open',
        ];
    }

    /** @return array<string, mixed> */
    private static function capability(array $routes, array $artifacts, array $moderation, array $retention): array
    {
        return [
            'routes' => $routes,
            'artifacts' => $artifacts,
            'moderation_rules' => $moderation,
            'retention_rules' => $retention,
        ];
    }
}
