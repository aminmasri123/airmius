import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class DevicePermissionPrivacyParitySuiteScreen extends StatefulWidget {
  const DevicePermissionPrivacyParitySuiteScreen({super.key});

  @override
  State<DevicePermissionPrivacyParitySuiteScreen> createState() =>
      _DevicePermissionPrivacyParitySuiteScreenState();
}

class _DevicePermissionPrivacyParitySuiteScreenState
    extends State<DevicePermissionPrivacyParitySuiteScreen> {
  String _permission = 'Standort';
  String _status = 'Erlaubt';
  bool _showPurpose = true;
  bool _showFallback = true;
  bool _storeReadyCopy = true;

  static const _permissions = [
    'Standort',
    'Kamera',
    'Dateien',
    'Fotos',
    'Push',
    'Biometrie',
  ];
  static const _statuses = [
    'Erlaubt',
    'Einmalig',
    'Verweigert',
    'Noch nicht gefragt',
  ];

  static const _flows = <_PermissionFlow>[
    _PermissionFlow(
      permission: 'Standort',
      title: 'Standort für Sportkarte und Events',
      body:
          'Sportkarte, Routen, Treffpunkte, Fahrgemeinschaften, Vereinsadresse und Public-Orte brauchen klare Standort-Zweckbindung.',
      purpose: 'Karte, Route, Treffpunkt und Navigation',
      fallback: 'Ort manuell suchen oder Treffpunkt als Text anzeigen.',
      icon: Icons.location_on_outlined,
      color: AirmiusColors.green,
    ),
    _PermissionFlow(
      permission: 'Kamera',
      title: 'Kamera für Uploads und Scans',
      body:
          'Mitgliedsantrag-Anlagen, Vereinsdokumente, Profilbilder, Trainingsnachweise und Produktbilder können direkt aufgenommen werden.',
      purpose: 'Foto, Scan, Nachweis und Profilbild',
      fallback: 'Datei aus Galerie oder Dateimanager wählen.',
      icon: Icons.photo_camera_outlined,
      color: AirmiusColors.blue,
    ),
    _PermissionFlow(
      permission: 'Dateien',
      title: 'Dateizugriff für Dokumente',
      body:
          'Vereinsregeln, Datenschutz, SEPA, Chat-Anhaenge, Kursmaterial und Belege brauchen sicheren Dateiimport.',
      purpose: 'Dokumente hochladen und verknuepfen',
      fallback: 'Link eintragen oder später hochladen.',
      icon: Icons.folder_outlined,
      color: AirmiusColors.amber,
    ),
    _PermissionFlow(
      permission: 'Fotos',
      title: 'Fotos für Medien und Profil',
      body:
          'Profilbild, Club-Logo, Blogmedien, Marketplace-Galerie und Trainingsbilder werden mit Vorschau und Datenschutzstatus gefuehrt.',
      purpose: 'Medien aus Galerie auswählen',
      fallback: 'Standardavatar oder bestehendes Bild behalten.',
      icon: Icons.photo_library_outlined,
      color: AirmiusColors.blue,
    ),
    _PermissionFlow(
      permission: 'Push',
      title: 'Push für wichtige Updates',
      body:
          'Mitgliedschaftsanfragen, Rückzuege, Chat, Events, Zahlungen, Moderation und Guardian-Freigaben werden direkt zugestellt.',
      purpose: 'Benachrichtigungen und Deep Links',
      fallback: 'In-App Inbox und E-Mail-Fallback verwenden.',
      icon: Icons.notifications_none_outlined,
      color: AirmiusColors.green,
    ),
    _PermissionFlow(
      permission: 'Biometrie',
      title: 'Biometrie für sensible Aktionen',
      body:
          'Zahlungen, API-Token, Kontoaktionen, Adminentscheidungen und Datenschutzexport können später extra geschuetzt werden.',
      purpose: 'Sensible Aktionen sicher bestätigen',
      fallback: 'Passwort oder 2FA-Code verwenden.',
      icon: Icons.fingerprint,
      color: AirmiusColors.amber,
    ),
  ];

  List<_PermissionFlow> get _visibleFlows =>
      _flows.where((flow) => flow.permission == _permission).toList();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: const AirmiusLogo(compact: true),
      ),
      body: SafeArea(
        child: PageFrame(
          title: 'Device Permission Privacy Parity',
          subtitle:
              'Native Berechtigungen, Zweckbindung und Datenschutz-Prompts.',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _Hero(
                permission: _permission,
                status: _status,
                storeReadyCopy: _storeReadyCopy,
              ),
              const SizedBox(height: 16),
              _ChoicePanel(
                title: 'Berechtigung',
                items: _permissions,
                active: _permission,
                color: airmiusAccentColor(context),
                onChanged: (value) => setState(() => _permission = value),
              ),
              const SizedBox(height: 16),
              _ChoicePanel(
                title: 'Status',
                items: _statuses,
                active: _status,
                color: Theme.of(context).colorScheme.secondary,
                onChanged: (value) => setState(() => _status = value),
              ),
              const SizedBox(height: 16),
              _RulesPanel(
                showPurpose: _showPurpose,
                showFallback: _showFallback,
                storeReadyCopy: _storeReadyCopy,
                onPurpose: (value) => setState(() => _showPurpose = value),
                onFallback: (value) => setState(() => _showFallback = value),
                onStoreCopy: (value) => setState(() => _storeReadyCopy = value),
              ),
              const SizedBox(height: 16),
              _PermissionStatusPreview(
                permission: _permission,
                status: _status,
                showPurpose: _showPurpose,
                showFallback: _showFallback,
              ),
              const SizedBox(height: 16),
              for (final flow in _visibleFlows) ...[
                _PermissionFlowCard(
                  flow: flow,
                  status: _status,
                  storeReadyCopy: _storeReadyCopy,
                ),
                const SizedBox(height: 12),
              ],
              if (_visibleFlows.isEmpty)
                const EmptyPanel(
                  'Keine Permission-Flows für diese Auswahl sichtbar.',
                ),
              const SizedBox(height: 4),
              _Checklist(
                onOpen: () => openUiAction(
                  context,
                  title: 'Device Permission Privacy Parity',
                  body:
                      'Standort, Kamera, Dateien, Fotos, Push, Biometrie, Zweckbindung, Fallbacks und Store-ready Permission-Texte sind als mobile UI vorbereitet.',
                  status: 'Permissions',
                  icon: Icons.privacy_tip_outlined,
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
    required this.permission,
    required this.status,
    required this.storeReadyCopy,
  });

  final String permission;
  final String status;
  final bool storeReadyCopy;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      gradient: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Eyebrow('DEVICE PERMISSIONS'),
          const SizedBox(height: 8),
          Text(
            'Berechtigungen brauchen Vertrauen, nicht nur einen Systemdialog.',
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 24,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Flutter bereitet native Permission-Flows mit Zweck, Datenschutz, Fallback und Store-ready Begruendung vor, bevor Android oder iOS fragt.',
            style: TextStyle(
              color: airmiusMutedColor(context),
              height: 1.45,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 16),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _Metric(value: permission, label: 'Permission'),
              _Metric(value: status, label: 'Status'),
              _Metric(value: storeReadyCopy ? 'Store' : 'Kurz', label: 'Copy'),
              const _Metric(value: 'Privacy', label: 'Gate'),
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
    final textColor = airmiusTextColor(context);
    final mutedColor = airmiusMutedColor(context);
    final borderColor = airmiusBorderColor(context);
    final surfaceColor = airmiusSurfaceSoftColor(context);
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
                  backgroundColor: surfaceColor,
                  side: BorderSide(color: active == item ? color : borderColor),
                  labelStyle: TextStyle(
                    color: active == item ? textColor : mutedColor,
                    fontWeight: FontWeight.w900,
                  ),
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
    required this.showPurpose,
    required this.showFallback,
    required this.storeReadyCopy,
    required this.onPurpose,
    required this.onFallback,
    required this.onStoreCopy,
  });

  final bool showPurpose;
  final bool showFallback;
  final bool storeReadyCopy;
  final ValueChanged<bool> onPurpose;
  final ValueChanged<bool> onFallback;
  final ValueChanged<bool> onStoreCopy;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: 'Permission-Regeln',
      subtitle:
          'Diese Regeln sorgen dafür, dass Berechtigungen transparent und app-store-tauglich erklaert werden.',
      children: [
        _SwitchLine(
          title: 'Zweckbindung anzeigen',
          value: showPurpose,
          onChanged: onPurpose,
        ),
        _SwitchLine(
          title: 'Fallback-Aktion anbieten',
          value: showFallback,
          onChanged: onFallback,
        ),
        _SwitchLine(
          title: 'Store-ready Begruendung verwenden',
          value: storeReadyCopy,
          onChanged: onStoreCopy,
        ),
      ],
    );
  }
}

class _PermissionStatusPreview extends StatelessWidget {
  const _PermissionStatusPreview({
    required this.permission,
    required this.status,
    required this.showPurpose,
    required this.showFallback,
  });

  final String permission;
  final String status;
  final bool showPurpose;
  final bool showFallback;

  @override
  Widget build(BuildContext context) {
    final semanticColor = status == 'Verweigert'
        ? AirmiusColors.red
        : status == 'Noch nicht gefragt'
        ? AirmiusColors.amber
        : AirmiusColors.green;
    final color = airmiusSemanticColor(context, semanticColor);
    return AirmiusPanel(
      borderColor: color,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(_iconForPermission(permission), color: color, size: 32),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  '$permission · $status',
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              StatusPill(status, color: color),
            ],
          ),
          const SizedBox(height: 12),
          if (showPurpose)
            Text(
              _purposeForPermission(permission),
              style: TextStyle(
                color: airmiusMutedColor(context),
                height: 1.4,
                fontWeight: FontWeight.w700,
              ),
            ),
          if (showFallback) ...[
            const SizedBox(height: 8),
            Text(
              _fallbackForPermission(permission),
              style: TextStyle(
                color: airmiusAccentColor(context),
                height: 1.35,
                fontWeight: FontWeight.w900,
              ),
            ),
          ],
          const SizedBox(height: 14),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              AirmiusButton(
                label: status == 'Verweigert'
                    ? 'Einstellungen öffnen'
                    : 'Berechtigung fragen',
                icon: _iconForPermission(permission),
                onPressed: () => openUiAction(
                  context,
                  title: '$permission Berechtigung',
                  body:
                      'Status $status, Zweck ${_purposeForPermission(permission)} und Fallback ${_fallbackForPermission(permission)}.',
                  status: 'Permission',
                  icon: _iconForPermission(permission),
                ),
              ),
              AirmiusButton(
                label: 'Datenschutz',
                icon: Icons.privacy_tip_outlined,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: '$permission Datenschutz',
                  body:
                      'Zweckbindung, Widerruf, Datensparsamkeit, Guardian-Regeln und Store-Beschreibung für $permission.',
                  status: 'Privacy',
                  icon: Icons.privacy_tip_outlined,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _PermissionFlowCard extends StatelessWidget {
  const _PermissionFlowCard({
    required this.flow,
    required this.status,
    required this.storeReadyCopy,
  });

  final _PermissionFlow flow;
  final String status;
  final bool storeReadyCopy;

  @override
  Widget build(BuildContext context) {
    final color = airmiusSemanticColor(context, flow.color);
    return AirmiusPanel(
      borderColor: color.withValues(alpha: .55),
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
                  color: color.withValues(alpha: .14),
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: color.withValues(alpha: .55)),
                ),
                child: Icon(flow.icon, color: color),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      flow.title,
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontSize: 17,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      flow.purpose,
                      style: TextStyle(
                        color: airmiusAccentColor(context),
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
              StatusPill(status, color: color),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            flow.body,
            style: TextStyle(
              color: airmiusMutedColor(context),
              height: 1.42,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 10),
          Text(
            storeReadyCopy
                ? 'Store Copy: ${flow.purpose}. Du kannst diese Berechtigung jederzeit widerrufen.'
                : flow.fallback,
            style: TextStyle(
              color: Theme.of(context).colorScheme.secondary,
              height: 1.35,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              AirmiusButton(
                label: 'Flow öffnen',
                icon: flow.icon,
                onPressed: () => openUiAction(
                  context,
                  title: flow.title,
                  body:
                      '${flow.title}: ${flow.body}\n\nZweck: ${flow.purpose}\nFallback: ${flow.fallback}',
                  status: status,
                  icon: flow.icon,
                ),
              ),
              AirmiusButton(
                label: 'Fallback',
                icon: Icons.undo_outlined,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: '${flow.permission} Fallback',
                  body: flow.fallback,
                  status: 'Fallback',
                  icon: Icons.undo_outlined,
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
      title: 'Permission-/Privacy-Paritaet',
      subtitle: 'Was native Berechtigungen in Flutter erklaeren sollen.',
      children: [
        const _CheckLine(
          'Jede Berechtigung hat Zweckbindung, Datenschutztext, Status und Fallback.',
        ),
        const _CheckLine(
          'Standort, Kamera, Dateien, Fotos, Push und Biometrie werden getrennt erklaert.',
        ),
        const _CheckLine(
          'Verweigert, einmalig erlaubt und noch nicht gefragt sind eigene mobile Zustaende.',
        ),
        const _CheckLine(
          'Store-ready Begruendungen helfen später bei Android/iOS Review und User-Vertrauen.',
        ),
        const SizedBox(height: 12),
        AirmiusButton(
          label: 'Permission-Paritaet markieren',
          icon: Icons.fact_check_outlined,
          onPressed: onOpen,
        ),
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
        color: airmiusInputColor(context),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Row(
        children: [
          Expanded(
            child: Text(
              title,
              style: TextStyle(
                color: airmiusTextColor(context),
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          Switch(
            value: value,
            activeThumbColor: Theme.of(context).colorScheme.secondary,
            onChanged: onChanged,
          ),
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
          Icon(
            Icons.check_circle_outline,
            color: Theme.of(context).colorScheme.secondary,
            size: 19,
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              text,
              style: TextStyle(
                color: airmiusMutedColor(context),
                fontWeight: FontWeight.w700,
                height: 1.35,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Metric extends StatelessWidget {
  const _Metric({required this.value, required this.label});

  final String value;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: airmiusSurfaceColor(context).withValues(alpha: .55),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            value,
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 18,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            style: TextStyle(
              color: airmiusMutedColor(context),
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _PermissionFlow {
  const _PermissionFlow({
    required this.permission,
    required this.title,
    required this.body,
    required this.purpose,
    required this.fallback,
    required this.icon,
    required this.color,
  });

  final String permission;
  final String title;
  final String body;
  final String purpose;
  final String fallback;
  final IconData icon;
  final Color color;
}

IconData _iconForPermission(String permission) {
  if (permission == 'Kamera') return Icons.photo_camera_outlined;
  if (permission == 'Dateien') return Icons.folder_outlined;
  if (permission == 'Fotos') return Icons.photo_library_outlined;
  if (permission == 'Push') return Icons.notifications_none_outlined;
  if (permission == 'Biometrie') return Icons.fingerprint;
  return Icons.location_on_outlined;
}

String _purposeForPermission(String permission) {
  if (permission == 'Kamera') {
    return 'Damit du Fotos, Scans und Nachweise direkt in Airmius aufnehmen kannst.';
  }
  if (permission == 'Dateien') {
    return 'Damit du Dokumente, Belege und Anlagen sicher hochladen kannst.';
  }
  if (permission == 'Fotos') {
    return 'Damit du Profilbilder, Club-Logos und Medien aus deiner Galerie wählen kannst.';
  }
  if (permission == 'Push') {
    return 'Damit du wichtige Updates, Anfragen, Chat und Zahlungen nicht verpasst.';
  }
  if (permission == 'Biometrie') {
    return 'Damit sensible Aktionen später bequem und sicher bestätigt werden können.';
  }
  return 'Damit Karten, Routen, Treffpunkte und Standortvorschläge in Airmius funktionieren.';
}

String _fallbackForPermission(String permission) {
  if (permission == 'Kamera') {
    return 'Alternative: Datei oder Bild aus Galerie wählen.';
  }
  if (permission == 'Dateien') {
    return 'Alternative: Link eintragen oder Upload später nachholen.';
  }
  if (permission == 'Fotos') {
    return 'Alternative: Standardbild behalten oder Kamera nutzen.';
  }
  if (permission == 'Push') {
    return 'Alternative: In-App Inbox und E-Mail-Benachrichtigungen nutzen.';
  }
  if (permission == 'Biometrie') {
    return 'Alternative: Passwort oder 2FA-Code verwenden.';
  }
  return 'Alternative: Ort manuell suchen oder Textadresse verwenden.';
}
