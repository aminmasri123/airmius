import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../models/club_summary.dart';
import '../widgets/airmius_widgets.dart';

class ClubAnnouncementScreen extends StatefulWidget {
  const ClubAnnouncementScreen({super.key, required this.club});

  final ClubSummary club;

  @override
  State<ClubAnnouncementScreen> createState() => _ClubAnnouncementScreenState();
}

class _ClubAnnouncementScreenState extends State<ClubAnnouncementScreen> {
  bool _loading = false;
  String? _error;
  List<AirmiusClubAnnouncement> _items = const [];
  int? _readingId;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_loading && _items.isEmpty && _error == null) _load();
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

  Future<void> _create() async {
    if (!widget.club.canManage) return;
    final payload = await showDialog<JsonMap>(
      context: context,
      builder: (_) => _CreateAnnouncementDialog(teams: widget.club.teamList),
    );
    if (payload == null || !mounted) return;
    try {
      await AirmiusServicesScope.of(
        context,
      ).repositories.clubs.createAnnouncement(widget.club.id, payload);
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
              if (widget.club.canManage)
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
                if (widget.club.canManage) ...[
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
  });
  final AirmiusClubAnnouncement item;
  final bool reading;
  final VoidCallback onRead;

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
              if (!item.readByMe)
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
          if (!item.readByMe) ...[
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
  const _CreateAnnouncementDialog({required this.teams});
  final List<TeamSummary> teams;
  @override
  State<_CreateAnnouncementDialog> createState() =>
      _CreateAnnouncementDialogState();
}

class _CreateAnnouncementDialogState extends State<_CreateAnnouncementDialog> {
  final _title = TextEditingController();
  final _body = TextEditingController();
  String _audience = 'all_members';
  int? _teamId;
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
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: _title,
              maxLength: 160,
              decoration: InputDecoration(
                labelText: scope.t('clubs.announcements.title'),
              ),
            ),
            TextField(
              controller: _body,
              minLines: 4,
              maxLines: 8,
              maxLength: 10000,
              decoration: InputDecoration(
                labelText: scope.t('clubs.announcements.body'),
              ),
            ),
            DropdownButtonFormField<String>(
              initialValue: _audience,
              decoration: InputDecoration(
                labelText: scope.t('clubs.announcements.audience'),
              ),
              items: [
                DropdownMenuItem(
                  value: 'all_members',
                  child: Text(scope.t('clubs.announcements.allMembers')),
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
                initialValue: _teamId,
                decoration: InputDecoration(
                  labelText: scope.t('clubs.announcements.team'),
                ),
                items: [
                  for (final team in widget.teams)
                    DropdownMenuItem(value: team.id, child: Text(team.name)),
                ],
                onChanged: (value) => setState(() => _teamId = value),
              ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(scope.t('clubs.announcements.cancel')),
        ),
        FilledButton(
          onPressed: () {
            if (_title.text.trim().isEmpty ||
                _body.text.trim().isEmpty ||
                (_audience == 'team' && _teamId == null)) {
              return;
            }
            Navigator.pop(context, {
              'title': _title.text.trim(),
              'body': _body.text.trim(),
              'audience_type': _audience,
              if (_teamId != null) 'team_id': _teamId,
            });
          },
          child: Text(scope.t('clubs.announcements.publish')),
        ),
      ],
    );
  }
}
