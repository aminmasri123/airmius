import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_deep_links.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../screens/clubs_screen.dart';
import '../screens/chat_detail_screen.dart';
import '../screens/feed_post_detail_screen.dart';
import '../screens/friend_invitation_response_screen.dart';
import '../screens/club_external_invitation_response_screen.dart';
import '../screens/club_request_inbox_screen.dart';
import '../screens/email_verification_screen.dart';
import '../screens/membership_request_status_screen.dart';
import '../screens/notification_detail_screen.dart';
import '../screens/notifications_center_screen.dart';
import '../screens/profile_screen.dart';
import '../screens/user_profile_detail_screen.dart';
import '../screens/password_recovery_screen.dart';
import '../screens/team_detail_screen.dart';
import '../screens/team_invitation_response_screen.dart';
import '../screens/training_event_detail_screen.dart';
import '../models/club_summary.dart';
import '../widgets/airmius_widgets.dart';

class AirmiusDeepLinkNavigator {
  const AirmiusDeepLinkNavigator._();

  static final AirmiusDeepLinkResolver _resolver = AirmiusDeepLinkResolver();

  static AirmiusDeepLinkTarget resolve(String rawLink) =>
      _resolver.resolve(rawLink);

  static void open(BuildContext context, String rawLink) {
    final target = resolve(rawLink);
    final authState = AirmiusServicesScope.of(context).authState;
    if (target.requiresAuth && !authState.isAuthenticated) {
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => AirmiusDeepLinkAuthGateScreen(target: target),
        ),
      );
      return;
    }
    Navigator.of(
      context,
    ).push(MaterialPageRoute(builder: (_) => screenFor(target)));
  }

  static Widget screenFor(AirmiusDeepLinkTarget target) {
    return switch (target.type) {
      AirmiusDeepLinkTargetType.club =>
        target.section == 'membership-requests'
            ? ClubRequestInboxScreen(initialClubId: target.id)
            : AirmiusDeepLinkedClubProfileScreen(clubId: target.id ?? 0),
      AirmiusDeepLinkTargetType.team => TeamDetailScreen(
        title: 'Team',
        mode: target.section ?? 'overview',
        teamId: target.id,
      ),
      AirmiusDeepLinkTargetType.membershipApplication =>
        MembershipRequestStatusScreen(applicationId: target.id),
      AirmiusDeepLinkTargetType.event => AirmiusDeepLinkedEventScreen(
        target: target,
      ),
      AirmiusDeepLinkTargetType.post => AirmiusDeepLinkedPostScreen(
        target: target,
      ),
      AirmiusDeepLinkTargetType.chat => AirmiusDeepLinkedChatScreen(
        target: target,
      ),
      AirmiusDeepLinkTargetType.message => AirmiusDeepLinkedMessageScreen(
        target: target,
      ),
      AirmiusDeepLinkTargetType.notifications =>
        const NotificationsCenterScreen(),
      AirmiusDeepLinkTargetType.invitation =>
        target.path.startsWith('/team-invitations/')
            ? TeamInvitationResponseScreen(token: target.token)
            : (target.path.startsWith('/friends/invitations/') ||
                  target.path.startsWith('/invitations/'))
            ? FriendInvitationResponseScreen(token: target.token)
            : target.path.startsWith('/club-member-invitations/')
            ? ClubExternalInvitationResponseScreen(token: target.token)
            : AirmiusDeepLinkedTargetScreen(
                target: target,
                title: 'Einladung',
                body:
                    'Die App hat einen Einladungslink erkannt und öffnet danach den passenden Annahmebereich.',
                icon: Icons.mark_email_read_outlined,
                color: null,
                actionLabel: 'Einladung öffnen',
                actionScreen: const TeamInvitationResponseScreen(),
              ),
      AirmiusDeepLinkTargetType.notification =>
        AirmiusDeepLinkedNotificationScreen(target: target),
      AirmiusDeepLinkTargetType.profile =>
        target.id != null
            ? AirmiusDeepLinkedProfileScreen(target: target)
            : AirmiusDeepLinkedTargetScreen(
                target: target,
                title: 'Profilbereich',
                body:
                    'Die App hat einen Profilbereich erkannt und kann nach Auth-Prüfung direkt in dein Profil wechseln.',
                icon: Icons.person_outline,
                color: null,
                actionLabel: 'Profil öffnen',
                actionScreen: const ProfileScreen(),
              ),
      AirmiusDeepLinkTargetType.passwordReset => PasswordRecoveryScreen(
        initialEmail: target.query['email'],
        initialToken: target.token,
      ),
      AirmiusDeepLinkTargetType.emailVerification => EmailVerificationScreen(
        userId: target.id,
        hash: target.token,
        query: target.query,
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
      AirmiusDeepLinkTargetType.notifications => 'Benachrichtigungen',
      AirmiusDeepLinkTargetType.notification => 'Benachrichtigungen',
      AirmiusDeepLinkTargetType.profile => 'Profil',
      AirmiusDeepLinkTargetType.passwordReset => 'Passwort zurücksetzen',
      AirmiusDeepLinkTargetType.emailVerification => 'E-Mail bestätigen',
      AirmiusDeepLinkTargetType.unknown => 'Sicherer Fallback',
    };
  }
}

/// Keeps protected deep links from constructing authenticated screens while a
/// user is still a guest or the session has expired.
class AirmiusDeepLinkAuthGateScreen extends StatelessWidget {
  const AirmiusDeepLinkAuthGateScreen({super.key, required this.target});

  final AirmiusDeepLinkTarget target;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final destination = scope.t('deepLink.destination.${target.analyticsName}');
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: Text(
          scope.t('deepLink.authGate'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: scope.t('deepLink.authGate'),
        subtitle: destination,
        child: AirmiusPanel(
          borderColor: airmiusAccentColor(context).withValues(alpha: .55),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              IconBadge(
                icon: Icons.lock_outline,
                color: airmiusAccentColor(context),
              ),
              const SizedBox(height: 14),
              Text(
                scope.t('deepLink.authRequired'),
                style: TextStyle(
                  color: airmiusTextColor(context),
                  fontSize: 18,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                destination,
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  height: 1.4,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 16),
              AirmiusButton(
                label: scope.t('login.button'),
                icon: Icons.login_outlined,
                onPressed: () => Navigator.of(context).pop(),
              ),
              const SizedBox(height: 8),
              TextButton(
                onPressed: () => Navigator.of(context).pop(),
                child: Text(scope.t('common.back')),
              ),
            ],
          ),
        ),
      ),
    );
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
            backgroundColor: Theme.of(context).scaffoldBackgroundColor,
            appBar: AppBar(
              backgroundColor:
                  (Theme.of(context).appBarTheme.backgroundColor ??
                  airmiusSurfaceColor(context)),
              surfaceTintColor: Colors.transparent,
              title: Text(
                AirmiusScope.of(context).t('deepLink.clubLoadingTitle'),
                style: const TextStyle(fontWeight: FontWeight.w900),
              ),
            ),
            body: PageFrame(
              title: AirmiusScope.of(context).t('deepLink.clubLoadingTitle'),
              subtitle: AirmiusScope.of(
                context,
              ).t('deepLink.clubLoadingSubtitle'),
              child: AirmiusPanel(
                child: Center(
                  child: Padding(
                    padding: EdgeInsets.all(18),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        CircularProgressIndicator(
                          color: airmiusAccentColor(context),
                        ),
                        SizedBox(height: 12),
                        Text(
                          AirmiusScope.of(
                            context,
                          ).t('deepLink.clubLoadingBody'),
                          style: TextStyle(
                            color: airmiusMutedColor(context),
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
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

/// Loads the protected event before opening the detail screen. The event API
/// remains the authority for visibility, membership and participation rights;
/// a deep link never renders event data from the URL itself.
class AirmiusDeepLinkedEventScreen extends StatefulWidget {
  const AirmiusDeepLinkedEventScreen({super.key, required this.target});

  final AirmiusDeepLinkTarget target;

  @override
  State<AirmiusDeepLinkedEventScreen> createState() =>
      _AirmiusDeepLinkedEventScreenState();
}

/// Loads a protected post before constructing the full feed detail screen.
/// The post API applies the same visibility and moderation policy as the feed.
class AirmiusDeepLinkedPostScreen extends StatefulWidget {
  const AirmiusDeepLinkedPostScreen({super.key, required this.target});

  final AirmiusDeepLinkTarget target;

  @override
  State<AirmiusDeepLinkedPostScreen> createState() =>
      _AirmiusDeepLinkedPostScreenState();
}

class _AirmiusDeepLinkedPostScreenState
    extends State<AirmiusDeepLinkedPostScreen> {
  Future<AirmiusPost>? _future;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  Future<AirmiusPost> _load() async {
    final id = widget.target.id;
    if (id == null) {
      throw const FormatException('Missing post id');
    }
    return AirmiusServicesScope.of(context).repositories.feed.post(id);
  }

  void _retry() => setState(() => _future = _load());

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return FutureBuilder<AirmiusPost>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return _postStateScaffold(
            context,
            title: scope.t('deepLink.post.title'),
            body: scope.t('deepLink.detailLoading'),
            child: const CircularProgressIndicator(),
          );
        }

        if (snapshot.hasError || !snapshot.hasData) {
          return _postStateScaffold(
            context,
            title: scope.t('deepLink.detailFailed'),
            body: snapshot.error is AirmiusApiException
                ? (snapshot.error! as AirmiusApiException).userMessage
                : scope.t('deepLink.retryLater'),
            child: AirmiusButton(
              label: scope.t('common.retry'),
              icon: Icons.refresh_outlined,
              onPressed: _retry,
            ),
          );
        }

        return FeedPostDetailScreen(post: snapshot.data!);
      },
    );
  }

  Widget _postStateScaffold(
    BuildContext context, {
    required String title,
    required String body,
    required Widget child,
  }) {
    final scope = AirmiusScope.of(context);
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: title,
        subtitle: scope.t('deepLink.destination.post'),
        child: AirmiusPanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                body,
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  height: 1.4,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 14),
              Align(alignment: AlignmentDirectional.centerStart, child: child),
            ],
          ),
        ),
      ),
    );
  }
}

/// Resolves the conversation title and access through the protected API before
/// opening the realtime message screen.
class AirmiusDeepLinkedChatScreen extends StatefulWidget {
  const AirmiusDeepLinkedChatScreen({super.key, required this.target});

  final AirmiusDeepLinkTarget target;

  @override
  State<AirmiusDeepLinkedChatScreen> createState() =>
      _AirmiusDeepLinkedChatScreenState();
}

class _AirmiusDeepLinkedChatScreenState
    extends State<AirmiusDeepLinkedChatScreen> {
  Future<AirmiusConversation>? _future;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  Future<AirmiusConversation> _load() async {
    final id = widget.target.id;
    if (id == null) throw const FormatException('Missing conversation id');
    return AirmiusServicesScope.of(
      context,
    ).repositories.conversations.conversation(id);
  }

  void _retry() => setState(() => _future = _load());

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return FutureBuilder<AirmiusConversation>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return _chatStateScaffold(
            context,
            title: scope.t('deepLink.chat.title'),
            body: scope.t('deepLink.detailLoading'),
            child: const CircularProgressIndicator(),
          );
        }
        if (snapshot.hasError || !snapshot.hasData) {
          return _chatStateScaffold(
            context,
            title: scope.t('deepLink.detailFailed'),
            body: snapshot.error is AirmiusApiException
                ? (snapshot.error! as AirmiusApiException).userMessage
                : scope.t('deepLink.retryLater'),
            child: AirmiusButton(
              label: scope.t('common.retry'),
              icon: Icons.refresh_outlined,
              onPressed: _retry,
            ),
          );
        }
        final conversation = snapshot.data!;
        return ChatDetailScreen(
          conversationId: conversation.id,
          title: conversation.title,
          kind: conversation.kind,
        );
      },
    );
  }

  Widget _chatStateScaffold(
    BuildContext context, {
    required String title,
    required String body,
    required Widget child,
  }) {
    final scope = AirmiusScope.of(context);
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: title,
        subtitle: scope.t('deepLink.destination.chat'),
        child: AirmiusPanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                body,
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  height: 1.4,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 14),
              Align(alignment: AlignmentDirectional.centerStart, child: child),
            ],
          ),
        ),
      ),
    );
  }
}

/// Resolves an individual message before showing its protected context. This
/// keeps `/messages/{id}` useful without leaking a message from a hidden or
/// inaccessible conversation into a generic chat screen.
class AirmiusDeepLinkedMessageScreen extends StatefulWidget {
  const AirmiusDeepLinkedMessageScreen({super.key, required this.target});

  final AirmiusDeepLinkTarget target;

  @override
  State<AirmiusDeepLinkedMessageScreen> createState() =>
      _AirmiusDeepLinkedMessageScreenState();
}

class _AirmiusDeepLinkedMessageScreenState
    extends State<AirmiusDeepLinkedMessageScreen> {
  Future<_DeepLinkedMessageData>? _future;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  Future<_DeepLinkedMessageData> _load() async {
    final id = widget.target.id;
    if (id == null) throw const FormatException('Missing message id');
    final repository = AirmiusServicesScope.of(
      context,
    ).repositories.conversations;
    final message = await repository.message(id);
    final conversation = await repository.conversation(message.conversationId);
    return _DeepLinkedMessageData(message: message, conversation: conversation);
  }

  void _retry() => setState(() => _future = _load());

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return FutureBuilder<_DeepLinkedMessageData>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return _stateScaffold(
            context,
            title: scope.t('deepLink.message.title'),
            body: scope.t('deepLink.detailLoading'),
            child: const CircularProgressIndicator(),
          );
        }
        if (snapshot.hasError || !snapshot.hasData) {
          return _stateScaffold(
            context,
            title: scope.t('deepLink.detailFailed'),
            body: snapshot.error is AirmiusApiException
                ? (snapshot.error! as AirmiusApiException).userMessage
                : scope.t('deepLink.retryLater'),
            child: AirmiusButton(
              label: scope.t('common.retry'),
              icon: Icons.refresh_outlined,
              onPressed: _retry,
            ),
          );
        }

        final data = snapshot.data!;
        final message = data.message;
        final body = message.message.trim();
        return Scaffold(
          backgroundColor: Theme.of(context).scaffoldBackgroundColor,
          appBar: AppBar(
            backgroundColor:
                Theme.of(context).appBarTheme.backgroundColor ??
                airmiusSurfaceColor(context),
            surfaceTintColor: Colors.transparent,
            title: Text(
              scope.t('deepLink.message.title'),
              style: const TextStyle(fontWeight: FontWeight.w900),
            ),
          ),
          body: PageFrame(
            title: scope.t('deepLink.message.title'),
            subtitle: data.conversation.title,
            child: AirmiusPanel(
              gradient: true,
              borderColor: airmiusAccentColor(context).withValues(alpha: .55),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      IconBadge(
                        icon: Icons.mark_chat_read_outlined,
                        color: airmiusAccentColor(context),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          data.conversation.title,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            color: airmiusTextColor(context),
                            fontSize: 18,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      StatusPill(message.senderName),
                      StatusPill(
                        '#${message.id}',
                        color: airmiusAccentColor(context),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  AirmiusPanel(
                    child: Text(
                      body.isEmpty ? scope.t('deepLink.message.body') : body,
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        height: 1.45,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  AirmiusButton(
                    label: scope.t('deepLink.message.action'),
                    icon: Icons.forum_outlined,
                    onPressed: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => ChatDetailScreen(
                          conversationId: data.conversation.id,
                          title: data.conversation.title,
                          kind: data.conversation.kind,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }

  Widget _stateScaffold(
    BuildContext context, {
    required String title,
    required String body,
    required Widget child,
  }) {
    final scope = AirmiusScope.of(context);
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: title,
        subtitle: scope.t('deepLink.destination.message'),
        child: AirmiusPanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                body,
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  height: 1.4,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 14),
              Align(alignment: AlignmentDirectional.centerStart, child: child),
            ],
          ),
        ),
      ),
    );
  }
}

class _DeepLinkedMessageData {
  const _DeepLinkedMessageData({
    required this.message,
    required this.conversation,
  });

  final AirmiusMessage message;
  final AirmiusConversation conversation;
}

/// Loads a privacy-filtered sport profile before handing it to the existing
/// profile detail view. The API supplies the display name, so a deep link never
/// needs to trust a name from the URL itself.
class AirmiusDeepLinkedProfileScreen extends StatefulWidget {
  const AirmiusDeepLinkedProfileScreen({super.key, required this.target});

  final AirmiusDeepLinkTarget target;

  @override
  State<AirmiusDeepLinkedProfileScreen> createState() =>
      _AirmiusDeepLinkedProfileScreenState();
}

class _AirmiusDeepLinkedProfileScreenState
    extends State<AirmiusDeepLinkedProfileScreen> {
  Future<Map<String, dynamic>>? _future;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final id = widget.target.id;
    if (id == null) throw const FormatException('Missing profile id');
    final services = AirmiusServicesScope.of(context);
    final response = await services
        .clientForSession(services.authState.session)
        .sportCvForUser(id);
    final data = response['data'];
    if (data is Map<String, dynamic>) return data;
    return response;
  }

  void _retry() => setState(() => _future = _load());

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return FutureBuilder<Map<String, dynamic>>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return _stateScaffold(
            context,
            title: scope.t('deepLink.profile.title'),
            body: scope.t('deepLink.detailLoading'),
            child: const CircularProgressIndicator(),
          );
        }
        if (snapshot.hasError || !snapshot.hasData) {
          return _stateScaffold(
            context,
            title: scope.t('deepLink.detailFailed'),
            body: snapshot.error is AirmiusApiException
                ? (snapshot.error! as AirmiusApiException).userMessage
                : scope.t('deepLink.retryLater'),
            child: AirmiusButton(
              label: scope.t('common.retry'),
              icon: Icons.refresh_outlined,
              onPressed: _retry,
            ),
          );
        }

        final data = snapshot.data!;
        final profile = data['profile'] is Map<String, dynamic>
            ? data['profile'] as Map<String, dynamic>
            : const <String, dynamic>{};
        final name = _profileString(
          profile['name'],
          fallback: scope.t('profile.title'),
        );
        final body = _profileString(
          profile['bio'],
          fallback: _profileString(
            data['headline'],
            fallback: scope.t('deepLink.profile.body'),
          ),
        );
        return UserProfileDetailScreen(
          userId: widget.target.id,
          name: name,
          body: body,
          status: scope.t('deepLink.profile.title'),
          context: scope.t('deepLink.destination.profile'),
          initialSportCv: data,
        );
      },
    );
  }

  Widget _stateScaffold(
    BuildContext context, {
    required String title,
    required String body,
    required Widget child,
  }) {
    final scope = AirmiusScope.of(context);
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: title,
        subtitle: scope.t('deepLink.destination.profile'),
        child: AirmiusPanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                body,
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  height: 1.4,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 14),
              Align(alignment: AlignmentDirectional.centerStart, child: child),
            ],
          ),
        ),
      ),
    );
  }
}

String _profileString(Object? value, {required String fallback}) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty || text == 'null' ? fallback : text;
}

/// Resolves a notification from the API before rendering its detail and
/// action context, so a notification URL cannot inject local content.
class AirmiusDeepLinkedNotificationScreen extends StatefulWidget {
  const AirmiusDeepLinkedNotificationScreen({super.key, required this.target});

  final AirmiusDeepLinkTarget target;

  @override
  State<AirmiusDeepLinkedNotificationScreen> createState() =>
      _AirmiusDeepLinkedNotificationScreenState();
}

class _AirmiusDeepLinkedNotificationScreenState
    extends State<AirmiusDeepLinkedNotificationScreen> {
  Future<AirmiusNotification>? _future;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  Future<AirmiusNotification> _load() async {
    final id = widget.target.id;
    if (id == null) throw const FormatException('Missing notification id');
    return AirmiusServicesScope.of(
      context,
    ).repositories.notifications.notification(id);
  }

  void _retry() => setState(() => _future = _load());

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return FutureBuilder<AirmiusNotification>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return _notificationStateScaffold(
            context,
            title: scope.t('settings.notifications'),
            body: scope.t('deepLink.detailLoading'),
            child: const CircularProgressIndicator(),
          );
        }
        if (snapshot.hasError || !snapshot.hasData) {
          return _notificationStateScaffold(
            context,
            title: scope.t('deepLink.detailFailed'),
            body: snapshot.error is AirmiusApiException
                ? (snapshot.error! as AirmiusApiException).userMessage
                : scope.t('deepLink.retryLater'),
            child: AirmiusButton(
              label: scope.t('common.retry'),
              icon: Icons.refresh_outlined,
              onPressed: _retry,
            ),
          );
        }
        final notification = snapshot.data!;
        return NotificationDetailScreen(
          notification: notification,
          typeLabel: scope.t('settings.notifications'),
          icon: Icons.notifications_active_outlined,
          onChanged: () {},
        );
      },
    );
  }

  Widget _notificationStateScaffold(
    BuildContext context, {
    required String title,
    required String body,
    required Widget child,
  }) {
    final scope = AirmiusScope.of(context);
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: title,
        subtitle: scope.t('deepLink.destination.notification'),
        child: AirmiusPanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                body,
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  height: 1.4,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 14),
              Align(alignment: AlignmentDirectional.centerStart, child: child),
            ],
          ),
        ),
      ),
    );
  }
}

class _AirmiusDeepLinkedEventScreenState
    extends State<AirmiusDeepLinkedEventScreen> {
  Future<AirmiusEvent>? _future;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  Future<AirmiusEvent> _load() async {
    final id = widget.target.id;
    if (id == null) {
      throw const FormatException('Missing event id');
    }
    return AirmiusServicesScope.of(context).repositories.events.event(id);
  }

  void _retry() => setState(() => _future = _load());

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return FutureBuilder<AirmiusEvent>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return _eventStateScaffold(
            context,
            title: scope.t('deepLink.event.title'),
            body: scope.t('deepLink.detailLoading'),
            child: const CircularProgressIndicator(),
          );
        }

        if (snapshot.hasError || !snapshot.hasData) {
          return _eventStateScaffold(
            context,
            title: scope.t('deepLink.detailFailed'),
            body: snapshot.error is AirmiusApiException
                ? (snapshot.error! as AirmiusApiException).userMessage
                : scope.t('deepLink.retryLater'),
            child: AirmiusButton(
              label: scope.t('common.retry'),
              icon: Icons.refresh_outlined,
              onPressed: _retry,
            ),
          );
        }

        return TrainingEventDetailScreen(
          event: snapshot.data!,
          fallbackBody: scope.t('deepLink.event.body'),
        );
      },
    );
  }

  Widget _eventStateScaffold(
    BuildContext context, {
    required String title,
    required String body,
    required Widget child,
  }) {
    final scope = AirmiusScope.of(context);
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: title,
        subtitle: scope.t('deepLink.destination.event'),
        child: AirmiusPanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                body,
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  height: 1.4,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 14),
              Align(alignment: AlignmentDirectional.centerStart, child: child),
            ],
          ),
        ),
      ),
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
  final Color? color;
  final String actionLabel;
  final Widget actionScreen;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final accent = color ?? airmiusAccentColor(context);
    final typeKey = target.analyticsName;
    final localizedTitle = scope.t('deepLink.$typeKey.title');
    final localizedBody = scope.t('deepLink.$typeKey.body');
    final localizedAction = scope.t('deepLink.$typeKey.action');
    final localizedDestination = scope.t('deepLink.destination.$typeKey');
    final idLabel = target.id == null
        ? scope.t('deepLink.noId')
        : '#${target.id}';
    final sectionLabel = target.section == null
        ? null
        : '${scope.t('deepLink.type')}: ${target.section}';
    final resolvedTitle = localizedTitle == 'deepLink.$typeKey.title'
        ? title
        : localizedTitle;
    final resolvedBody = localizedBody == 'deepLink.$typeKey.body'
        ? body
        : localizedBody;
    final resolvedAction = localizedAction == 'deepLink.$typeKey.action'
        ? actionLabel
        : localizedAction;
    final resolvedDestination =
        localizedDestination == 'deepLink.destination.$typeKey'
        ? AirmiusDeepLinkNavigator.destinationLabel(target)
        : localizedDestination;
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor:
            (Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context)),
        surfaceTintColor: Colors.transparent,
        title: Text(
          resolvedTitle,
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: resolvedTitle,
        subtitle: resolvedDestination,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              borderColor: accent.withValues(alpha: .55),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      IconBadge(icon: icon, color: accent),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Eyebrow(scope.t('deepLink.recognized')),
                            const SizedBox(height: 8),
                            Text(
                              resolvedBody,
                              style: TextStyle(
                                color: airmiusTextColor(context),
                                height: 1.38,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            const SizedBox(height: 12),
                            Wrap(
                              spacing: 8,
                              runSpacing: 8,
                              children: [
                                StatusPill(idLabel, color: accent),
                                StatusPill(
                                  target.requiresAuth
                                      ? scope.t('deepLink.authGate')
                                      : scope.t('deepLink.public'),
                                ),
                                if (sectionLabel != null)
                                  StatusPill(sectionLabel, color: accent),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  AirmiusButton(
                    label: resolvedAction,
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
                  Eyebrow(scope.t('deepLink.routingAudit')),
                  const SizedBox(height: 10),
                  _DeepLinkAuditLine(
                    label: scope.t('deepLink.path'),
                    value: target.path.isEmpty ? '-' : target.path,
                  ),
                  _DeepLinkAuditLine(
                    label: scope.t('deepLink.type'),
                    value: target.analyticsName,
                  ),
                  _DeepLinkAuditLine(
                    label: scope.t('deepLink.target'),
                    value: resolvedDestination,
                  ),
                  _DeepLinkAuditLine(
                    label: scope.t('deepLink.protection'),
                    value: target.requiresAuth
                        ? scope.t('deepLink.authRequired')
                        : scope.t('deepLink.publicReachable'),
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
    final scope = AirmiusScope.of(context);
    if (id == null) {
      return _DeepLinkPreviewData(
        title: scope.t('deepLink.noId'),
        body: scope.t('deepLink.fallbackBody'),
        status: scope.t('deepLink.noId'),
      );
    }

    final repositories = AirmiusServicesScope.of(context).repositories;
    return switch (widget.target.type) {
      AirmiusDeepLinkTargetType.membershipApplication =>
        repositories.memberships
            .application(id)
            .then(
              (item) => _DeepLinkPreviewData(
                title: '${scope.t('deepLink.previewRequest')} #${item.id}',
                body:
                    '${scope.t('deepLink.previewStatus')}: ${item.status} - '
                    '${scope.t('deepLink.previewClub')} #${item.clubId}',
                status: item.status,
              ),
            ),
      AirmiusDeepLinkTargetType.event =>
        repositories.events
            .event(id)
            .then(
              (item) => _DeepLinkPreviewData(
                title: item.title,
                body:
                    '${scope.t('deepLink.previewType')}: ${item.type} - '
                    '${scope.t('deepLink.previewStart')}: ${item.startsAt}',
                status: scope.t('deepLink.previewEvent'),
              ),
            ),
      AirmiusDeepLinkTargetType.message =>
        repositories.conversations
            .conversation(id)
            .then(
              (item) => _DeepLinkPreviewData(
                title: item.title,
                body: item.lastMessage,
                status:
                    '${item.unreadCount} ${scope.t('deepLink.previewUnread')}',
              ),
            ),
      AirmiusDeepLinkTargetType.notification =>
        repositories.notifications
            .notification(id)
            .then(
              (item) => _DeepLinkPreviewData(
                title: item.title,
                body: item.body,
                status: item.unread
                    ? scope.t('deepLink.previewUnread')
                    : scope.t('deepLink.previewRead'),
              ),
            ),
      _ => Future<_DeepLinkPreviewData>.value(
        _DeepLinkPreviewData(
          title: scope.t('deepLink.previewNotNeeded'),
          body: scope.t('deepLink.direct'),
          status: scope.t('deepLink.direct'),
        ),
      ),
    };
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return FutureBuilder<_DeepLinkPreviewData>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return AirmiusPanel(
            child: Row(
              children: [
                SizedBox(
                  width: 20,
                  height: 20,
                  child: CircularProgressIndicator(
                    color: airmiusAccentColor(context),
                    strokeWidth: 2,
                  ),
                ),
                SizedBox(width: 12),
                Expanded(
                  child: Text(
                    scope.t('deepLink.detailLoading'),
                    style: TextStyle(
                      color: airmiusMutedColor(context),
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
                Eyebrow(scope.t('deepLink.detailPreview')),
                const SizedBox(height: 8),
                Text(
                  scope.t('deepLink.detailFailed'),
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  snapshot.error is AirmiusApiException
                      ? (snapshot.error! as AirmiusApiException).userMessage
                      : scope.t('deepLink.retryLater'),
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    height: 1.35,
                  ),
                ),
              ],
            ),
          );
        }

        final data = snapshot.data!;
        return AirmiusPanel(
          borderColor: airmiusAccentColor(context).withValues(alpha: .45),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  Expanded(child: Eyebrow(scope.t('deepLink.detailPreview'))),
                  StatusPill(data.status, color: airmiusAccentColor(context)),
                ],
              ),
              const SizedBox(height: 10),
              Text(
                data.title,
                style: TextStyle(
                  color: airmiusTextColor(context),
                  fontSize: 18,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                data.body,
                style: TextStyle(
                  color: airmiusMutedColor(context),
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
              style: TextStyle(
                color: airmiusAccentColor(context),
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: TextStyle(
                color: airmiusMutedColor(context),
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
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor:
            (Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context)),
        surfaceTintColor: Colors.transparent,
        title: Text(
          AirmiusScope.of(context).t('deepLink.unknown.title'),
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: AirmiusScope.of(context).t('deepLink.unknown.title'),
        subtitle: AirmiusScope.of(context).t('deepLink.secureFallback'),
        child: AirmiusPanel(
          borderColor: Theme.of(
            context,
          ).colorScheme.tertiary.withValues(alpha: .55),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Eyebrow(AirmiusScope.of(context).t('deepLink.secureFallback')),
              const SizedBox(height: 10),
              Text(
                target.path.isEmpty
                    ? AirmiusScope.of(context).t('deepLink.unknownPath')
                    : target.path,
                style: TextStyle(
                  color: airmiusTextColor(context),
                  fontSize: 20,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                AirmiusScope.of(context).t('deepLink.fallbackBody'),
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  height: 1.42,
                ),
              ),
              const SizedBox(height: 12),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  StatusPill(
                    target.analyticsName,
                    color: Theme.of(context).colorScheme.tertiary,
                  ),
                  StatusPill(
                    target.requiresAuth
                        ? AirmiusScope.of(context).t('deepLink.authGate')
                        : AirmiusScope.of(context).t('deepLink.public'),
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
