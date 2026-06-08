import 'package:flutter/material.dart';

class ModuleDefinition {
  const ModuleDefinition({
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.actions,
    required this.metrics,
  });

  final String title;
  final String subtitle;
  final IconData icon;
  final List<String> actions;
  final Map<String, String> metrics;
}

const appModules = [
  ModuleDefinition(title: 'Arbeitsbereiche', subtitle: 'Rollen, Rechte und Vereinsbereiche', icon: Icons.dashboard_customize_outlined, actions: ['Arbeitsbereich wechseln', 'Rollen pruefen', 'Einladungen ansehen'], metrics: {'Aktiv': '3', 'Offen': '1'}),
  ModuleDefinition(title: 'Vereins-Cockpit', subtitle: 'Profil, Teams, Mitglieder, Beitraege und Dokumente', icon: Icons.apartment_outlined, actions: ['Profil bearbeiten', 'Anfragen pruefen', 'Dokumente verknuepfen'], metrics: {'Anfragen': '1', 'Profil': '82%'}),
  ModuleDefinition(title: 'Vereine & Teams', subtitle: 'Profile, Teams, Mitglieder und Antraege', icon: Icons.groups_outlined, actions: ['Verein suchen', 'Team ansehen', 'Mitgliedschaft anfragen'], metrics: {'Vereine': '3', 'Teams': '11'}),
  ModuleDefinition(title: 'Teams', subtitle: 'Teamprofile, Kader, Rollen, Einladungen und Beitritte', icon: Icons.groups_2_outlined, actions: ['Team erstellen', 'Kader verwalten', 'Einladungen senden'], metrics: {'Teams': '11', 'Einladungen': '3'}),
  ModuleDefinition(title: 'Rollen & Rechte', subtitle: 'Permissions, Rollen, Zugriff und Sicherheitsregeln', icon: Icons.admin_panel_settings_outlined, actions: ['Rolle pruefen', 'Rechte bearbeiten', 'Audit ansehen'], metrics: {'Rollen': '6', 'Rechte': '42'}),
  ModuleDefinition(title: 'Sportarten', subtitle: 'Sportprofile, Disziplinen, Leistungsdaten und Ziele', icon: Icons.sports_outlined, actions: ['Sportprofil bearbeiten', 'Disziplin waehlen', 'Ziele setzen'], metrics: {'Sportarten': '5', 'Profile': '2'}),
  ModuleDefinition(title: 'Feed', subtitle: 'Beitraege, Kommentare, Stories und Reaktionen', icon: Icons.dynamic_feed_outlined, actions: ['Beitrag erstellen', 'Story ansehen', 'Kommentare lesen'], metrics: {}),
  ModuleDefinition(title: 'Events', subtitle: 'Kalender, Teilnahme, Warteliste, Chat und Erinnerungen', icon: Icons.event_outlined, actions: ['Event erstellen', 'Teilnehmerliste', 'Erinnerung senden'], metrics: {'Events': '5', 'Teilnehmer': '18'}),
  ModuleDefinition(title: 'Events & Training', subtitle: 'Termine, Teilnahme und Trainingsplaene', icon: Icons.event_available_outlined, actions: ['Event beitreten', 'Training loggen', 'Plan oeffnen'], metrics: {'Events': '5', 'Plaene': '2'}),
  ModuleDefinition(title: 'Trainer-Cockpit', subtitle: 'Athleten, Feedback, Wochenaktionen und Risiken', icon: Icons.sports_score_outlined, actions: ['Feedback beantworten', 'Plan freigeben', 'Risiken pruefen'], metrics: {'Athleten': '11', 'Offen': '4'}),
  ModuleDefinition(title: 'Ernaehrung', subtitle: 'Ziele, Mahlzeiten, Wasser und KI-Analyse', icon: Icons.restaurant_menu_outlined, actions: ['Mahlzeit erfassen', 'Wasser loggen', 'Barcode suchen'], metrics: {'Kalorien': '1840', 'Wasser': '1.5L'}),
  ModuleDefinition(title: 'Sportkarte', subtitle: 'Routen, Tracks, Orte und Vorschlaege', icon: Icons.map_outlined, actions: ['Route planen', 'Track starten', 'Ort speichern'], metrics: {'Routen': '8', 'Orte': '6'}),
  ModuleDefinition(title: 'Freunde', subtitle: 'Kontakte, Einladungen und Empfehlungen', icon: Icons.person_add_alt_1_outlined, actions: ['Freund suchen', 'Einladung senden', 'Anfragen bearbeiten'], metrics: {'Freunde': '18', 'Offen': '2'}),
  ModuleDefinition(title: 'Nachrichten', subtitle: 'Chats, Gruppen, Reaktionen und Lesestatus', icon: Icons.chat_bubble_outline, actions: ['Chat oeffnen', 'Nachricht senden', 'Teilnehmer verwalten'], metrics: {'Chats': '6', 'Ungelesen': '3'}),
  ModuleDefinition(title: 'Fahrgemeinschaften', subtitle: 'Mitfahrten, Routen, Treffpunkte und Sicherheit', icon: Icons.directions_car_filled_outlined, actions: ['Fahrt anbieten', 'Mitfahrt suchen', 'Treffpunkt teilen'], metrics: {'Fahrten': '3', 'Plaetze': '8'}),
  ModuleDefinition(title: 'Dateien', subtitle: 'Dateimanager, Uploads, Freigaben und Regeln', icon: Icons.folder_outlined, actions: ['Datei hochladen', 'Ordner erstellen', 'Dokument teilen'], metrics: {'Dateien': '24', 'Freigaben': '4'}),
  ModuleDefinition(title: 'Badges', subtitle: 'Gamification, Fortschritt und Auszeichnungen', icon: Icons.workspace_premium_outlined, actions: ['Badge ansehen', 'Fortschritt pruefen', 'Regeln oeffnen'], metrics: {'Badges': '9', 'Neu': '1'}),
  ModuleDefinition(title: 'Gamification-Regeln', subtitle: 'XP, Badges, Streaks, Leaderboard und Regelpruefung', icon: Icons.rule_outlined, actions: ['Regel erstellen', 'Historie ansehen', 'Leaderboard'], metrics: {'Regeln': '4', 'XP': '420'}),
  ModuleDefinition(title: 'Kurse', subtitle: 'E-Learning, Zertifikate und Lernstudio', icon: Icons.school_outlined, actions: ['Kurs starten', 'Lektion oeffnen', 'Zertifikat anzeigen'], metrics: {'Kurse': '4', 'Zertifikate': '2'}),
  ModuleDefinition(title: 'Marketplace', subtitle: 'Produkte, Warenkorb, Bestellungen und Anbieter', icon: Icons.storefront_outlined, actions: ['Produkt suchen', 'Warenkorb oeffnen', 'Bestellung verfolgen'], metrics: {'Produkte': '36', 'Orders': '2'}),
  ModuleDefinition(title: 'Commerce', subtitle: 'Produkte, Orders, Coupons, Inventar, Payouts und Qualitaet', icon: Icons.store_mall_directory_outlined, actions: ['Produkt anlegen', 'Order pruefen', 'Payout vorbereiten'], metrics: {'Orders': '12', 'Payouts': '2'}),
  ModuleDefinition(title: 'Sponsoren', subtitle: 'Sponsorprofile, Kampagnen, Pakete und Sichtbarkeit', icon: Icons.handshake_outlined, actions: ['Sponsor ansehen', 'Paket pruefen', 'Kontakt aufnehmen'], metrics: {'Sponsoren': '4', 'Kampagnen': '2'}),
  ModuleDefinition(title: 'Medienrichtlinien', subtitle: 'Bildrechte, Upload-Regeln, Freigaben und Moderation', icon: Icons.policy_outlined, actions: ['Freigabe pruefen', 'Regel bearbeiten', 'Meldung ansehen'], metrics: {'Regeln': '5', 'Pruefung': '2'}),
  ModuleDefinition(title: 'Blog & Medien', subtitle: 'Artikel, Medienrichtlinien, Freigaben und Redaktion', icon: Icons.article_outlined, actions: ['Artikel lesen', 'Beitrag planen', 'Richtlinien pruefen'], metrics: {'Artikel': '8', 'Entwuerfe': '2'}),
  ModuleDefinition(title: 'Nutzer', subtitle: 'Personen, Profile, Status, Rollen und Verbindungen', icon: Icons.people_alt_outlined, actions: ['Nutzer suchen', 'Profil ansehen', 'Status pruefen'], metrics: {'Nutzer': '42', 'Online': '7'}),
  ModuleDefinition(title: 'Abos & Rechnungen', subtitle: 'Plaene, Checkouts, Banktransfer und Rechnungen', icon: Icons.receipt_long_outlined, actions: ['Plan wechseln', 'Rechnung herunterladen', 'Offene Zahlung pruefen'], metrics: {'Aktiv': '2', 'Offen': '1'}),
  ModuleDefinition(title: 'Eltern & Jugendschutz', subtitle: 'Guardian Consent, Elternlogin und Kinderkonten', icon: Icons.family_restroom_outlined, actions: ['Zustimmung pruefen', 'Elterncode senden', 'Widerruf verwalten'], metrics: {'Offen': '1', 'Kinder': '2'}),
  ModuleDefinition(title: 'Altersfreigaben', subtitle: 'Maturity, Content-Gates, Guardian-Freigaben und Schutzregeln', icon: Icons.visibility_off_outlined, actions: ['Gate erstellen', 'Consent pruefen', 'Regeln bearbeiten'], metrics: {'Gates': '3', 'Offen': '1'}),
  ModuleDefinition(title: 'Outfit-Abos', subtitle: 'Style-Profil, Plaene, Lieferungen und Support', icon: Icons.checkroom_outlined, actions: ['Style bearbeiten', 'Lieferung ansehen', 'Abo pausieren'], metrics: {'Aktiv': '1', 'Lieferungen': '2'}),
  ModuleDefinition(title: 'Einstellungen', subtitle: 'Profil, Sprache, Datenschutz und Zahlungen', icon: Icons.settings_outlined, actions: ['Profil bearbeiten', 'Sprache wechseln', 'Abo verwalten'], metrics: {'Profil': '82%', 'Sprache': 'DE'}),
  ModuleDefinition(title: 'Admin', subtitle: 'Moderation, Abos, Commerce und Systembereiche', icon: Icons.admin_panel_settings_outlined, actions: ['Mitglieder verwalten', 'Moderation pruefen', 'Zahlungen ansehen'], metrics: {'Tickets': '2', 'Pruefung': '1'}),
];



