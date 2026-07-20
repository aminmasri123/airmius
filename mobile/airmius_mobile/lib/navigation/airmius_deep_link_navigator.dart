import 'package:flutter/material.dart';

import '../core/airmius_deep_links.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../screens/clubs_screen.dart';
import '../screens/conversations_center_screen.dart';
import '../screens/feed_center_screen.dart';
import '../screens/membership_request_status_screen.dart';
import '../screens/notifications_center_screen.dart';
import '../screens/profile_screen.dart';
import '../screens/team_detail_screen.dart';
import '../screens/team_invitation_response_screen.dart';
import '../screens/training_center_screen.dart';
import '../models/club_summary.dart';
import '../widgets/airmius_widgets.dart';

class AirmiusDeepLinkNavigator {
  const AirmiusDeepLinkNavigator._();

  static final AirmiusDeepLinkResolver _resolver = AirmiusDeepLinkResolver();

  static AirmiusDeepLinkTarget resolve(String rawLink) =>
      _resolver.resolve(rawLink);

  static void open(BuildContext context, String rawLink) {
    final target = resolve(rawLink);
    Navigator.of(
      context,
    ).push(MaterialPageRoute(builder: (_) => screenFor(target)));
  }

  static Widget screenFor(AirmiusDeepLinkTarget target) {
    return switch (target.type) {
      AirmiusDeepLinkTargetType.club => AirmiusDeepLinkedClubProfileScreen(
        clubId: target.id ?? 0,
      ),
      AirmiusDeepLinkTargetType.team => TeamDetailScreen(
        title: 'Team',
        mode: target.section ?? 'overview',
        teamId: target.id,
      ),
      AirmiusDeepLinkTargetType.membershipApplication =>
        AirmiusDeepLinkedTargetScreen(
          target: target,
          title: 'Mitgliedschaftsanfrage',
          body:
              'Die App hat eine konkrete Mitgliedschaftsanfrage erkannt und öffnet danach den passenden Statusbereich.',
          icon: Icons.assignment_ind_outlined,
          color: AirmiusColors.green,
          actionLabel: 'Anfragestatus öffnen',
          actionScreen: const MembershipRequestStatusScreen(),
        ),
      AirmiusDeepLinkTargetType.event => AirmiusDeepLinkedTargetScreen(
        target: target,
        title: 'Event oder Training',
        body:
            'Die App hat einen Event-Link erkannt und fuehrt dich danach in den Trainings- und Eventbereich.',
        icon: Icons.event_available_outlined,
        color: AirmiusColors.green,
        actionLabel: 'Eventbereich öffnen',
        actionScreen: const TrainingCenterScreen(),
      ),
      AirmiusDeepLinkTargetType.post => AirmiusDeepLinkedTargetScreen(
        target: target,
        title: 'Feed-Beitrag',
        body:
            'Die App hat einen Feed-Link erkannt und führt dich danach in den Feed.',
        icon: Icons.dynamic_feed_outlined,
        color: AirmiusColors.green,
        actionLabel: 'Feed öffnen',
        actionScreen: const FeedCenterScreen(),
      ),
      AirmiusDeepLinkTargetType.chat => AirmiusDeepLinkedTargetScreen(
        target: target,
        title: 'Chat',
        body:
            'Die App hat eine Konversation erkannt und zeigt zuerst den sicheren Routing-Kontext.',
        icon: Icons.forum_outlined,
        color: AirmiusColors.blue,
        actionLabel: 'Nachrichten öffnen',
        actionScreen: const ConversationsCenterScreen(),
      ),
      AirmiusDeepLinkTargetType.invitation => AirmiusDeepLinkedTargetScreen(
        target: target,
        title: 'Einladung',
        body:
            'Die App hat einen Einladungslink erkannt und öffnet danach den passenden Annahmebereich.',
        icon: Icons.mark_email_read_outlined,
        color: AirmiusColors.amber,
        actionLabel: 'Einladung öffnen',
        actionScreen: const TeamInvitationResponseScreen(),
      ),
      AirmiusDeepLinkTargetType.message => AirmiusDeepLinkedTargetScreen(
        target: target,
        title: 'Nachricht oder Konversation',
        body:
            'Die App hat eine Konversation erkannt und zeigt zuerst den sicheren Routing-Kontext.',
        icon: Icons.forum_outlined,
        color: AirmiusColors.blue,
        actionLabel: 'Nachrichten öffnen',
        actionScreen: const ConversationsCenterScreen(),
      ),
      AirmiusDeepLinkTargetType.notification => AirmiusDeepLinkedTargetScreen(
        target: target,
        title: 'Benachrichtigung',
        body:
            'Die App hat eine konkrete Benachrichtigung erkannt und leitet danach in die Notification-Zentrale.',
        icon: Icons.notifications_active_outlined,
        color: AirmiusColors.blue,
        actionLabel: 'Benachrichtigungen öffnen',
        actionScreen: const NotificationsCenterScreen(),
      ),
      AirmiusDeepLinkTargetType.profile => AirmiusDeepLinkedTargetScreen(
        target: target,
        title: 'Profilbereich',
        body:
            'Die App hat einen Profilbereich erkannt und kann nach Auth-Prüfung direkt in dein Profil wechseln.',
        icon: Icons.person_outline,
        color: AirmiusColors.amber,
        actionLabel: 'Profil öffnen',
        actionScreen: const ProfileScreen(),
      ),
      AirmiusDeepLinkTargetType.unknown => AirmiusDeepLinkFallbackScreen(
        target: target,
      ),
    };
  }

  static String destinationLabel(AirmiusDeepLinkTarget target) {
    return switch (target.type) {
      AirmiusDeepLinkTargetType.club => 'Vereinsprofil / Vereine',
      AirmiusDeepLinkTargetType.team => 'Team',
      AirmiusDeepLinkTargetType.membershipApplication =>
        'Mitgliedschaftsanfrage',
      AirmiusDeepLinkTargetType.event => 'Events & Training',
      AirmiusDeepLinkTargetType.post => 'Feed',
      AirmiusDeepLinkTargetType.chat => 'Nachrichten',
      AirmiusDeepLinkTargetType.invitation => 'Einladung',
      AirmiusDeepLinkTargetType.message => 'Nachrichten',
      AirmiusDeepLinkTargetType.notification => 'Benachrichtigungen',
      AirmiusDeepLinkTargetType.profile => 'Profil',
      AirmiusDeepLinkTargetType.unknown => 'Sicherer Fallback',
    };
  }
}

class AirmiusDeepLinkedClubProfileScreen extends StatefulWidget {
  const AirmiusDeepLinkedClubProfileScreen({super.key, required this.clubId});

  final int clubId;

  @override
  State<AirmiusDeepLinkedClubProfileScreen> createState() =>
      _AirmiusDeepLinkedClubProfileScreenState();
}

class _AirmiusDeepLinkedClubProfileScreenState
    extends State<AirmiusDeepLinkedClubProfileScreen> {
  Future<ClubSummary>? _clubFuture;
  bool _requested = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _clubFuture ??= _loadClub();
  }

  Future<ClubSummary> _loadClub() async {
    final club = await AirmiusServicesScope.of(
      context,
    ).repositories.clubs.club(widget.clubId);
    return ClubSummary.fromAirmiusClub(club);
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<ClubSummary>(
      future: _clubFuture,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return Scaffold(
            backgroundColor: AirmiusColors.bg,
            appBar: AppBar(
              backgroundColor: AirmiusColors.header,
              surfaceTintColor: Colors.transparent,
              title: const Text(
                'Verein wird geladen',
                style: TextStyle(fontWeight: FontWeight.w900),
              ),
            ),
            body: const PageFrame(
              title: 'Verein wird geladen',
              subtitle: 'Der Deep Link öffnet das konkrete Vereinsprofil.',
              child: AirmiusPanel(
                child: Center(
                  child: Padding(
                    padding: EdgeInsets.all(18),
                    child: CircularProgressIndicator(color: AirmiusColors.blue),
                  ),
                ),
              ),
            ),
          );
        }
        if (snapshot.hasError || !snapshot.hasData) {
          return AirmiusDeepLinkFallbackScreen(
            target: AirmiusDeepLinkTarget(
              type: AirmiusDeepLinkTargetType.unknown,
              path: '/clubs/${widget.clubId}',
            ),
          );
        }

        final club = snapshot.data!;
        return ClubProfileScreen(
          club: club,
          requested: _requested,
          onRequest: (_) => setState(() => _requested = true),
          onWithdraw: (_) => setState(() => _requested = false),
        );
      },
    );
  }
}

class AirmiusDeepLinkedTargetScreen extends StatelessWidget {
  const AirmiusDeepLinkedTargetScreen({
    super.key,
    required this.target,
    required this.title,
    required this.body,
    required this.icon,
    required this.color,
    required this.actionLabel,
    required this.actionScreen,
  });

  final AirmiusDeepLinkTarget target;
  final String title;
  final String body;
  final IconData icon;
  final Color color;
  final String actionLabel;
  final Widget actionScreen;

  @override
  Widget build(BuildContext context) {
    final idLabel = target.id == null ? 'ohne ID' : '#${target.id}';
    final sectionLabel = target.section == null
        ? null
        : 'Bereich: ${target.section}';
    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: title,
        subtitle: AirmiusDeepLinkNavigator.destinationLabel(target),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              borderColor: color.withValues(alpha: .55),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      IconBadge(icon: icon, color: color),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Eyebrow('DEEP LINK ERKANNT'),
                            const SizedBox(height: 8),
                            Text(
                              body,
                              style: const TextStyle(
                                color: AirmiusColors.text,
                                height: 1.38,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            const SizedBox(height: 12),
                            Wrap(
                              spacing: 8,
                              runSpacing: 8,
                              children: [
                                StatusPill(idLabel, color: color),
                                StatusPill(
                                  target.requiresAuth ? 'Auth Gate' : 'Public',
                                ),
                                if (sectionLabel != null)
                                  StatusPill(
                                    sectionLabel,
                                    color: AirmiusColors.amber,
                                  ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  AirmiusButton(
                    label: actionLabel,
                    icon: Icons.open_in_new_outlined,
                    onPressed: () => Navigator.of(
                      context,
                    ).push(MaterialPageRoute(builder: (_) => actionScreen)),
                  ),
                ],
              ),
            ),
            if (target.id != null &&
                target.type != AirmiusDeepLinkTargetType.profile) ...[
              const SizedBox(height: 14),
              AirmiusDeepLinkDetailPreview(target: target),
            ],
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('ROUTING AUDIT'),
                  const SizedBox(height: 10),
                  _DeepLinkAuditLine(
                    label: 'Pfad',
                    value: target.path.isEmpty ? '-' : target.path,
                  ),
                  _DeepLinkAuditLine(label: 'Typ', value: target.analyticsName),
                  _DeepLinkAuditLine(
                    label: 'Ziel',
                    value: AirmiusDeepLinkNavigator.destinationLabel(target),
                  ),
                  _DeepLinkAuditLine(
                    label: 'Schutz',
                    value: target.requiresAuth
                        ? 'Login / Session erforderlich'
                        : 'Öffentlich erreichbar',
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class AirmiusDeepLinkDetailPreview extends StatefulWidget {
  const AirmiusDeepLinkDetailPreview({super.key, required this.target});

  final AirmiusDeepLinkTarget target;

  @override
  State<AirmiusDeepLinkDetailPreview> createState() =>
      _AirmiusDeepLinkDetailPreviewState();
}

class _AirmiusDeepLinkDetailPreviewState
    extends State<AirmiusDeepLinkDetailPreview> {
  Future<_DeepLinkPreviewData>? _future;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  Future<_DeepLinkPreviewData> _load() async {
    final id = widget.target.id;
    if (id == null) {
      return const _DeepLinkPreviewData(
        title: 'Keine ID',
        body: 'Dieser Link enthaelt keine konkrete Ziel-ID.',
        status: 'Ohne ID',
      );
    }

    final repositories = AirmiusServicesScope.of(context).repositories;
    return switch (widget.target.type) {
      AirmiusDeepLinkTargetType.membershipApplication =>
        repositories.memberships
            .application(id)
            .then(
              (item) => _DeepLinkPreviewData(
                title: 'Anfrage #${item.id}',
                body: 'Status: ${item.status} - Verein #${item.clubId}',
                status: item.status,
              ),
            ),
      AirmiusDeepLinkTargetType.event =>
        repositories.events
            .event(id)
            .then(
              (item) => _DeepLinkPreviewData(
                title: item.title,
                body: 'Typ: ${item.type} - Start: ${item.startsAt}',
                status: 'Event',
              ),
            ),
      AirmiusDeepLinkTargetType.message =>
        repositories.conversations
            .conversation(id)
            .then(
              (item) => _DeepLinkPreviewData(
                title: item.title,
                body: item.lastMessage,
                status: '${item.unreadCount} ungelesen',
              ),
            ),
      AirmiusDeepLinkTargetType.notification =>
        repositories.notifications
            .notification(id)
            .then(
              (item) => _DeepLinkPreviewData(
                title: item.title,
                body: item.body,
                status: item.unread ? 'Ungelesen' : 'Gelesen',
              ),
            ),
      _ => Future<_DeepLinkPreviewData>.value(
        const _DeepLinkPreviewData(
          title: 'Preview nicht noetig',
          body: 'Dieses Ziel nutzt eine direkte Spezialnavigation.',
          status: 'Direkt',
        ),
      ),
    };
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<_DeepLinkPreviewData>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const AirmiusPanel(
            child: Row(
              children: [
                SizedBox(
                  width: 20,
                  height: 20,
                  child: CircularProgressIndicator(
                    color: AirmiusColors.blue,
                    strokeWidth: 2,
                  ),
                ),
                SizedBox(width: 12),
                Expanded(
                  child: Text(
                    'Detaildaten werden geladen...',
                    style: TextStyle(
                      color: AirmiusColors.muted,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ],
            ),
          );
        }

        if (snapshot.hasError || !snapshot.hasData) {
          return AirmiusPanel(
            borderColor: AirmiusColors.red.withValues(alpha: .55),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Eyebrow('DETAIL PREVIEW'),
                const SizedBox(height: 8),
                const Text(
                  'Detaildaten konnten nicht geladen werden.',
                  style: TextStyle(
                    color: AirmiusColors.text,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  '${snapshot.error}',
                  style: const TextStyle(
                    color: AirmiusColors.muted,
                    height: 1.35,
                  ),
                ),
              ],
            ),
          );
        }

        final data = snapshot.data!;
        return AirmiusPanel(
          borderColor: AirmiusColors.green.withValues(alpha: .45),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  const Expanded(child: Eyebrow('DETAIL PREVIEW')),
                  StatusPill(data.status, color: AirmiusColors.green),
                ],
              ),
              const SizedBox(height: 10),
              Text(
                data.title,
                style: const TextStyle(
                  color: AirmiusColors.text,
                  fontSize: 18,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                data.body,
                style: const TextStyle(
                  color: AirmiusColors.muted,
                  height: 1.38,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

class _DeepLinkPreviewData {
  const _DeepLinkPreviewData({
    required this.title,
    required this.body,
    required this.status,
  });

  final String title;
  final String body;
  final String status;
}

class _DeepLinkAuditLine extends StatelessWidget {
  const _DeepLinkAuditLine({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 80,
            child: Text(
              label,
              style: const TextStyle(
                color: AirmiusColors.blue,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: const TextStyle(
                color: AirmiusColors.muted,
                height: 1.35,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class AirmiusDeepLinkFallbackScreen extends StatelessWidget {
  const AirmiusDeepLinkFallbackScreen({super.key, required this.target});

  final AirmiusDeepLinkTarget target;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text(
          'Deep Link',
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: 'Link konnte nicht geöffnet werden',
        subtitle:
            'Die App hat den Link erkannt, aber kein sicheres Ziel gefunden.',
        child: AirmiusPanel(
          borderColor: AirmiusColors.amber.withValues(alpha: .55),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Eyebrow('SICHERER FALLBACK'),
              const SizedBox(height: 10),
              Text(
                target.path.isEmpty ? 'Unbekannter Link' : target.path,
                style: const TextStyle(
                  color: AirmiusColors.text,
                  fontSize: 20,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 8),
              const Text(
                'Später kann hier erklaert werden, ob der Link abgelaufen ist, eine Rolle fehlt, der Workspace gewechselt werden muss oder das Ziel nicht mehr existiert.',
                style: TextStyle(color: AirmiusColors.muted, height: 1.42),
              ),
              const SizedBox(height: 12),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  StatusPill(target.analyticsName, color: AirmiusColors.amber),
                  StatusPill(
                    target.requiresAuth ? 'Auth erforderlich' : 'Public',
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
