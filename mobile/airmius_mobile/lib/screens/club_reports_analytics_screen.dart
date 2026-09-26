import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../models/club_summary.dart';
import '../widgets/airmius_widgets.dart';

/// A current snapshot of values returned by the club detail API. This is not
/// a historical report: trends and exports need their own server contracts.
class ClubReportsAnalyticsScreen extends StatefulWidget {
  const ClubReportsAnalyticsScreen({super.key, required this.club});

  final ClubSummary club;

  @override
  State<ClubReportsAnalyticsScreen> createState() =>
      _ClubReportsAnalyticsScreenState();
}

class _ClubReportsAnalyticsScreenState
    extends State<ClubReportsAnalyticsScreen> {
  late ClubSummary _club = widget.club;
  bool _loading = false;
  String? _error;

  Future<void> _refresh() async {
    if (_loading) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final detail = await AirmiusServicesScope.of(
        context,
      ).repositories.clubs.club(widget.club.id);
      if (!mounted) return;
      setState(() {
        _club = ClubSummary.fromAirmiusClub(detail);
        _loading = false;
      });
    } on AirmiusApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error.userMessage;
        _loading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _error = AirmiusScope.of(context).t('clubReports.loadFailed');
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final management = _club.management;
    final summary = management?.summary;
    final canViewMembers = management?.canManageMembers ?? false;
    final canViewFinance = management?.canManageFinance ?? false;
    final money = NumberFormat.simpleCurrency(
      locale: Localizations.localeOf(context).toLanguageTag(),
      name: 'EUR',
    );
    return Scaffold(
      appBar: AppBar(
        title: Text(t('clubReports.title')),
        actions: [
          IconButton(
            tooltip: t('common.refresh'),
            onPressed: _loading ? null : _refresh,
            icon: const Icon(Icons.refresh_outlined),
          ),
        ],
      ),
      body: PageFrame(
        title: t('clubReports.title'),
        subtitle: _club.name,
        showHeader: false,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(
                    _club.name,
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 4),
                  Text(t('clubReports.currentSnapshot')),
                ],
              ),
            ),
            if (_loading) const LinearProgressIndicator(),
            if (_error != null) AirmiusPanel(child: Text(_error!)),
            const SizedBox(height: 12),
            if (management == null || summary == null || summary.isEmpty)
              AirmiusPanel(child: Text(t('clubReports.unavailable')))
            else ...[
              _ReportSection(
                title: t('clubReports.members'),
                footer: t('clubReports.activeMembersHelp').replaceFirst(
                  '{ratio}',
                  _club.members <= 0
                      ? '0'
                      : '${(management.activeMembersCount / _club.members * 100).round()}',
                ),
                values: [
                  _ReportValue(t('clubHub.totalMembers'), '${_club.members}'),
                  if (canViewMembers &&
                      summary.containsKey('active_members_count'))
                    _ReportValue(
                      t('clubReports.activeMembers'),
                      '${management.activeMembersCount}',
                    ),
                  if (canViewMembers &&
                      summary.containsKey('pending_membership_requests_count'))
                    _ReportValue(
                      t('clubReports.openRequests'),
                      '${management.pendingMembershipRequestsCount}',
                    ),
                  _ReportValue(t('clubReports.teams'), '${_club.teams}'),
                ],
              ),
              if (canViewFinance) ...[
                const SizedBox(height: 12),
                _ReportSection(
                  title: t('clubReports.finance'),
                  footer: management.openInvoicesCount > 0
                      ? t('clubReports.financeOpenHelp')
                      : t('clubReports.financeClearHelp'),
                  values: [
                    if (summary.containsKey('open_invoices_count'))
                      _ReportValue(
                        t('clubReports.openInvoices'),
                        '${management.openInvoicesCount}',
                      ),
                    if (summary.containsKey('open_invoice_amount'))
                      _ReportValue(
                        t('clubReports.openAmount'),
                        money.format(management.openInvoiceAmount),
                      ),
                    if (summary.containsKey('total_balance'))
                      _ReportValue(
                        t('clubReports.balance'),
                        money.format(management.totalBalance),
                      ),
                    if (management.hasFinancePeriodTotals)
                      _ReportValue(
                        '${t('clubHub.income')} · ${management.financePeriodLabel}',
                        money.format(management.incomePeriodTotal),
                      ),
                    if (management.hasFinancePeriodTotals)
                      _ReportValue(
                        '${t('clubHub.expenses')} · ${management.financePeriodLabel}',
                        money.format(management.expensePeriodTotal),
                      ),
                    if (management.hasFinancePeriodTotals)
                      _ReportValue(
                        t('clubReports.periodResult'),
                        money.format(
                          management.incomePeriodTotal -
                              management.expensePeriodTotal,
                        ),
                      ),
                  ],
                ),
              ],
            ],
          ],
        ),
      ),
    );
  }
}

class _ReportValue {
  const _ReportValue(this.label, this.value);
  final String label;
  final String value;
}

class _ReportSection extends StatelessWidget {
  const _ReportSection({
    required this.title,
    required this.values,
    this.footer,
  });
  final String title;
  final List<_ReportValue> values;
  final String? footer;

  @override
  Widget build(BuildContext context) => AirmiusPanel(
    title: title,
    child: Column(
      children: [
        for (final item in values)
          ListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(item.label),
            trailing: Text(
              item.value,
              style: Theme.of(context).textTheme.titleMedium,
            ),
          ),
        if (footer != null) ...[
          const Divider(height: 20),
          Align(
            alignment: AlignmentDirectional.centerStart,
            child: Text(
              footer!,
              style: TextStyle(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
            ),
          ),
        ],
      ],
    ),
  );
}
