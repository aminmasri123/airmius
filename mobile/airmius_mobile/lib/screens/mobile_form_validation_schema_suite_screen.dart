import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class MobileFormValidationSchemaSuiteScreen extends StatefulWidget {
  const MobileFormValidationSchemaSuiteScreen({super.key});

  @override
  State<MobileFormValidationSchemaSuiteScreen> createState() => _MobileFormValidationSchemaSuiteScreenState();
}

class _MobileFormValidationSchemaSuiteScreenState extends State<MobileFormValidationSchemaSuiteScreen> {
  String _scope = 'Mitgliedsantrag';
  bool _requiredFields = true;
  bool _conditionalRules = true;
  bool _inputMasks = true;
  bool _schemaVersioning = true;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('Formularvalidierung', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'Mobile Form Validation Schema',
        subtitle: 'Pflichtfelder, Regeln, Masken, Fehlertexte und API-Payloads fuer dynamische Vereinsformulare.',
        trailing: const StatusPill('API-ready', color: AirmiusColors.green),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('FORM SCHEMA'),
                  const SizedBox(height: 8),
                  const Text(
                    'Jedes Vereinsformular bleibt stabil.',
                    style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.08),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Die App bildet die Web-Logik mobil nach: Vereine koennen Felder aktivieren, als Pflicht markieren, Bedingungen setzen und die App zeigt sofort klare Fehlermeldungen.',
                    style: TextStyle(color: AirmiusColors.muted, height: 1.42),
                  ),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: ['Mitgliedsantrag', 'Profil', 'Zahlung', 'Dokumente', 'Guardian'].map((item) {
                      return ChoiceChip(
                        selected: _scope == item,
                        label: Text(item),
                        onSelected: (_) => setState(() => _scope = item),
                        selectedColor: AirmiusColors.blue.withValues(alpha: .22),
                        backgroundColor: AirmiusColors.panelSoft,
                        side: BorderSide(color: _scope == item ? AirmiusColors.blue : AirmiusColors.border),
                        labelStyle: TextStyle(color: _scope == item ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
                      );
                    }).toList(),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: const [
                Expanded(child: MetricCard(value: '42', label: 'Felder')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '18', label: 'Regeln')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: 'v3', label: 'Schema')),
              ],
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('AKTIVE VALIDIERUNG'),
                  const SizedBox(height: 10),
                  _ToggleRow(
                    icon: Icons.star_outline,
                    title: 'Pflichtfelder erzwingen',
                    body: 'Vorname, Nachname, Geburtstag, E-Mail, Adresse und Vereins-spezifische Pflichtfelder werden vor dem Senden geprueft.',
                    enabled: _requiredFields,
                    onChanged: (value) => setState(() => _requiredFields = value),
                  ),
                  _ToggleRow(
                    icon: Icons.account_tree_outlined,
                    title: 'Bedingte Felder',
                    body: 'Guardian-Daten nur bei Minderjaehrigen, SEPA nur bei Lastschrift, Lizenznummer nur bei Sportpflicht.',
                    enabled: _conditionalRules,
                    onChanged: (value) => setState(() => _conditionalRules = value),
                  ),
                  _ToggleRow(
                    icon: Icons.keyboard_outlined,
                    title: 'Eingabemasken',
                    body: 'IBAN, PLZ, Telefon, Geburtsdatum, Mitgliedsnummer und Preisfelder bekommen passende mobile Tastaturen.',
                    enabled: _inputMasks,
                    onChanged: (value) => setState(() => _inputMasks = value),
                  ),
                  _ToggleRow(
                    icon: Icons.history_outlined,
                    title: 'Schema-Versionen',
                    body: 'Jede Vereinskonfiguration wird versioniert, damit alte Antraege nachvollziehbar bleiben.',
                    enabled: _schemaVersioning,
                    onChanged: (value) => setState(() => _schemaVersioning = value),
                    last: true,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            _SchemaPreview(scope: _scope),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('API PAYLOAD PREVIEW'),
                  const SizedBox(height: 10),
                  _PayloadLine(label: 'form_scope', value: _scope.toLowerCase().replaceAll(' ', '_')),
                  const _PayloadLine(label: 'required_missing', value: 'gender, street, city'),
                  const _PayloadLine(label: 'conditional_visible', value: 'guardian_email, sepa_mandate'),
                  const _PayloadLine(label: 'document_links', value: 'privacy_policy, club_rules, contribution_rules'),
                  const SizedBox(height: 12),
                  Row(
                    children: const [
                      Expanded(child: StatusPill('Draft saved', color: AirmiusColors.green)),
                      SizedBox(width: 8),
                      Expanded(child: StatusPill('Retry safe', color: AirmiusColors.blue)),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              borderColor: AirmiusColors.amber.withValues(alpha: .45),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('FEHLERTEXTE'),
                  const SizedBox(height: 10),
                  const _ErrorCard(title: 'Geschlecht fehlt', body: 'Bitte waehle eine Option oder markiere das Feld im Vereins-Builder als optional.'),
                  const _ErrorCard(title: 'SEPA unvollstaendig', body: 'IBAN und SEPA-Mandat sind erforderlich, wenn Lastschrift aktiv ist.'),
                  const _ErrorCard(title: 'Dokument fehlt', body: 'Bitte lade das Pflichtdokument hoch oder bestaetige die verknuepfte Vereinsregel.'),
                  const SizedBox(height: 4),
                  AirmiusButton(label: 'Schema spaeter mit Laravel API verbinden', icon: Icons.api_outlined, onPressed: () {}),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _SchemaPreview extends StatelessWidget {
  const _SchemaPreview({required this.scope});

  final String scope;

  @override
  Widget build(BuildContext context) {
    final rows = [
      _SchemaRow('Personendaten', 'Vorname, Nachname, Geschlecht, Geburtstag', 'Pflicht'),
      _SchemaRow('Wohndaten', 'Land, Strasse, Hausnummer, PLZ, Stadt', 'Pflicht'),
      _SchemaRow('Kontakt', 'E-Mail, Telefon, Notfallkontakt', 'Teilweise'),
      _SchemaRow('Zahlung', 'Zahlart, Zahlungsrhythmus, IBAN, Mandat', 'Bedingt'),
      _SchemaRow('Dateien', 'Datenschutz, Satzung, Beitragsordnung, Nachweise', 'Upload'),
    ];

    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              const Expanded(child: Eyebrow('SCHEMA VORSCHAU')),
              StatusPill(scope, color: AirmiusColors.blue),
            ],
          ),
          const SizedBox(height: 12),
          for (final row in rows) ...[
            _SchemaTile(row: row),
            if (row != rows.last) const SizedBox(height: 10),
          ],
        ],
      ),
    );
  }
}

class _SchemaRow {
  const _SchemaRow(this.title, this.body, this.status);

  final String title;
  final String body;
  final String status;
}

class _SchemaTile extends StatelessWidget {
  const _SchemaTile({required this.row});

  final _SchemaRow row;

  @override
  Widget build(BuildContext context) {
    final color = row.status == 'Pflicht'
        ? AirmiusColors.green
        : row.status == 'Upload'
            ? AirmiusColors.blue
            : AirmiusColors.amber;
    return Container(
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: AirmiusColors.cardSoft,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AirmiusColors.border),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.rule_outlined, color: color),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(row.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                const SizedBox(height: 4),
                Text(row.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
              ],
            ),
          ),
          const SizedBox(width: 8),
          StatusPill(row.status, color: color),
        ],
      ),
    );
  }
}

class _ToggleRow extends StatelessWidget {
  const _ToggleRow({required this.icon, required this.title, required this.body, required this.enabled, required this.onChanged, this.last = false});

  final IconData icon;
  final String title;
  final String body;
  final bool enabled;
  final ValueChanged<bool> onChanged;
  final bool last;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(bottom: last ? 0 : 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: (enabled ? AirmiusColors.green : AirmiusColors.muted).withValues(alpha: .14),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: enabled ? AirmiusColors.green.withValues(alpha: .45) : AirmiusColors.border),
            ),
            child: Icon(icon, color: enabled ? AirmiusColors.green : AirmiusColors.muted),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                const SizedBox(height: 4),
                Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
              ],
            ),
          ),
          Switch(value: enabled, activeThumbColor: AirmiusColors.green, onChanged: onChanged),
        ],
      ),
    );
  }
}

class _PayloadLine extends StatelessWidget {
  const _PayloadLine({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: AirmiusColors.cardSoft,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: AirmiusColors.border),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(width: 128, child: Text(label, style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w900))),
            Expanded(child: Text(value, style: const TextStyle(color: AirmiusColors.text, height: 1.35))),
          ],
        ),
      ),
    );
  }
}

class _ErrorCard extends StatelessWidget {
  const _ErrorCard({required this.title, required this.body});

  final String title;
  final String body;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: AirmiusColors.red.withValues(alpha: .08),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: AirmiusColors.red.withValues(alpha: .28)),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Icon(Icons.error_outline, color: AirmiusColors.red),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                  const SizedBox(height: 4),
                  Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
