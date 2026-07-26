import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class ClubPublicProfilePreviewSuiteScreen extends StatefulWidget {
  const ClubPublicProfilePreviewSuiteScreen({super.key});

  @override
  State<ClubPublicProfilePreviewSuiteScreen> createState() =>
      _ClubPublicProfilePreviewSuiteScreenState();
}

class _ClubPublicProfilePreviewSuiteScreenState
    extends State<ClubPublicProfilePreviewSuiteScreen> {
  bool _requestSent = false;
  bool _showMembers = true;
  bool _showTeams = true;
  bool _showDocuments = true;
  bool _showContact = true;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: const Text(
          'Vereinsprofil Vorschau',
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: 'Club Public Profile Preview',
        subtitle:
            'Mobile Clubseite mit Web-App-Hero, sichtbaren Bereichen, Mitgliedschafts-CTA und Vereinsregeln.',
        trailing: StatusPill('Public UI', color: airmiusAccentColor(context)),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _ClubHero(
              requestSent: _requestSent,
              onRequest: () => setState(() => _requestSent = !_requestSent),
            ),
            const SizedBox(height: 14),
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
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('VEREINSSICHTBARKEIT'),
                  const SizedBox(height: 10),
                  _VisibilityToggle(
                    title: 'Kontaktdaten anzeigen',
                    body:
                        'E-Mail, Telefon, Webseite und Ansprechpartner erscheinen nur, wenn der Verein sie freigibt.',
                    value: _showContact,
                    onChanged: (value) => setState(() => _showContact = value),
                  ),
                  _VisibilityToggle(
                    title: 'Teams anzeigen',
                    body:
                        'Teamlisten bleiben optional, damit Vereine Jugend-, Trainer- oder interne Teams schuetzen können.',
                    value: _showTeams,
                    onChanged: (value) => setState(() => _showTeams = value),
                  ),
                  _VisibilityToggle(
                    title: 'Mitglieder anzeigen',
                    body:
                        'Mitgliederzahlen und einzelne Mitglieder werden getrennt steuerbar vorbereitet.',
                    value: _showMembers,
                    onChanged: (value) => setState(() => _showMembers = value),
                  ),
                  _VisibilityToggle(
                    title: 'Dokumente anzeigen',
                    body:
                        'Datenschutz, Satzung, Beitragsordnung und Regeln können sichtbar oder nur im Antrag verknuepft sein.',
                    value: _showDocuments,
                    onChanged: (value) =>
                        setState(() => _showDocuments = value),
                    last: true,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            if (_showContact) ...[
              const _InfoPanel(
                title: 'Kontakt',
                icon: Icons.contact_mail_outlined,
                rows: [
                  _InfoRow(label: 'E-Mail', value: 'verein@airmius.local'),
                  _InfoRow(label: 'Ort', value: 'Kleinblittersdorf'),
                  _InfoRow(label: 'Status', value: 'Nimmt Anfragen an'),
                ],
              ),
              const SizedBox(height: 14),
            ],
            if (_showDocuments) ...[
              const _InfoPanel(
                title: 'Vereinsdokumente',
                icon: Icons.rule_folder_outlined,
                rows: [
                  _InfoRow(label: 'Datenschutz', value: 'Version 2026.1'),
                  _InfoRow(label: 'Satzung', value: 'Im Antrag bestätigen'),
                  _InfoRow(label: 'Beitragsordnung', value: 'Upload oder Link'),
                ],
              ),
              const SizedBox(height: 14),
            ],
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: _MiniSection(
                    title: 'Teams',
                    empty: !_showTeams,
                    body: _showTeams
                        ? 'Noch keine sichtbaren Teams.'
                        : 'Vom Verein ausgeblendet.',
                    icon: Icons.groups_2_outlined,
                    color: AirmiusColors.green,
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _MiniSection(
                    title: 'Admins',
                    empty: false,
                    body: 'verein airmius\nAdmin',
                    icon: Icons.admin_panel_settings_outlined,
                    color: AirmiusColors.blue,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              borderColor:
                  (_requestSent
                          ? Theme.of(context).colorScheme.secondary
                          : airmiusAccentColor(context))
                      .withValues(alpha: .45),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('MITGLIEDSCHAFT'),
                  const SizedBox(height: 8),
                  Text(
                    _requestSent
                        ? 'Anfrage gesendet'
                        : 'Mitgliedschaft beantragen',
                    style: TextStyle(
                      color: airmiusTextColor(context),
                      fontSize: 18,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    _requestSent
                        ? 'Der User sieht den Status und kann die Anfrage direkt zurückziehen, solange der Verein noch nicht entschieden hat.'
                        : 'Der CTA fuehrt zum dynamischen Formular mit Vereinsfeldern, Dokumenten, Zahlungsdaten und Datenschutzbestätigung.',
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      height: 1.38,
                    ),
                  ),
                  const SizedBox(height: 12),
                  AirmiusButton(
                    label: _requestSent
                        ? 'Anfrage zurückziehen'
                        : 'Mitgliedschaft anfragen',
                    icon: _requestSent
                        ? Icons.undo_outlined
                        : Icons.assignment_add,
                    secondary: _requestSent,
                    onPressed: () =>
                        setState(() => _requestSent = !_requestSent),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ClubHero extends StatelessWidget {
  const _ClubHero({required this.requestSent, required this.onRequest});

  final bool requestSent;
  final VoidCallback onRequest;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: ConstrainedBox(
        constraints: const BoxConstraints(minHeight: 220),
        child: Container(
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(20),
            color: Theme.of(context).colorScheme.primary,
          ),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.end,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  Container(
                    width: 58,
                    height: 58,
                    decoration: BoxDecoration(
                      color: Theme.of(context).colorScheme.onPrimary,
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: Center(
                      child: Text(
                        'Z',
                        style: TextStyle(
                          color: Theme.of(context).colorScheme.primary,
                          fontSize: 28,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'ZBB',
                          style: TextStyle(
                            color: Theme.of(context).colorScheme.onPrimary,
                            fontSize: 24,
                            fontWeight: FontWeight.w900,
                            shadows: [
                              Shadow(color: Colors.black54, blurRadius: 5),
                            ],
                          ),
                        ),
                        SizedBox(height: 2),
                        Text(
                          'Verein - Profil',
                          style: TextStyle(
                            color: Theme.of(context).colorScheme.onPrimary,
                            fontWeight: FontWeight.w800,
                            shadows: [
                              Shadow(color: Colors.black54, blurRadius: 5),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 18),
              Align(
                alignment: Alignment.centerRight,
                child: OutlinedButton.icon(
                  onPressed: onRequest,
                  icon: Icon(
                    requestSent
                        ? Icons.check_circle_outline
                        : Icons.assignment_add,
                    size: 18,
                  ),
                  label: Text(
                    requestSent ? 'Anfrage gesendet' : 'Mitglied werden',
                  ),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: requestSent
                        ? Theme.of(context).colorScheme.secondary
                        : Theme.of(context).colorScheme.primary,
                    backgroundColor: Theme.of(
                      context,
                    ).colorScheme.surface.withValues(alpha: .86),
                    side: BorderSide(
                      color: requestSent
                          ? Theme.of(context).colorScheme.secondary
                          : Theme.of(context).colorScheme.primary,
                    ),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _VisibilityToggle extends StatelessWidget {
  const _VisibilityToggle({
    required this.title,
    required this.body,
    required this.value,
    required this.onChanged,
    this.last = false,
  });

  final String title;
  final String body;
  final bool value;
  final ValueChanged<bool> onChanged;
  final bool last;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(bottom: last ? 0 : 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            value ? Icons.visibility_outlined : Icons.visibility_off_outlined,
            color: value
                ? Theme.of(context).colorScheme.secondary
                : airmiusMutedColor(context),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  body,
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    height: 1.35,
                  ),
                ),
              ],
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

class _InfoPanel extends StatelessWidget {
  const _InfoPanel({
    required this.title,
    required this.icon,
    required this.rows,
  });

  final String title;
  final IconData icon;
  final List<_InfoRow> rows;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Icon(icon, color: airmiusAccentColor(context)),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  title,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          for (final row in rows) ...[
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                SizedBox(
                  width: 118,
                  child: Text(
                    row.label,
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                Expanded(
                  child: Text(
                    row.value,
                    style: TextStyle(
                      color: airmiusTextColor(context),
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ],
            ),
            if (row != rows.last) const SizedBox(height: 8),
          ],
        ],
      ),
    );
  }
}

class _InfoRow {
  const _InfoRow({required this.label, required this.value});

  final String label;
  final String value;
}

class _MiniSection extends StatelessWidget {
  const _MiniSection({
    required this.title,
    required this.body,
    required this.icon,
    required this.color,
    required this.empty,
  });

  final String title;
  final String body;
  final IconData icon;
  final Color color;
  final bool empty;

  @override
  Widget build(BuildContext context) {
    final sectionColor = airmiusSemanticColor(context, color);
    return AirmiusPanel(
      borderColor: sectionColor.withValues(alpha: .38),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: sectionColor),
          const SizedBox(height: 10),
          Text(
            title,
            style: TextStyle(
              color: airmiusTextColor(context),
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            body,
            style: TextStyle(
              color: empty
                  ? airmiusMutedColor(context)
                  : airmiusTextColor(context),
              height: 1.35,
            ),
          ),
        ],
      ),
    );
  }
}
