/// Stable navigation order, independent of role-filtered module availability.
const sidebarGroups = <String, List<String>>{
  'sport': ['Sportarten', 'Events & Training', 'Trainingsplanung', 'Ernährung', 'Sport-Apps & Gesundheitsdaten', 'Badges', 'Altersfreigaben'],
  'community': ['Feed', 'Nachrichten', 'Freunde', 'Sport-Matching'],
  'club': ['Vereine & Teams', 'Teams', 'Dateien', 'Fahrgemeinschaften', 'Eltern & Jugendschutz'],
  'management': ['Vereins-Cockpit', 'Trainer-Cockpit', 'Sponsoren', 'Recruiting', 'Commerce', 'Medienrichtlinien', 'Nutzer', 'Rollen & Rechte', 'Gamification-Regeln', 'Admin'],
  'discover': ['Sportkarte', 'Challenges', 'Kurse', 'Marketplace', 'Blog & Medien'],
  'account': ['Arbeitsbereiche', 'Abos & Rechnungen', 'Outfit-Abos', 'Einstellungen'],
};

String sidebarGroupLabel(String group, String language) {
  const labels = {
    'de': ['Mein Sport', 'Gemeinschaft', 'Verein & Team', 'Verwaltung', 'Entdecken', 'Konto & Hilfe'],
    'en': ['My sport', 'Community', 'Club & team', 'Management', 'Discover', 'Account & help'],
    'fr': ['Mon sport', 'Communauté', 'Club et équipe', 'Administration', 'Découvrir', 'Compte et aide'],
    'ar': ['رياضتي', 'المجتمع', 'النادي والفريق', 'الإدارة', 'استكشاف', 'الحساب والمساعدة'],
  };
  return (labels[language] ?? labels['en']!)[sidebarGroups.keys.toList().indexOf(group)];
}
