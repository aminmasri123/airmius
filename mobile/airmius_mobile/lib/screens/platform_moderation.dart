part of 'platform_admin_screen.dart';

String _caseReason(String Function(String) t, String value) {
  final key =
      'moderation.report.reason.${value == 'image_rights' ? 'imageRights' : value}';
  final label = t(key);
  return label == key ? value.replaceAll('_', ' ') : label;
}

String _caseLabel(String Function(String) t, dynamic raw) {
  final value = _platformText(raw);
  final key = switch (value.toLowerCase()) {
    'low' || 'medium' || 'high' => 'trainingHub.load.$value',
    'open' => 'clubTasks.status.open',
    'actioned' => 'platformAdmin.status.actioned',
    'dismissed' => 'moderationDesk.dismissed',
    'content_removed' => 'moderationDesk.removed',
    'user_report' => 'platformAdmin.userReports',
    'automatic' => 'moderationDesk.automatic',
    'pending' => 'backoffice.status.pending',
    'accepted' => 'adminNative.approved',
    'rejected' => 'adminNative.rejected',
    'post' || 'comment' || 'message' => 'moderationDesk.${value.toLowerCase()}',
    _ => '',
  };
  return key.isEmpty ? value.replaceAll('_', ' ') : t(key);
}

class _ModerationCaseRow extends StatelessWidget {
  const _ModerationCaseRow({
    required this.item,
    required this.list,
    required this.onTap,
  });
  final JsonMap item;
  final String list;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final content = _platformMap(item['content']);
    final date = DateTime.tryParse(
      _platformText(item['created_at']),
    )?.toLocal();
    final loc = MaterialLocalizations.of(context);
    final reason = list == 'reports'
        ? _caseReason(t, _platformText(item['reason']))
        : _platformStrings(
            item['categories'],
          ).map((v) => _caseReason(t, v)).join(', ');
    final pending = item['appeal_status'] == 'pending';
    final action = pending
        ? 'platformAdmin.decideAppeal'
        : item['status'] == 'open'
        ? 'platformAdmin.review'
        : 'moderationDesk.view';
    return Card(
      child: ListTile(
        onTap: onTap,
        leading: Icon(
          list == 'reports' ? Icons.report_outlined : Icons.flag_outlined,
        ),
        title: Text(
          _platformText(
            content['text'],
            fallback: '${t('moderationDesk.case')} #${item['id']}',
          ),
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              [
                _caseLabel(t, content['type']),
                t('moderationDesk.$list'),
                reason,
              ].where((v) => v.isNotEmpty).join(' · '),
            ),
            Text(
              [
                _caseLabel(t, item['status']),
                if (date != null)
                  '${loc.formatCompactDate(date)} ${loc.formatTimeOfDay(TimeOfDay.fromDateTime(date))}',
              ].join(' · '),
            ),
            Text(
              t(action),
              style: TextStyle(color: Theme.of(context).colorScheme.primary),
            ),
          ],
        ),
        trailing: const Icon(Icons.chevron_right),
      ),
    );
  }
}

class _ModerationCasePage extends StatefulWidget {
  const _ModerationCasePage({required this.item, required this.list});
  final JsonMap item;
  final String list;
  @override
  State<_ModerationCasePage> createState() => _ModerationCasePageState();
}

class _ModerationCasePageState extends State<_ModerationCasePage> {
  bool _busy = false;

  Future<void> _decide({bool appeal = false}) async {
    if (_busy) return;
    final payload = await showDialog<JsonMap>(
      context: context,
      builder: (_) => appeal
          ? const _AppealDecisionDialog()
          : _ModerationDecisionDialog(item: widget.item),
    );
    if (!mounted || payload == null) return;
    final services = AirmiusServicesScope.of(context);
    final client = services.clientForSession(services.authState.session);
    final id = _platformInt(widget.item['id']);
    setState(() => _busy = true);
    try {
      if (appeal) {
        await client.adminDecideModerationAppeal(id, payload);
      } else if (widget.list == 'reports') {
        await client.adminUpdateModerationReport(id, payload);
      } else {
        await client.adminUpdateModerationFlag(id, payload);
      }
      if (!mounted) return;
      final t = AirmiusScope.of(context).t;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            t(
              appeal
                  ? 'platformAdmin.appealSaved'
                  : 'platformAdmin.moderationSaved',
            ),
          ),
        ),
      );
      setState(() => _busy = false);
      Navigator.pop(context, true);
    } on AirmiusApiException catch (error) {
      if (!mounted) return;
      setState(() => _busy = false);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.userMessage)));
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final item = widget.item;
    final content = _platformMap(item['content']);
    final details = {
      'moderationDesk.source': [
        _caseLabel(t, content['type']),
        if (content['id'] != null) '#${content['id']}',
      ].where((v) => v.isNotEmpty).join(' '),
      'moderationDesk.author': _platformText(
        content['author'],
        fallback: _platformText(_platformMap(item['user'])['name']),
      ),
      'platformAdmin.reporter': _platformText(
        _platformMap(item['reporter'])['name'],
      ),
      'platformAdmin.source': _caseLabel(t, item['source']),
      'platformAdmin.reason': [
        if (item['reason'] != null)
          _caseReason(t, _platformText(item['reason'])),
        ..._platformStrings(item['categories']).map((v) => _caseReason(t, v)),
      ].join(', '),
      'platformAdmin.severity': _caseLabel(t, item['severity']),
      'moderation.report.details': _platformText(item['details']),
      'adminNative.status': _caseLabel(t, item['status']),
      if (item['action_taken'] != item['status'])
        'platformAdmin.decision': _caseLabel(t, item['action_taken']),
      'platformAdmin.decisionReason': _platformText(item['decision_reason']),
      'platformAdmin.appeal': [
        _caseLabel(t, item['appeal_status']),
        _platformText(item['appeal_reason']),
      ].where((v) => v.isNotEmpty).join(' · '),
      'platformAdmin.decideAppeal': _platformText(item['appeal_decision']),
    };
    return PopScope(
      canPop: !_busy,
      child: Scaffold(
        appBar: AppBar(
          title: Text('${t('moderationDesk.case')} #${item['id']}'),
        ),
        body: SafeArea(
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Text(
                _platformText(
                  content['text'],
                  fallback: t('platformAdmin.moderationCase'),
                ),
                style: Theme.of(context).textTheme.titleLarge,
              ),
              for (final field in details.entries)
                if (field.value.isNotEmpty)
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    title: Text(t(field.key)),
                    subtitle: Text(field.value),
                  ),
              if (item['appeal_status'] == 'pending')
                FilledButton(
                  onPressed: _busy ? null : () => _decide(appeal: true),
                  child: Text(t('platformAdmin.decideAppeal')),
                ),
              OutlinedButton(
                onPressed: _busy ? null : () => _decide(),
                child: Text(
                  t(
                    item['status'] == 'open'
                        ? 'platformAdmin.reviewCase'
                        : 'moderationDesk.change',
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
