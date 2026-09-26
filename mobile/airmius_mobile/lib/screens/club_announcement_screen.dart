import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../models/club_summary.dart';
import '../widgets/airmius_widgets.dart';

class ClubAnnouncementScreen extends StatefulWidget {
  const ClubAnnouncementScreen({
    super.key,
    required this.club,
    this.openCreateOnStart = false,
  });

  final ClubSummary club;
  final bool openCreateOnStart;

  @override
  State<ClubAnnouncementScreen> createState() => _ClubAnnouncementScreenState();
}

class _ClubAnnouncementScreenState extends State<ClubAnnouncementScreen> {
  bool _loading = false;
  String? _error;
  List<AirmiusClubAnnouncement> _items = const [];
  int? _readingId;
  bool _initialCreateOpened = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_loading && _items.isEmpty && _error == null) _load();
    if (widget.openCreateOnStart && !_initialCreateOpened) {
      _initialCreateOpened = true;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) _create();
      });
    }
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final items = await AirmiusServicesScope.of(
        context,
      ).repositories.clubs.announcements(widget.club.id);
      if (mounted) {
        setState(() {
          _items = items;
          _loading = false;
        });
      }
    } on AirmiusApiException catch (error) {
      if (mounted) {
        setState(() {
          _error = error.userMessage;
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _error = AirmiusScope.of(context).t('clubs.announcements.loadError');
          _loading = false;
        });
      }
    }
  }

  Future<void> _read(AirmiusClubAnnouncement item) async {
    if (item.readByMe || _readingId != null) return;
    setState(() => _readingId = item.id);
    try {
      await AirmiusServicesScope.of(
        context,
      ).repositories.clubs.acknowledgeAnnouncement(widget.club.id, item.id);
      if (mounted) {
        setState(() {
          _items = _items
              .map(
                (entry) => entry.id == item.id
                    ? AirmiusClubAnnouncement(
                        id: entry.id,
                        clubId: entry.clubId,
                        title: entry.title,
                        body: entry.body,
                        audienceType: entry.audienceType,
                        teamId: entry.teamId,
                        teamName: entry.teamName,
                        authorName: entry.authorName,
                        publishedAt: entry.publishedAt,
                        readByMe: true,
                        readCount: entry.readCount + 1,
                        canManage: entry.canManage,
                        canEdit: entry.canEdit,
                        canPublish: entry.canPublish,
                        canDelete: entry.canDelete,
                        publicationStatus: entry.publicationStatus,
                      )
                    : entry,
              )
              .toList();
        });
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              AirmiusScope.of(context).t('clubs.announcements.readError'),
            ),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _readingId = null);
    }
  }

  Future<void> _create([AirmiusClubAnnouncement? existing]) async {
    if (!widget.club.canEditAnnouncements) return;
    final payload = await showDialog<JsonMap>(
      context: context,
      builder: (_) => _CreateAnnouncementDialog(
        teams: widget.club.teamList,
        existing: existing,
        canPublish: widget.club.canPublishAnnouncements,
      ),
    );
    if (payload == null || !mounted) return;
    try {
      if (existing == null) {
        await AirmiusServicesScope.of(
          context,
        ).repositories.clubs.createAnnouncement(widget.club.id, payload);
      } else {
        await AirmiusServicesScope.of(context).repositories.clubs
            .updateAnnouncement(widget.club.id, existing.id, payload);
      }
      await _load();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              AirmiusScope.of(context).t('clubs.announcements.created'),
            ),
          ),
        );
      }
    } on AirmiusApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.userMessage)));
      }
    }
  }

  Future<void> _publish(AirmiusClubAnnouncement item) async {
    if (!item.canPublish) return;
    try {
      await AirmiusServicesScope.of(
        context,
      ).repositories.clubs.publishAnnouncement(widget.club.id, item.id);
      await _load();
    } on AirmiusApiException catch (error) {
      if (mounted) _message(error.userMessage);
    }
  }

  Future<void> _delete(AirmiusClubAnnouncement item) async {
    if (!item.canDelete) return;
    final confirmed = await _confirmDelete(item.title);
    if (!confirmed || !mounted) return;
    try {
      await AirmiusServicesScope.of(
        context,
      ).repositories.clubs.deleteAnnouncement(widget.club.id, item.id);
      await _load();
    } on AirmiusApiException catch (error) {
      if (mounted) _message(error.userMessage);
    }
  }

  Future<bool> _confirmDelete(String label) async =>
      await showDialog<bool>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: Text(AirmiusScope.of(context).t('common.delete')),
          content: Text(label),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: Text(AirmiusScope.of(context).t('common.cancel')),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              child: Text(AirmiusScope.of(context).t('common.delete')),
            ),
          ],
        ),
      ) ??
      false;

  void _message(String value) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(value)));
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AirmiusPanel(
          gradient: true,
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              IconBadge(
                icon: Icons.campaign_outlined,
                color: airmiusAccentColor(context),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Eyebrow(scope.t('clubs.announcements.eyebrow')),
                    const SizedBox(height: 5),
                    Text(
                      scope.t('clubs.announcements.subtitle'),
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        height: 1.35,
                      ),
                    ),
                  ],
                ),
              ),
              if (widget.club.canEditAnnouncements && _items.isNotEmpty)
                IconButton(
                  tooltip: scope.t('clubs.announcements.create'),
                  onPressed: _loading ? null : _create,
                  icon: const Icon(Icons.add_circle_outline),
                ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        if (_loading)
          AirmiusPanel(
            child: LinearProgressIndicator(color: airmiusAccentColor(context)),
          )
        else if (_error != null)
          AirmiusPanel(
            borderColor: AirmiusColors.red.withValues(alpha: .5),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  _error!,
                  style: const TextStyle(
                    color: AirmiusColors.red,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 10),
                AirmiusButton(
                  label: scope.t('clubs.announcements.retry'),
                  icon: Icons.refresh,
                  secondary: true,
                  onPressed: _load,
                ),
              ],
            ),
          )
        else if (_items.isEmpty)
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  scope.t('clubs.announcements.empty'),
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  scope.t('clubs.announcements.emptyBody'),
                  style: TextStyle(color: airmiusMutedColor(context)),
                ),
                if (widget.club.canEditAnnouncements) ...[
                  const SizedBox(height: 10),
                  AirmiusButton(
                    label: scope.t('clubs.announcements.create'),
                    icon: Icons.add_outlined,
                    onPressed: _create,
                  ),
                ],
              ],
            ),
          )
        else
          for (final item in _items) ...[
            _AnnouncementCard(
              item: item,
              reading: _readingId == item.id,
              onRead: () => _read(item),
              onEdit: item.canEdit && item.publicationStatus != 'published'
                  ? () => _create(item)
                  : null,
              onPublish: item.canPublish && item.publicationStatus != 'published'
                  ? () => _publish(item)
                  : null,
              onDelete: item.canDelete && item.publicationStatus != 'published'
                  ? () => _delete(item)
                  : null,
            ),
            const SizedBox(height: 12),
          ],
      ],
    );
  }
}

class _AnnouncementCard extends StatelessWidget {
  const _AnnouncementCard({
    required this.item,
    required this.reading,
    required this.onRead,
    this.onEdit,
    this.onPublish,
    this.onDelete,
  });
  final AirmiusClubAnnouncement item;
  final bool reading;
  final VoidCallback onRead;
  final VoidCallback? onEdit;
  final VoidCallback? onPublish;
  final VoidCallback? onDelete;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final audience = item.audienceType == 'team'
        ? '${scope.t('clubs.announcements.team')}: ${item.teamName ?? '-'}'
        : scope.t('clubs.announcements.allMembers');
    return AirmiusPanel(
      borderColor: item.readByMe
          ? airmiusBorderColor(context)
          : airmiusAccentColor(context).withValues(alpha: .55),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  item.title,
                  style: const TextStyle(
                    fontSize: 17,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              if (item.publicationStatus != 'published')
                Chip(
                  label: Text(
                    item.publicationStatus == 'draft'
                        ? scope.t('clubs.announcements.draft')
                        : scope.t('clubs.announcements.scheduled'),
                  ),
                )
              else if (!item.readByMe)
                Chip(label: Text(scope.t('clubs.announcements.unread'))),
            ],
          ),
          const SizedBox(height: 6),
          Text(
            item.body,
            style: TextStyle(color: airmiusMutedColor(context), height: 1.4),
          ),
          const SizedBox(height: 12),
          Text(
            '$audience · ${item.authorName ?? 'Airmius'}',
            style: TextStyle(color: airmiusMutedColor(context), fontSize: 12),
          ),
          if (item.canManage) ...[
            const SizedBox(height: 4),
            Text(
              scope
                  .t('clubs.announcements.readCount')
                  .replaceFirst('{count}', '${item.readCount}'),
              style: TextStyle(color: airmiusMutedColor(context), fontSize: 12),
            ),
          ],
          if (onEdit != null) ...[
            const SizedBox(height: 10),
            AirmiusButton(
              label: scope.t('clubs.announcements.edit'),
              icon: Icons.edit_outlined,
              secondary: true,
              onPressed: onEdit,
            ),
          ],
          if (onPublish != null) ...[
            const SizedBox(height: 8),
            AirmiusButton(
              label: scope.t('clubs.announcements.publish'),
              icon: Icons.publish_outlined,
              onPressed: onPublish,
            ),
          ],
          if (onDelete != null) ...[
            const SizedBox(height: 8),
            AirmiusButton(
              label: scope.t('common.delete'),
              icon: Icons.delete_outline,
              secondary: true,
              onPressed: onDelete,
            ),
          ],
          if (item.publicationStatus == 'published' && !item.readByMe) ...[
            const SizedBox(height: 10),
            AirmiusButton(
              label: reading
                  ? scope.t('clubs.announcements.reading')
                  : scope.t('clubs.announcements.markRead'),
              icon: Icons.done_all_outlined,
              secondary: true,
              onPressed: reading ? null : onRead,
            ),
          ],
        ],
      ),
    );
  }
}

class _CreateAnnouncementDialog extends StatefulWidget {
  const _CreateAnnouncementDialog({
    required this.teams,
    required this.canPublish,
    this.existing,
  });
  final List<TeamSummary> teams;
  final bool canPublish;
  final AirmiusClubAnnouncement? existing;
  @override
  State<_CreateAnnouncementDialog> createState() =>
      _CreateAnnouncementDialogState();
}

class _CreateAnnouncementDialogState extends State<_CreateAnnouncementDialog> {
  final _formKey = GlobalKey<FormState>();
  final _title = TextEditingController();
  final _body = TextEditingController();
  String _audience = 'all_members';
  int? _teamId;
  String _mode = 'now';
  DateTime? _publishAt;

  @override
  void initState() {
    super.initState();
    final existing = widget.existing;
    if (!widget.canPublish) _mode = 'draft';
    if (existing != null) {
      _title.text = existing.title;
      _body.text = existing.body;
      _audience = existing.audienceType;
      _teamId = existing.teamId;
      _mode = widget.canPublish && existing.publicationStatus == 'scheduled'
          ? 'schedule'
          : 'draft';
      _publishAt = existing.publicationStatus == 'scheduled'
          ? existing.publishedAt?.toLocal()
          : null;
    }
  }

  Future<void> _chooseSchedule() async {
    final date = await showDatePicker(
      context: context,
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 365)),
      initialDate: _publishAt ?? DateTime.now().add(const Duration(days: 1)),
    );
    if (date == null || !mounted) return;
    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(
        _publishAt ?? DateTime.now().add(const Duration(hours: 1)),
      ),
    );
    if (time == null || !mounted) return;
    setState(
      () => _publishAt = DateTime(
        date.year,
        date.month,
        date.day,
        time.hour,
        time.minute,
      ),
    );
  }

  @override
  void dispose() {
    _title.dispose();
    _body.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return AlertDialog(
      title: Text(scope.t('clubs.announcements.createTitle')),
      content: SingleChildScrollView(
        child: Form(
          key: _formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextFormField(
                controller: _title,
                maxLength: 160,
                validator: (value) => value == null || value.trim().isEmpty
                    ? scope.t('clubs.announcements.required')
                    : null,
                decoration: InputDecoration(
                  labelText: scope.t('clubs.announcements.title'),
                ),
              ),
              TextFormField(
                controller: _body,
                minLines: 4,
                maxLines: 8,
                maxLength: 10000,
                validator: (value) => value == null || value.trim().isEmpty
                    ? scope.t('clubs.announcements.required')
                    : null,
                decoration: InputDecoration(
                  labelText: scope.t('clubs.announcements.body'),
                ),
              ),
              DropdownButtonFormField<String>(
                isExpanded: true,
                initialValue: _audience,
                decoration: InputDecoration(
                  labelText: scope.t('clubs.announcements.audience'),
                ),
                items: [
                  DropdownMenuItem(
                    value: 'all_members',
                    child: Text(
                      scope.t('clubs.announcements.allMembers'),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  if (widget.teams.isNotEmpty)
                    DropdownMenuItem(
                      value: 'team',
                      child: Text(scope.t('clubs.announcements.teamOnly')),
                    ),
                ],
                onChanged: (value) => setState(() {
                  _audience = value ?? 'all_members';
                  if (_audience != 'team') _teamId = null;
                }),
              ),
              if (_audience == 'team')
                DropdownButtonFormField<int>(
                  isExpanded: true,
                  initialValue: _teamId,
                  decoration: InputDecoration(
                    labelText: scope.t('clubs.announcements.team'),
                  ),
                  items: [
                    for (final team in widget.teams)
                      DropdownMenuItem(
                        value: team.id,
                        child: Text(team.name, overflow: TextOverflow.ellipsis),
                      ),
                  ],
                  validator: (value) => value == null
                      ? scope.t('clubs.announcements.required')
                      : null,
                  onChanged: (value) => setState(() => _teamId = value),
                ),
              const SizedBox(height: 8),
              DropdownButtonFormField<String>(
                isExpanded: true,
                initialValue: _mode,
                decoration: InputDecoration(
                  labelText: scope.t('clubs.announcements.publication'),
                ),
                items: [
                  if (widget.canPublish)
                    DropdownMenuItem(
                      value: 'now',
                      child: Text(
                        scope.t('clubs.announcements.publishNow'),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  DropdownMenuItem(
                    value: 'draft',
                    child: Text(
                      scope.t('clubs.announcements.saveDraft'),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  if (widget.canPublish)
                    DropdownMenuItem(
                      value: 'schedule',
                      child: Text(
                        scope.t('clubs.announcements.schedule'),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                ],
                onChanged: (value) => setState(() => _mode = value ?? 'now'),
              ),
              if (_mode == 'schedule')
                TextButton.icon(
                  onPressed: _chooseSchedule,
                  icon: const Icon(Icons.schedule),
                  label: Text(
                    _publishAt == null
                        ? scope.t('clubs.announcements.chooseTime')
                        : '${_publishAt!.day}.${_publishAt!.month}.${_publishAt!.year} · ${TimeOfDay.fromDateTime(_publishAt!).format(context)}',
                  ),
                ),
              Text(
                scope.t('clubs.announcements.deliveryHint'),
                style: Theme.of(context).textTheme.bodySmall,
              ),
              Align(
                alignment: Alignment.centerLeft,
                child: TextButton.icon(
                  onPressed: () {
                    if (!(_formKey.currentState?.validate() ?? false)) return;
                    showDialog<void>(
                      context: context,
                      builder: (_) => AlertDialog(
                        title: Text(_title.text.trim()),
                        content: SingleChildScrollView(
                          child: Text(_body.text.trim()),
                        ),
                        actions: [
                          TextButton(
                            onPressed: () => Navigator.pop(context),
                            child: Text(scope.t('clubs.announcements.back')),
                          ),
                        ],
                      ),
                    );
                  },
                  icon: const Icon(Icons.visibility_outlined),
                  label: Text(scope.t('clubs.announcements.preview')),
                ),
              ),
            ],
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(scope.t('clubs.announcements.cancel')),
        ),
        FilledButton(
          onPressed: () async {
            if (!(_formKey.currentState?.validate() ?? false)) return;
            if (_mode == 'schedule' &&
                (_publishAt == null || !_publishAt!.isAfter(DateTime.now()))) {
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(
                  content: Text(scope.t('clubs.announcements.invalidTime')),
                ),
              );
              return;
            }
            if (_mode == 'now') {
              final confirmed = await showDialog<bool>(
                context: context,
                builder: (dialogContext) => AlertDialog(
                  title: Text(
                    scope.t('clubs.announcements.confirmPublishTitle'),
                  ),
                  content: Text(
                    _audience == 'team'
                        ? scope.t('clubs.announcements.confirmTeamPublish')
                        : scope.t('clubs.announcements.confirmAllPublish'),
                  ),
                  actions: [
                    TextButton(
                      onPressed: () => Navigator.pop(dialogContext, false),
                      child: Text(scope.t('clubs.announcements.cancel')),
                    ),
                    FilledButton(
                      onPressed: () => Navigator.pop(dialogContext, true),
                      child: Text(scope.t('clubs.announcements.publish')),
                    ),
                  ],
                ),
              );
              if (confirmed != true || !context.mounted) return;
            }
            Navigator.pop(context, {
              'title': _title.text.trim(),
              'body': _body.text.trim(),
              'audience_type': _audience,
              if (_teamId != null) 'team_id': _teamId,
              'publication_mode': _mode,
              if (_mode == 'schedule')
                'publish_at': _publishAt!.toUtc().toIso8601String(),
            });
          },
          child: Text(
            _mode == 'draft'
                ? scope.t('clubs.announcements.saveDraft')
                : _mode == 'schedule'
                ? scope.t('clubs.announcements.schedule')
                : scope.t('clubs.announcements.publish'),
          ),
        ),
      ],
    );
  }
}
