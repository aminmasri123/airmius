import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_club_task_attachment_service.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../models/club_summary.dart';
import 'club_membership_management_screen.dart';
import 'club_request_inbox_screen.dart';
import 'teams_center_screen.dart';

class ClubTasksScreen extends StatefulWidget {
  const ClubTasksScreen({
    super.key,
    required this.clubId,
    this.initialCalendar = false,
  });

  final int clubId;
  final bool initialCalendar;

  @override
  State<ClubTasksScreen> createState() => _ClubTasksScreenState();
}

class _ClubTasksScreenState extends State<ClubTasksScreen> {
  ClubSummary? _club;
  List<AirmiusJson> _tasks = [];
  List<AirmiusJson> _members = [];
  List<AirmiusJson> _teams = [];
  List<AirmiusJson> _events = [];
  _ClubTaskView _view = _ClubTaskView.tasks;
  _ClubCalendarView _calendarView = _ClubCalendarView.week;
  DateTime _calendarCursor = DateTime.now();
  bool _loaded = false;
  bool _loading = true;
  bool _saving = false;
  String? _error;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  AirmiusClubTaskAttachmentService get _attachmentService {
    final services = AirmiusServicesScope.of(context);
    final client = services.clientForSession(services.authState.session);
    return AirmiusClubTaskAttachmentService(
      baseUrl: client.baseUrl,
      token: client.token,
      locale: client.locale,
    );
  }

  String _t(String key) => AirmiusScope.of(context).t(key);

  @override
  void initState() {
    super.initState();
    if (widget.initialCalendar) {
      _view = _ClubTaskView.calendar;
    }
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_loaded) {
      _loaded = true;
      _load();
    }
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final club = ClubSummary.fromAirmiusClub(
        await AirmiusServicesScope.of(
          context,
        ).repositories.clubs.club(widget.clubId),
      );
      if (!mounted) return;
      setState(() => _club = club);
      final response = await _client.clubTasks(widget.clubId);
      final meta = (response['meta'] as AirmiusJson?) ?? const {};
      if (!mounted) return;
      setState(() {
        _tasks = ((response['data'] as List?) ?? const []).cast<AirmiusJson>();
        _members = ((meta['members'] as List?) ?? const []).cast<AirmiusJson>();
        _teams = ((meta['teams'] as List?) ?? const []).cast<AirmiusJson>();
        _events = ((meta['calendar_events'] as List?) ?? const [])
            .cast<AirmiusJson>();
      });
    } catch (error) {
      if (mounted) {
        setState(
          () => _error = error is AirmiusApiException
              ? error.userMessage
              : _t('common.errorDetails'),
        );
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _save(Future<void> Function() action) async {
    if (_saving) return;
    setState(() => _saving = true);
    try {
      await action();
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              error is AirmiusApiException
                  ? error.userMessage
                  : _t('common.errorDetails'),
            ),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _edit([AirmiusJson? task]) async {
    final result = await Navigator.of(context).push<_ClubTaskEditorResult>(
      MaterialPageRoute(
        builder: (_) => _ClubTaskEditor(
          members: _members,
          teams: _teams,
          task: task,
          t: _t,
        ),
      ),
    );
    if (result == null || !mounted) return;
    await _save(() async {
      final payload = Map<String, dynamic>.from(result.payload);
      if (task != null && _text(task, 'updated_at').isNotEmpty) {
        payload['updated_at'] = _text(task, 'updated_at');
      }
      final response = task == null
          ? await _client.createClubTask(widget.clubId, payload)
          : await _client.updateClubTask(
              widget.clubId,
              task['id'] as int,
              payload,
            );
      if (!mounted) return;
      final updated = response['data'] as AirmiusJson;
      if (result.attachments.isNotEmpty) {
        await _attachmentService.upload(
          clubId: widget.clubId,
          taskId: updated['id'] as int,
          attachments: result.attachments,
        );
        await _load();
      } else {
        _upsert(updated);
      }
    });
  }

  Future<void> _toggle(AirmiusJson task, bool completed) => _save(() async {
    final response = await _client
        .updateClubTask(widget.clubId, task['id'] as int, {
          'completed': completed,
          if (_text(task, 'updated_at').isNotEmpty)
            'updated_at': _text(task, 'updated_at'),
        });
    if (!mounted) return;
    _upsert(response['data'] as AirmiusJson);
  });

  Future<void> _comment(AirmiusJson task, String body) => _save(() async {
    await _client.commentClubTask(widget.clubId, task['id'] as int, body);
    await _load();
  });

  Future<void> _attach(AirmiusJson task) async {
    final picked = await FilePicker.platform.pickFiles(
      allowMultiple: true,
      withData: true,
    );
    if (picked == null || picked.files.isEmpty || !mounted) return;
    await _save(() async {
      await _attachmentService.upload(
        clubId: widget.clubId,
        taskId: task['id'] as int,
        attachments: picked.files,
      );
      await _load();
    });
  }

  Future<void> _detach(AirmiusJson task, int fileId) => _save(() async {
    await _client.deleteClubTaskAttachment(
      widget.clubId,
      task['id'] as int,
      fileId,
    );
    await _load();
  });

  Future<void> _delete(AirmiusJson task) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(_t('clubTasks.delete')),
        content: Text(_text(task, 'title')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(_t('common.cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(_t('common.delete')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    await _save(() async {
      await _client.deleteClubTask(widget.clubId, task['id'] as int);
      if (mounted) {
        setState(() => _tasks.removeWhere((item) => item['id'] == task['id']));
      }
    });
  }

  Future<void> _open(Widget screen) async {
    await Navigator.of(
      context,
    ).push(MaterialPageRoute<void>(builder: (_) => screen));
    if (mounted) await _load();
  }

  void _upsert(AirmiusJson updated) {
    setState(() {
      _tasks = [
        updated,
        ..._tasks.where((item) => item['id'] != updated['id']),
      ];
    });
  }

  @override
  Widget build(BuildContext context) {
    final club = _club;
    final pendingMembers = club?.pendingMembershipRequests ?? 0;
    final pendingTeams = club?.pendingTeamJoinRequests ?? 0;
    final invoices = club?.management?.openInvoicesCount ?? 0;
    final openTasks = _tasks.where((task) => _text(task, 'status') != 'done');
    final doneTasks = _tasks.where((task) => _text(task, 'status') == 'done');

    return Scaffold(
      appBar: AppBar(title: Text(_t('clubTasks.title'))),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _saving || _loading || _error != null ? null : () => _edit(),
        icon: const Icon(Icons.add_task_outlined),
        label: Text(_t('clubTasks.add')),
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
          children: [
            if (_loading) const LinearProgressIndicator(),
            SegmentedButton<_ClubTaskView>(
              segments: [
                ButtonSegment(
                  value: _ClubTaskView.tasks,
                  icon: const Icon(Icons.checklist_outlined),
                  label: Text(_t('clubTasks.tab.tasks')),
                ),
                ButtonSegment(
                  value: _ClubTaskView.calendar,
                  icon: const Icon(Icons.calendar_month_outlined),
                  label: Text(_t('clubTasks.tab.calendar')),
                ),
              ],
              selected: {_view},
              onSelectionChanged: (values) =>
                  setState(() => _view = values.first),
            ),
            const SizedBox(height: 16),
            if (club != null) ...[
              Text(club.name, style: Theme.of(context).textTheme.titleLarge),
              const SizedBox(height: 20),
            ],
            if (_view == _ClubTaskView.calendar) ...[
              _ClubTaskCalendar(
                tasks: _tasks,
                events: _events,
                view: _calendarView,
                cursor: _calendarCursor,
                t: _t,
                onViewChanged: (value) => setState(() => _calendarView = value),
                onMove: (delta) => setState(() {
                  _calendarCursor = switch (_calendarView) {
                    _ClubCalendarView.day => DateTime(
                      _calendarCursor.year,
                      _calendarCursor.month,
                      _calendarCursor.day + delta,
                    ),
                    _ClubCalendarView.week => DateTime(
                      _calendarCursor.year,
                      _calendarCursor.month,
                      _calendarCursor.day + (delta * 7),
                    ),
                    _ClubCalendarView.month => _shiftCalendarMonths(
                      _calendarCursor,
                      delta,
                    ),
                    _ClubCalendarView.year => _shiftCalendarMonths(
                      _calendarCursor,
                      delta * 12,
                    ),
                  };
                }),
              ),
            ] else ...[
              if (club != null) ...[
                Text(
                  _t('clubTasks.clubActions'),
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                if (pendingMembers > 0)
                  ListTile(
                    leading: const Icon(Icons.person_add_outlined),
                    title: Text(_t('clubHub.pendingApplications')),
                    trailing: Text('$pendingMembers'),
                    onTap: () => _open(
                      ClubRequestInboxScreen(initialClubId: widget.clubId),
                    ),
                  ),
                if (pendingTeams > 0)
                  ListTile(
                    leading: const Icon(Icons.groups_outlined),
                    title: Text(_t('clubHub.pendingTeamRequests')),
                    trailing: Text('$pendingTeams'),
                    onTap: () =>
                        _open(TeamsCenterScreen(initialClubId: widget.clubId)),
                  ),
                if (invoices > 0)
                  ListTile(
                    leading: const Icon(Icons.receipt_long_outlined),
                    title: Text(_t('clubHub.openPayments')),
                    trailing: Text('$invoices'),
                    onTap: () => _open(
                      ClubMembershipManagementScreen(
                        initialClubId: widget.clubId,
                        initialSection: 'payments',
                      ),
                    ),
                  ),
                if (pendingMembers + pendingTeams + invoices == 0)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    child: Text(_t('clubHub.noOpenTasksBody')),
                  ),
              ],
              const Divider(height: 32),
              Text(
                _t('clubTasks.ownTasks'),
                style: Theme.of(context).textTheme.titleMedium,
              ),
              const SizedBox(height: 8),
              if (_error != null) ...[
                Text(_error!),
                TextButton(
                  onPressed: _loading ? null : _load,
                  child: Text(_t('common.retry')),
                ),
              ],
              if (_saving) const LinearProgressIndicator(),
              if (!_loading && _error == null && _tasks.isEmpty)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  child: Text(_t('clubTasks.empty')),
                ),
              if (openTasks.isNotEmpty) _SectionTitle(_t('clubTasks.open')),
              for (final task in openTasks)
                _TaskTile(
                  task: task,
                  completed: false,
                  t: _t,
                  onTap: () => _showDetails(task),
                  onToggle: (value) => _toggle(task, value),
                  onEdit: () => _edit(task),
                  onDelete: () => _delete(task),
                ),
              if (doneTasks.isNotEmpty)
                _SectionTitle(_t('clubTasks.completed')),
              for (final task in doneTasks)
                _TaskTile(
                  task: task,
                  completed: true,
                  t: _t,
                  onTap: () => _showDetails(task),
                  onToggle: (value) => _toggle(task, value),
                  onEdit: () => _edit(task),
                  onDelete: () => _delete(task),
                ),
            ],
          ],
        ),
      ),
    );
  }

  Future<void> _showDetails(AirmiusJson task) async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => _ClubTaskDetails(
        task: task,
        t: _t,
        saving: _saving,
        onComment: (body) => _comment(task, body),
        onAttach: () => _attach(task),
        onRemoveAttachment: (fileId) => _detach(task, fileId),
        onOpenUrl: _openUrl,
      ),
    );
    if (mounted) await _load();
  }

  Future<void> _openUrl(String value) async {
    final uri = Uri.tryParse(value);
    if (uri == null || !uri.hasScheme) return;
    await launchUrl(uri, mode: LaunchMode.externalApplication);
  }
}

class _TaskTile extends StatelessWidget {
  const _TaskTile({
    required this.task,
    required this.completed,
    required this.t,
    required this.onTap,
    required this.onToggle,
    required this.onEdit,
    required this.onDelete,
  });

  final AirmiusJson task;
  final bool completed;
  final String Function(String key) t;
  final VoidCallback onTap;
  final ValueChanged<bool> onToggle;
  final VoidCallback onEdit;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final assignee = (task['assignee'] as AirmiusJson?)?['name'] as String?;
    final dueAt = _text(task, 'due_at');
    final meta = [
      t('clubTasks.priority.${_text(task, 'priority', fallback: 'normal')}'),
      if (assignee != null && assignee.trim().isNotEmpty) assignee,
      if (dueAt.isNotEmpty) '${t('clubTasks.due')}: $dueAt',
    ].join(' · ');
    return Card(
      margin: const EdgeInsets.symmetric(vertical: 5),
      child: ListTile(
        onTap: onTap,
        leading: Checkbox(
          value: completed,
          semanticLabel: _text(task, 'title'),
          onChanged: (value) => onToggle(value ?? false),
        ),
        title: Text(
          _text(task, 'title'),
          style: TextStyle(
            decoration: completed ? TextDecoration.lineThrough : null,
          ),
        ),
        subtitle: Text(meta),
        trailing: PopupMenuButton<String>(
          onSelected: (value) => value == 'edit' ? onEdit() : onDelete(),
          itemBuilder: (_) => [
            PopupMenuItem(value: 'edit', child: Text(t('clubTasks.edit'))),
            PopupMenuItem(value: 'delete', child: Text(t('common.delete'))),
          ],
        ),
      ),
    );
  }
}

class _ClubTaskDetails extends StatefulWidget {
  const _ClubTaskDetails({
    required this.task,
    required this.t,
    required this.saving,
    required this.onComment,
    required this.onAttach,
    required this.onRemoveAttachment,
    required this.onOpenUrl,
  });

  final AirmiusJson task;
  final String Function(String key) t;
  final bool saving;
  final Future<void> Function(String body) onComment;
  final Future<void> Function() onAttach;
  final Future<void> Function(int fileId) onRemoveAttachment;
  final Future<void> Function(String url) onOpenUrl;

  @override
  State<_ClubTaskDetails> createState() => _ClubTaskDetailsState();
}

class _ClubTaskDetailsState extends State<_ClubTaskDetails> {
  final _comment = TextEditingController();

  @override
  void dispose() {
    _comment.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final comments = _list(widget.task['comments']);
    final attachments = _list(widget.task['attachments']);
    final attachmentLinks = _list(widget.task['attachment_links']);
    final checklist = _list(widget.task['checklist']);
    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: 0.88,
      minChildSize: 0.55,
      maxChildSize: 0.96,
      builder: (context, controller) => ListView(
        controller: controller,
        padding: EdgeInsets.only(
          left: 16,
          right: 16,
          top: 12,
          bottom: MediaQuery.of(context).viewInsets.bottom + 16,
        ),
        children: [
          Text(
            _text(widget.task, 'title'),
            style: Theme.of(context).textTheme.titleLarge,
          ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              Chip(
                label: Text(
                  widget.t(
                    'clubTasks.status.${_text(widget.task, 'status', fallback: 'open')}',
                  ),
                ),
              ),
              Chip(
                label: Text(
                  widget.t(
                    'clubTasks.priority.${_text(widget.task, 'priority', fallback: 'normal')}',
                  ),
                ),
              ),
              if (_text(widget.task, 'due_at').isNotEmpty)
                Chip(
                  label: Text(
                    '${widget.t('clubTasks.due')}: ${_text(widget.task, 'due_at')}',
                  ),
                ),
            ],
          ),
          if (_text(widget.task, 'description').isNotEmpty) ...[
            const SizedBox(height: 16),
            Text(_text(widget.task, 'description')),
          ],
          if (checklist.isNotEmpty) ...[
            const SizedBox(height: 18),
            Text(
              widget.t('clubTasks.checklist'),
              style: Theme.of(context).textTheme.titleMedium,
            ),
            for (final item in checklist)
              CheckboxListTile(
                value: item['done'] == true,
                onChanged: null,
                dense: true,
                title: Text(_text(item, 'title')),
              ),
          ],
          const SizedBox(height: 18),
          Row(
            children: [
              Expanded(
                child: Text(
                  widget.t('clubTasks.attachments'),
                  style: Theme.of(context).textTheme.titleMedium,
                ),
              ),
              IconButton.filledTonal(
                onPressed: widget.saving ? null : widget.onAttach,
                tooltip: widget.t('clubTasks.addAttachment'),
                icon: const Icon(Icons.attach_file),
              ),
            ],
          ),
          if (attachments.isEmpty && attachmentLinks.isEmpty)
            Text(widget.t('clubTasks.noAttachments'))
          else
            for (final attachment in attachments)
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: const Icon(Icons.insert_drive_file_outlined),
                title: Text(
                  _text(
                    attachment,
                    'display_name',
                    fallback: _text(attachment, 'path'),
                  ),
                ),
                subtitle: Text(_text(attachment, 'type')),
                onTap: () {
                  final url = _text(
                    attachment,
                    'preview_url',
                    fallback: _text(attachment, 'url'),
                  );
                  if (url.isNotEmpty) widget.onOpenUrl(url);
                },
                trailing: IconButton(
                  tooltip: widget.t('common.delete'),
                  onPressed: widget.saving
                      ? null
                      : () {
                          final fileId = attachment['id'];
                          if (fileId is int) widget.onRemoveAttachment(fileId);
                        },
                  icon: const Icon(Icons.delete_outline),
                ),
              ),
          for (final link in attachmentLinks)
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.link_outlined),
              title: Text(_text(link, 'title', fallback: _text(link, 'url'))),
              subtitle: Text(_text(link, 'url')),
              onTap: () {
                final url = _text(link, 'url');
                if (url.isNotEmpty) widget.onOpenUrl(url);
              },
            ),
          const SizedBox(height: 18),
          Text(
            widget.t('clubTasks.comments'),
            style: Theme.of(context).textTheme.titleMedium,
          ),
          if (comments.isEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 8),
              child: Text(widget.t('clubTasks.noComments')),
            ),
          for (final comment in comments)
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const CircleAvatar(child: Icon(Icons.comment_outlined)),
              title: Text(
                _text(
                  comment['user'] as AirmiusJson? ?? const {},
                  'name',
                  fallback: widget.t('clubTasks.unknown'),
                ),
              ),
              subtitle: Text(_text(comment, 'body')),
            ),
          const SizedBox(height: 8),
          TextField(
            controller: _comment,
            minLines: 1,
            maxLines: 4,
            decoration: InputDecoration(
              labelText: widget.t('clubTasks.commentPlaceholder'),
              suffixIcon: IconButton(
                onPressed: widget.saving
                    ? null
                    : () async {
                        final body = _comment.text.trim();
                        if (body.isEmpty) return;
                        _comment.clear();
                        await widget.onComment(body);
                        if (context.mounted) Navigator.pop(context);
                      },
                icon: const Icon(Icons.send_outlined),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

enum _ClubTaskView { tasks, calendar }

enum _ClubCalendarView { day, week, month, year }

class _ClubTaskCalendar extends StatelessWidget {
  const _ClubTaskCalendar({
    required this.tasks,
    required this.events,
    required this.view,
    required this.cursor,
    required this.t,
    required this.onViewChanged,
    required this.onMove,
  });

  final List<AirmiusJson> tasks;
  final List<AirmiusJson> events;
  final _ClubCalendarView view;
  final DateTime cursor;
  final String Function(String key) t;
  final ValueChanged<_ClubCalendarView> onViewChanged;
  final ValueChanged<int> onMove;

  @override
  Widget build(BuildContext context) {
    final items = _calendarItems();
    final days = switch (view) {
      _ClubCalendarView.day => [_dateOnly(cursor)],
      _ClubCalendarView.week => _weekDays(cursor),
      _ClubCalendarView.month => List.generate(
        DateUtils.getDaysInMonth(cursor.year, cursor.month),
        (index) => DateTime(cursor.year, cursor.month, index + 1),
      ),
      _ClubCalendarView.year => List.generate(
        12,
        (index) => DateTime(cursor.year, index + 1),
      ),
    };
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: SegmentedButton<_ClubCalendarView>(
                showSelectedIcon: false,
                segments: [
                  ButtonSegment(
                    value: _ClubCalendarView.day,
                    label: Text(t('clubTasks.calendar.day')),
                  ),
                  ButtonSegment(
                    value: _ClubCalendarView.week,
                    label: Text(t('clubTasks.calendar.week')),
                  ),
                  ButtonSegment(
                    value: _ClubCalendarView.month,
                    label: Text(t('clubTasks.calendar.month')),
                  ),
                  ButtonSegment(
                    value: _ClubCalendarView.year,
                    label: Text(t('clubTasks.calendar.year')),
                  ),
                ],
                selected: {view},
                onSelectionChanged: (values) => onViewChanged(values.first),
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            IconButton.filledTonal(
              tooltip: MaterialLocalizations.of(context).previousPageTooltip,
              onPressed: () => onMove(-1),
              icon: const Icon(Icons.chevron_left),
            ),
            Expanded(
              child: Center(
                child: Text(
                  _rangeLabel(context),
                  style: Theme.of(context).textTheme.titleMedium,
                ),
              ),
            ),
            IconButton.filledTonal(
              tooltip: MaterialLocalizations.of(context).nextPageTooltip,
              onPressed: () => onMove(1),
              icon: const Icon(Icons.chevron_right),
            ),
          ],
        ),
        const SizedBox(height: 12),
        for (final day in days)
          _CalendarBucket(
            title: view == _ClubCalendarView.year
                ? MaterialLocalizations.of(context).formatMonthYear(day)
                : MaterialLocalizations.of(context).formatFullDate(day),
            items: items
                .where(
                  (item) => view == _ClubCalendarView.year
                      ? item.date.year == day.year &&
                            item.date.month == day.month
                      : _sameDay(item.date, day),
                )
                .toList(),
            t: t,
          ),
      ],
    );
  }

  List<_CalendarItem> _calendarItems() {
    final result = <_CalendarItem>[];
    for (final task in tasks) {
      final date = DateTime.tryParse(_text(task, 'due_at'));
      if (date == null) continue;
      result.add(
        _CalendarItem(
          title: _text(task, 'title'),
          date: _dateOnly(date),
          type: t('clubTasks.calendar.task'),
          icon: Icons.check_circle_outline,
        ),
      );
    }
    for (final event in events) {
      final date = DateTime.tryParse(_text(event, 'start_time'));
      if (date == null) continue;
      result.add(
        _CalendarItem(
          title: _text(event, 'title'),
          date: date.toLocal(),
          type: t('clubTasks.calendar.event'),
          icon: Icons.event_available_outlined,
        ),
      );
    }
    result.sort((a, b) => a.date.compareTo(b.date));
    return result;
  }

  String _rangeLabel(BuildContext context) {
    final localizations = MaterialLocalizations.of(context);
    return switch (view) {
      _ClubCalendarView.day => localizations.formatFullDate(cursor),
      _ClubCalendarView.week =>
        '${localizations.formatShortDate(_weekDays(cursor).first)} - ${localizations.formatShortDate(_weekDays(cursor).last)}',
      _ClubCalendarView.month => localizations.formatMonthYear(cursor),
      _ClubCalendarView.year => localizations.formatYear(cursor),
    };
  }
}

class _CalendarBucket extends StatelessWidget {
  const _CalendarBucket({
    required this.title,
    required this.items,
    required this.t,
  });

  final String title;
  final List<_CalendarItem> items;
  final String Function(String key) t;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: Theme.of(context).textTheme.titleSmall),
            const SizedBox(height: 8),
            if (items.isEmpty)
              Text(t('clubTasks.calendar.empty'))
            else
              for (final item in items.take(8))
                ListTile(
                  dense: true,
                  contentPadding: EdgeInsets.zero,
                  leading: Icon(item.icon),
                  title: Text(item.title),
                  subtitle: Text(
                    '${item.type} · ${MaterialLocalizations.of(context).formatShortDate(item.date)}',
                  ),
                ),
            if (items.length > 8)
              Text(
                t(
                  'clubTasks.calendar.more',
                ).replaceFirst('{count}', '${items.length - 8}'),
              ),
          ],
        ),
      ),
    );
  }
}

class _CalendarItem {
  const _CalendarItem({
    required this.title,
    required this.date,
    required this.type,
    required this.icon,
  });

  final String title;
  final DateTime date;
  final String type;
  final IconData icon;
}

class _ClubTaskEditor extends StatefulWidget {
  const _ClubTaskEditor({
    required this.members,
    required this.teams,
    required this.task,
    required this.t,
  });

  final List<AirmiusJson> members;
  final List<AirmiusJson> teams;
  final AirmiusJson? task;
  final String Function(String key) t;

  @override
  State<_ClubTaskEditor> createState() => _ClubTaskEditorState();
}

class _ClubTaskEditorResult {
  const _ClubTaskEditorResult({
    required this.payload,
    required this.attachments,
  });

  final AirmiusJson payload;
  final List<PlatformFile> attachments;
}

class _ClubTaskEditorState extends State<_ClubTaskEditor> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _title;
  late final TextEditingController _description;
  late final TextEditingController _dueAt;
  late final TextEditingController _checklist;
  late final TextEditingController _attachmentLinks;
  late final FocusNode _checklistFocus;
  final List<PlatformFile> _pendingAttachments = [];
  String _status = 'open';
  String _priority = 'normal';
  String _visibility = 'club';
  int? _assignedTo;
  int? _teamId;

  @override
  void initState() {
    super.initState();
    final task = widget.task;
    _title = TextEditingController(text: _text(task ?? const {}, 'title'));
    _description = TextEditingController(
      text: _text(task ?? const {}, 'description'),
    );
    _dueAt = TextEditingController(text: _text(task ?? const {}, 'due_at'));
    _status = _text(task ?? const {}, 'status', fallback: 'open');
    _priority = _text(task ?? const {}, 'priority', fallback: 'normal');
    _visibility = _text(task ?? const {}, 'visibility', fallback: 'club');
    _assignedTo = task?['assigned_to'] as int?;
    _teamId = task?['team_id'] as int?;
    _checklist = TextEditingController(
      text: _list(task?['checklist'])
          .map(
            (item) =>
                '${item['done'] == true ? '[x] ' : ''}${_text(item, 'title')}',
          )
          .join('\n'),
    );
    _attachmentLinks = TextEditingController(
      text: _list(task?['attachment_links'])
          .map((item) => _text(item, 'url'))
          .where((url) => url.isNotEmpty)
          .join('\n'),
    );
    _checklistFocus = FocusNode();
  }

  @override
  void dispose() {
    _title.dispose();
    _description.dispose();
    _dueAt.dispose();
    _checklist.dispose();
    _attachmentLinks.dispose();
    _checklistFocus.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 3,
      child: Scaffold(
        appBar: AppBar(
          title: Text(
            widget.t(widget.task == null ? 'clubTasks.add' : 'clubTasks.edit'),
          ),
        ),
        body: SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Form(
              key: _formKey,
              child: Column(
                children: [
                  TabBar(
                    isScrollable: true,
                    tabAlignment: TabAlignment.start,
                    tabs: [
                      Tab(text: widget.t('clubTasks.editor.basic')),
                      Tab(text: widget.t('clubTasks.editor.planning')),
                      Tab(text: widget.t('clubTasks.editor.checklist')),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Expanded(
                    child: TabBarView(
                      children: [
                        _EditorTab(
                          children: [
                            TextFormField(
                              controller: _title,
                              autofocus: true,
                              maxLength: 255,
                              decoration: InputDecoration(
                                labelText: widget.t('clubTasks.task'),
                                prefixIcon: const Icon(
                                  Icons.edit_note_outlined,
                                ),
                              ),
                              validator: (value) =>
                                  value == null || value.trim().isEmpty
                                  ? widget.t('clubTasks.required')
                                  : null,
                            ),
                            TextFormField(
                              controller: _description,
                              minLines: 4,
                              maxLines: 8,
                              decoration: InputDecoration(
                                labelText: widget.t('clubTasks.description'),
                                alignLabelWithHint: true,
                                prefixIcon: const Icon(Icons.notes_outlined),
                              ),
                            ),
                          ],
                        ),
                        _EditorTab(
                          children: [
                            DropdownButtonFormField<String>(
                              initialValue: _status,
                              decoration: InputDecoration(
                                labelText: widget.t('clubTasks.status'),
                                prefixIcon: const Icon(Icons.flag_outlined),
                              ),
                              items: ['open', 'in_progress', 'waiting', 'done']
                                  .map(
                                    (value) => DropdownMenuItem(
                                      value: value,
                                      child: Text(
                                        widget.t('clubTasks.status.$value'),
                                      ),
                                    ),
                                  )
                                  .toList(),
                              onChanged: (value) =>
                                  setState(() => _status = value ?? 'open'),
                            ),
                            DropdownButtonFormField<String>(
                              initialValue: _priority,
                              decoration: InputDecoration(
                                labelText: widget.t('clubTasks.priority'),
                                prefixIcon: const Icon(Icons.priority_high),
                              ),
                              items: ['low', 'normal', 'high', 'urgent']
                                  .map(
                                    (value) => DropdownMenuItem(
                                      value: value,
                                      child: Text(
                                        widget.t('clubTasks.priority.$value'),
                                      ),
                                    ),
                                  )
                                  .toList(),
                              onChanged: (value) =>
                                  setState(() => _priority = value ?? 'normal'),
                            ),
                            TextFormField(
                              controller: _dueAt,
                              decoration: InputDecoration(
                                labelText: widget.t('clubTasks.dueDate'),
                                hintText: '2026-10-15',
                                prefixIcon: const Icon(
                                  Icons.event_available_outlined,
                                ),
                              ),
                            ),
                            _AssigneePicker(
                              members: widget.members,
                              selectedId: _assignedTo,
                              label: widget.t('clubTasks.assignee'),
                              unassignedLabel: widget.t('clubTasks.unassigned'),
                              searchLabel: widget.t('membership.search'),
                              searchHint: widget.t(
                                'membership.memberSearchHint',
                              ),
                              noResultsLabel: widget.t(
                                'membership.noSearchResults',
                              ),
                              onChanged: (value) =>
                                  setState(() => _assignedTo = value),
                            ),
                            DropdownButtonFormField<int?>(
                              initialValue: _teamId,
                              decoration: InputDecoration(
                                labelText: widget.t('clubTasks.team'),
                                prefixIcon: const Icon(Icons.groups_outlined),
                              ),
                              items: [
                                DropdownMenuItem<int?>(
                                  value: null,
                                  child: Text(widget.t('clubTasks.noTeam')),
                                ),
                                ...widget.teams.map(
                                  (team) => DropdownMenuItem<int?>(
                                    value: team['id'] as int,
                                    child: Text(_text(team, 'name')),
                                  ),
                                ),
                              ],
                              onChanged: (value) =>
                                  setState(() => _teamId = value),
                            ),
                            DropdownButtonFormField<String>(
                              initialValue: _visibility,
                              decoration: InputDecoration(
                                labelText: widget.t('clubTasks.visibility'),
                                prefixIcon: const Icon(
                                  Icons.visibility_outlined,
                                ),
                              ),
                              items: ['personal', 'shared', 'team', 'club']
                                  .map(
                                    (value) => DropdownMenuItem(
                                      value: value,
                                      child: Text(
                                        widget.t('clubTasks.visibility.$value'),
                                      ),
                                    ),
                                  )
                                  .toList(),
                              onChanged: (value) =>
                                  setState(() => _visibility = value ?? 'club'),
                            ),
                          ],
                        ),
                        _EditorTab(
                          children: [
                            Text(
                              widget.t('clubTasks.checklistHelp'),
                              style: Theme.of(context).textTheme.bodySmall,
                            ),
                            TextFormField(
                              controller: _checklist,
                              focusNode: _checklistFocus,
                              minLines: 10,
                              maxLines: 16,
                              textInputAction: TextInputAction.newline,
                              keyboardType: TextInputType.multiline,
                              decoration: InputDecoration(
                                labelText: widget.t('clubTasks.checklist'),
                                hintText:
                                    '${widget.t('clubTasks.checklistHint')}\n${widget.t('clubTasks.checklistHint')}',
                                alignLabelWithHint: true,
                                prefixIcon: const Icon(
                                  Icons.checklist_outlined,
                                ),
                              ),
                            ),
                            Align(
                              alignment: AlignmentDirectional.centerStart,
                              child: OutlinedButton.icon(
                                onPressed: _addChecklistLine,
                                icon: const Icon(Icons.add_task_outlined),
                                label: Text(
                                  widget.t('clubTasks.checklistHint'),
                                ),
                              ),
                            ),
                            const Divider(height: 28),
                            Text(
                              widget.t('clubTasks.attachments'),
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                            Wrap(
                              spacing: 8,
                              runSpacing: 8,
                              children: [
                                for (final file in _pendingAttachments)
                                  InputChip(
                                    avatar: const Icon(
                                      Icons.insert_drive_file_outlined,
                                      size: 18,
                                    ),
                                    label: Text(file.name),
                                    onDeleted: () => setState(
                                      () => _pendingAttachments.remove(file),
                                    ),
                                  ),
                              ],
                            ),
                            Align(
                              alignment: AlignmentDirectional.centerStart,
                              child: OutlinedButton.icon(
                                onPressed: _pickAttachments,
                                icon: const Icon(Icons.attach_file),
                                label: Text(
                                  widget.t('clubTasks.addAttachment'),
                                ),
                              ),
                            ),
                            TextFormField(
                              controller: _attachmentLinks,
                              minLines: 3,
                              maxLines: 6,
                              keyboardType: TextInputType.url,
                              decoration: InputDecoration(
                                labelText: widget.t(
                                  'clubTasks.attachmentLinks',
                                ),
                                hintText: 'https://example.com/datei.pdf',
                                alignLabelWithHint: true,
                                prefixIcon: const Icon(Icons.link_outlined),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
        bottomNavigationBar: SafeArea(
          child: Padding(
            padding: EdgeInsets.fromLTRB(
              16,
              8,
              16,
              MediaQuery.viewInsetsOf(context).bottom + 16,
            ),
            child: Wrap(
              alignment: WrapAlignment.end,
              spacing: 12,
              runSpacing: 8,
              children: [
                TextButton(
                  onPressed: () => Navigator.pop(context),
                  child: Text(widget.t('common.cancel')),
                ),
                FilledButton(
                  onPressed: () {
                    if (!_formKey.currentState!.validate()) return;
                    Navigator.pop(
                      context,
                      _ClubTaskEditorResult(
                        attachments: List<PlatformFile>.unmodifiable(
                          _pendingAttachments,
                        ),
                        payload: <String, dynamic>{
                          'title': _title.text.trim(),
                          'description': _description.text.trim(),
                          'status': _status,
                          'priority': _priority,
                          'visibility': _visibility,
                          'assigned_to': _assignedTo,
                          'team_id': _teamId,
                          'due_at': _dueAt.text.trim().isEmpty
                              ? null
                              : _dueAt.text.trim(),
                          'participant_ids': [
                            if (_assignedTo != null) _assignedTo,
                          ],
                          'checklist': _parseChecklist(_checklist.text),
                          'attachment_links': _parseAttachmentLinks(
                            _attachmentLinks.text,
                          ),
                        },
                      ),
                    );
                  },
                  child: Text(widget.t('common.save')),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  List<AirmiusJson> _parseChecklist(String value) => value
      .split('\n')
      .map((line) => line.trim())
      .where((line) => line.isNotEmpty)
      .map((line) {
        final done = line.startsWith('[x]') || line.startsWith('[X]');
        final title = line
            .replaceFirst(RegExp(r'^\[[ xX]\]\s*'), '')
            .replaceFirst(RegExp(r'^[-*•]\s*'), '')
            .trim();
        return <String, dynamic>{'title': title, 'done': done};
      })
      .where((item) => (item['title'] as String).isNotEmpty)
      .toList();

  void _addChecklistLine() {
    final text = _checklist.text;
    final separator = text.trim().isEmpty || text.endsWith('\n') ? '' : '\n';
    final next = '$text$separator';
    _checklist.value = TextEditingValue(
      text: next,
      selection: TextSelection.collapsed(offset: next.length),
    );
    _checklistFocus.requestFocus();
  }

  Future<void> _pickAttachments() async {
    final picked = await FilePicker.platform.pickFiles(
      allowMultiple: true,
      withData: true,
    );
    if (picked == null || picked.files.isEmpty || !mounted) return;
    setState(() => _pendingAttachments.addAll(picked.files));
  }

  List<AirmiusJson> _parseAttachmentLinks(String value) => value
      .split('\n')
      .map((line) => line.trim())
      .where((line) => line.isNotEmpty)
      .map((line) {
        final separator = line.indexOf('|');
        if (separator > 0 && separator < line.length - 1) {
          return <String, dynamic>{
            'title': line.substring(0, separator).trim(),
            'url': line.substring(separator + 1).trim(),
          };
        }

        return <String, dynamic>{'url': line};
      })
      .where((item) => _text(item, 'url').isNotEmpty)
      .toList();
}

class _AssigneePicker extends StatelessWidget {
  const _AssigneePicker({
    required this.members,
    required this.selectedId,
    required this.label,
    required this.unassignedLabel,
    required this.searchLabel,
    required this.searchHint,
    required this.noResultsLabel,
    required this.onChanged,
  });

  final List<AirmiusJson> members;
  final int? selectedId;
  final String label;
  final String unassignedLabel;
  final String searchLabel;
  final String searchHint;
  final String noResultsLabel;
  final ValueChanged<int?> onChanged;

  @override
  Widget build(BuildContext context) {
    final selected = members.cast<AirmiusJson?>().firstWhere(
      (member) => member?['id'] == selectedId,
      orElse: () => null,
    );
    return InkWell(
      borderRadius: BorderRadius.circular(4),
      onTap: () async {
        final value = await showModalBottomSheet<_AssigneeSelection>(
          context: context,
          isScrollControlled: true,
          builder: (context) => _AssigneeSearchSheet(
            members: members,
            selectedId: selectedId,
            title: label,
            unassignedLabel: unassignedLabel,
            searchLabel: searchLabel,
            searchHint: searchHint,
            noResultsLabel: noResultsLabel,
          ),
        );
        if (value != null && value.memberId != selectedId) {
          onChanged(value.memberId);
        }
      },
      child: InputDecorator(
        decoration: InputDecoration(
          labelText: label,
          prefixIcon: const Icon(Icons.person_search_outlined),
          suffixIcon: const Icon(Icons.expand_more),
        ),
        child: Text(
          selected == null ? unassignedLabel : _text(selected, 'name'),
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
      ),
    );
  }
}

class _AssigneeSelection {
  const _AssigneeSelection(this.memberId);

  final int? memberId;
}

class _AssigneeSearchSheet extends StatefulWidget {
  const _AssigneeSearchSheet({
    required this.members,
    required this.selectedId,
    required this.title,
    required this.unassignedLabel,
    required this.searchLabel,
    required this.searchHint,
    required this.noResultsLabel,
  });

  final List<AirmiusJson> members;
  final int? selectedId;
  final String title;
  final String unassignedLabel;
  final String searchLabel;
  final String searchHint;
  final String noResultsLabel;

  @override
  State<_AssigneeSearchSheet> createState() => _AssigneeSearchSheetState();
}

class _AssigneeSearchSheetState extends State<_AssigneeSearchSheet> {
  String _query = '';

  @override
  Widget build(BuildContext context) {
    final query = _query.trim().toLowerCase();
    final filtered = widget.members.where((member) {
      if (query.isEmpty) return true;
      return '${_text(member, 'name')} ${_text(member, 'email')}'
          .toLowerCase()
          .contains(query);
    }).toList();
    return SafeArea(
      child: Padding(
        padding: EdgeInsets.fromLTRB(
          16,
          16,
          16,
          MediaQuery.viewInsetsOf(context).bottom + 16,
        ),
        child: ConstrainedBox(
          constraints: BoxConstraints(
            maxHeight: MediaQuery.sizeOf(context).height * 0.78,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(widget.title, style: Theme.of(context).textTheme.titleLarge),
              const SizedBox(height: 12),
              TextField(
                autofocus: true,
                decoration: InputDecoration(
                  labelText: widget.searchLabel,
                  hintText: widget.searchHint,
                  prefixIcon: const Icon(Icons.search),
                ),
                onChanged: (value) => setState(() => _query = value),
              ),
              const SizedBox(height: 8),
              Flexible(
                child: ListView(
                  shrinkWrap: true,
                  children: [
                    ListTile(
                      leading: const Icon(Icons.person_off_outlined),
                      title: Text(widget.unassignedLabel),
                      trailing: widget.selectedId == null
                          ? const Icon(Icons.check)
                          : null,
                      onTap: () => Navigator.pop(
                        context,
                        const _AssigneeSelection(null),
                      ),
                    ),
                    if (filtered.isEmpty)
                      Padding(
                        padding: const EdgeInsets.symmetric(vertical: 24),
                        child: Text(
                          widget.noResultsLabel,
                          textAlign: TextAlign.center,
                          style: Theme.of(context).textTheme.bodyMedium,
                        ),
                      ),
                    for (final member in filtered)
                      ListTile(
                        leading: const CircleAvatar(
                          child: Icon(Icons.person_outline),
                        ),
                        title: Text(_text(member, 'name')),
                        subtitle: _text(member, 'email').isEmpty
                            ? null
                            : Text(_text(member, 'email')),
                        trailing: member['id'] == widget.selectedId
                            ? const Icon(Icons.check)
                            : null,
                        onTap: () => Navigator.pop(
                          context,
                          _AssigneeSelection(member['id'] as int?),
                        ),
                      ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle(this.label);

  final String label;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: 16, bottom: 8),
    child: Text(label, style: Theme.of(context).textTheme.labelLarge),
  );
}

class _EditorTab extends StatelessWidget {
  const _EditorTab({required this.children});

  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          for (final child in children) ...[child, const SizedBox(height: 14)],
        ],
      ),
    );
  }
}

String _text(AirmiusJson json, String key, {String fallback = ''}) {
  final value = json[key];
  if (value == null) return fallback;
  return '$value';
}

List<AirmiusJson> _list(Object? value) {
  if (value is! List) return const [];
  return value.whereType<AirmiusJson>().toList();
}

DateTime _dateOnly(DateTime value) =>
    DateTime(value.year, value.month, value.day);

bool _sameDay(DateTime a, DateTime b) =>
    a.year == b.year && a.month == b.month && a.day == b.day;

List<DateTime> _weekDays(DateTime cursor) {
  final start = DateTime(
    cursor.year,
    cursor.month,
    cursor.day - cursor.weekday + 1,
  );
  return List.generate(
    7,
    (index) => DateTime(start.year, start.month, start.day + index),
  );
}

DateTime _shiftCalendarMonths(DateTime cursor, int months) {
  final target = DateTime(cursor.year, cursor.month + months);
  final lastDay = DateUtils.getDaysInMonth(target.year, target.month);
  return DateTime(
    target.year,
    target.month,
    cursor.day > lastDay ? lastDay : cursor.day,
  );
}
