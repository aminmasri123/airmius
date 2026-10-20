import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';

class SponsorProfileReviewScreen extends StatefulWidget {
  const SponsorProfileReviewScreen({super.key, required this.sponsor, required this.canReview});

  final Map<String, dynamic> sponsor;
  final bool canReview;

  @override
  State<SponsorProfileReviewScreen> createState() => _SponsorProfileReviewScreenState();
}

class _SponsorProfileReviewScreenState extends State<SponsorProfileReviewScreen> {
  bool _busy = false;
  String? _error;

  bool get _english => Localizations.localeOf(context).languageCode == 'en';
  String _text(String de, String en) => _english ? en : de;

  static const _labels = <String, (String, String)>{
    'name': ('Markenname', 'Brand name'),
    'legal_name': ('Rechtlicher Unternehmensname', 'Legal company name'),
    'country_code': ('Land', 'Country'),
    'region': ('Region', 'Region'),
    'city': ('Ort', 'City'),
    'registration_number': ('Registernummer', 'Registration number'),
    'vat_id': ('USt-IdNr.', 'VAT ID'),
    'contact_name': ('Ansprechperson', 'Contact person'),
    'email': ('E-Mail', 'Email'),
    'website': ('Website', 'Website'),
    'logo': ('Logo', 'Logo'),
    'logo_light': ('Logo für helles Design', 'Light logo'),
    'logo_dark': ('Logo für dunkles Design', 'Dark logo'),
  };

  static const _readinessLabels = <String, (String, String)>{
    'sponsor_name': ('Markenname', 'Brand name'),
    'sponsor_legal_name': ('Rechtlicher Unternehmensname', 'Legal company name'),
    'sponsor_country': ('Land', 'Country'),
    'sponsor_contact': ('E-Mail', 'Email'),
    'sponsor_website': ('Website', 'Website'),
    'sponsor_legal_accuracy': ('Bestätigung der rechtlichen Angaben', 'Legal accuracy confirmation'),
    'sponsor_data_privacy': ('Datenschutzbestätigung', 'Privacy confirmation'),
  };

  String _value(Object? value) {
    final text = value?.toString().trim() ?? '';
    return text.isEmpty ? '–' : text;
  }

  Future<void> _decide(String decision) async {
    final note = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(decision == 'verified'
            ? _text('Sponsorprofil freigeben?', 'Approve sponsor profile?')
            : _text('Sponsorprofil ablehnen?', 'Reject sponsor profile?')),
        content: TextField(
          controller: note,
          minLines: 2,
          maxLines: 4,
          decoration: InputDecoration(labelText: _text('Begründung', 'Reason')),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: Text(_text('Abbrechen', 'Cancel'))),
          FilledButton(onPressed: () => Navigator.pop(dialogContext, true), child: Text(_text('Bestätigen', 'Confirm'))),
        ],
      ),
    );
    final reason = note.text.trim();
    note.dispose();
    if (confirmed != true || !mounted) return;
    if (decision == 'rejected' && reason.isEmpty) {
      setState(() => _error = _text('Bitte begründe die Ablehnung.', 'Please provide a reason for rejection.'));
      return;
    }
    setState(() { _busy = true; _error = null; });
    try {
      final services = AirmiusServicesScope.of(context);
      final client = services.clientForSession(services.authState.session);
      await client.reviewManagedSponsor(widget.sponsor['id'] as int, decision: decision, note: reason);
      if (mounted) Navigator.pop(context, true);
    } on AirmiusApiException catch (error) {
      if (mounted) setState(() => _error = error.userMessage);
    } catch (_) {
      if (mounted) setState(() => _error = _text('Prüfung fehlgeschlagen.', 'Review failed.'));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final sponsor = widget.sponsor;
    final fields = sponsor['profile_fields'] is Map
        ? Map<String, dynamic>.from(sponsor['profile_fields'] as Map)
        : <String, dynamic>{};
    final changes = sponsor['review_changes'] is List
        ? (sponsor['review_changes'] as List).whereType<Map>().toList()
        : <Map>[];
    final pending = sponsor['verification_status'] == 'pending_review';
    final readiness = sponsor['readiness'] is Map
        ? Map<String, dynamic>.from(sponsor['readiness'] as Map)
        : <String, dynamic>{};
    final missing = readiness['blocks'] is List
        ? (readiness['blocks'] as List).whereType<String>().toList()
        : <String>[];

    return Scaffold(
      appBar: AppBar(title: Text(_text('Sponsorprofil prüfen', 'Review sponsor profile'))),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(_value(sponsor['name']), style: Theme.of(context).textTheme.headlineSmall),
          const SizedBox(height: 6),
          Text(pending ? _text('In Prüfung', 'Pending review') : _value(sponsor['verification_status'])),
          if (missing.isNotEmpty) ...[
            const SizedBox(height: 12),
            Text(_text('Für die Freigabe fehlen:', 'Required before approval:'),
                style: TextStyle(color: Theme.of(context).colorScheme.error)),
            ...missing.map((key) {
              final label = _readinessLabels[key];
              return Text('• ${label == null ? key : _text(label.$1, label.$2)}');
            }),
          ],
          const SizedBox(height: 20),
          Text(_text('Änderungen seit der letzten Einreichung', 'Changes since submission'),
              style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 10),
          if (changes.isEmpty)
            Text(_text('Für diese Einreichung ist kein Vorher/Nachher-Vergleich vorhanden. Prüfe alle aktuellen Angaben unten.',
                'No before-and-after history is available for this submission. Review all current details below.')),
          ...changes.map((change) {
            final field = '${change['field'] ?? ''}';
            final label = _labels[field];
            return Card(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(label == null ? field : _text(label.$1, label.$2),
                      style: const TextStyle(fontWeight: FontWeight.bold)),
                  const SizedBox(height: 7),
                  Text('${_text('Vorher', 'Before')}: ${_value(change['before'])}'),
                  Text('${_text('Jetzt', 'Now')}: ${_value(change['after'])}'),
                ]),
              ),
            );
          }),
          const SizedBox(height: 20),
          Text(_text('Aktuelle Unternehmensangaben', 'Current company details'),
              style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 8),
          ..._labels.entries.map((entry) => ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text(_text(entry.value.$1, entry.value.$2)),
                subtitle: SelectableText(_value(fields[entry.key])),
              )),
          if (_error != null) Padding(
            padding: const EdgeInsets.symmetric(vertical: 12),
            child: Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
          ),
          if (pending && widget.canReview) ...[
            const SizedBox(height: 16),
            FilledButton.icon(
              onPressed: _busy ? null : () => _decide('verified'),
              icon: const Icon(Icons.verified_outlined),
              label: Text(_text('Freigeben', 'Approve')),
            ),
            OutlinedButton.icon(
              onPressed: _busy ? null : () => _decide('rejected'),
              icon: const Icon(Icons.block_outlined),
              label: Text(_text('Ablehnen', 'Reject')),
            ),
          ],
        ],
      ),
    );
  }
}
