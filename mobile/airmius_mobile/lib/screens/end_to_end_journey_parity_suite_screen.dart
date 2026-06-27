import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class EndToEndJourneyParitySuiteScreen extends StatefulWidget {
  const EndToEndJourneyParitySuiteScreen({super.key});

  @override
  State<EndToEndJourneyParitySuiteScreen> createState() => _EndToEndJourneyParitySuiteScreenState();
}

class _EndToEndJourneyParitySuiteScreenState extends State<EndToEndJourneyParitySuiteScreen> {
  String _journey = 'Verein beitreten';
  String _role = 'Player';
  bool _showApiStates = true;
  bool _showNotifications = true;
  bool _showExitPaths = true;

  static const _journeys = ['Verein beitreten', 'Training', 'Commerce', 'Guardian', 'Admin Review'];
  static const _roles = ['Player', 'Verein', 'Trainer', 'Guardian', 'Admin'];

  static const _flows = <_JourneyFlow>[
    _JourneyFlow(
      journey: 'Verein beitreten',
      title: 'User findet Verein und sendet Antrag',
      role: 'Player',
      body: 'Suche, Club-Profil, Mitgliedsantrag, Pflichtfelder, Uploads, Datenschutz, Zahlungsauswahl und Anfrage gesendet.',
      steps: ['Suche', 'Club-Profil', 'Formular', 'Upload', 'Senden'],
      status: 'User Flow',
      icon: Icons.assignment_ind_outlined,
      color: AirmiusColors.green,
    ),
    _JourneyFlow(
      journey: 'Verein beitreten',
      title: 'Verein bearbeitet Antrag',
      role: 'Verein',
      body: 'Inbox, neue Anfrage, Dokumentstatus, Rückfragen, Annahme/Ablehnung, Benachrichtigung und Mitgliederanlage.',
      steps: ['Inbox', 'Prüfen', 'Rückfrage', 'Entscheiden', 'Benachrichtigen'],
      status: 'Club Admin',
      icon: Icons.inbox_outlined,
      color: AirmiusColors.blue,
    ),
    _JourneyFlow(
      journey: 'Verein beitreten',
      title: 'User zieht Antrag zurück',
      role: 'Player',
      body: 'Statusseite, Rückzug-Modal, Bestätigung, Verein benachrichtigen, Audit und neuer Status.',
      steps: ['Status', 'Rückzug', 'Confirm', 'Notify', 'Audit'],
      status: 'Withdraw',
      icon: Icons.undo_outlined,
      color: AirmiusColors.red,
    ),
    _JourneyFlow(
      journey: 'Training',
      title: 'Training planen und teilnehmen',
      role: 'Trainer',
      body: 'Event erstellen, Team wählen, Ort, Push, Teilnahme, Warteliste, Kalender und Anwesenheit.',
      steps: ['Planen', 'Einladen', 'Ort', 'Teilnahme', 'Anwesenheit'],
      status: 'Training',
      icon: Icons.event_available_outlined,
      color: AirmiusColors.green,
    ),
    _JourneyFlow(
      journey: 'Training',
      title: 'Athlet erfasst Log',
      role: 'Player',
      body: 'Trainingsplan, Log, Werte, Mediennachweis, Offline-Draft, Coach-Feedback und Fortschritt.',
      steps: ['Plan', 'Log', 'Werte', 'Upload', 'Feedback'],
      status: 'Log',
      icon: Icons.fitness_center_outlined,
      color: AirmiusColors.blue,
    ),
    _JourneyFlow(
      journey: 'Commerce',
      title: 'Produkt kaufen',
      role: 'Player',
      body: 'Marketplace, Produktdetail, Warenkorb, Checkout, Banktransfer, Order Status, Beleg und Support.',
      steps: ['Shop', 'Produkt', 'Warenkorb', 'Zahlung', 'Status'],
      status: 'Checkout',
      icon: Icons.storefront_outlined,
      color: AirmiusColors.green,
    ),
    _JourneyFlow(
      journey: 'Commerce',
      title: 'Beitrag bezahlen',
      role: 'Player',
      body: 'Mitgliedsbeitrag, Zahlungsrhythmus, Rechnung, Überweisung/Bar, Mahnung, Status und Quittung.',
      steps: ['Beitrag', 'Rechnung', 'Methode', 'Zahlen', 'Beleg'],
      status: 'Payment',
      icon: Icons.payments_outlined,
      color: AirmiusColors.amber,
    ),
    _JourneyFlow(
      journey: 'Guardian',
      title: 'Elternfreigabe geben',
      role: 'Guardian',
      body: 'Guardian Login, Kind wählen, Antrag/Event/Chat prüfen, Zustimmung, Ablehnung und Benachrichtigung.',
      steps: ['Login', 'Kind', 'Prüfen', 'Entscheiden', 'Notify'],
      status: 'Consent',
      icon: Icons.verified_user_outlined,
      color: AirmiusColors.blue,
    ),
    _JourneyFlow(
      journey: 'Admin Review',
      title: 'Moderation entscheiden',
      role: 'Admin',
      body: 'Report, Kontext, Inhalt, Entscheidung, Sperre, Rückmeldung, Audit und Trust-Status.',
      steps: ['Report', 'Kontext', 'Entscheidung', 'Audit', 'Notify'],
      status: 'Moderation',
      icon: Icons.flag_outlined,
      color: AirmiusColors.red,
    ),
    _JourneyFlow(
      journey: 'Admin Review',
      title: 'Verein verifizieren',
      role: 'Admin',
      body: 'Club-Dokumente, Impressum, Admin-Kontakt, Entscheidung, Ablehnung, Rückfrage und Public-Freigabe.',
      steps: ['Dokumente', 'Kontakt', 'Prüfen', 'Freigeben', 'Public'],
      status: 'Verification',
      icon: Icons.verified_outlined,
      color: AirmiusColors.green,
    ),
  ];

  List<_JourneyFlow> get _visibleFlows {
    return _flows.where((flow) {
      final journeyMatch = flow.journey == _journey;
      final roleMatch = _role == 'Player' || flow.role == _role || flow.role == 'Player';
      return journeyMatch && roleMatch;
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const AirmiusLogo(compact: true),
      ),
      body: SafeArea(
        child: PageFrame(
          title: 'End-to-End Journey Parity',
          subtitle: 'Durchgehende mobile Nutzerwege statt isolierte Screens.',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _Hero(
                journey: _journey,
                role: _role,
                showApiStates: _showApiStates,
                showNotifications: _showNotifications,
              ),
              const SizedBox(height: 16),
              _ChoicePanel(
                title: 'Journey',
                items: _journeys,
                active: _journey,
                color: AirmiusColors.blue,
                onChanged: (value) => setState(() => _journey = value),
              ),
              const SizedBox(height: 16),
              _ChoicePanel(
                title: 'Rolle',
                items: _roles,
                active: _role,
                color: AirmiusColors.green,
                onChanged: (value) => setState(() => _role = value),
              ),
              const SizedBox(height: 16),
              _RulesPanel(
                showApiStates: _showApiStates,
                showNotifications: _showNotifications,
                showExitPaths: _showExitPaths,
                onApiStates: (value) => setState(() => _showApiStates = value),
                onNotifications: (value) => setState(() => _showNotifications = value),
                onExitPaths: (value) => setState(() => _showExitPaths = value),
              ),
              const SizedBox(height: 16),
              for (final flow in _visibleFlows) ...[
                _JourneyCard(
                  flow: flow,
                  showApiStates: _showApiStates,
                  showNotifications: _showNotifications,
                  showExitPaths: _showExitPaths,
                ),
                const SizedBox(height: 12),
              ],
              if (_visibleFlows.isEmpty) const EmptyPanel('Keine Journey-Flows für diese Kombination sichtbar.'),
              const SizedBox(height: 4),
              _Checklist(
                onOpen: () => openUiAction(
                  context,
                  title: 'End-to-End Journey Parity',
                  body: 'Verein beitreten, Antrag rückziehen, Training, Commerce, Guardian und Admin Review sind als durchgehende mobile User Journeys vorbereitet.',
                  status: 'Journey',
                  icon: Icons.route_outlined,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Hero extends StatelessWidget {
  const _Hero({
    required this.journey,
    required this.role,
    required this.showApiStates,
    required this.showNotifications,
  });

  final String journey;
  final String role;
  final bool showApiStates;
  final bool showNotifications;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      gradient: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Eyebrow('END-TO-END FLOWS'),
          const SizedBox(height: 8),
          const Text(
            'Eine fertige App besteht aus Wegen, nicht aus Screens.',
            style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 8),
          const Text(
            'Diese Suite verbindet Suche, Profile, Formulare, Uploads, Zahlungen, Benachrichtigungen, Adminentscheidungen und Statusseiten zu echten mobilen Airmius-Journeys.',
            style: TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 16),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _Metric(value: journey, label: 'Journey'),
              _Metric(value: role, label: 'Rolle'),
              _Metric(value: showApiStates ? 'API' : 'UI', label: 'Zustaende'),
              _Metric(value: showNotifications ? 'Notify' : 'Still', label: 'Updates'),
            ],
          ),
        ],
      ),
    );
  }
}

class _ChoicePanel extends StatelessWidget {
  const _ChoicePanel({
    required this.title,
    required this.items,
    required this.active,
    required this.color,
    required this.onChanged,
  });

  final String title;
  final List<String> items;
  final String active;
  final Color color;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: title,
      children: [
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: items
              .map(
                (item) => ChoiceChip(
                  selected: active == item,
                  label: Text(item),
                  onSelected: (_) => onChanged(item),
                  selectedColor: color.withValues(alpha: .24),
                  backgroundColor: AirmiusColors.cardSoft,
                  side: BorderSide(color: active == item ? color : AirmiusColors.border),
                  labelStyle: TextStyle(color: active == item ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
                ),
              )
              .toList(),
        ),
      ],
    );
  }
}

class _RulesPanel extends StatelessWidget {
  const _RulesPanel({
    required this.showApiStates,
    required this.showNotifications,
    required this.showExitPaths,
    required this.onApiStates,
    required this.onNotifications,
    required this.onExitPaths,
  });

  final bool showApiStates;
  final bool showNotifications;
  final bool showExitPaths;
  final ValueChanged<bool> onApiStates;
  final ValueChanged<bool> onNotifications;
  final ValueChanged<bool> onExitPaths;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: 'Journey-Regeln',
      subtitle: 'Diese Regeln machen aus Web-Modulen echte mobile Prozesse.',
      children: [
        _SwitchLine(title: 'API-Zustaende pro Schritt zeigen', value: showApiStates, onChanged: onApiStates),
        _SwitchLine(title: 'Benachrichtigungen einplanen', value: showNotifications, onChanged: onNotifications),
        _SwitchLine(title: 'Rückwege und Abbruchpfade zeigen', value: showExitPaths, onChanged: onExitPaths),
      ],
    );
  }
}

class _JourneyCard extends StatelessWidget {
  const _JourneyCard({
    required this.flow,
    required this.showApiStates,
    required this.showNotifications,
    required this.showExitPaths,
  });

  final _JourneyFlow flow;
  final bool showApiStates;
  final bool showNotifications;
  final bool showExitPaths;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      borderColor: flow.color.withValues(alpha: .55),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: flow.color.withValues(alpha: .14),
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: flow.color.withValues(alpha: .55)),
                ),
                child: Icon(flow.icon, color: flow.color),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(flow.title, style: const TextStyle(color: AirmiusColors.text, fontSize: 17, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 5),
                    Text('${flow.role} · ${flow.journey}', style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w800)),
                  ],
                ),
              ),
              StatusPill(flow.status, color: flow.color),
            ],
          ),
          const SizedBox(height: 12),
          Text(flow.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.42, fontWeight: FontWeight.w700)),
          const SizedBox(height: 14),
          _StepRail(steps: flow.steps, color: flow.color),
          const SizedBox(height: 14),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              if (showApiStates) const StatusPill('Loading/Error/Success', color: AirmiusColors.blue),
              if (showNotifications) const StatusPill('Push/In-App', color: AirmiusColors.green),
              if (showExitPaths) const StatusPill('Abbruch/Rückweg', color: AirmiusColors.amber),
            ],
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              AirmiusButton(
                label: 'Journey starten',
                icon: flow.icon,
                danger: flow.color == AirmiusColors.red,
                onPressed: () => openUiAction(
                  context,
                  title: flow.title,
                  body: '${flow.title}: ${flow.body}\n\nSchritte: ${flow.steps.join(' -> ')}',
                  status: flow.status,
                  icon: flow.icon,
                ),
              ),
              AirmiusButton(
                label: 'Flow prüfen',
                icon: Icons.fact_check_outlined,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: '${flow.title} prüfen',
                  body: 'API-Zustaende, Notifications, Permissions, Fehler, Rückwege und naechster Screen für ${flow.title}.',
                  status: 'Flow Check',
                  icon: Icons.fact_check_outlined,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _StepRail extends StatelessWidget {
  const _StepRail({
    required this.steps,
    required this.color,
  });

  final List<String> steps;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: [
        for (var index = 0; index < steps.length; index++)
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
            decoration: BoxDecoration(
              color: color.withValues(alpha: .12),
              borderRadius: BorderRadius.circular(999),
              border: Border.all(color: color.withValues(alpha: .55)),
            ),
            child: Text('${index + 1}. ${steps[index]}', style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w900)),
          ),
      ],
    );
  }
}

class _Checklist extends StatelessWidget {
  const _Checklist({required this.onOpen});

  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: 'Journey-Paritaet',
      subtitle: 'Was zusammenhaengend funktionieren muss.',
      children: [
        const _CheckLine('User-Journeys verbinden Suche, Profile, Formulare, Uploads, Zahlungen und Statusseiten.'),
        const _CheckLine('Vereins-, Trainer-, Guardian- und Admin-Flows haben eigene Rollen- und Benachrichtigungsschritte.'),
        const _CheckLine('Rückzug, Ablehnung, Fehler, Retry und Abbruchpfade werden als mobile Prozesse sichtbar.'),
        const _CheckLine('Backend kommt später über Laravel API; die UI-Journeys sind als App-Struktur vorbereitet.'),
        const SizedBox(height: 12),
        AirmiusButton(label: 'Journey-Paritaet markieren', icon: Icons.fact_check_outlined, onPressed: onOpen),
      ],
    );
  }
}

class _SwitchLine extends StatelessWidget {
  const _SwitchLine({
    required this.title,
    required this.value,
    required this.onChanged,
  });

  final String title;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(top: 10),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: AirmiusColors.input,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AirmiusColors.border),
      ),
      child: Row(
        children: [
          Expanded(child: Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900))),
          Switch(value: value, activeThumbColor: AirmiusColors.green, onChanged: onChanged),
        ],
      ),
    );
  }
}

class _CheckLine extends StatelessWidget {
  const _CheckLine(this.text);

  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.check_circle_outline, color: AirmiusColors.green, size: 19),
          const SizedBox(width: 8),
          Expanded(child: Text(text, style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700, height: 1.35))),
        ],
      ),
    );
  }
}

class _Metric extends StatelessWidget {
  const _Metric({
    required this.value,
    required this.label,
  });

  final String value;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: AirmiusColors.bg.withValues(alpha: .55),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AirmiusColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(value, style: const TextStyle(color: AirmiusColors.text, fontSize: 18, fontWeight: FontWeight.w900)),
          const SizedBox(height: 2),
          Text(label, style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700)),
        ],
      ),
    );
  }
}

class _JourneyFlow {
  const _JourneyFlow({
    required this.journey,
    required this.title,
    required this.role,
    required this.body,
    required this.steps,
    required this.status,
    required this.icon,
    required this.color,
  });

  final String journey;
  final String title;
  final String role;
  final String body;
  final List<String> steps;
  final String status;
  final IconData icon;
  final Color color;
}
