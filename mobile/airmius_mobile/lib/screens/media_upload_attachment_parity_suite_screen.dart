import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class MediaUploadAttachmentParitySuiteScreen extends StatefulWidget {
  const MediaUploadAttachmentParitySuiteScreen({super.key});

  @override
  State<MediaUploadAttachmentParitySuiteScreen> createState() =>
      _MediaUploadAttachmentParitySuiteScreenState();
}

class _MediaUploadAttachmentParitySuiteScreenState
    extends State<MediaUploadAttachmentParitySuiteScreen> {
  String _source = 'Dateien';
  String _purpose = 'Vereinsdokument';
  bool _autoLinkToManager = true;
  bool _needsPrivacyScope = true;
  bool _showUploadProgress = true;

  static const _sources = ['Dateien', 'Kamera', 'Galerie', 'Scan'];
  static const _purposes = [
    'Vereinsdokument',
    'Profilbild',
    'Chat',
    'Blog',
    'Marketplace',
    'Training',
  ];

  static const _flows = <_UploadFlow>[
    _UploadFlow(
      title: 'Vereinsdokument hochladen',
      route: 'ClubPolicyDocuments + Files/Index',
      purpose: 'Vereinsdokument',
      body:
          'Datenschutz, Satzung, Beitragsordnung, SEPA-Mandat und Regeln können hochgeladen, versioniert und automatisch im Dateimanager des Vereins verknüpft werden.',
      status: 'Pflichtdokument',
      icon: Icons.rule_folder_outlined,
      primary: 'Dokument hochladen',
      secondary: 'Dateimanager',
      color: AirmiusColors.green,
    ),
    _UploadFlow(
      title: 'Mitgliedsantrag Anlage',
      route: 'MembershipApplicationForm',
      purpose: 'Vereinsdokument',
      body:
          'Ausweis, Lizenz, Einwilligung, Guardian-Nachweis oder club-spezifische Pflichtanlage mit Uploadstatus und Rückzugsschutz.',
      status: 'Antragsanlage',
      icon: Icons.assignment_ind_outlined,
      primary: 'Anlage auswählen',
      secondary: 'Pflichtfelder',
      color: AirmiusColors.blue,
    ),
    _UploadFlow(
      title: 'Profilbild und Club-Logo',
      route: 'Profile/Show + ClubProfileEditor',
      purpose: 'Profilbild',
      body:
          'Avatar, Vereinslogo, Banner, Zuschnitt, Vorschau, Entfernen und Sichtbarkeit werden als mobile Medienkarte vorbereitet.',
      status: 'Bild',
      icon: Icons.image_outlined,
      primary: 'Bild wählen',
      secondary: 'Zuschneiden',
      color: AirmiusColors.blue,
    ),
    _UploadFlow(
      title: 'Chat-Anhang',
      route: 'Chat/Index + Conversations',
      purpose: 'Chat',
      body:
          'Bilder, PDFs, Trainingspläne oder Vereinsdateien werden als Message Attachment mit Preview, Uploadstatus und Zugriffskontext gezeigt.',
      status: 'Attachment',
      icon: Icons.attach_file,
      primary: 'Anhang senden',
      secondary: 'Preview',
      color: AirmiusColors.green,
    ),
    _UploadFlow(
      title: 'Blog- und Mediencenter',
      route: 'Blogs/Index + BlogMediaCenter',
      purpose: 'Blog',
      body:
          'Titelbild, Galerie, Alt-Text, Copyright, Public Preview, Freigabe und Kategoriebezug als mobile Uploadstrecke.',
      status: 'Editorial',
      icon: Icons.article_outlined,
      primary: 'Medium laden',
      secondary: 'Alt-Text',
      color: AirmiusColors.amber,
    ),
    _UploadFlow(
      title: 'Marketplace Produktbilder',
      route: 'Commerce/ProductShow',
      purpose: 'Marketplace',
      body:
          'Produktbilder, Variantenbilder, Anbieter-Assets, Reihenfolge, Preview und Moderationsstatus für mobile Commerce-UI.',
      status: 'Product Media',
      icon: Icons.inventory_2_outlined,
      primary: 'Produktbild',
      secondary: 'Galerie',
      color: AirmiusColors.green,
    ),
    _UploadFlow(
      title: 'Trainingsnachweis',
      route: 'Training/LogCreate + LogShow',
      purpose: 'Training',
      body:
          'Foto, Video, Dokument, Route oder Messwert-Anhang für Trainingslog mit Coach-Sichtbarkeit und Maturity-Gate.',
      status: 'Evidence',
      icon: Icons.fitness_center_outlined,
      primary: 'Nachweis',
      secondary: 'Coach-Freigabe',
      color: AirmiusColors.blue,
    ),
  ];

  List<_UploadFlow> get _visibleFlows =>
      _flows.where((flow) => flow.purpose == _purpose).toList();

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
          title: 'Media Upload Attachment Parity',
          subtitle: 'Uploads, Medien, Vorschau und Dateimanager-Verknüpfung.',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _Hero(
                source: _source,
                purpose: _purpose,
                autoLinkToManager: _autoLinkToManager,
                showUploadProgress: _showUploadProgress,
              ),
              const SizedBox(height: 16),
              _UploadDropZone(
                source: _source,
                onOpen: () => openUiAction(
                  context,
                  title: 'Upload starten',
                  body:
                      'Quelle $_source, Zweck $_purpose, Datenschutz $_needsPrivacyScope und Dateimanager-Verknüpfung $_autoLinkToManager als mobile Upload-Aktion vorbereiten.',
                  status: 'Upload',
                  icon: Icons.upload_file_outlined,
                ),
              ),
              const SizedBox(height: 16),
              _ChoicePanel(
                title: 'Quelle',
                items: _sources,
                active: _source,
                onChanged: (value) => setState(() => _source = value),
                green: false,
              ),
              const SizedBox(height: 16),
              _ChoicePanel(
                title: 'Zweckbindung',
                items: _purposes,
                active: _purpose,
                onChanged: (value) => setState(() => _purpose = value),
                green: true,
              ),
              const SizedBox(height: 16),
              _SwitchPanel(
                autoLinkToManager: _autoLinkToManager,
                needsPrivacyScope: _needsPrivacyScope,
                showUploadProgress: _showUploadProgress,
                onAutoLink: (value) =>
                    setState(() => _autoLinkToManager = value),
                onPrivacy: (value) =>
                    setState(() => _needsPrivacyScope = value),
                onProgress: (value) =>
                    setState(() => _showUploadProgress = value),
              ),
              const SizedBox(height: 16),
              if (_showUploadProgress) const _ProgressPanel(),
              if (_showUploadProgress) const SizedBox(height: 16),
              for (final flow in _visibleFlows) ...[
                _UploadFlowCard(flow: flow),
                const SizedBox(height: 12),
              ],
              if (_visibleFlows.isEmpty)
                const EmptyPanel(
                  'Keine Upload-Flows für diesen Zweck sichtbar.',
                ),
              const SizedBox(height: 4),
              _Checklist(
                onOpen: () => openUiAction(
                  context,
                  title: 'Upload Parity',
                  body:
                      'Dateien, Bilder, Scans, Chat-Anhänge, Vereinsdokumente, Produktbilder, Blogmedien und Trainingsnachweise sind als mobile UI-Flows vorbereitet.',
                  status: 'Media Upload',
                  icon: Icons.cloud_upload_outlined,
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
    required this.source,
    required this.purpose,
    required this.autoLinkToManager,
    required this.showUploadProgress,
  });

  final String source;
  final String purpose;
  final bool autoLinkToManager;
  final bool showUploadProgress;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      gradient: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Eyebrow('UPLOADS & MEDIEN'),
          const SizedBox(height: 8),
          Text(
            'Uploads müssen mobil einfach, sicher und verknüpft sein.',
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 24,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Flutter bildet Upload-Auswahl, Kamera/Galerie, Scan, Vorschau, Fortschritt, Datenschutz, Zweckbindung und Dateimanager-Verknüpfung als native App-Flows ab.',
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
              _Metric(value: source, label: 'Quelle'),
              _Metric(value: purpose, label: 'Zweck'),
              _Metric(
                value: autoLinkToManager ? 'Auto' : 'Manuell',
                label: 'Dateimanager',
              ),
              _Metric(
                value: showUploadProgress ? 'Live' : 'Still',
                label: 'Fortschritt',
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _UploadDropZone extends StatelessWidget {
  const _UploadDropZone({required this.source, required this.onOpen});

  final String source;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      borderColor: airmiusAccentColor(context),
      child: Column(
        children: [
          Container(
            width: 74,
            height: 74,
            decoration: BoxDecoration(
              color: airmiusAccentColor(context).withValues(alpha: .14),
              borderRadius: BorderRadius.circular(24),
              border: Border.all(
                color: airmiusAccentColor(context).withValues(alpha: .65),
              ),
            ),
            child: Icon(
              Icons.cloud_upload_outlined,
              color: airmiusAccentColor(context),
              size: 34,
            ),
          ),
          const SizedBox(height: 12),
          Text(
            'Quelle: $source',
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 18,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            'Datei auswählen, Kamera starten, Bild scannen oder bestehende Vereinsdatei verknüpfen.',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: airmiusMutedColor(context),
              height: 1.4,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 14),
          AirmiusButton(
            label: 'Upload simulieren',
            icon: Icons.upload_file_outlined,
            onPressed: onOpen,
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
    required this.green,
  });

  final String title;
  final List<String> items;
  final String active;
  final ValueChanged<String> onChanged;
  final bool green;

  @override
  Widget build(BuildContext context) {
    final color = green
        ? Theme.of(context).colorScheme.secondary
        : airmiusAccentColor(context);
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
                  label: Text(item),
                  selected: active == item,
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

class _SwitchPanel extends StatelessWidget {
  const _SwitchPanel({
    required this.autoLinkToManager,
    required this.needsPrivacyScope,
    required this.showUploadProgress,
    required this.onAutoLink,
    required this.onPrivacy,
    required this.onProgress,
  });

  final bool autoLinkToManager;
  final bool needsPrivacyScope;
  final bool showUploadProgress;
  final ValueChanged<bool> onAutoLink;
  final ValueChanged<bool> onPrivacy;
  final ValueChanged<bool> onProgress;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: 'Upload-Regeln',
      subtitle:
          'Diese Optionen werden später aus Route, Verein, Rolle und Laravel-API geladen.',
      children: [
        _SwitchLine(
          title: 'Automatisch im Dateimanager verknüpfen',
          value: autoLinkToManager,
          onChanged: onAutoLink,
        ),
        _SwitchLine(
          title: 'Datenschutz-/Zweckbindung verlangen',
          value: needsPrivacyScope,
          onChanged: onPrivacy,
        ),
        _SwitchLine(
          title: 'Upload-Fortschritt anzeigen',
          value: showUploadProgress,
          onChanged: onProgress,
        ),
      ],
    );
  }
}

class _ProgressPanel extends StatelessWidget {
  const _ProgressPanel();

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: 'Uploadstatus',
      subtitle:
          'Mobile Vorschau für Fortschritt, Validierung und Verarbeitung.',
      children: const [
        _ProgressLine(label: 'Auswahl validieren', value: .92, status: 'OK'),
        _ProgressLine(
          label: 'Upload zu Laravel Storage',
          value: .64,
          status: 'Läuft',
        ),
        _ProgressLine(
          label: 'Dateimanager verknüpfen',
          value: .38,
          status: 'Wartet',
        ),
      ],
    );
  }
}

class _ProgressLine extends StatelessWidget {
  const _ProgressLine({
    required this.label,
    required this.value,
    required this.status,
  });

  final String label;
  final double value;
  final String status;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Wrap(
            spacing: 8,
            runSpacing: 6,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              Text(
                label,
                style: TextStyle(
                  color: airmiusTextColor(context),
                  fontWeight: FontWeight.w900,
                ),
              ),
              StatusPill(
                status,
                color: value > .8
                    ? Theme.of(context).colorScheme.secondary
                    : Theme.of(context).colorScheme.tertiary,
              ),
            ],
          ),
          const SizedBox(height: 8),
          LinearProgressIndicator(
            value: value,
            minHeight: 8,
            borderRadius: BorderRadius.circular(99),
            backgroundColor: airmiusSurfaceSoftColor(context),
            valueColor: AlwaysStoppedAnimation<Color>(
              value > .8
                  ? Theme.of(context).colorScheme.secondary
                  : airmiusAccentColor(context),
            ),
          ),
        ],
      ),
    );
  }
}

class _UploadFlowCard extends StatelessWidget {
  const _UploadFlowCard({required this.flow});

  final _UploadFlow flow;

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
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontSize: 17,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      flow.route,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: airmiusAccentColor(context),
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Align(
            alignment: AlignmentDirectional.centerStart,
            child: StatusPill(flow.status, color: color),
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
          const SizedBox(height: 14),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              AirmiusButton(
                label: flow.primary,
                icon: flow.icon,
                onPressed: () => openUiAction(
                  context,
                  title: flow.primary,
                  body: '${flow.title}: ${flow.body}\n\nRoute: ${flow.route}',
                  status: flow.status,
                  icon: flow.icon,
                ),
              ),
              AirmiusButton(
                label: flow.secondary,
                icon: Icons.link_outlined,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: flow.secondary,
                  body:
                      'Dateimanager, Vorschau, Datenschutz, Version, Zweckbindung und Audit für ${flow.title}.',
                  status: 'Verknüpfung',
                  icon: Icons.link_outlined,
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
      title: 'Upload-Parität',
      subtitle: 'Was mobile Uploads aus der Web-App übernehmen.',
      children: [
        const _CheckLine(
          'Kamera, Galerie, Dateien und Scan werden als Quellen vorbereitet.',
        ),
        const _CheckLine(
          'Jeder Upload hat Zweckbindung, Datenschutzstatus, Vorschau und Fortschritt.',
        ),
        const _CheckLine(
          'Vereinsdokumente können automatisch im Dateimanager verknüpft werden.',
        ),
        const _CheckLine(
          'Chat, Blog, Marketplace, Training und Profil nutzen ein gemeinsames Medienmuster.',
        ),
        const SizedBox(height: 12),
        AirmiusButton(
          label: 'Upload-Parität markieren',
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

class _UploadFlow {
  const _UploadFlow({
    required this.title,
    required this.route,
    required this.purpose,
    required this.body,
    required this.status,
    required this.icon,
    required this.primary,
    required this.secondary,
    required this.color,
  });

  final String title;
  final String route;
  final String purpose;
  final String body;
  final String status;
  final IconData icon;
  final String primary;
  final String secondary;
  final Color color;
}
