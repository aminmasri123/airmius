import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import 'admin_backoffice_screen.dart';
import 'admin_mail_center_screen.dart';
import 'outfit_operations_screen.dart';
import 'admin_clubs_screen.dart';
import 'admin_commerce_operations_screen.dart';
import 'admin_support_ticket_screen.dart';
import 'admin_trainer_applications_screen.dart';
import 'platform_admin_screen.dart';

class AdminInsightsScreen extends StatefulWidget {
  const AdminInsightsScreen({super.key, this.analytics = false});
  final bool analytics;

  @override
  State<AdminInsightsScreen> createState() => _InsightsState();
}

class _InsightsState extends State<AdminInsightsScreen> {
  Future<AirmiusJson>? _future;
  int _days = 28;
  String? _workspace;

  String t(String key) => AirmiusScope.of(context).t(key);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  Future<AirmiusJson> _load() async {
    final services = AirmiusServicesScope.of(context);
    final client = services.clientForSession(services.authState.session);
    final response = widget.analytics
        ? await client.adminProductAnalytics(_days)
        : await client.adminOperations(workspace: _workspace);
    return Map<String, dynamic>.from(response['data'] as Map);
  }

  void _reload() => setState(() => _future = _load());

  Widget? _destination(String? target) {
    final path = Uri.tryParse(target ?? '')?.path ?? '';
    if (path.contains('mail-center')) return const AdminMailCenterScreen();
    if (path.contains('outfit-subscriptions')) return const OutfitOperationsScreen();
    if (path.contains('club-verifications') || path == '/admin/clubs') {
      return const AdminClubsScreen();
    }
    if (path.contains('trainer-applications')) {
      return const AdminTrainerApplicationsScreen();
    }
    if (path.contains('moderation')) {
      return const PlatformAdminScreen(initialSection: 'moderation');
    }
    if (path.contains('support')) return const AdminSupportTicketScreen();
    if (path.contains('commerce')) return const AdminCommerceOperationsScreen();
    if (path.contains('subscription') ||
        path.contains('payment') ||
        path.contains('invoice')) {
      return const AdminBackofficeScreen();
    }
    return null;
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(
        t(
          widget.analytics ? 'adminNative.analytics' : 'adminNative.operations',
        ),
      ),
      actions: [
        IconButton(
          tooltip: t('common.refresh'),
          onPressed: _reload,
          icon: const Icon(Icons.refresh),
        ),
      ],
    ),
    body: FutureBuilder<AirmiusJson>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return Center(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Text(
                snapshot.error is AirmiusApiException
                    ? (snapshot.error as AirmiusApiException).userMessage
                    : t('platformAdmin.loadFailed'),
              ),
            ),
          );
        }
        final data = snapshot.data ?? {};
        return ListView(
          padding: const EdgeInsets.all(16),
          children: widget.analytics ? _analytics(data) : _operations(data),
        );
      },
    ),
  );

  List<Widget> _analytics(AirmiusJson data) => [
    SegmentedButton<int>(
      segments: [
        for (final days in [7, 28, 90])
          ButtonSegment(
            value: days,
            label: Text('$days ${t('adminNative.days')}'),
          ),
      ],
      selected: {_days},
      onSelectionChanged: (value) {
        _days = value.single;
        _reload();
      },
    ),
    const SizedBox(height: 16),
    if (data['status'] == 'disabled') Text(t('adminNative.disabled')),
    if (data['period'] is Map)
      Text('${data['period']['from']} – ${data['period']['to']}'),
    for (final metric in (data['metrics'] as List? ?? []).whereType<Map>()) ...[
      ListTile(
        contentPadding: EdgeInsets.zero,
        title: Text(t('adminNative.${metric['key']}')),
        subtitle: metric['suppressed'] == true
            ? Text(t('adminNative.private'))
            : null,
        trailing: Text(
          metric['suppressed'] == true
              ? '—'
              : '${metric['value'] ?? '—'}${metric['rate_percent'] == null ? '' : ' (${metric['rate_percent']}%)'}',
        ),
      ),
      const Divider(height: 1),
    ],
  ];

  List<Widget> _operations(AirmiusJson data) {
    final cases = (data['cases'] as List? ?? []).whereType<Map>();
    final workspaces = (data['workspaces'] as List? ?? []).whereType<Map>();
    return [
      DropdownButtonFormField<String>(
        initialValue: data['workspace'] as String?,
        isExpanded: true,
        items: [
          for (final workspace in workspaces)
            DropdownMenuItem(
              value: '${workspace['key']}',
              child: Text('${workspace['label']}'),
            ),
        ],
        onChanged: (value) {
          _workspace = value;
          _reload();
        },
      ),
      const SizedBox(height: 16),
      if (cases.isEmpty) Text(t('adminNative.empty')),
      for (final item in cases) ...[
        Builder(
          builder: (context) {
            final destination = _destination(item['target_url'] as String?);
            return ListTile(
              contentPadding: EdgeInsets.zero,
              title: Text('${item['title']}'),
              subtitle: Text(
                '${item['reference']}\n${item['status_label']} · ${item['priority_label']}${item['due_at'] == null ? '' : '\n${item['due_at']}'}',
              ),
              leading: Icon(
                item['is_overdue'] == true
                    ? Icons.warning_amber
                    : Icons.assignment_outlined,
              ),
              trailing: destination == null
                  ? null
                  : const Icon(Icons.chevron_right),
              onTap: destination == null
                  ? null
                  : () => Navigator.of(context).push(
                      MaterialPageRoute<void>(builder: (_) => destination),
                    ),
            );
          },
        ),
        const Divider(height: 1),
      ],
      const SizedBox(height: 24),
      for (final source in (data['sources'] as List? ?? []).whereType<Map>())
        ListTile(
          title: Text('${source['label']}'),
          trailing: Text('${source['visible']}${source['has_more'] == true ? '+' : ''}'),
        ),
      for (final event in (data['timeline'] as List? ?? []).whereType<Map>())
        ListTile(
          leading: const Icon(Icons.history),
          title: Text('${event['label'] ?? ''}'),
          subtitle: Text('${event['occurred_at'] ?? event['at'] ?? ''}'),
        ),
    ];
  }
}
