import 'package:flutter/material.dart';

import '../core/airmius_api_models.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'ui_action_result_screen.dart';
import 'file_operations_screen.dart';
import 'club_membership_management_screen.dart';
import 'membership_operations_screen.dart';

class ClubMembershipAdminScreen extends StatefulWidget {
  const ClubMembershipAdminScreen({super.key});

  @override
  State<ClubMembershipAdminScreen> createState() => _ClubMembershipAdminScreenState();
}

class _ClubMembershipAdminScreenState extends State<ClubMembershipAdminScreen> {
  static const int _defaultClubId = 26;

  final Map<String, bool> _fields = {
    'Personendaten': true,
    'Wohndaten': true,
    'Kontaktdaten': true,
    'Erziehungsberechtigte': true,
    'Notfallkontakt': false,
    'Zahlungsdaten': true,
    'Lizenznummer': false,
    'Dokument-Upload': true,
  };

  String _cycle = 'Monatlich';
  String _payment = 'Überweisung';
  late Future<AirmiusPage<AirmiusClubMembershipRequest>> _requestsFuture;
  bool _reviewing = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _requestsFuture = _loadRequests();
  }

  Future<AirmiusPage<AirmiusClubMembershipRequest>> _loadRequests() {
    return AirmiusServicesScope.of(context).repositories.memberships.clubRequests(_defaultClubId);
  }

  void _reloadRequests() {
    setState(() => _requestsFuture = _loadRequests());
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
        floatingActionButton: FloatingActionButton.extended(backgroundColor: const Color(0xFF1D5FA8), foregroundColor: Colors.white, icon: const Icon(Icons.folder_shared_outlined), label: const Text('Dokument Ops', style: TextStyle(fontWeight: FontWeight.w900)), onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => FileOperationsScreen(initialTab: 'Antrag')))),
        
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('Mitgliedschaften', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'Mitgliedschaften',
        subtitle: 'Anfragen, Formularfelder, Regeln und Zahlungen',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: const [
                  Eyebrow('Vereinsadmin'),
                  SizedBox(height: 8),
                  Text('ZBB', style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900)),
                  SizedBox(height: 6),
                  Text('Hier verwaltet der Verein, welche Daten Mitglieder im Antrag ausfuellen müssen und welche Dokumente gelten.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                ],
              ),
            ),
            const SizedBox(height: 14),
            FutureBuilder<AirmiusPage<AirmiusClubMembershipRequest>>(
              future: _requestsFuture,
              builder: (context, snapshot) {
                final requests = snapshot.data?.items ?? const <AirmiusClubMembershipRequest>[];
                final pending = requests.where((request) => request.status == 'pending').length;
                final approved = requests.where((request) => request.status == 'approved').length;
                return Row(
                  children: [
                    Expanded(child: MetricCard(value: '${snapshot.hasData ? pending : 1}', label: 'Offen')),
                    const SizedBox(width: 10),
                    Expanded(child: MetricCard(value: '$approved', label: 'Angenommen')),
                    const SizedBox(width: 10),
                    const Expanded(child: MetricCard(value: '3', label: 'Dokumente')),
                  ],
                );
              },
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubMembershipManagementScreen())),
              borderColor: AirmiusColors.blue.withValues(alpha: 0.55),
              child: const Row(
                children: [
                  Icon(Icons.groups_3_outlined, color: AirmiusColors.blue),
                  SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Eyebrow('Komplette Verwaltung'),
                        SizedBox(height: 5),
                        Text('Mitglieder, externe Mitglieder, Rechnungen, Zahlungen, SEPA, DATEV und Import verwalten.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                      ],
                    ),
                  ),
                  Icon(Icons.chevron_right, color: AirmiusColors.muted),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              borderColor: AirmiusColors.green.withValues(alpha: 0.55),
              child: FutureBuilder<AirmiusPage<AirmiusClubMembershipRequest>>(
                future: _requestsFuture,
                builder: (context, snapshot) {
                  final requests = snapshot.data?.items.where((request) => request.status == 'pending').toList() ?? const <AirmiusClubMembershipRequest>[];
                  return Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Row(
                        children: [
                          const Expanded(child: Eyebrow('Neue Anfrage')),
                          if (snapshot.connectionState == ConnectionState.waiting)
                            const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: AirmiusColors.blue))
                          else
                            IconButton(onPressed: _reloadRequests, icon: const Icon(Icons.refresh_outlined, color: AirmiusColors.muted)),
                        ],
                      ),
                      const SizedBox(height: 12),
                      if (snapshot.hasError)
                        _RequestFallback(onRetry: _reloadRequests)
                      else if (requests.isEmpty && snapshot.hasData)
                        const Text('Keine offenen Mitgliedschaftsanfragen.', style: TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800))
                      else if (requests.isEmpty)
                        const _RequestCard.fallback()
                      else
                        for (final request in requests) ...[
                          _RequestCard(request: request),
                          const SizedBox(height: 12),
                          Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              AirmiusButton(label: _reviewing ? 'Speichere...' : 'Annehmen', icon: Icons.check_circle_outline, onPressed: _reviewing ? null : () => _reviewRequest(request, approve: true)),
                              AirmiusButton(label: 'Ablehnen', icon: Icons.cancel_outlined, danger: true, onPressed: _reviewing ? null : () => _reviewRequest(request, approve: false)),
                              AirmiusButton(label: 'Operations', icon: Icons.tune_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MembershipOperationsScreen()))),
                            ],
                          ),
                        ],
                    ],
                  );
                },
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Formularfelder'),
                  const SizedBox(height: 8),
                  const Text('Der Verein entscheidet pro Feld, ob es im Antrag sichtbar ist.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                  const SizedBox(height: 8),
                  for (final entry in _fields.entries)
                    SwitchListTile(
                      value: entry.value,
                      onChanged: (value) => setState(() => _fields[entry.key] = value),
                      title: Text(entry.key, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                      subtitle: Text(entry.value ? 'Wird im Formular angezeigt' : 'Ist für Antragsteller ausgeblendet', style: const TextStyle(color: AirmiusColors.muted)),
                      activeColor: AirmiusColors.blue,
                      contentPadding: EdgeInsets.zero,
                    ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Beitrag & Zahlung'),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<String>(
                    value: _cycle,
                    dropdownColor: AirmiusColors.cardSoft,
                    decoration: const InputDecoration(labelText: 'Zahlungsrhythmus'),
                    items: const ['Monatlich', 'Alle 4 Monate', 'Halbjaehrlich', 'Jaehrlich'].map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(),
                    onChanged: (value) => setState(() => _cycle = value ?? _cycle),
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<String>(
                    value: _payment,
                    dropdownColor: AirmiusColors.cardSoft,
                    decoration: const InputDecoration(labelText: 'Zahlmethode'),
                    items: const ['Überweisung', 'Bar', 'SEPA-Lastschrift'].map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(),
                    onChanged: (value) => setState(() => _payment = value ?? _payment),
                  ),
                  const SizedBox(height: 12),
                  const AirmiusTextField(label: 'Beitrag', hint: 'z.B. 12,00 EUR'),
                ],
              ),
            ),
            const SizedBox(height: 14),
            const AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Eyebrow('Verknuepfte Dokumente'),
                  SizedBox(height: 10),
                  _DocumentLinkLine(title: 'Datenschutz.pdf', status: 'Pflicht'),
                  _DocumentLinkLine(title: 'Beitragsordnung.docx', status: 'Pflicht'),
                  _DocumentLinkLine(title: 'Vereinsregeln.pdf', status: 'Optional'),
                ],
              ),
            ),
            const SizedBox(height: 16),
            AirmiusButton(label: 'Einstellungen speichern', icon: Icons.save_outlined, onPressed: () => openUiAction(context, title: 'Einstellungen speichern', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird später über die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.save_outlined)),
            const SizedBox(height: 10),
            AirmiusButton(label: 'Membership Operations', icon: Icons.tune_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MembershipOperationsScreen()))),
          ],
        ),
      ),
    );
  }

  Future<void> _reviewRequest(AirmiusClubMembershipRequest request, {required bool approve}) async {
    if (_reviewing) return;
    final title = approve ? 'Anfrage annehmen' : 'Anfrage ablehnen';
    final message = approve
        ? 'Moechtest du ${request.applicantName ?? 'diese Person'} als Mitglied aufnehmen?'
        : 'Moechtest du die Anfrage von ${request.applicantName ?? 'dieser Person'} ablehnen?';
    final ok = approve ? true : await confirmDanger(context, title, message);
    if (!ok || !mounted) return;

    setState(() => _reviewing = true);
    try {
      final repository = AirmiusServicesScope.of(context).repositories.memberships;
      if (approve) {
        await repository.approveClubRequest(request.clubId, request.id);
      } else {
        await repository.declineClubRequest(request.clubId, request.id);
      }
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(approve ? 'Anfrage wurde angenommen.' : 'Anfrage wurde abgelehnt.')));
      setState(() {
        _reviewing = false;
        _requestsFuture = _loadRequests();
      });
    } catch (error) {
      if (!mounted) return;
      setState(() => _reviewing = false);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Aktion konnte nicht gespeichert werden: $error')));
    }
  }
}

class _RequestCard extends StatelessWidget {
  const _RequestCard({required this.request}) : fallbackName = null, fallbackClub = null, fallbackStatus = null;
  const _RequestCard.fallback()
      : request = null,
        fallbackName = 'ZBB Konto',
        fallbackClub = 'ZBB',
        fallbackStatus = 'Offen';

  final AirmiusClubMembershipRequest? request;
  final String? fallbackName;
  final String? fallbackClub;
  final String? fallbackStatus;

  @override
  Widget build(BuildContext context) {
    final name = request?.applicantName ?? fallbackName ?? 'Mitglied';
    final club = request?.clubName ?? fallbackClub ?? 'Verein';
    final status = request == null ? fallbackStatus ?? 'Offen' : _statusLabel(request!.status);
    final meta = request?.message == null || request!.message!.isEmpty ? '$club - Antrag mit Personendaten und Dokumenten' : request!.message!;
    return Container(
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(16), border: Border.all(color: AirmiusColors.border)),
      child: Row(
        children: [
          AirmiusAvatar(name),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(name, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                const SizedBox(height: 3),
                Text(meta, style: const TextStyle(color: AirmiusColors.muted)),
              ],
            ),
          ),
          StatusPill(status, color: AirmiusColors.green),
        ],
      ),
    );
  }

  String _statusLabel(String status) {
    return switch (status) {
      'approved' => 'Angenommen',
      'declined' => 'Abgelehnt',
      'withdrawn' => 'Zurückgezogen',
      _ => 'Offen',
    };
  }
}

class _RequestFallback extends StatelessWidget {
  const _RequestFallback({required this.onRetry});

  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Text('Anfragen konnten nicht geladen werden.', style: TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800)),
        const SizedBox(height: 10),
        AirmiusButton(label: 'Erneut laden', icon: Icons.refresh_outlined, secondary: true, onPressed: onRetry),
      ],
    );
  }
}

class _DocumentLinkLine extends StatelessWidget {
  const _DocumentLinkLine({required this.title, required this.status});

  final String title;
  final String status;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 12),
      child: Row(
        children: [
          const Icon(Icons.description_outlined, color: AirmiusColors.blue),
          const SizedBox(width: 12),
          Expanded(child: Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900))),
          StatusPill(status),
        ],
      ),
    );
  }
}


