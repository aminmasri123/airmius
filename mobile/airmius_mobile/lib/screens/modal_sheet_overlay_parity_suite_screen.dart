import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class ModalSheetOverlayParitySuiteScreen extends StatefulWidget {
  const ModalSheetOverlayParitySuiteScreen({super.key});

  @override
  State<ModalSheetOverlayParitySuiteScreen> createState() => _ModalSheetOverlayParitySuiteScreenState();
}

class _ModalSheetOverlayParitySuiteScreenState extends State<ModalSheetOverlayParitySuiteScreen> {
  String _mode = 'Bottom Sheet';
  String _density = 'Mobile';
  bool _fullScreenOnMobile = true;
  bool _stickyActions = true;
  bool _dangerConfirm = true;

  static const _modes = ['Bottom Sheet', 'Dialog', 'Fullscreen', 'Drawer'];
  static const _densities = ['Mobile', 'Tablet', 'Desktop'];

  static const _patterns = <_OverlayPattern>[
    _OverlayPattern(
      title: 'Anfrage zurückziehen',
      source: 'Club Profile / Membership Request Status',
      body: 'Bestätigungsdialog mit Vereinsname, Konsequenz, Rückzug-CTA, Abbrechen und gut lesbarem Kontrast.',
      status: 'Confirm',
      icon: Icons.undo_outlined,
      primary: 'Rückzug zeigen',
      secondary: 'Kontrast prüfen',
      color: AirmiusColors.red,
    ),
    _OverlayPattern(
      title: 'Mitgliedsantrag ausfuellen',
      source: 'Membership Application Form',
      body: 'Langes Formular wird mobil als Fullscreen-Modal mit eigenem Scrollbereich, Sticky Footer und Abschnittsnavigation gefuehrt.',
      status: 'Fullscreen',
      icon: Icons.assignment_add,
      primary: 'Formularmodal',
      secondary: 'Sticky Footer',
      color: AirmiusColors.blue,
    ),
    _OverlayPattern(
      title: 'Tabellenfilter',
      source: 'Admin, Club, Commerce, Files',
      body: 'Filterspalten aus der Web-App werden als Bottom-Sheet mit Chips, Suche, Reset, Anwenden und aktivem Filterzaehler abgebildet.',
      status: 'Filter Sheet',
      icon: Icons.filter_alt_outlined,
      primary: 'Filter öffnen',
      secondary: 'Reset',
      color: AirmiusColors.green,
    ),
    _OverlayPattern(
      title: 'Datei-Vorschau',
      source: 'Files / Club Documents / Chat Attachments',
      body: 'PDF, Bild oder Dokument wird in einem Preview-Sheet mit Download, Teilen, Verknuepfen, Version und Datenschutzstatus gezeigt.',
      status: 'Preview',
      icon: Icons.visibility_outlined,
      primary: 'Preview',
      secondary: 'Verknuepfen',
      color: AirmiusColors.amber,
    ),
    _OverlayPattern(
      title: 'Zahlungsdialog',
      source: 'Checkout / BankTransfer / Member Payments',
      body: 'Bankdaten, IBAN, Verwendungszweck, Betrag, Rechnung und Zahlung bestätigen als mobile Dialog-/Sheet-Kombination.',
      status: 'Payment',
      icon: Icons.payments_outlined,
      primary: 'Zahlung',
      secondary: 'Beleg',
      color: AirmiusColors.green,
    ),
    _OverlayPattern(
      title: 'Danger Action',
      source: 'Admin Moderation / User / Club',
      body: 'Sperren, löschen, ablehnen oder entfernen braucht Grund, Audit, Bestätigungstext und klare rote Aktion.',
      status: 'Danger',
      icon: Icons.warning_amber_outlined,
      primary: 'Bestätigen',
      secondary: 'Audit',
      color: AirmiusColors.red,
    ),
    _OverlayPattern(
      title: 'Workspace Drawer',
      source: 'Mobile Navigation / Sidebar',
      body: 'Desktop-Sidebar wird mobil zum Drawer mit Workspace Switcher, Rollenstatus, Suchfeld, Badges und Hauptmodulen.',
      status: 'Drawer',
      icon: Icons.menu_open_outlined,
      primary: 'Drawer',
      secondary: 'Workspace',
      color: AirmiusColors.blue,
    ),
    _OverlayPattern(
      title: 'Success / Error Overlay',
      source: 'Forms / API Actions',
      body: 'Nach Speichern, Ablehnen, Upload, Checkout oder API-Fehler zeigt Flutter ein klares Ergebnis mit naechster Aktion.',
      status: 'Feedback',
      icon: Icons.task_alt_outlined,
      primary: 'Success',
      secondary: 'Error',
      color: AirmiusColors.green,
    ),
  ];

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
          title: 'Modal Sheet Overlay Parity',
          subtitle: 'Web-Modals werden mobile Dialoge, Sheets und Drawer.',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _Hero(
                mode: _mode,
                density: _density,
                fullScreenOnMobile: _fullScreenOnMobile,
                stickyActions: _stickyActions,
              ),
              const SizedBox(height: 16),
              _ChoicePanel(
                title: 'Overlay-Typ',
                items: _modes,
                active: _mode,
                onChanged: (value) => setState(() => _mode = value),
                color: AirmiusColors.blue,
              ),
              const SizedBox(height: 16),
              _ChoicePanel(
                title: 'Layout-Dichte',
                items: _densities,
                active: _density,
                onChanged: (value) => setState(() => _density = value),
                color: AirmiusColors.green,
              ),
              const SizedBox(height: 16),
              _RulesPanel(
                fullScreenOnMobile: _fullScreenOnMobile,
                stickyActions: _stickyActions,
                dangerConfirm: _dangerConfirm,
                onFullScreen: (value) => setState(() => _fullScreenOnMobile = value),
                onSticky: (value) => setState(() => _stickyActions = value),
                onDanger: (value) => setState(() => _dangerConfirm = value),
              ),
              const SizedBox(height: 16),
              _OverlayPreview(
                mode: _mode,
                density: _density,
                stickyActions: _stickyActions,
                dangerConfirm: _dangerConfirm,
              ),
              const SizedBox(height: 16),
              for (final pattern in _patterns) ...[
                _PatternCard(pattern: pattern),
                const SizedBox(height: 12),
              ],
              const SizedBox(height: 4),
              _Checklist(
                onOpen: () => openUiAction(
                  context,
                  title: 'Overlay Parity',
                  body: 'Web-Modals, Confirmations, Filter, Preview, Drawer, Fullscreen Forms und Action Feedback sind als mobile Overlay-Muster vorbereitet.',
                  status: 'Overlay',
                  icon: Icons.layers_outlined,
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
    required this.mode,
    required this.density,
    required this.fullScreenOnMobile,
    required this.stickyActions,
  });

  final String mode;
  final String density;
  final bool fullScreenOnMobile;
  final bool stickyActions;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      gradient: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Eyebrow('MODALS & SHEETS'),
          const SizedBox(height: 8),
          const Text(
            'Mobile Overlays dürfen nicht wie gequetschte Web-Modals wirken.',
            style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 8),
          const Text(
            'Flutter bekommt klare Regeln für Dialoge, Bottom-Sheets, Drawer, Fullscreen-Formulare, Scrollbereiche, Sticky Actions, Danger-Confirmations und Ergebnis-Overlays.',
            style: TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 16),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _Metric(value: mode, label: 'Typ'),
              _Metric(value: density, label: 'Dichte'),
              _Metric(value: fullScreenOnMobile ? 'Ja' : 'Nein', label: 'Fullscreen mobil'),
              _Metric(value: stickyActions ? 'Sticky' : 'Inline', label: 'Aktionen'),
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
    required this.onChanged,
    required this.color,
  });

  final String title;
  final List<String> items;
  final String active;
  final ValueChanged<String> onChanged;
  final Color color;

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
    required this.fullScreenOnMobile,
    required this.stickyActions,
    required this.dangerConfirm,
    required this.onFullScreen,
    required this.onSticky,
    required this.onDanger,
  });

  final bool fullScreenOnMobile;
  final bool stickyActions;
  final bool dangerConfirm;
  final ValueChanged<bool> onFullScreen;
  final ValueChanged<bool> onSticky;
  final ValueChanged<bool> onDanger;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: 'Responsive Overlay-Regeln',
      subtitle: 'Diese Regeln verhindern unlesbare, zu schmale oder nicht scrollbare Modals.',
      children: [
        _SwitchLine(title: 'Lange Formulare mobil fullscreen', value: fullScreenOnMobile, onChanged: onFullScreen),
        _SwitchLine(title: 'Aktionen unten sticky halten', value: stickyActions, onChanged: onSticky),
        _SwitchLine(title: 'Danger-Aktionen mit Pflichtbestätigung', value: dangerConfirm, onChanged: onDanger),
      ],
    );
  }
}

class _OverlayPreview extends StatelessWidget {
  const _OverlayPreview({
    required this.mode,
    required this.density,
    required this.stickyActions,
    required this.dangerConfirm,
  });

  final String mode;
  final String density;
  final bool stickyActions;
  final bool dangerConfirm;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      borderColor: mode == 'Dialog' ? AirmiusColors.amber : AirmiusColors.blue,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: AirmiusColors.blue.withValues(alpha: .14),
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: AirmiusColors.blue.withValues(alpha: .6)),
                ),
                child: const Icon(Icons.layers_outlined, color: AirmiusColors.blue),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('$mode Preview', style: const TextStyle(color: AirmiusColors.text, fontSize: 18, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 4),
                    Text('Layout: $density', style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700)),
                  ],
                ),
              ),
              StatusPill(stickyActions ? 'Sticky CTA' : 'Inline CTA', color: AirmiusColors.green),
            ],
          ),
          const SizedBox(height: 12),
          const Text(
            'Beispielinhalt mit eigenem Scrollbereich, lesbarem Kontrast, klarer Schließen-Aktion und festen Buttons am unteren Rand.',
            style: TextStyle(color: AirmiusColors.muted, height: 1.42, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              AirmiusButton(
                label: 'Overlay öffnen',
                icon: Icons.open_in_full_outlined,
                onPressed: () => openUiAction(
                  context,
                  title: '$mode Overlay',
                  body: 'Mobile $mode Vorschau mit $density-Dichte, Sticky Actions $stickyActions und Danger Confirm $dangerConfirm.',
                  status: 'Preview',
                  icon: Icons.layers_outlined,
                ),
              ),
              AirmiusButton(
                label: 'Abbrechen',
                icon: Icons.close_outlined,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: 'Overlay abbrechen',
                  body: 'Abbrechen, Schließen, Back-Button und Dirty-State-Schutz werden als mobile Overlay-Regeln vorbereitet.',
                  status: 'Cancel',
                  icon: Icons.close_outlined,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _PatternCard extends StatelessWidget {
  const _PatternCard({required this.pattern});

  final _OverlayPattern pattern;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      borderColor: pattern.color.withValues(alpha: .55),
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
                  color: pattern.color.withValues(alpha: .14),
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: pattern.color.withValues(alpha: .55)),
                ),
                child: Icon(pattern.icon, color: pattern.color),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(pattern.title, style: const TextStyle(color: AirmiusColors.text, fontSize: 17, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 5),
                    Text(pattern.source, style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w800)),
                  ],
                ),
              ),
              StatusPill(pattern.status, color: pattern.color),
            ],
          ),
          const SizedBox(height: 12),
          Text(pattern.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.42, fontWeight: FontWeight.w700)),
          const SizedBox(height: 14),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              AirmiusButton(
                label: pattern.primary,
                icon: pattern.icon,
                danger: pattern.color == AirmiusColors.red,
                onPressed: () => openUiAction(
                  context,
                  title: pattern.primary,
                  body: '${pattern.title}: ${pattern.body}\n\nQuelle: ${pattern.source}',
                  status: pattern.status,
                  icon: pattern.icon,
                ),
              ),
              AirmiusButton(
                label: pattern.secondary,
                icon: Icons.tune_outlined,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: pattern.secondary,
                  body: 'Overlay-Regeln, Scrollbereich, Back-Button, Sticky CTA, Accessibility und API-Fehler für ${pattern.title}.',
                  status: 'Overlay Detail',
                  icon: Icons.tune_outlined,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _Checklist extends StatelessWidget {
  const _Checklist({required this.onOpen});

  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: 'Overlay-Paritaet',
      subtitle: 'Was aus Web-Modals mobil übernommen wird.',
      children: [
        const _CheckLine('Lange Modals werden mobil fullscreen und bekommen eigenen Scrollbereich.'),
        const _CheckLine('Buttons bleiben sichtbar und werden bei langen Formularen sticky unten gefuehrt.'),
        const _CheckLine('Danger-Aktionen brauchen klare Warnung, Grund, Bestätigung und Audit-Hinweis.'),
        const _CheckLine('Filter, Preview, Drawer und Ergebnisdialoge folgen einem gemeinsamen Airmius-Muster.'),
        const SizedBox(height: 12),
        AirmiusButton(label: 'Overlay-Paritaet markieren', icon: Icons.fact_check_outlined, onPressed: onOpen),
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

class _OverlayPattern {
  const _OverlayPattern({
    required this.title,
    required this.source,
    required this.body,
    required this.status,
    required this.icon,
    required this.primary,
    required this.secondary,
    required this.color,
  });

  final String title;
  final String source;
  final String body;
  final String status;
  final IconData icon;
  final String primary;
  final String secondary;
  final Color color;
}
