import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../models/club_summary.dart';
import '../widgets/airmius_widgets.dart';
import 'conversations_center_screen.dart';
import 'notifications_center_screen.dart';

class UpdatesCenterScreen extends StatefulWidget {
  const UpdatesCenterScreen({
    super.key,
    this.requestedClubIds = const {},
    this.onWithdrawClub,
  });

  final Set<int> requestedClubIds;
  final ValueChanged<ClubSummary>? onWithdrawClub;

  @override
  State<UpdatesCenterScreen> createState() => _UpdatesCenterScreenState();
}

class _UpdatesCenterScreenState extends State<UpdatesCenterScreen> {
  String _active = 'chats';
  Future<List<ClubSummary>>? _requestsFuture;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _requestsFuture ??= _loadRequests();
  }

  Future<List<ClubSummary>> _loadRequests() async {
    final services = AirmiusServicesScope.of(context);
    final page = await services.repositories.clubs.searchClubs(mine: true);
    return page.items
        .map(ClubSummary.fromAirmiusClub)
        .where((club) => club.hasPendingMembershipRequest)
        .toList();
  }

  Future<void> _withdrawRequest(ClubSummary club) async {
    final t = AirmiusScope.of(context).t;
    final confirmed = await confirmDanger(
      context,
      t('updates.withdrawTitle'),
      t('updates.withdrawBody').replaceAll('{club}', club.name),
    );
    if (!confirmed || !mounted) return;

    try {
      await AirmiusServicesScope.of(
        context,
      ).repositories.memberships.withdrawClubRequest(club.id);
      widget.onWithdrawClub?.call(club);
      if (!mounted) return;
      setState(() => _requestsFuture = _loadRequests());
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(t('updates.withdrawSuccess'))));
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            t('updates.withdrawFailed').replaceAll(
              '{error}',
              error is AirmiusApiException
                  ? error.userMessage
                  : t('common.errorDetails'),
            ),
          ),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);

    return PageFrame(
      title: scope.t('updates'),
      subtitle: scope.t('updates.subtitle'),
      showHeader: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          AirmiusPanel(
            gradient: true,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Eyebrow(scope.t('updates.communication')),
                const SizedBox(height: 8),
                Text(
                  scope.t('updates.headline'),
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontSize: 23,
                    fontWeight: FontWeight.w900,
                    height: 1.12,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  scope.t('updates.body'),
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    height: 1.35,
                  ),
                ),
                const SizedBox(height: 14),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    StatusPill(scope.t('updates.realtime')),
                    StatusPill(scope.t('updates.attachments')),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: MetricCard(
                  value: scope.t('updates.chats'),
                  label: scope.t('messages.chat'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: MetricCard(
                  value: scope.t('updates.live'),
                  label: scope.t('notifications.title'),
                ),
              ),
            ],
          ),
          FutureBuilder<List<ClubSummary>>(
            future: _requestsFuture,
            builder: (context, snapshot) {
              final requestedClubs = snapshot.data ?? const <ClubSummary>[];
              if (snapshot.connectionState == ConnectionState.waiting) {
                return const Padding(
                  padding: EdgeInsets.only(top: 14),
                  child: LinearProgressIndicator(),
                );
              }
              if (snapshot.hasError || requestedClubs.isEmpty) {
                return const SizedBox.shrink();
              }
              return Padding(
                padding: const EdgeInsets.only(top: 14),
                child: _RequestsPanel(
                  requestedClubs: requestedClubs,
                  onWithdrawClub: _withdrawRequest,
                ),
              );
            },
          ),
          const SizedBox(height: 14),
          _UpdatesTabs(
            active: _active,
            onSelect: (value) => setState(() => _active = value),
          ),
          const SizedBox(height: 14),
          if (_active == 'chats')
            const ConversationsCenterScreen(embedded: true)
          else
            const NotificationsCenterScreen(embedded: true),
        ],
      ),
    );
  }
}

class _UpdatesTabs extends StatelessWidget {
  const _UpdatesTabs({required this.active, required this.onSelect});

  final String active;
  final ValueChanged<String> onSelect;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: [
          _TabChip(
            id: 'chats',
            label: t('updates.chatsTab'),
            icon: Icons.chat_bubble_outline,
            active: active,
            onSelect: onSelect,
          ),
          const SizedBox(width: 8),
          _TabChip(
            id: 'notifications',
            label: t('updates.notificationsTab'),
            icon: Icons.notifications_outlined,
            active: active,
            onSelect: onSelect,
          ),
        ],
      ),
    );
  }
}

class _TabChip extends StatelessWidget {
  const _TabChip({
    required this.id,
    required this.label,
    required this.icon,
    required this.active,
    required this.onSelect,
  });

  final String id;
  final String label;
  final IconData icon;
  final String active;
  final ValueChanged<String> onSelect;

  @override
  Widget build(BuildContext context) {
    final selected = active == id;
    return ChoiceChip(
      selected: selected,
      label: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            icon,
            size: 16,
            color: selected
                ? airmiusTextColor(context)
                : airmiusMutedColor(context),
          ),
          const SizedBox(width: 6),
          Text(label),
        ],
      ),
      onSelected: (_) => onSelect(id),
      selectedColor: airmiusAccentColor(context).withValues(alpha: 0.24),
      backgroundColor: airmiusSurfaceSoftColor(context),
      side: BorderSide(
        color: selected
            ? airmiusAccentColor(context)
            : airmiusBorderColor(context),
      ),
      labelStyle: TextStyle(
        color: selected
            ? airmiusTextColor(context)
            : airmiusMutedColor(context),
        fontWeight: FontWeight.w900,
      ),
    );
  }
}

class _RequestsPanel extends StatelessWidget {
  const _RequestsPanel({
    required this.requestedClubs,
    required this.onWithdrawClub,
  });

  final List<ClubSummary> requestedClubs;
  final Future<void> Function(ClubSummary) onWithdrawClub;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return AirmiusPanel(
      borderColor: AirmiusColors.green.withValues(alpha: 0.50),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(scope.t('updates.membershipRequests')),
          for (final club in requestedClubs) ...[
            const SizedBox(height: 14),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                AirmiusAvatar(club.name, imageUrl: club.logoUrl),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        club.name,
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      Text(
                        '${club.city} - ${scope.t('sent')}',
                        style: TextStyle(
                          color: airmiusMutedColor(context),
                          fontSize: 12,
                        ),
                      ),
                      const SizedBox(height: 10),
                      AirmiusButton(
                        label: scope.t('withdraw'),
                        icon: Icons.undo_outlined,
                        danger: true,
                        onPressed: () => onWithdrawClub(club),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}
