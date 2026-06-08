import 'package:flutter/material.dart';

import '../core/api_contract.dart';

class SafetyCommunityOperationsScreen extends StatefulWidget {
  const SafetyCommunityOperationsScreen({super.key, this.initialTab = 0});

  final int initialTab;

  @override
  State<SafetyCommunityOperationsScreen> createState() => _SafetyCommunityOperationsScreenState();
}

class _SafetyCommunityOperationsScreenState extends State<SafetyCommunityOperationsScreen> {
  static const _bg = Color(0xFF070B12);
  static const _panel = Color(0xFF111821);
  static const _panelSoft = Color(0xFF151F2D);
  static const _border = Color(0xFF28384D);
  static const _text = Color(0xFFF7FAFF);
  static const _muted = Color(0xFF9AA8BA);
  static const _blue = Color(0xFF5BA7FF);
  static const _green = Color(0xFF35D49A);
  static const _red = Color(0xFFFF5C6C);

  final _query = TextEditingController();
  String _filter = '';

  @override
  void dispose() {
    _query.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final tabs = <_OpsTab>[
      _OpsTab('Community', Icons.groups_2_outlined, _communityOps),
      _OpsTab('Fahrten', Icons.directions_car_filled_outlined, _carpoolOps),
      _OpsTab('Guardian', Icons.family_restroom_outlined, _guardianOps),
      _OpsTab('Maturity', Icons.security_outlined, _maturityOps),
      _OpsTab('Reports', Icons.report_outlined, _reportOps),
      _OpsTab('Alle', Icons.view_list_outlined, [..._communityOps, ..._carpoolOps, ..._guardianOps, ..._maturityOps, ..._reportOps]),
    ];

    return DefaultTabController(
      length: tabs.length,
      initialIndex: widget.initialTab.clamp(0, tabs.length - 1).toInt(),
      child: Scaffold(
        backgroundColor: _bg,
        appBar: AppBar(
          backgroundColor: _panel,
          foregroundColor: _text,
          elevation: 0,
          title: const Text('Safety & Community Ops', style: TextStyle(fontWeight: FontWeight.w900)),
          bottom: TabBar(
            isScrollable: true,
            indicatorColor: _blue,
            labelColor: _text,
            unselectedLabelColor: _muted,
            tabs: [for (final tab in tabs) Tab(icon: Icon(tab.icon), text: tab.title)],
          ),
        ),
        body: SafeArea(
          child: Column(
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 10),
                child: _SearchField(
                  controller: _query,
                  onChanged: (value) => setState(() => _filter = value.trim().toLowerCase()),
                ),
              ),
              Expanded(
                child: TabBarView(
                  children: [for (final tab in tabs) _OperationsList(operations: _filtered(tab.operations), onTap: _openOperation)],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  List<_Operation> _filtered(List<_Operation> items) {
    if (_filter.isEmpty) return items;
    return items.where((item) {
      final haystack = '${item.title} ${item.subtitle} ${item.endpoint} ${item.method}'.toLowerCase();
      return haystack.contains(_filter);
    }).toList();
  }

  Future<void> _openOperation(_Operation operation) async {
    final dangerConfirmed = !operation.danger || await _confirmDanger(operation);
    if (!dangerConfirmed || !mounted) return;

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => _OperationSheet(operation: operation),
    );
  }

  Future<bool> _confirmDanger(_Operation operation) async {
    final result = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        backgroundColor: _panel,
        surfaceTintColor: _panel,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(22), side: const BorderSide(color: _border)),
        title: Text(operation.title, style: const TextStyle(color: _text, fontWeight: FontWeight.w900)),
        content: Text('Diese Aktion ist kritisch. Moechtest du fortfahren?', style: const TextStyle(color: _muted)),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Abbrechen')),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: _red, foregroundColor: Colors.white),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Fortfahren'),
          ),
        ],
      ),
    );
    return result ?? false;
  }

  static final _communityOps = <_Operation>[
    _Operation('Einladungslink erstellen', 'Freundschaftseinladung erzeugen und teilen.', 'POST', ApiContract.friendInvitations, Icons.link_outlined),
    _Operation('Einladung per Token annehmen', 'Token pruefen und Beziehung herstellen.', 'GET', ApiContract.friendInvitationToken('{token}'), Icons.task_alt_outlined),
    _Operation('Freundschaft annehmen', 'Ausstehende Anfrage bestaetigen.', 'POST', ApiContract.friendInvitationAccept(1), Icons.check_circle_outline),
    _Operation('Freundschaft ablehnen', 'Ausstehende Anfrage ablehnen.', 'DELETE', ApiContract.friendInvitationDecline(1), Icons.cancel_outlined, danger: true),
    _Operation('Freund entfernen', 'Bestehende Verbindung sauber trennen.', 'DELETE', ApiContract.friendRemove(1), Icons.person_remove_outlined, danger: true),
    _Operation('User blockieren', 'Kontakt und Interaktion sofort stoppen.', 'POST', ApiContract.friendBlock(1), Icons.block_outlined, danger: true),
  ];

  static final _carpoolOps = <_Operation>[
    _Operation('Fahrt anbieten', 'Fahrgemeinschaft mit Plaetzen, Route und Zeiten erstellen.', 'POST', ApiContract.carpools, Icons.add_circle_outline),
    _Operation('Mitfahrt suchen', 'Offene Fahrten passend zum Verein finden.', 'GET', ApiContract.carpools, Icons.search_outlined),
    _Operation('Mitfahrt anfragen', 'Platz fuer eine konkrete Fahrt anfragen.', 'POST', ApiContract.carpoolJoin(1), Icons.how_to_reg_outlined),
    _Operation('Anfrage bestaetigen', 'Fahrer bestaetigt Mitfahrer.', 'PUT', ApiContract.carpoolRequest(1, 1), Icons.check_circle_outline),
    _Operation('Mitfahrer entfernen', 'Teilnehmer aus Fahrt entfernen.', 'DELETE', ApiContract.carpoolMember(1, 1), Icons.person_remove_outlined, danger: true),
    _Operation('Kontaktfreigabe', 'Telefon oder Chatdaten nur nach Zustimmung freigeben.', 'POST', ApiContract.carpoolContactRelease(1), Icons.visibility_outlined),
    _Operation('Fahrt verlassen', 'Eigene Teilnahme zurueckziehen.', 'DELETE', ApiContract.carpoolLeave(1), Icons.logout_outlined, danger: true),
  ];

  static final _guardianOps = <_Operation>[
    _Operation('Zustimmung ausstehend', 'Offene Elternfreigaben laden.', 'GET', ApiContract.guardianConsentPending(), Icons.pending_actions_outlined),
    _Operation('Zustimmung erneut senden', 'Erziehungsberechtigte erneut benachrichtigen.', 'POST', ApiContract.guardianConsentResend(), Icons.mark_email_unread_outlined),
    _Operation('Consent Token ansehen', 'Oeffentliche Freigabeseite per Token laden.', 'GET', ApiContract.guardianConsentToken('{token}'), Icons.password_outlined),
    _Operation('Consent bestaetigen', 'Elternfreigabe per Token bestaetigen.', 'POST', ApiContract.guardianConsentApproveToken('{token}'), Icons.check_circle_outline),
    _Operation('Consent ablehnen', 'Elternfreigabe per Token ablehnen.', 'DELETE', ApiContract.guardianConsentRejectToken('{token}'), Icons.cancel_outlined, danger: true),
    _Operation('Elternlogin starten', 'Elternzugang mit E-Mail oder Daten beginnen.', 'POST', ApiContract.guardianAccessPublic, Icons.login_outlined),
    _Operation('Elterncode pruefen', 'Einmalcode bestaetigen und Zugriff herstellen.', 'POST', ApiContract.guardianAccessCode, Icons.verified_user_outlined),
    _Operation('Kinder verwalten', 'Kinderkonten und Freigaben anzeigen.', 'GET', ApiContract.guardianChildrenPublic, Icons.child_care_outlined),
    _Operation('Kinderkonto erstellen', 'Neues Eltern-/Kinderkonto anlegen.', 'POST', ApiContract.guardianAccountCreate, Icons.person_add_alt_1_outlined),
    _Operation('Kind zustimmen', 'Freigabe fuer Kind aktivieren.', 'PUT', ApiContract.guardianChildApprove(1), Icons.check_circle_outline),
    _Operation('Kind widerrufen', 'Freigabe fuer Kind zurueckziehen.', 'PUT', ApiContract.guardianChildRevoke(1), Icons.undo_outlined, danger: true),
    _Operation('Elternlogout', 'Elternsession beenden.', 'POST', ApiContract.guardianAccessLogout, Icons.logout_outlined),
  ];

  static final _maturityOps = <_Operation>[
    _Operation('Maturity Overview', 'Status, Schutzlogik und Freigaben laden.', 'GET', ApiContract.maturityOverview, Icons.dashboard_outlined),
    _Operation('Feed Discovery', 'Altersgerechte Inhalte entdecken.', 'GET', ApiContract.feedDiscovery, Icons.explore_outlined),
    _Operation('Feed Trending', 'Trending-Inhalte mit Schutzfilter laden.', 'GET', ApiContract.feedTrending, Icons.local_fire_department_outlined),
    _Operation('Motivation', 'Motivationskarten und Hinweise laden.', 'GET', ApiContract.maturityMotivation, Icons.favorite_border_outlined),
    _Operation('Maturity Suche', 'Suche mit Minderjaehrigen-Schutz ausfuehren.', 'GET', ApiContract.maturitySearch, Icons.search_outlined),
    _Operation('Challenges', 'Freigegebene Challenges laden.', 'GET', ApiContract.maturityChallenges, Icons.emoji_events_outlined),
    _Operation('Routen Analytics', 'SportRoute-Auswertung altersgerecht anzeigen.', 'GET', ApiContract.sportRouteAnalytics(1), Icons.analytics_outlined),
    _Operation('Coach Weekly', 'Wochenuebersicht fuer Coaching laden.', 'GET', ApiContract.coachWeekly, Icons.calendar_month_outlined),
    _Operation('Onboarding', 'Maturity-Onboarding starten.', 'POST', ApiContract.maturityOnboarding, Icons.flag_outlined),
    _Operation('Viral Guard', 'Virale Inhalte vor Ausspielung pruefen.', 'GET', ApiContract.maturityViral, Icons.privacy_tip_outlined),
    _Operation('Safety Center', 'Safety-Regeln und Warnungen anzeigen.', 'GET', ApiContract.maturitySafety, Icons.security_outlined),
    _Operation('Gate erstellen', 'Content Gate fuer sensible Inhalte anlegen.', 'POST', ApiContract.maturityGates, Icons.lock_outlined),
    _Operation('Gate aktualisieren', 'Gate-Regeln bearbeiten.', 'PUT', ApiContract.maturityGate(1), Icons.edit_outlined),
    _Operation('Gate loeschen', 'Content Gate entfernen.', 'DELETE', ApiContract.maturityGate(1), Icons.delete_outline, danger: true),
  ];

  static final _reportOps = <_Operation>[
    _Operation('Community melden', 'Problematische Verbindung oder Einladung melden.', 'POST', ApiContract.friendReport(1), Icons.report_outlined, danger: true),
    _Operation('Fahrt melden', 'Fahrgemeinschaft wegen Sicherheit oder Verhalten melden.', 'POST', ApiContract.carpoolReport(1), Icons.warning_amber_outlined, danger: true),
    _Operation('Safety Fall pruefen', 'Meldung fuer Admin-Moderation vorbereiten.', 'GET', ApiContract.maturitySafety, Icons.fact_check_outlined),
  ];
}

class _OpsTab {
  const _OpsTab(this.title, this.icon, this.operations);
  final String title;
  final IconData icon;
  final List<_Operation> operations;
}

class _Operation {
  const _Operation(this.title, this.subtitle, this.method, this.endpoint, this.icon, {this.danger = false});
  final String title;
  final String subtitle;
  final String method;
  final String endpoint;
  final IconData icon;
  final bool danger;
}

class _SearchField extends StatelessWidget {
  const _SearchField({required this.controller, required this.onChanged});

  final TextEditingController controller;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) => TextField(
        controller: controller,
        onChanged: onChanged,
        style: const TextStyle(color: _SafetyCommunityOperationsScreenState._text, fontWeight: FontWeight.w700),
        decoration: InputDecoration(
          filled: true,
          fillColor: _SafetyCommunityOperationsScreenState._panel,
          hintText: 'Aktionen, Endpunkte oder Bereiche suchen',
          hintStyle: const TextStyle(color: _SafetyCommunityOperationsScreenState._muted),
          prefixIcon: const Icon(Icons.search_outlined, color: _SafetyCommunityOperationsScreenState._muted),
          enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(18), borderSide: const BorderSide(color: _SafetyCommunityOperationsScreenState._border)),
          focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(18), borderSide: const BorderSide(color: _SafetyCommunityOperationsScreenState._blue)),
        ),
      );
}

class _OperationsList extends StatelessWidget {
  const _OperationsList({required this.operations, required this.onTap});

  final List<_Operation> operations;
  final ValueChanged<_Operation> onTap;

  @override
  Widget build(BuildContext context) => ListView.separated(
        padding: const EdgeInsets.fromLTRB(16, 4, 16, 28),
        itemCount: operations.length,
        separatorBuilder: (_, __) => const SizedBox(height: 12),
        itemBuilder: (context, index) => _OperationCard(operation: operations[index], onTap: () => onTap(operations[index])),
      );
}

class _OperationCard extends StatelessWidget {
  const _OperationCard({required this.operation, required this.onTap});

  final _Operation operation;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Material(
        color: _SafetyCommunityOperationsScreenState._panel,
        borderRadius: BorderRadius.circular(22),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(22),
          child: Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(22),
              border: Border.all(color: operation.danger ? _SafetyCommunityOperationsScreenState._red.withValues(alpha: .45) : _SafetyCommunityOperationsScreenState._border),
            ),
            child: Row(
              children: [
                Container(
                  width: 48,
                  height: 48,
                  decoration: BoxDecoration(
                    color: operation.danger ? _SafetyCommunityOperationsScreenState._red.withValues(alpha: .12) : _SafetyCommunityOperationsScreenState._panelSoft,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: operation.danger ? _SafetyCommunityOperationsScreenState._red : _SafetyCommunityOperationsScreenState._border),
                  ),
                  child: Icon(operation.icon, color: operation.danger ? _SafetyCommunityOperationsScreenState._red : _SafetyCommunityOperationsScreenState._blue),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(operation.title, style: const TextStyle(color: _SafetyCommunityOperationsScreenState._text, fontSize: 16, fontWeight: FontWeight.w900)),
                      const SizedBox(height: 5),
                      Text(operation.subtitle, style: const TextStyle(color: _SafetyCommunityOperationsScreenState._muted, fontWeight: FontWeight.w600)),
                      const SizedBox(height: 9),
                      Text('${operation.method} ${operation.endpoint}', style: TextStyle(color: operation.danger ? _SafetyCommunityOperationsScreenState._red : _SafetyCommunityOperationsScreenState._green, fontSize: 12, fontWeight: FontWeight.w900)),
                    ],
                  ),
                ),
                const Icon(Icons.chevron_right, color: _SafetyCommunityOperationsScreenState._muted),
              ],
            ),
          ),
        ),
      );
}

class _OperationSheet extends StatelessWidget {
  const _OperationSheet({required this.operation});

  final _Operation operation;

  @override
  Widget build(BuildContext context) => Padding(
        padding: EdgeInsets.only(left: 12, right: 12, bottom: MediaQuery.of(context).viewInsets.bottom + 12),
        child: Container(
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(
            color: _SafetyCommunityOperationsScreenState._panel,
            borderRadius: BorderRadius.circular(28),
            border: Border.all(color: _SafetyCommunityOperationsScreenState._border),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Icon(operation.icon, color: operation.danger ? _SafetyCommunityOperationsScreenState._red : _SafetyCommunityOperationsScreenState._blue),
                  const SizedBox(width: 10),
                  Expanded(child: Text(operation.title, style: const TextStyle(color: _SafetyCommunityOperationsScreenState._text, fontSize: 20, fontWeight: FontWeight.w900))),
                  IconButton(onPressed: () => Navigator.pop(context), icon: const Icon(Icons.close, color: _SafetyCommunityOperationsScreenState._muted)),
                ],
              ),
              const SizedBox(height: 10),
              Text(operation.subtitle, style: const TextStyle(color: _SafetyCommunityOperationsScreenState._muted, fontWeight: FontWeight.w600)),
              const SizedBox(height: 16),
              _SheetRow(label: 'Methode', value: operation.method),
              _SheetRow(label: 'Endpoint', value: operation.endpoint),
              const SizedBox(height: 16),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(color: _SafetyCommunityOperationsScreenState._bg, borderRadius: BorderRadius.circular(18), border: Border.all(color: _SafetyCommunityOperationsScreenState._border)),
                child: const Text(
                  'Backend-Anbindung folgt ueber den Laravel API-Client. Diese Flutter-UI bildet den kompletten Prozess bereits nativ ab.',
                  style: TextStyle(color: _SafetyCommunityOperationsScreenState._muted, fontWeight: FontWeight.w700),
                ),
              ),
            ],
          ),
        ),
      );
}

class _SheetRow extends StatelessWidget {
  const _SheetRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: 10),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(width: 92, child: Text(label, style: const TextStyle(color: _SafetyCommunityOperationsScreenState._muted, fontWeight: FontWeight.w800))),
            Expanded(child: Text(value, style: const TextStyle(color: _SafetyCommunityOperationsScreenState._text, fontWeight: FontWeight.w900))),
          ],
        ),
      );
}

