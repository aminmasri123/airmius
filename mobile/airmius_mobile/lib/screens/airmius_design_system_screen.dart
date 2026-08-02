import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class AirmiusDesignSystemScreen extends StatefulWidget {
  const AirmiusDesignSystemScreen({super.key});

  @override
  State<AirmiusDesignSystemScreen> createState() =>
      _AirmiusDesignSystemScreenState();
}

class _AirmiusDesignSystemScreenState extends State<AirmiusDesignSystemScreen> {
  String _filter = 'Alle';

  @override
  Widget build(BuildContext context) {
    final patterns = _filter == 'Alle'
        ? _patterns
        : _patterns.where((pattern) => pattern.area == _filter).toList();
    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text(
          'Airmius Design System',
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: 'Airmius Design System',
        subtitle: 'Mobile Web-App-Optik als native Flutter-Bausteine',
        trailing: const StatusPill('Design'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const AirmiusLogo(),
                  const SizedBox(height: 14),
                  const Text(
                    'Die App soll nicht nur funktionieren, sondern eindeutig nach Airmius aussehen.',
                    style: TextStyle(
                      color: AirmiusColors.text,
                      fontSize: 24,
                      fontWeight: FontWeight.w900,
                      height: 1.08,
                    ),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Diese Seite sammelt die mobilen Web-App-Muster: dunkler Header, Panels, Pills, Formulare, Modal-Layer, Listen, Admin-Zeilen, Tabellenersatz und Bottom-Navigation.',
                    style: TextStyle(color: AirmiusColors.muted, height: 1.4),
                  ),
                  const SizedBox(height: 14),
                  Row(
                    children: const [
                      Expanded(
                        child: MetricCard(value: 'Dark', label: 'Look'),
                      ),
                      SizedBox(width: 10),
                      Expanded(
                        child: MetricCard(value: 'Mobile', label: 'Layout'),
                      ),
                      SizedBox(width: 10),
                      Expanded(
                        child: MetricCard(value: 'Airmius', label: 'Brand'),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final area in _areas)
                        ChoiceChip(
                          label: Text(area),
                          selected: _filter == area,
                          onSelected: (_) => setState(() => _filter = area),
                          selectedColor: AirmiusColors.blue.withValues(
                            alpha: .22,
                          ),
                          backgroundColor: AirmiusColors.cardSoft,
                          side: BorderSide(
                            color: _filter == area
                                ? AirmiusColors.blue
                                : AirmiusColors.border,
                          ),
                          labelStyle: TextStyle(
                            color: _filter == area
                                ? AirmiusColors.text
                                : AirmiusColors.muted,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            _PreviewCluster(),
            const SizedBox(height: 16),
            for (final pattern in patterns) ...[
              _PatternCard(pattern: pattern),
              const SizedBox(height: 12),
            ],
          ],
        ),
      ),
    );
  }
}

class _PreviewCluster extends StatelessWidget {
  @override
  Widget build(BuildContext context) => AirmiusPanel(
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Eyebrow('Live-Muster'),
        const SizedBox(height: 12),
        _MockHeader(),
        const SizedBox(height: 12),
        _MockClubHero(),
        const SizedBox(height: 12),
        Row(
          children: const [
            Expanded(
              child: MetricCard(value: '1', label: 'Mitglieder'),
            ),
            SizedBox(width: 10),
            Expanded(
              child: MetricCard(value: '0', label: 'Teams'),
            ),
            SizedBox(width: 10),
            Expanded(
              child: MetricCard(value: '0', label: 'Beiträge'),
            ),
          ],
        ),
        const SizedBox(height: 12),
        _MockForm(),
        const SizedBox(height: 12),
        _MockBottomNav(),
      ],
    ),
  );
}

class _MockHeader extends StatelessWidget {
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: AirmiusColors.header,
      borderRadius: BorderRadius.circular(16),
      border: Border.all(color: AirmiusColors.border),
    ),
    child: Row(
      children: const [
        AirmiusLogo(compact: true),
        SizedBox(width: 10),
        Expanded(
          child: Text(
            'ZBB',
            style: TextStyle(
              color: AirmiusColors.text,
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
        Icon(Icons.search, color: AirmiusColors.muted),
        SizedBox(width: 10),
        UserBubble(label: 'ZK', small: true),
      ],
    ),
  );
}

class _MockClubHero extends StatelessWidget {
  @override
  Widget build(BuildContext context) => Container(
    constraints: const BoxConstraints(minHeight: 160),
    padding: const EdgeInsets.all(18),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [Color(0xFFEFF5FF), Color(0xFF5BA7FF)],
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
      ),
      borderRadius: BorderRadius.circular(18),
      border: Border.all(color: AirmiusColors.border),
    ),
    child: Column(
      mainAxisAlignment: MainAxisAlignment.end,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: const [
            AirmiusAvatar('ZBB', large: true),
            SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'ZBB',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 22,
                      fontWeight: FontWeight.w900,
                      shadows: [Shadow(color: Colors.black54, blurRadius: 5)],
                    ),
                  ),
                  Text(
                    'Verein - Profil',
                    style: TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w700,
                      shadows: [Shadow(color: Colors.black54, blurRadius: 5)],
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
        const SizedBox(height: 14),
        Align(
          alignment: Alignment.centerRight,
          child: OutlinedButton(
            onPressed: null,
            style: OutlinedButton.styleFrom(
              side: const BorderSide(color: AirmiusColors.green),
              foregroundColor: AirmiusColors.green,
              backgroundColor: AirmiusColors.bg.withValues(alpha: .86),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
            ),
            child: const Text(
              'Anfrage gesendet',
              style: TextStyle(fontWeight: FontWeight.w900),
            ),
          ),
        ),
      ],
    ),
  );
}

class _MockForm extends StatelessWidget {
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(
      color: AirmiusColors.bg,
      borderRadius: BorderRadius.circular(16),
      border: Border.all(color: AirmiusColors.border),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: const [
        Eyebrow('Mitgliedsantrag'),
        SizedBox(height: 10),
        AirmiusTextField(
          label: 'Vorname *',
          hint: 'ZBB',
          icon: Icons.person_outline,
        ),
        SizedBox(height: 10),
        AirmiusTextField(
          label: 'E-Mail *',
          hint: 'konto@example.com',
          icon: Icons.mail_outline,
        ),
      ],
    ),
  );
}

class _MockBottomNav extends StatelessWidget {
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 12),
    decoration: BoxDecoration(
      color: AirmiusColors.header,
      borderRadius: BorderRadius.circular(18),
      border: Border.all(color: AirmiusColors.border),
    ),
    child: Row(
      mainAxisAlignment: MainAxisAlignment.spaceAround,
      children: const [
        _NavDot(icon: Icons.dashboard_outlined, label: 'Home'),
        _NavDot(icon: Icons.groups_2_outlined, label: 'Vereine', active: true),
        _NavDot(icon: Icons.notifications_none, label: 'Updates'),
        _NavDot(icon: Icons.person_outline, label: 'Profil'),
      ],
    ),
  );
}

class _NavDot extends StatelessWidget {
  const _NavDot({required this.icon, required this.label, this.active = false});

  final IconData icon;
  final String label;
  final bool active;

  @override
  Widget build(BuildContext context) => Column(
    mainAxisSize: MainAxisSize.min,
    children: [
      Container(
        width: 54,
        height: 30,
        decoration: BoxDecoration(
          color: active
              ? AirmiusColors.blue.withValues(alpha: .28)
              : Colors.transparent,
          borderRadius: BorderRadius.circular(99),
        ),
        child: Icon(
          icon,
          color: active ? AirmiusColors.text : AirmiusColors.muted,
          size: 20,
        ),
      ),
      const SizedBox(height: 4),
      Text(
        label,
        style: TextStyle(
          color: active ? AirmiusColors.text : AirmiusColors.muted,
          fontSize: 11,
          fontWeight: FontWeight.w800,
        ),
      ),
    ],
  );
}

class _PatternCard extends StatelessWidget {
  const _PatternCard({required this.pattern});

  final _DesignPattern pattern;

  @override
  Widget build(BuildContext context) => AirmiusPanel(
    borderColor: pattern.color.withValues(alpha: .44),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 50,
          height: 50,
          decoration: BoxDecoration(
            color: pattern.color.withValues(alpha: .13),
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: pattern.color.withValues(alpha: .45)),
          ),
          child: Icon(pattern.icon, color: pattern.color),
        ),
        const SizedBox(width: 14),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                pattern.title,
                style: const TextStyle(
                  color: AirmiusColors.text,
                  fontWeight: FontWeight.w900,
                  fontSize: 16,
                ),
              ),
              const SizedBox(height: 5),
              Text(
                pattern.body,
                style: const TextStyle(
                  color: AirmiusColors.muted,
                  height: 1.35,
                ),
              ),
              const SizedBox(height: 10),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  StatusPill(pattern.area, color: pattern.color),
                  StatusPill(pattern.status, color: pattern.color),
                ],
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _DesignPattern {
  const _DesignPattern({
    required this.area,
    required this.title,
    required this.body,
    required this.status,
    required this.icon,
    required this.color,
  });

  final String area;
  final String title;
  final String body;
  final String status;
  final IconData icon;
  final Color color;
}

const _areas = ['Alle', 'Layout', 'Form', 'Data', 'Action', 'State'];

const _patterns = <_DesignPattern>[
  _DesignPattern(
    area: 'Layout',
    title: 'Header wie mobile Web-App',
    body:
        'Dunkle Topbar, Logo, Seitentitel, globale Suche, Chat, Notification und User-Bubble bleiben als Airmius-Muster erhalten.',
    status: 'Standard',
    icon: Icons.web_asset_outlined,
    color: AirmiusColors.blue,
  ),
  _DesignPattern(
    area: 'Layout',
    title: 'Drawer und Bottom Navigation',
    body:
        'Desktop-Web-Sidebar wird mobil als Drawer plus Bottom-Navigation mit Home, Vereine, Updates und Profil übersetzt.',
    status: 'Mobile',
    icon: Icons.space_dashboard_outlined,
    color: AirmiusColors.green,
  ),
  _DesignPattern(
    area: 'Layout',
    title: 'Club Hero und Panels',
    body:
        'Vereinsprofile behalten den großen Verlauf, Avatar, Metriken, Admin-/Mitglieder-Panels und responsive Kartenstruktur.',
    status: 'Brand',
    icon: Icons.groups_2_outlined,
    color: AirmiusColors.blue,
  ),
  _DesignPattern(
    area: 'Form',
    title: 'Mitgliedsantrag und Formulare',
    body:
        'Mehrspaltige Webformulare werden mobil in klare Sections mit Pflichtfeldern, Dokumenten, Zahlweise und Consent übersetzt.',
    status: 'Ready',
    icon: Icons.assignment_ind_outlined,
    color: AirmiusColors.green,
  ),
  _DesignPattern(
    area: 'Form',
    title: 'Uploads und Dateimanager',
    body:
        'Link-only Webdokumente bekommen native Upload-Zeilen, Zweck-Pills, Dateimanager-Status und spätere API-Übergabe.',
    status: 'Ready',
    icon: Icons.folder_outlined,
    color: AirmiusColors.amber,
  ),
  _DesignPattern(
    area: 'Data',
    title: 'Tabellen als mobile Listen',
    body:
        'Admin-, Mitglieder-, Order- und Billing-Tabellen werden als stapelbare Karten mit Status, Metriken und Detailnavigation dargestellt.',
    status: 'Native',
    icon: Icons.view_agenda_outlined,
    color: AirmiusColors.blue,
  ),
  _DesignPattern(
    area: 'Action',
    title: 'Buttons und kritische Aktionen',
    body:
        'Primäre Aktionen, sekundare Aktionen, Rückzug, Löschen, Sperren und Melden nutzen einheitliche Airmius-Buttons und Danger-Flows.',
    status: 'Consistent',
    icon: Icons.touch_app_outlined,
    color: AirmiusColors.red,
  ),
  _DesignPattern(
    area: 'State',
    title: 'Modal, Empty, Loading, Error',
    body:
        'Modal-Layer, Empty Panels, API-Hinweise, Erfolgsmeldungen und spätere Loading/Error/Retry-Zustände folgen dem dunklen Web-App-Stil.',
    status: 'Prepared',
    icon: Icons.layers_outlined,
    color: AirmiusColors.amber,
  ),
];
