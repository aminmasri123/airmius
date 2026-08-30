import 'package:flutter/material.dart';

import '../core/airmius_preferences.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_mvp_surface.dart';
import '../models/club_summary.dart';
import '../screens/admin_center_screen.dart';
import '../screens/badges_center_screen.dart';
import '../screens/blog_media_center_screen.dart';
import '../screens/carpool_center_screen.dart';
import '../screens/challenges_screen.dart';
import '../screens/club_cockpit_screen.dart';
import '../screens/clubs_screen.dart';
import '../screens/commerce_center_screen.dart';
import '../screens/conversations_center_screen.dart';
import '../screens/event_management_screen.dart';
import '../screens/feed_center_screen.dart';
import '../screens/file_manager_screen.dart';
import '../screens/friends_social_graph_screen.dart';
import '../screens/gamification_rules_screen.dart';
import '../screens/guardian_center_screen.dart';
import '../screens/learning_screen.dart';
import '../screens/marketplace_screen.dart';
import '../screens/maturity_center_screen.dart';
import '../screens/media_guidelines_screen.dart';
import '../screens/nutrition_center_screen.dart';
import '../screens/outfit_subscription_center_screen.dart';
import '../screens/recruiting_pipeline_screen.dart';
import '../screens/roles_permissions_screen.dart';
import '../screens/settings_center_screen.dart';
import '../screens/sponsor_cockpit_screen.dart';
import '../screens/sponsors_center_screen.dart';
import '../screens/sport_integrations_screen.dart';
import '../screens/sport_map_center_screen.dart';
import '../screens/sport_matching_screen.dart';
import '../screens/sports_center_screen.dart';
import '../screens/subscription_center_screen.dart';
import '../screens/support_helpdesk_screen.dart';
import '../screens/teams_center_screen.dart';
import '../screens/trainer_cockpit_screen.dart';
import '../screens/training_plans_logs_screen.dart';
import '../screens/users_center_screen.dart';
import '../screens/workspace_center_screen.dart';

/// Single production routing contract for every module shown in the shell.
///
/// The shell, persona home and module launcher must resolve to the same live
/// API-backed screen. Keeping this map centralized prevents a module from
/// silently falling back to a prototype or a different workspace.
final class AirmiusModuleDestination {
  const AirmiusModuleDestination._();

  static const Set<String> supportedTitles = AirmiusMvpSurface.mvpModuleTitles;

  static const Map<String, String> searchableModuleTitles = {
    'workspaces': 'Arbeitsbereiche',
    'training': 'Trainingsplanung',
    'events': 'Events & Training',
    'nutrition': 'Ernährung',
    'sport_map': 'Sportkarte',
    'sport_matching': 'Sport-Matching',
    'challenges': 'Challenges',
    'friends': 'Freunde',
    'feed': 'Feed',
    'messages': 'Nachrichten',
    'files': 'Dateien',
    'courses': 'Kurse',
    'marketplace': 'Marketplace',
    'blog': 'Blog & Medien',
    'outfit': 'Outfit-Abos',
    'badges': 'Badges',
    'maturity': 'Altersfreigaben',
    'settings': 'Einstellungen',
    'clubs': 'Vereine & Teams',
    'teams': 'Teams',
    'club_cockpit': 'Vereins-Cockpit',
    'trainer_cockpit': 'Trainer-Cockpit',
    'sponsor': 'Sponsoren',
    'recruiting': 'Recruiting',
    'users': 'Nutzer',
    'roles': 'Rollen & Rechte',
    'commerce': 'Commerce',
  };

  static Widget? resolveKey(
    BuildContext context,
    String key, {
    Set<int> requestedClubIds = const {},
    ValueChanged<ClubSummary>? onRequestClub,
    ValueChanged<ClubSummary>? onWithdrawClub,
    AirmiusPreferences? preferences,
    VoidCallback? onFooterNavigationChanged,
  }) {
    if (key == 'support') return const SupportHelpdeskScreen();

    final title = searchableModuleTitles[key];
    if (title == null) return null;

    return resolve(
      context,
      title,
      requestedClubIds: requestedClubIds,
      onRequestClub: onRequestClub,
      onWithdrawClub: onWithdrawClub,
      preferences: preferences,
      onFooterNavigationChanged: onFooterNavigationChanged,
    );
  }

  static Widget? resolve(
    BuildContext context,
    String title, {
    Set<int> requestedClubIds = const {},
    ValueChanged<ClubSummary>? onRequestClub,
    ValueChanged<ClubSummary>? onWithdrawClub,
    AirmiusPreferences? preferences,
    VoidCallback? onFooterNavigationChanged,
  }) {
    final user = AirmiusServicesScope.of(context).authState.user;

    return switch (title) {
      'Arbeitsbereiche' => const WorkspaceCenterScreen(),
      'Rollen & Rechte' => const RolesPermissionsScreen(),
      'Vereins-Cockpit' => const ClubCockpitScreen(),
      'Vereine & Teams' => ClubsScreen(
        requestedClubIds: requestedClubIds,
        onRequestClub: onRequestClub ?? (_) {},
        onWithdrawClub: onWithdrawClub ?? (_) {},
      ),
      'Teams' => const TeamsCenterScreen(),
      'Sportarten' => const SportsCenterScreen(),
      'Sport-Apps & Gesundheitsdaten' => const SportIntegrationsScreen(),
      'Feed' => const FeedCenterScreen(),
      'Nachrichten' => const ConversationsCenterScreen(),
      'Events & Training' => const EventManagementScreen(),
      'Trainingsplanung' => const TrainingPlansLogsScreen(),
      'Trainer-Cockpit' => const TrainerCockpitScreen(),
      'Ernährung' => const NutritionCenterScreen(),
      'Sportkarte' => const SportMapCenterScreen(),
      'Sport-Matching' => const SportMatchingScreen(),
      'Challenges' => const ChallengesScreen(),
      'Freunde' => const FriendsSocialGraphScreen(),
      'Fahrgemeinschaften' => const CarpoolCenterScreen(),
      'Badges' => const BadgesCenterScreen(),
      'Gamification-Regeln' => const GamificationRulesScreen(),
      'Altersfreigaben' => const MaturityCenterScreen(),
      'Kurse' => const LearningScreen(),
      'Sponsoren' =>
        user?.hasAnyRole(const {'sponsor', 'sponsor_manager'}) == true
            ? const SponsorCockpitScreen()
            : const SponsorsCenterScreen(),
      'Recruiting' => const RecruitingPipelineScreen(),
      'Medienrichtlinien' => const MediaGuidelinesScreen(),
      'Blog & Medien' => const BlogMediaCenterScreen(),
      'Nutzer' => const UsersCenterScreen(),
      'Marketplace' => const MarketplaceScreen(),
      'Commerce' => const CommerceCenterScreen(),
      'Admin' => const AdminCenterScreen(),
      'Abos & Rechnungen' => const SubscriptionCenterScreen(),
      'Outfit-Abos' => const OutfitSubscriptionCenterScreen(),
      'Eltern & Jugendschutz' => const GuardianCenterScreen(),
      'Dateien' => const FileManagerScreen(),
      'Einstellungen' => SettingsCenterScreen(
        preferences: preferences,
        onFooterNavigationChanged: onFooterNavigationChanged,
      ),
      _ => null,
    };
  }
}
