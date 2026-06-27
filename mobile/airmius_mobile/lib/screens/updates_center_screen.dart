import 'package:flutter/material.dart';

import '../core/airmius_l10n.dart';
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

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final requestedClubs = demoClubs.where((club) => widget.requestedClubIds.contains(club.id)).toList();

    return PageFrame(
      title: scope.t('updates'),
      subtitle: 'Chats, Gruppen, Reaktionen und Benachrichtigungen',
      showHeader: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          AirmiusPanel(
            gradient: true,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Eyebrow('Kommunikation'),
                const SizedBox(height: 8),
                const Text('Nachrichten & Updates wie im Web-Center.', style: TextStyle(color: AirmiusColors.text, fontSize: 23, fontWeight: FontWeight.w900, height: 1.12)),
                const SizedBox(height: 8),
                const Text('Direktchats, Teamgruppen, Systemmeldungen und offene Vereinsanfragen in einer mobilen Cockpit-Ansicht.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                const SizedBox(height: 14),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    const StatusPill('Realtime bereit'),
                    const StatusPill('Anhaenge'),
                    if (requestedClubs.isNotEmpty) StatusPill('${requestedClubs.length} Anfrage(n)', color: AirmiusColors.green),
                  ],
                ),
              ],
            ),
          ),
          if (requestedClubs.isNotEmpty) ...[
            const SizedBox(height: 14),
            _RequestsPanel(requestedClubs: requestedClubs, onWithdrawClub: widget.onWithdrawClub),
          ],
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(child: MetricCard(value: 'Chats', label: scope.t('messages.chat'))),
              const SizedBox(width: 10),
              Expanded(child: MetricCard(value: 'Live', label: scope.t('notifications.title'))),
              const SizedBox(width: 10),
              Expanded(child: MetricCard(value: '${requestedClubs.length}', label: scope.t('notifications.requests'))),
            ],
          ),
          const SizedBox(height: 14),
          _UpdatesTabs(active: _active, onSelect: (value) => setState(() => _active = value)),
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
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: [
          _TabChip(id: 'chats', label: 'Chats', icon: Icons.chat_bubble_outline, active: active, onSelect: onSelect),
          const SizedBox(width: 8),
          _TabChip(id: 'notifications', label: 'Benachrichtigungen', icon: Icons.notifications_outlined, active: active, onSelect: onSelect),
        ],
      ),
    );
  }
}

class _TabChip extends StatelessWidget {
  const _TabChip({required this.id, required this.label, required this.icon, required this.active, required this.onSelect});

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
          Icon(icon, size: 16, color: selected ? AirmiusColors.text : AirmiusColors.muted),
          const SizedBox(width: 6),
          Text(label),
        ],
      ),
      onSelected: (_) => onSelect(id),
      selectedColor: AirmiusColors.blue.withValues(alpha: 0.24),
      backgroundColor: AirmiusColors.cardSoft,
      side: BorderSide(color: selected ? AirmiusColors.blue : AirmiusColors.border),
      labelStyle: TextStyle(color: selected ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
    );
  }
}

class _RequestsPanel extends StatelessWidget {
  const _RequestsPanel({required this.requestedClubs, required this.onWithdrawClub});

  final List<ClubSummary> requestedClubs;
  final ValueChanged<ClubSummary>? onWithdrawClub;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return AirmiusPanel(
      borderColor: AirmiusColors.green.withValues(alpha: 0.50),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Eyebrow('Mitgliedschaftsanfragen'),
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
                      Text(club.name, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                      Text('${club.city} - ${scope.t('sent')}', style: const TextStyle(color: AirmiusColors.muted, fontSize: 12)),
                      const SizedBox(height: 10),
                      AirmiusButton(
                        label: scope.t('withdraw'),
                        icon: Icons.undo_outlined,
                        danger: true,
                        onPressed: onWithdrawClub == null
                            ? null
                            : () async {
                                final ok = await confirmDanger(context, 'Anfrage zurückziehen', 'Moechtest du deine Anfrage bei ${club.name} wirklich zurückziehen?');
                                if (ok) onWithdrawClub!(club);
                              },
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
