class AirmiusLocalizationReadiness {
  const AirmiusLocalizationReadiness._();

  static const supportedLanguages = ['de', 'en', 'fr', 'ar'];
  static const primaryLanguage = 'de';
  static const rtlLanguages = ['ar'];

  static const mustLocalizeAreas = [
    'Login und Onboarding',
    'App Shell',
    'Vereine und Clubprofil',
    'Mitgliedsantrag',
    'Notifications und Messages',
    'Events und Training',
    'Profil und Einstellungen',
    'Deep-Link-Ankunft',
    'Fehler, Empty, Loading und Success States',
  ];

  static const remainingGates = [
    'Alle public/member-facing Screens vollstaendig auf Keys umstellen',
    'Mitgliedsantrag-Felder und Deep-Link-Texte final extrahieren',
    'Arabisch RTL visuell prüfen',
    'Store-Screenshots je Listing-Sprache prüfen',
  ];
}
