import 'airmius_api_models.dart';

/// Central source of truth for role-aware module navigation.
///
/// The API remains authoritative for every read and write operation. This
/// policy keeps the mobile navigation aligned with those server permissions so
/// users do not see operational areas they cannot use.
final class AirmiusModuleAccess {
  const AirmiusModuleAccess._();

  static const personalModuleTitles = <String>{
    'Sportarten',
    'Sport-Apps & Gesundheitsdaten',
    'Feed',
    'Ernährung',
    'Sportkarte',
    'Sport-Matching',
    'Challenges',
    'Freunde',
    'Nachrichten',
    'Fahrgemeinschaften',
    'Badges',
    'Altersfreigaben',
    'Kurse',
    'Marketplace',
    'Abos & Rechnungen',
    'Outfit-Abos',
    'Einstellungen',
  };

  static const _platformRoles = <String>{
    'super_admin',
    'admin',
    'system_admin',
  };

  static const _trainerRoles = <String>{
    'coach',
    'assistant_coach',
    'performance_coach',
    'fitness_coach',
    'team_manager',
    'captain',
    'trainer',
  };

  static const _trainerMembershipRoles = <String>{
    'coach',
    'trainer',
    'captain',
    'admin',
    'manager',
  };

  static const _clubRoles = <String>{
    'club_owner',
    'club_admin',
    'club_manager',
    'academy_manager',
    'financial_controller',
  };

  static bool canOpen(AirmiusUser? user, String moduleTitle) {
    if (user == null) return false;
    if (personalModuleTitles.contains(moduleTitle)) return true;

    return switch (moduleTitle) {
      'Arbeitsbereiche' => _hasWorkspace(user),
      'Vereine & Teams' || 'Teams' => true,
      'Events & Training' => _hasAnyPermission(user, const {
        'event.join',
        'event.create',
        'event.update',
        'event.delete',
        'training.view',
        'training.create',
        'training.edit',
      }),
      'Trainingsplanung' => canOpenTrainerCockpit(user),
      'Trainer-Cockpit' => canOpenTrainerCockpit(user),
      'Vereins-Cockpit' => canOpenClubCockpit(user),
      'Dateien' => _hasAnyPermission(user, const {
        'file.view',
        'file.upload',
        'file.delete',
      }),
      'Rollen & Rechte' => canOpenPlatformAdmin(user),
      'Gamification-Regeln' => canOpenPlatformAdmin(user),
      'Commerce' => _adminTwoFactorSatisfied(user) && _canManageCommerce(user),
      'Sponsoren' => _adminTwoFactorSatisfied(user) && _canManageSponsors(user),
      'Recruiting' => user.can('club.jobs.manage'),
      'Medienrichtlinien' =>
        _adminTwoFactorSatisfied(user) && _canManageMedia(user),
      'Blog & Medien' =>
        _adminTwoFactorSatisfied(user) && _canManageEditorial(user),
      'Nutzer' => canOpenPlatformAdmin(user),
      'Eltern & Jugendschutz' => canOpenGuardianCenter(user),
      'Admin' => canOpenAdmin(user),
      _ => false,
    };
  }

  static bool canOpenGuardianCenter(AirmiusUser? user) {
    if (user == null) return false;
    return user.hasAnyRole(const {'guardian', 'parent'}) ||
        _hasAnyPermission(user, const {
          'guardians.children.view',
          'guardians.children.manage',
        });
  }

  static bool canOpenTrainerCockpit(AirmiusUser? user) {
    if (user == null) return false;
    return user.hasAnyRole(_trainerRoles) ||
        user.teams.any(
          (team) => _trainerMembershipRoles.contains(
            team.membershipRole?.toLowerCase(),
          ),
        );
  }

  static bool canOpenClubCockpit(AirmiusUser? user) {
    if (user == null) return false;
    final platformAdmin =
        user.hasAnyRole(_platformRoles) && user.twoFactorEnabled;
    return platformAdmin ||
        user.hasAnyRole(_clubRoles) ||
        _hasAnyPermission(user, const {'club-cockpit.view', 'cockpit.view'}) ||
        user.clubs.any((club) => club.canManage);
  }

  static bool canOpenAdmin(AirmiusUser? user) {
    if (user == null || !_adminTwoFactorSatisfied(user)) return false;
    return _hasAnyPermission(user, const {
          'users.view',
          'users.edit',
          'users.assign_roles',
          'user.manage',
          'roles.manage',
          'marketplace.manage',
          'commerce.orders.manage',
          'outfit-subscriptions.manage',
          'blog.manage',
          'sponsors.view',
          'community.moderate',
          'support.tickets',
          'system.manage',
        }) ||
        user.hasAnyRole(const {
          'marketplace_manager',
          'outfit_subscription_manager',
          'sponsor_manager',
          'redaktor',
          'media_manager',
          'support',
        });
  }

  /// Platform administration requires the dedicated system-management
  /// capability. Specialist admin roles can use their own admin surfaces but
  /// must not enter the platform-wide user/role administration pages.
  static bool canOpenPlatformAdmin(AirmiusUser? user) {
    return canOpenAdmin(user) && user?.can('system.manage') == true;
  }

  static bool _hasWorkspace(AirmiusUser user) {
    return user.clubs.isNotEmpty ||
        user.teams.isNotEmpty ||
        canOpenTrainerCockpit(user) ||
        canOpenClubCockpit(user) ||
        canOpenGuardianCenter(user) ||
        canOpenAdmin(user);
  }

  static bool _canManageCommerce(AirmiusUser user) {
    return _hasAnyPermission(user, const {
          'marketplace.manage',
          'commerce.orders.manage',
        }) ||
        user.hasAnyRole(const {
          'marketplace_manager',
          'outfit_subscription_manager',
          'sponsor',
          'sponsor_manager',
        });
  }

  static bool _canManageSponsors(AirmiusUser user) {
    return _hasAnyPermission(user, const {
          'sponsors.view',
          'finance.edit',
          'sponsor.workspace.view',
        }) ||
        user.hasAnyRole(const {'sponsor', 'sponsor_manager'});
  }

  static bool _canManageMedia(AirmiusUser user) {
    return _hasAnyPermission(user, const {
          'blog.view',
          'blog.manage',
          'content.create',
          'content.edit',
          'media.upload',
          'seo.manage',
        }) ||
        user.hasAnyRole(const {'redaktor', 'media_manager'});
  }

  static bool _canManageEditorial(AirmiusUser user) {
    return _hasAnyPermission(user, const {
          'blog.view',
          'blog.create',
          'blog.update',
          'blog.manage',
          'content.create',
          'content.edit',
        }) ||
        user.hasAnyRole(const {'redaktor', 'media_manager'});
  }

  static bool _adminTwoFactorSatisfied(AirmiusUser user) {
    return !user.hasAnyRole(_platformRoles) || user.twoFactorEnabled;
  }

  static bool _hasAnyPermission(AirmiusUser user, Set<String> permissions) {
    return user.permissions.any(permissions.contains);
  }
}
