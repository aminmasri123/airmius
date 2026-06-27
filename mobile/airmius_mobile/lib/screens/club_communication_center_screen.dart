import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'club_member_directory_screen.dart';
import 'club_team_admin_screen.dart';
import 'notification_chat_operations_screen.dart';
import 'social_operations_screen.dart';

class ClubCommunicationCenterScreen extends StatefulWidget {
  const ClubCommunicationCenterScreen({super.key});

  @override
  State<ClubCommunicationCenterScreen> createState() => _ClubCommunicationCenterScreenState();
}

class _ClubCommunicationCenterScreenState extends State<ClubCommunicationCenterScreen> {
  String _channel = 'Alle';
  bool _pushEnabled = true;
  bool _emailEnabled = true;
  bool _chatEnabled = true;
  bool _approvalRequired = true;

  static const _channels = ['Alle', 'Push', 'Chat', 'E-Mail', 'Feed', 'Teams'];

  final List<_MessagePlan> _plans = const [
    _MessagePlan(channel: 'Push', title: 'Training faellt aus', target: 'Team U16 Jugend', body: 'Sofortige Push-Info mit Ersatztermin, Trainerhinweis und Lesestatus.', status: 'Entwurf', metric: '18 Empfaenger', icon: Icons.notifications_active_outlined, color: AirmiusColors.blue),
    _MessagePlan(channel: 'Chat', title: 'Rückfrage Mitgliedsantrag', target: 'Vereinsadmin + Antragsteller', body: 'Rückfrage-Thread zu fehlenden Daten, Dokumenten oder Zahlungsart.', status: 'Offen', metric: '2 Antworten', icon: Icons.forum_outlined, color: AirmiusColors.green),
    _MessagePlan(channel: 'E-Mail', title: 'Beitragsinformation', target: 'Aktive Mitglieder', body: 'Vorlage für Beitrag, Intervall, Zahlmethode, Datenschutz und Vereinsregeln.', status: 'Freigabe', metric: '31 Empfaenger', icon: Icons.alternate_email_outlined, color: AirmiusColors.amber),
    _MessagePlan(channel: 'Feed', title: 'Saisonstart Beitrag', target: 'Öffentliches Vereinsprofil', body: 'Sichtbarer Vereinsbeitrag mit Bild, Kommentarfreigabe und Moderation.', status: 'Geplant', metric: 'Mo 09:00', icon: Icons.dynamic_feed_outlined, color: AirmiusColors.blueDeep),
    _MessagePlan(channel: 'Teams', title: 'Teaminterne Info', target: 'Herren Aktiv', body: 'Nur für Teammitglieder sichtbar, mit Trainerrolle und Antwortsteuerung.', status: 'Privat', metric: '24 Mitglieder', icon: Icons.groups_2_outlined, color: AirmiusColors.red),
  ];

  List<_MessagePlan> get _visiblePlans => _plans.where((plan) => _channel == 'Alle' || plan.channel == _channel).toList();

  @override
  Widget build(BuildContext context) {
    final visiblePlans = _visiblePlans;

    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      body: SafeArea(
        child: CustomScrollView(
          slivers: [
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 18),
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 760),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        const PageTitle(title: 'Vereinskommunikation', subtitle: 'Push, Chat, E-Mail, Feed, Teamnachrichten, Vorlagen, Freigaben und Lesestatus.'),
                        const SizedBox(height: 16),
                        _CommunicationHero(onCreate: () => _toast('Nachricht erstellen vorbereitet')),
                        const SizedBox(height: 16),
                        _ChannelPicker(channels: _channels, value: _channel, onChanged: (value) => setState(() => _channel = value)),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Kommunikationsregeln',
                          child: Column(
                            children: [
                              _SwitchRow(title: 'Push aktiv', subtitle: 'Schnelle Infos für Training, Events, Anfragen und Zahlungen.', value: _pushEnabled, onChanged: (value) => setState(() => _pushEnabled = value)),
                              _SwitchRow(title: 'E-Mail aktiv', subtitle: 'Formelle Vereinsinfos, Datenschutz, Regeln und Zahlungsdaten.', value: _emailEnabled, onChanged: (value) => setState(() => _emailEnabled = value)),
                              _SwitchRow(title: 'Chat aktiv', subtitle: 'Rückfragen und Teamkommunikation direkt in der App.', value: _chatEnabled, onChanged: (value) => setState(() => _chatEnabled = value)),
                              _SwitchRow(title: 'Freigabe erforderlich', subtitle: 'Öffentliche Vereinsbeiträge brauchen Adminfreigabe.', value: _approvalRequired, onChanged: (value) => setState(() => _approvalRequired = value)),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        for (final plan in visiblePlans) ...[
                          _MessageCard(plan: plan, onAction: _handleAction),
                          const SizedBox(height: 12),
                        ],
                        if (visiblePlans.isEmpty) const EmptyPanel('Keine Kommunikation für diesen Kanal gefunden.'),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Verknuepfte Bereiche',
                          child: Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              AirmiusButton(label: 'Chat & Push', icon: Icons.forum_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => NotificationChatOperationsScreen(initialTab: 'Chat')))),
                              AirmiusButton(label: 'Mitglieder', icon: Icons.badge_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubMemberDirectoryScreen()))),
                              AirmiusButton(label: 'Teams', icon: Icons.groups_2_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubTeamAdminScreen()))),
                              AirmiusButton(label: 'Social', icon: Icons.dynamic_feed_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SocialOperationsScreen()))),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _handleAction(String action, _MessagePlan plan) {
    if (action == 'chat') {
      Navigator.push(context, MaterialPageRoute(builder: (_) => NotificationChatOperationsScreen(initialTab: 'Chat')));
      return;
    }
    if (action == 'push') {
      Navigator.push(context, MaterialPageRoute(builder: (_) => NotificationChatOperationsScreen(initialTab: 'Push')));
      return;
    }
    _toast('${plan.title}: $action vorbereitet');
  }

  void _toast(String message) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }
}

class _CommunicationHero extends StatelessWidget {
  const _CommunicationHero({required this.onCreate});

  final VoidCallback onCreate;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [Color(0xFF122238), Color(0xFF0A111C)], begin: Alignment.topLeft, end: Alignment.bottomRight),
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: AirmiusColors.borderStrong),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const AirmiusLogo(size: 42),
              const SizedBox(width: 12),
              const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Eyebrow('VEREINSKOMMUNIKATION'), SizedBox(height: 4), Text('Nachrichten gezielt senden', style: TextStyle(color: AirmiusColors.text, fontSize: 22, fontWeight: FontWeight.w900))])),
              AirmiusButton(label: 'Neu', icon: Icons.add_comment_outlined, onPressed: onCreate),
            ],
          ),
          const SizedBox(height: 14),
          const Text('Vereine brauchen unterschiedliche Kanaele: schnelle Pushes, sichere Rückfragen, formelle E-Mails und sichtbare Vereinsbeiträge.', style: TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          const Row(children: [Expanded(child: MetricCard(value: '5', label: 'Kanaele')), SizedBox(width: 10), Expanded(child: MetricCard(value: '31', label: 'Empfaenger')), SizedBox(width: 10), Expanded(child: MetricCard(value: '4', label: 'Vorlagen'))]),
        ],
      ),
    );
  }
}

class _ChannelPicker extends StatelessWidget {
  const _ChannelPicker({required this.channels, required this.value, required this.onChanged});

  final List<String> channels;
  final String value;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: [
          for (final channel in channels) ...[
            ChoiceChip(
              label: Text(channel),
              selected: value == channel,
              onSelected: (_) => onChanged(channel),
              selectedColor: AirmiusColors.blue.withValues(alpha: .24),
              backgroundColor: AirmiusColors.card,
              labelStyle: TextStyle(color: value == channel ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
              side: BorderSide(color: value == channel ? AirmiusColors.blue : AirmiusColors.border),
            ),
            const SizedBox(width: 8),
          ],
        ],
      ),
    );
  }
}

class _SwitchRow extends StatelessWidget {
  const _SwitchRow({required this.title, required this.subtitle, required this.value, required this.onChanged});

  final String title;
  final String subtitle;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: AirmiusColors.input, borderRadius: BorderRadius.circular(16), border: Border.all(color: AirmiusColors.border)),
      child: Row(
        children: [
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text(subtitle, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, height: 1.35, fontWeight: FontWeight.w700))])),
          Switch.adaptive(value: value, onChanged: onChanged, activeColor: AirmiusColors.blue),
        ],
      ),
    );
  }
}

class _MessageCard extends StatelessWidget {
  const _MessageCard({required this.plan, required this.onAction});

  final _MessagePlan plan;
  final void Function(String action, _MessagePlan plan) onAction;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: plan.channel,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(width: 48, height: 48, decoration: BoxDecoration(color: plan.color.withValues(alpha: .18), borderRadius: BorderRadius.circular(16), border: Border.all(color: plan.color.withValues(alpha: .5))), child: Icon(plan.icon, color: plan.color)),
              const SizedBox(width: 12),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(plan.title, style: const TextStyle(color: AirmiusColors.text, fontSize: 18, fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text(plan.target, style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w900))])),
              StatusPill(plan.status, color: plan.color),
            ],
          ),
          const SizedBox(height: 12),
          Text(plan.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700)),
          const SizedBox(height: 12),
          StatusPill(plan.metric, color: AirmiusColors.blue),
          const SizedBox(height: 14),
          Wrap(spacing: 10, runSpacing: 10, children: [
            AirmiusButton(label: 'Bearbeiten', icon: Icons.edit_outlined, secondary: true, onPressed: () => onAction('Bearbeiten', plan)),
            AirmiusButton(label: 'Senden', icon: Icons.send_outlined, secondary: true, onPressed: () => onAction('Senden', plan)),
            AirmiusButton(label: 'Chat', icon: Icons.forum_outlined, secondary: true, onPressed: () => onAction('chat', plan)),
            AirmiusButton(label: 'Push', icon: Icons.notifications_active_outlined, secondary: true, onPressed: () => onAction('push', plan)),
          ]),
        ],
      ),
    );
  }
}

class _MessagePlan {
  const _MessagePlan({required this.channel, required this.title, required this.target, required this.body, required this.status, required this.metric, required this.icon, required this.color});

  final String channel;
  final String title;
  final String target;
  final String body;
  final String status;
  final String metric;
  final IconData icon;
  final Color color;
}
