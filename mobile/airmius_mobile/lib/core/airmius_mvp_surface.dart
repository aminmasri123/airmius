import '../models/module_definition.dart';

class AirmiusMvpSurface {
  static const showDeveloperSuites = bool.fromEnvironment(
    'AIRMIUS_SHOW_DEV_SUITES',
    defaultValue: false,
  );

  static const mvpModuleTitles = <String>{
    'Vereins-Cockpit',
    'Vereine & Teams',
    'Teams',
    'Feed',
    'Nachrichten',
    'Events & Training',
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

  static bool isModuleVisible(ModuleDefinition module) => isModuleTitleVisible(module.title);

  static bool isModuleTitleVisible(String title) => showDeveloperSuites || mvpModuleTitles.contains(title);

  static bool isDashboardWidgetVisible(String key) => showDeveloperSuites || mvpDashboardWidgetKeys.contains(key);

  static bool isOperationVisible(String title) => showDeveloperSuites || mvpOperationTitles.contains(title);

  static bool get isOperationsHubVisible => showDeveloperSuites;
}
