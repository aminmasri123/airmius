import 'package:flutter/material.dart';

import '../core/airmius_api_models.dart';
import '../core/airmius_module_access.dart';
import '../core/airmius_persona.dart';

enum FooterNavigationDestination {
  training,
  teams,
  feed,
  nutrition,
  profile,
  events,
  drink,
  courses,
  sportMap,
  messages,
  sportMatching,
  carpool,
  files,
  marketplace,
  settings,
  workspaces,
  coachCockpit,
  clubCockpit,
  clubTodos,
  clubCalendar,
  sponsors;

  static const defaultDestinations = <FooterNavigationDestination>[
    training,
    teams,
    feed,
    nutrition,
    profile,
  ];

  static List<FooterNavigationDestination> defaultsFor(AirmiusUser? user) {
    if (user == null) return defaultDestinations;

    return switch (AirmiusPersonaResolver.primary(user)) {
      AirmiusPersona.athlete => defaultDestinations,
      AirmiusPersona.coach => const [
        coachCockpit,
        training,
        teams,
        messages,
        profile,
      ],
      AirmiusPersona.club => const [
        clubCockpit,
        clubTodos,
        clubCalendar,
        teams,
        profile,
      ],
      AirmiusPersona.sponsor => const [
        sponsors,
        marketplace,
        messages,
        settings,
        profile,
      ],
      AirmiusPersona.multiWorkspace => const [
        workspaces,
        messages,
        feed,
        profile,
        settings,
      ],
    };
  }

  static FooterNavigationDestination? fromStorageKey(String value) {
    for (final destination in values) {
      if (destination.storageKey == value) return destination;
    }
    return null;
  }

  String get storageKey => switch (this) {
    training => 'training',
    teams => 'teams',
    feed => 'feed',
    nutrition => 'nutrition',
    profile => 'profile',
    events => 'events',
    drink => 'drink',
    courses => 'courses',
    sportMap => 'sport_map',
    messages => 'messages',
    sportMatching => 'sport_matching',
    carpool => 'carpool',
    files => 'files',
    marketplace => 'marketplace',
    settings => 'settings',
    workspaces => 'workspaces',
    coachCockpit => 'coach_cockpit',
    clubCockpit => 'club_cockpit',
    clubTodos => 'club_todos',
    clubCalendar => 'club_calendar',
    sponsors => 'sponsors',
  };

  String get labelKey => switch (this) {
    training => 'training.nav',
    teams => 'teams',
    feed => 'feed.title',
    nutrition => 'nutrition.title',
    profile => 'profile',
    events => 'footerNav.events',
    drink => 'footerNav.drink',
    courses => 'footerNav.courses',
    sportMap => 'footerNav.sportMap',
    messages => 'nav.messages',
    sportMatching => 'footerNav.sportMatching',
    carpool => 'footerNav.carpool',
    files => 'files',
    marketplace => 'footerNav.marketplace',
    settings => 'nav.settings',
    workspaces => 'footerNav.workspaces',
    coachCockpit => 'footerNav.coachCockpit',
    clubCockpit => 'footerNav.clubCockpit',
    clubTodos => 'footerNav.clubTodos',
    clubCalendar => 'footerNav.clubCalendar',
    sponsors => 'footerNav.sponsors',
  };

  IconData get icon => switch (this) {
    training => Icons.fitness_center_outlined,
    teams => Icons.groups_outlined,
    feed => Icons.dynamic_feed_outlined,
    nutrition => Icons.restaurant_menu_outlined,
    profile => Icons.person_outline,
    events => Icons.event_available_outlined,
    drink => Icons.water_drop_outlined,
    courses => Icons.school_outlined,
    sportMap => Icons.map_outlined,
    messages => Icons.chat_bubble_outline,
    sportMatching => Icons.connect_without_contact_outlined,
    carpool => Icons.directions_car_outlined,
    files => Icons.folder_outlined,
    marketplace => Icons.storefront_outlined,
    settings => Icons.settings_outlined,
    workspaces => Icons.dashboard_customize_outlined,
    coachCockpit => Icons.sports_score_outlined,
    clubCockpit => Icons.apartment_outlined,
    clubTodos => Icons.checklist_outlined,
    clubCalendar => Icons.calendar_month_outlined,
    sponsors => Icons.handshake_outlined,
  };

  IconData get selectedIcon => switch (this) {
    training => Icons.fitness_center,
    teams => Icons.groups,
    feed => Icons.dynamic_feed,
    nutrition => Icons.restaurant_menu,
    profile => Icons.person,
    events => Icons.event_available,
    drink => Icons.water_drop,
    courses => Icons.school,
    sportMap => Icons.map,
    messages => Icons.chat_bubble,
    sportMatching => Icons.connect_without_contact,
    carpool => Icons.directions_car,
    files => Icons.folder,
    marketplace => Icons.storefront,
    settings => Icons.settings,
    workspaces => Icons.dashboard_customize,
    coachCockpit => Icons.sports_score,
    clubCockpit => Icons.apartment,
    clubTodos => Icons.checklist,
    clubCalendar => Icons.calendar_month,
    sponsors => Icons.handshake,
  };

  String? get moduleTitle => switch (this) {
    events => 'Events & Training',
    courses => 'Kurse',
    sportMap => 'Sportkarte',
    messages => 'Nachrichten',
    sportMatching => 'Sport-Matching',
    carpool => 'Fahrgemeinschaften',
    files => 'Dateien',
    marketplace => 'Marketplace',
    settings => 'Einstellungen',
    workspaces => 'Arbeitsbereiche',
    coachCockpit => 'Trainer-Cockpit',
    clubCockpit => 'Vereins-Cockpit',
    clubTodos => 'Vereins-Cockpit',
    clubCalendar => 'Vereins-Cockpit',
    sponsors => 'Sponsoren',
    _ => null,
  };

  bool isAvailableTo(AirmiusUser? user) {
    if (user == null) return false;
    return switch (this) {
      training => AirmiusModuleAccess.canOpen(user, 'Events & Training'),
      teams => AirmiusModuleAccess.canOpen(user, 'Teams'),
      feed => AirmiusModuleAccess.canOpen(user, 'Feed'),
      nutrition || drink => AirmiusModuleAccess.canOpen(user, 'Ernährung'),
      profile => true,
      _ => AirmiusModuleAccess.canOpen(user, moduleTitle!),
    };
  }
}

List<FooterNavigationDestination> sanitizeFooterNavigation({
  required Iterable<FooterNavigationDestination> destinations,
  required AirmiusUser? user,
}) {
  final available = FooterNavigationDestination.values
      .where((destination) => destination.isAvailableTo(user))
      .toList(growable: false);
  final availableSet = available.toSet();
  final sanitized = <FooterNavigationDestination>[];

  for (final destination in destinations) {
    if (availableSet.contains(destination) &&
        !sanitized.contains(destination)) {
      sanitized.add(destination);
    }
    if (sanitized.length == 5) break;
  }

  for (final destination in FooterNavigationDestination.defaultsFor(user)) {
    if (sanitized.length >= 3) break;
    if (availableSet.contains(destination) &&
        !sanitized.contains(destination)) {
      sanitized.add(destination);
    }
  }

  for (final destination in available) {
    if (sanitized.length >= 3) break;
    if (!sanitized.contains(destination)) sanitized.add(destination);
  }

  return sanitized;
}
