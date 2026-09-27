import 'airmius_api_models.dart';
import 'airmius_module_access.dart';

enum AirmiusPersona { athlete, coach, club, sponsor, multiWorkspace }

final class AirmiusPersonaResolver {
  const AirmiusPersonaResolver._();

  static const _sponsorRoles = {'sponsor', 'sponsor_manager'};

  static AirmiusPersona primary(AirmiusUser user) {
    final coach = AirmiusModuleAccess.canOpenTrainerCockpit(user);
    final club = AirmiusModuleAccess.canOpenClubCockpit(user);
    final sponsor = _canOpenSponsorWorkspace(user);
    final operationalCount = [
      coach,
      club,
      sponsor,
    ].where((value) => value).length;

    if (operationalCount > 1) return AirmiusPersona.multiWorkspace;
    if (club) return AirmiusPersona.club;
    if (coach) return AirmiusPersona.coach;
    if (sponsor) return AirmiusPersona.sponsor;
    return AirmiusPersona.athlete;
  }

  static String? homeModuleTitle(AirmiusUser user) => switch (primary(user)) {
    AirmiusPersona.athlete => null,
    AirmiusPersona.coach => 'Trainer-Cockpit',
    AirmiusPersona.club => 'Vereins-Cockpit',
    AirmiusPersona.sponsor => 'Sponsoren',
    AirmiusPersona.multiWorkspace =>
      AirmiusModuleAccess.canOpenClubCockpit(user)
          ? 'Vereins-Cockpit'
          : AirmiusModuleAccess.canOpenTrainerCockpit(user)
          ? 'Trainer-Cockpit'
          : 'Arbeitsbereiche',
  };

  static Set<String> navigationModules(AirmiusUser user) {
    final modules = <String>{
      'Arbeitsbereiche',
      'Feed',
      'Nachrichten',
      'Einstellungen',
    };
    // Persona selects the home/workspace experience. It must not hide
    // personal sports and community features from club administrators,
    // coaches or platform administrators.
    modules.addAll(AirmiusModuleAccess.personalModuleTitles);
    final persona = primary(user);
    final coach = AirmiusModuleAccess.canOpenTrainerCockpit(user);
    final club = AirmiusModuleAccess.canOpenClubCockpit(user);
    final sponsor = _canOpenSponsorWorkspace(user);
    final platformAdmin = user.hasAnyRole(const {
      'super_admin',
      'admin',
      'system_admin',
    });
    final athlete =
        persona == AirmiusPersona.athlete ||
        user.hasAnyRole(const {
          'player',
          'youth_player',
          'minor_player',
          'guest_player',
          'captain',
        });

    // Platform admins are also regular app users. Keep the personal sports
    // modules visible in the drawer instead of hiding them because their
    // primary persona resolves to club/admin.
    if (athlete || platformAdmin) {
      modules.addAll(const {
        'Vereine & Teams',
        'Teams',
        'Sportarten',
        'Sport-Apps & Gesundheitsdaten',
        'Events & Training',
        'Ernährung',
        'Sportkarte',
        'Sport-Matching',
        'Freunde',
        'Fahrgemeinschaften',
        'Badges',
        'Dateien',
        'Kurse',
        'Marketplace',
        'Abos & Rechnungen',
        'Outfit-Abos',
      });
    }
    if (coach) {
      modules.addAll(const {
        'Trainer-Cockpit',
        'Vereine & Teams',
        'Teams',
        'Events & Training',
        'Trainingsplanung',
        'Sportarten',
        'Sport-Apps & Gesundheitsdaten',
        'Dateien',
        'Kurse',
      });
    }
    if (club) {
      modules.addAll(const {
        'Vereins-Cockpit',
        'Vereine & Teams',
        'Teams',
        'Events & Training',
        'Feed',
        'Challenges',
        'Dateien',
        'Kurse',
        'Marketplace',
        'Blog & Medien',
        'Sponsoren',
      });
    }
    if (sponsor) {
      modules.addAll(const {'Sponsoren', 'Commerce', 'Marketplace', 'Dateien'});
    }
    if (AirmiusModuleAccess.canOpenGuardianCenter(user)) {
      modules.add('Eltern & Jugendschutz');
    }
    if (AirmiusModuleAccess.canOpenAdmin(user)) {
      modules.addAll(const {
        'Admin',
        'Rollen & Rechte',
        'Gamification-Regeln',
        'Commerce',
        'Sponsoren',
        'Medienrichtlinien',
        'Blog & Medien',
        'Nutzer',
      });
    }
    return modules;
  }

  static bool _canOpenSponsorWorkspace(AirmiusUser user) =>
      user.hasAnyRole(_sponsorRoles) ||
      user.can('sponsor.workspace.view') ||
      user.can('system.manage');
}
