import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class WellbeingOperationsScreen extends StatefulWidget {
  const WellbeingOperationsScreen({super.key});

  @override
  State<WellbeingOperationsScreen> createState() => _WellbeingOperationsScreenState();
}

class _WellbeingOperationsScreenState extends State<WellbeingOperationsScreen> {
  String _tab = 'Ernährung';
  bool _coachVisible = true;
  bool _locationConsent = false;

  @override
  Widget build(BuildContext context) {
    final items = _items.where((item) => _tab == 'Alle' || item.tab == _tab).toList();
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Wellbeing Operations', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Wellbeing Operations',
        subtitle: 'Ernährung, Wasser, Barcode, KI-Fotoanalyse, Sportkarte, Tracks, Orte und Readiness',
        trailing: StatusPill(_tab),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Sport & Gesundheit'),
                  const SizedBox(height: 8),
                  const Text('Mobile Operations für alle Sport-/Gesundheitsdaten: Tagesziele, Mahlzeiten, Wasser, Barcode, KI-Fotoanalyse, Routen, Tracks, Orte, Analytics und Coach-Sichtbarkeit.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final tab in const ['Ernährung', 'Sportkarte', 'Readiness', 'Alle'])
                        ChoiceChip(
                          selected: _tab == tab,
                          label: Text(tab),
                          onSelected: (_) => setState(() => _tab = tab),
                          selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
                          backgroundColor: AirmiusColors.cardSoft,
                          side: BorderSide(color: _tab == tab ? AirmiusColors.blue : AirmiusColors.border),
                          labelStyle: TextStyle(color: _tab == tab ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
                        ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(children: const [Expanded(child: MetricCard(value: '1840', label: 'kcal')), SizedBox(width: 10), Expanded(child: MetricCard(value: '8', label: 'Routen')), SizedBox(width: 10), Expanded(child: MetricCard(value: '82%', label: 'Ready'))]),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Freigaben'),
                  SwitchListTile(value: _coachVisible, onChanged: (value) => setState(() => _coachVisible = value), activeThumbColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Coach darf relevante Daten sehen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Readiness, Trainingslogs, Sportprofil und ausgewählte Nutrition-Hinweise werden später rollenbasiert geteilt.', style: TextStyle(color: AirmiusColors.muted))),
                  SwitchListTile(value: _locationConsent, onChanged: (value) => setState(() => _locationConsent = value), activeThumbColor: AirmiusColors.green, contentPadding: EdgeInsets.zero, title: const Text('Standortfreigabe aktiv', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Routen, Live-Tracks und Orte benoetigen vor dem Start eine klare Zustimmung.', style: TextStyle(color: AirmiusColors.muted))),
                ],
              ),
            ),
            const SizedBox(height: 14),
            for (final item in items) ...[
              _WellbeingOperationCard(item: item),
              const SizedBox(height: 12),
            ],
          ],
        ),
      ),
    );
  }
}

class _WellbeingOperationCard extends StatelessWidget {
  const _WellbeingOperationCard({required this.item});

  final _WellbeingOperation item;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      borderColor: item.danger ? AirmiusColors.red.withValues(alpha: 0.45) : AirmiusColors.border,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(item.icon, color: item.danger ? AirmiusColors.red : AirmiusColors.blue, size: 28),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(item.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 4),
                    Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                    const SizedBox(height: 9),
                    Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(item.tab), StatusPill(item.status, color: item.danger ? AirmiusColors.red : AirmiusColors.blue)]),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              AirmiusButton(label: item.action, icon: item.icon, danger: item.danger, secondary: !item.danger, onPressed: () => _run(context, item)),
              AirmiusButton(label: 'Datenkontext', icon: Icons.manage_search_outlined, secondary: true, onPressed: () => openUiAction(context, title: '${item.title} Datenkontext', body: 'User, Coach, Sichtbarkeit, Datenschutz, Quelle und API-Zuordnung für ${item.title} anzeigen.', status: 'Context', icon: Icons.manage_search_outlined)),
            ],
          ),
        ],
      ),
    );
  }

  void _run(BuildContext context, _WellbeingOperation item) {
    void action() => openUiAction(context, title: item.action, body: '${item.action}: ${item.body}', status: item.status, icon: item.icon);
    if (item.danger) {
      confirmDanger(context, '${item.action}?', 'Diese Aktion beeinflusst persoenliche Sport-, Standort- oder Gesundheitsdaten und wird später auditiert.', item.action, action);
      return;
    }
    action();
  }
}

class _WellbeingOperation {
  const _WellbeingOperation({required this.tab, required this.title, required this.body, required this.status, required this.icon, required this.action, this.danger = false});

  final String tab;
  final String title;
  final String body;
  final String status;
  final IconData icon;
  final String action;
  final bool danger;
}

const _items = [
  _WellbeingOperation(tab: 'Ernährung', title: 'Tagesziel aktualisieren', body: 'Kalorien, Makros, Wasserziel und Coach-Sichtbarkeit speichern.', status: 'Goal', icon: Icons.flag_outlined, action: 'Ziel speichern'),
  _WellbeingOperation(tab: 'Ernährung', title: 'Lebensmittel suchen', body: 'Food Search mit Name, Marke, Portion und Makros vorbereiten.', status: 'Search', icon: Icons.search_outlined, action: 'Suche starten'),
  _WellbeingOperation(tab: 'Ernährung', title: 'Barcode nachschlagen', body: 'Barcode scannen, Produktdaten laden, Portion setzen und Mahlzeit vorbereiten.', status: 'Barcode', icon: Icons.qr_code_scanner_outlined, action: 'Barcode suchen'),
  _WellbeingOperation(tab: 'Ernährung', title: 'KI-Fotoanalyse', body: 'Mahlzeit per Foto schaetzen, Ergebnis korrigieren und Makros speichern.', status: 'AI', icon: Icons.camera_alt_outlined, action: 'Foto analysieren'),
  _WellbeingOperation(tab: 'Ernährung', title: 'Mahlzeit speichern', body: 'Meal mit Lebensmitteln, Portionen, Tageszeit und Notiz anlegen.', status: 'Meal', icon: Icons.restaurant_menu_outlined, action: 'Mahlzeit speichern'),
  _WellbeingOperation(tab: 'Ernährung', title: 'Wasser loggen', body: 'Wassermenge, Tageszeit und Ziel-Fortschritt speichern.', status: 'Water', icon: Icons.water_drop_outlined, action: 'Wasser speichern'),
  _WellbeingOperation(tab: 'Ernährung', title: 'Mahlzeit löschen', body: 'Fehlerhafte Mahlzeit entfernen und Tageswerte neu berechnen.', status: 'Delete', icon: Icons.delete_outline, action: 'Mahlzeit löschen', danger: true),
  _WellbeingOperation(tab: 'Sportkarte', title: 'Route erstellen', body: 'Start, Ziel, Wegpunkte, Sichtbarkeit, Sportart und Teamfreigabe speichern.', status: 'Route', icon: Icons.add_location_alt_outlined, action: 'Route speichern'),
  _WellbeingOperation(tab: 'Sportkarte', title: 'Route duplizieren', body: 'Vorhandene Route kopieren, Name und Sichtbarkeit anpassen.', status: 'Copy', icon: Icons.copy_outlined, action: 'Route duplizieren'),
  _WellbeingOperation(tab: 'Sportkarte', title: 'Route löschen', body: 'Route entfernen, sofern keine aktiven Tracks davon abhaengen.', status: 'Delete', icon: Icons.delete_outline, action: 'Route löschen', danger: true),
  _WellbeingOperation(tab: 'Sportkarte', title: 'Live-Track starten', body: 'GPS-Rechte prüfen, Track anlegen und erste Punkte speichern.', status: 'Track', icon: Icons.gps_fixed, action: 'Track starten'),
  _WellbeingOperation(tab: 'Sportkarte', title: 'Trackpunkte senden', body: 'Neue GPS-Punkte an aktiven Track anhaengen und Live-Status aktualisieren.', status: 'Points', icon: Icons.timeline_outlined, action: 'Punkte senden'),
  _WellbeingOperation(tab: 'Sportkarte', title: 'Track abschließen', body: 'Dauer, Distanz, Pace, Route und Sichtbarkeit finalisieren.', status: 'Complete', icon: Icons.task_alt_outlined, action: 'Track abschließen'),
  _WellbeingOperation(tab: 'Sportkarte', title: 'Sportort speichern', body: 'Ort, Koordinaten, Sportart, Verein/Team und Public-Sichtbarkeit anlegen.', status: 'Place', icon: Icons.place_outlined, action: 'Ort speichern'),
  _WellbeingOperation(tab: 'Readiness', title: 'Sportprofil aktualisieren', body: 'Erfahrung, Ziel, Leistungswerte, Pulsbereiche und KI-Plan-Freigabe speichern.', status: 'Profile', icon: Icons.sports_outlined, action: 'Profil speichern'),
  _WellbeingOperation(tab: 'Readiness', title: 'Route Analytics laden', body: 'Maturity-Analytics für Route, Challenges, Sicherheit und Performance anzeigen.', status: 'Analytics', icon: Icons.query_stats_outlined, action: 'Analytics laden'),
  _WellbeingOperation(tab: 'Readiness', title: 'Challenge vorbereiten', body: 'Sportliche Challenge mit Datenschutz, Altersfreigabe und Viral-Freigabe vorbereiten.', status: 'Challenge', icon: Icons.emoji_events_outlined, action: 'Challenge speichern'),
  _WellbeingOperation(tab: 'Readiness', title: 'Safety Check', body: 'Maturity Safety, Standortfreigabe, Minderjaehrige und sensible Gesundheitsdaten prüfen.', status: 'Safety', icon: Icons.health_and_safety_outlined, action: 'Safety prüfen'),
];
