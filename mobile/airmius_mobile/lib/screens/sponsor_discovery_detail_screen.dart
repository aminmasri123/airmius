import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_external_url.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class SponsorDiscoveryDetailScreen extends StatefulWidget {
  const SponsorDiscoveryDetailScreen({super.key, required this.sponsor, required this.clubs, required this.outgoing});

  final JsonMap sponsor;
  final List<JsonMap> clubs;
  final List<JsonMap> outgoing;

  @override
  State<SponsorDiscoveryDetailScreen> createState() => _SponsorDiscoveryDetailScreenState();
}

class _SponsorDiscoveryDetailScreenState extends State<SponsorDiscoveryDetailScreen> {
  final _form = GlobalKey<FormState>();
  final _subject = TextEditingController();
  final _message = TextEditingController();
  int? _clubId;
  bool _busy = false;
  String? _error;
  late List<JsonMap> _outgoing;

  bool get _english => Localizations.localeOf(context).languageCode == 'en';
  String tx(String de, String en) => _english ? en : de;
  String txt(Object? value) => value?.toString().trim() ?? '';

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  void initState() {
    super.initState();
    _clubId = widget.clubs.isEmpty ? null : int.tryParse('${widget.clubs.first['id']}');
    _outgoing = [...widget.outgoing];
  }

  @override
  void dispose() {
    _subject.dispose();
    _message.dispose();
    super.dispose();
  }

  JsonMap? get _currentInquiry {
    for (final inquiry in _outgoing) {
      if ('${inquiry['sponsor_id']}' == '${widget.sponsor['id']}' && '${inquiry['club_id']}' == '$_clubId') return inquiry;
    }
    return null;
  }

  Future<void> _send() async {
    if (_busy || !_form.currentState!.validate() || _clubId == null) return;
    setState(() { _busy = true; _error = null; });
    try {
      final response = await _client.createSponsorInquiry({
        'sponsor_id': widget.sponsor['id'], 'club_id': _clubId,
        'subject': _subject.text.trim(), 'message': _message.text.trim(),
      });
      if (!mounted) return;
      setState(() => _outgoing.insert(0, Map<String, dynamic>.from(response['data'] as Map)));
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(tx('Anfrage gesendet.', 'Inquiry sent.'))));
    } on AirmiusApiException catch (error) {
      if (mounted) setState(() => _error = error.userMessage);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _withdraw(JsonMap inquiry) async {
    final confirmed = await showDialog<bool>(context: context, builder: (context) => AlertDialog(
      title: Text(tx('Anfrage zurückziehen?', 'Withdraw inquiry?')),
      actions: [TextButton(onPressed: () => Navigator.pop(context, false), child: Text(tx('Abbrechen', 'Cancel'))),
        FilledButton(onPressed: () => Navigator.pop(context, true), child: Text(tx('Zurückziehen', 'Withdraw')))],
    ));
    if (confirmed != true || !mounted) return;
    setState(() { _busy = true; _error = null; });
    try {
      final response = await _client.withdrawSponsorInquiry(int.parse('${inquiry['id']}'));
      if (!mounted) return;
      setState(() {
        _outgoing = _outgoing.map((item) => '${item['id']}' == '${inquiry['id']}'
            ? Map<String, dynamic>.from(response['data'] as Map) : item).toList();
      });
    } on AirmiusApiException catch (error) {
      if (mounted) setState(() => _error = error.userMessage);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final sponsor = widget.sponsor;
    final inquiry = _currentInquiry;
    final logo = resolveAirmiusImageUrl(txt(sponsor['logo_url']));
    final website = safeExternalHttpUrl(txt(sponsor['website']), httpsOnly: false);
    final place = [sponsor['city'], sponsor['region'], sponsor['country_code']]
        .map(txt).where((value) => value.isNotEmpty).join(' · ');
    return Scaffold(
      appBar: AppBar(title: Text(tx('Sponsorprofil', 'Sponsor profile'))),
      body: PageFrame(title: txt(sponsor['name']), subtitle: place.isEmpty ? tx('Keine Region angegeben', 'No region provided') : place,
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            if (logo != null) Center(child: Image.network(logo, height: 110, fit: BoxFit.contain,
              errorBuilder: (_, _, _) => const Icon(Icons.handshake_outlined, size: 64))),
            if (website != null) TextButton.icon(onPressed: () => launchUrl(website, mode: LaunchMode.externalApplication),
              icon: const Icon(Icons.open_in_new), label: Text(tx('Website öffnen', 'Open website'))),
          ])),
          const SizedBox(height: 16),
          if (widget.clubs.isNotEmpty && txt(sponsor['scope']) == 'platform') ...[
            DropdownButtonFormField<int>(
              initialValue: _clubId,
              decoration: InputDecoration(labelText: tx('Verein', 'Club')),
              items: widget.clubs.map((club) => DropdownMenuItem(
                value: int.tryParse('${club['id']}'), child: Text(txt(club['name'])))).toList(),
              onChanged: _busy ? null : (value) => setState(() => _clubId = value),
            ),
            const SizedBox(height: 12),
            if (inquiry != null && txt(inquiry['status']) == 'pending') AirmiusPanel(child: Column(
              crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(tx('Anfrage gesendet · Antwort ausstehend', 'Inquiry sent · awaiting response')),
                TextButton.icon(onPressed: _busy ? null : () => _withdraw(inquiry),
                  icon: const Icon(Icons.undo), label: Text(tx('Zurückziehen', 'Withdraw'))),
              ],
            )) else ...[
              if (inquiry != null) Text('${tx('Letzte Antwort', 'Latest response')}: ${txt(inquiry['status'])}${txt(inquiry['response_note']).isEmpty ? '' : ' · ${txt(inquiry['response_note'])}'}'),
              Form(key: _form, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                Text(tx('Sponsoring anfragen', 'Request sponsorship'), style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 10),
                TextFormField(controller: _subject, maxLength: 160,
                  decoration: InputDecoration(labelText: tx('Betreff', 'Subject')),
                  validator: (value) => (value?.trim().length ?? 0) < 5 ? tx('Mindestens 5 Zeichen', 'At least 5 characters') : null),
                TextFormField(controller: _message, maxLines: 5, maxLength: 5000,
                  decoration: InputDecoration(labelText: tx('Projekt und gewünschte Unterstützung', 'Project and support needed')),
                  validator: (value) => (value?.trim().length ?? 0) < 20 ? tx('Mindestens 20 Zeichen', 'At least 20 characters') : null),
                FilledButton.icon(onPressed: _busy ? null : _send, icon: const Icon(Icons.send),
                  label: Text(tx('Anfrage senden', 'Send inquiry'))),
              ])),
            ],
          ] else Text(tx('Für Anfragen brauchst du einen Verein mit Sponsoren-Berechtigung.',
              'You need a club with sponsor permissions to send an inquiry.')),
          if (_error != null) Padding(padding: const EdgeInsets.only(top: 12),
            child: Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error))),
        ]),
      ),
    );
  }
}
