import '../models/module_definition.dart';

class AirmiusMvpSurface {
  static const showDeveloperSuites = bool.fromEnvironment(
    'AIRMIUS_SHOW_DEV_SUITES',
    defaultValue: false,
  );

  static const mvpModuleTitles = <String>{
    'Arbeitsbereiche',
    'Rollen & Rechte',
    'Vereins-Cockpit',
    'Vereine & Teams',
    'Teams',
    'Sportarten',
    'Sport-Apps & Gesundheitsdaten',
    'Feed',
    'Nachrichten',
    'Events & Training',
    'Trainingsplanung',
    'Trainer-Cockpit',
    'Ernährung',
    'Sportkarte',
    'Sport-Matching',
    'Freunde',
    'Fahrgemeinschaften',
    'Badges',
    'Gamification-Regeln',
    'Altersfreigaben',
    'Kurse',
    'Sponsoren',
    'Recruiting',
    'Medienrichtlinien',
    'Blog & Medien',
    'Nutzer',
    'Marketplace',
    'Commerce',
    'Admin',
    'Abos & Rechnungen',
    'Outfit-Abos',
    'Eltern & Jugendschutz',
    'Dateien',
    'Einstellungen',
  };

  static const mvpDashboardWidgetKeys = <String>{
    'training',
    'focus',
    'events',
    'files',
    'notifications',
  };

  static const mvpOperationTitles = <String>{
    'Konto & Sicherheit',
    'Globale Suche',
    'Inbox & Chat',
    'API Connection',
    'Mitgliedschaft',
    'Teams',
    'Dateien',
    'Feed & Stories',
    'Training',
    'Legal & Support',
  };

  static bool isModuleVisible(ModuleDefinition module) =>
      isModuleTitleVisible(module.title);

  static bool isModuleTitleVisible(String title) =>
      showDeveloperSuites || mvpModuleTitles.contains(title);

  static bool isDashboardWidgetVisible(String key) =>
      showDeveloperSuites || mvpDashboardWidgetKeys.contains(key);

  static bool isOperationVisible(String title) =>
      showDeveloperSuites || mvpOperationTitles.contains(title);

  static bool get isOperationsHubVisible => showDeveloperSuites;
}
