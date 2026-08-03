import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import '../widgets/content_report_dialog.dart';
import 'new_conversation_screen.dart';

class UserProfileDetailScreen extends StatefulWidget {
  const UserProfileDetailScreen({
    super.key,
    required this.name,
    required this.body,
    required this.status,
    required this.context,
    this.userId,
    this.ownProfile = false,
    this.initialSportCv,
  });

  final String name;
  final String body;
  final String status;
  final String context;
  final int? userId;
  final bool ownProfile;
  final JsonMap? initialSportCv;

  @override
  State<UserProfileDetailScreen> createState() =>
      _UserProfileDetailScreenState();
}

class _UserProfileDetailScreenState extends State<UserProfileDetailScreen> {
  Future<_ProfileData>? _dataFuture;
  String _relationship = 'unknown';
  int? _invitationId;
  bool _isFollowing = false;
  bool _canFollow = false;
  bool _canSendMessage = false;
  bool _hasBlocked = false;
  bool _isBlocked = false;
  bool _busy = false;
  bool _reported = false;

  String _t(String key) => AirmiusScope.of(context).t(key);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _dataFuture ??= _loadData();
  }

  Future<_ProfileData> _loadData() async {
    final userId = widget.userId;
    if (userId == null || userId <= 0) {
      return const _ProfileData();
    }

    final services = AirmiusServicesScope.of(context);
    final client = services.clientForSession(services.authState.session);
    JsonMap? sportCv = widget.initialSportCv;
    var profileError = false;
    JsonMap social = const {};
    if (sportCv?['social'] is JsonMap) {
      social = sportCv!['social'] as JsonMap;
    }
    if (sportCv == null) {
      try {
        final response = await client.sportCvForUser(userId);
        final data = response['data'];
        sportCv = data is JsonMap ? data : response;
        if (sportCv['social'] is JsonMap) {
          social = sportCv['social'] as JsonMap;
        }
      } catch (_) {
        profileError = true;
      }
    }

    var relationship = 'none';
    int? invitationId;
    if (!widget.ownProfile) {
      try {
        final response = await client.friends();
        final data = response['data'] is JsonMap
            ? response['data'] as JsonMap
            : response;
        final friends = _maps(data['friends']);
        if (friends.any((friend) => _id(friend['id']) == userId)) {
          relationship = 'friends';
        } else {
          for (final invitation in _maps(data['receivedInvitations'])) {
            final sender = invitation['sender'];
            if (sender is JsonMap && _id(sender['id']) == userId) {
              relationship = 'received';
              invitationId = _id(invitation['id']);
              break;
            }
          }
          if (relationship == 'none') {
            for (final invitation in _maps(data['sentInvitations'])) {
              final recipient = invitation['recipient'];
              if (recipient is JsonMap && _id(recipient['id']) == userId) {
                relationship = 'sent';
                invitationId = _id(invitation['id']);
                break;
              }
            }
          }
        }
      } catch (_) {
        profileError = true;
      }
    }

    if (!mounted) return _ProfileData(sportCv: sportCv, error: profileError);
    setState(() {
      _relationship = relationship;
      _invitationId = invitationId;
      _isFollowing = social['is_following'] == true;
      _canFollow = social['can_follow'] == true;
      _canSendMessage = social['can_send_message'] == true;
      _hasBlocked = social['has_blocked'] == true;
      _isBlocked = social['is_blocked'] == true;
    });
    return _ProfileData(sportCv: sportCv, error: profileError);
  }

  Future<void> _sendFriendRequest() async {
    final userId = widget.userId;
    if (userId == null || _busy) return;
    await _runAction(() async {
      final services = AirmiusServicesScope.of(context);
      final client = services.clientForSession(services.authState.session);
      final response = await client.inviteFriend(userId: userId);
      final responseData = response['data'];
      final invitationId = responseData is JsonMap
          ? _id(responseData['invitation_id'])
          : 0;
      if (!mounted) return;
      setState(() {
        _relationship = 'sent';
        _invitationId = invitationId > 0 ? invitationId : null;
      });
      _notify(_t('profile.detail.requestSent'));
    });
  }

  Future<void> _acceptInvitation() async {
    final invitationId = _invitationId;
    if (invitationId == null || _busy) return;
    await _runAction(() async {
      final services = AirmiusServicesScope.of(context);
      final client = services.clientForSession(services.authState.session);
      await client.acceptFriendInvitation(invitationId);
      if (!mounted) return;
      setState(() => _relationship = 'friends');
      _notify(_t('profile.detail.accepted'));
    });
  }

  Future<void> _declineInvitation() async {
    final invitationId = _invitationId;
    if (invitationId == null || _busy) return;
    await _runAction(() async {
      final services = AirmiusServicesScope.of(context);
      final client = services.clientForSession(services.authState.session);
      await client.declineFriendInvitation(invitationId);
      if (!mounted) return;
      setState(() {
        _relationship = 'none';
        _invitationId = null;
      });
      _notify(_t('profile.detail.declined'));
    });
  }

  Future<void> _withdrawFriendRequest() async {
    final invitationId = _invitationId;
    if (invitationId == null || _busy) return;
    final t = AirmiusScope.of(context).t;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        backgroundColor: airmiusSurfaceColor(context),
        title: Text(t('friends.withdrawTitle')),
        content: Text(t('friends.withdrawQuestion')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(t('cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(t('friends.withdraw')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    await _runAction(() async {
      final services = AirmiusServicesScope.of(context);
      final client = services.clientForSession(services.authState.session);
      await client.withdrawFriendInvitation(invitationId);
      if (!mounted) return;
      setState(() {
        _relationship = 'none';
        _invitationId = null;
      });
      _notify(t('friends.withdrawn'));
    });
  }

  Future<void> _removeFriend() async {
    final userId = widget.userId;
    if (userId == null || _busy) return;
    await _runAction(() async {
      final services = AirmiusServicesScope.of(context);
      final client = services.clientForSession(services.authState.session);
      await client.removeFriend(userId);
      if (!mounted) return;
      setState(() => _relationship = 'none');
      _notify(_t('profile.detail.removed'));
    });
  }

  Future<void> _followUser() async {
    final userId = widget.userId;
    if (userId == null || _busy) return;
    await _runAction(() async {
      final services = AirmiusServicesScope.of(context);
      await services
          .clientForSession(services.authState.session)
          .followUser(userId);
      if (!mounted) return;
      setState(() => _isFollowing = true);
      _notify(_t('profile.detail.followed'));
    });
  }

  Future<void> _unfollowUser() async {
    final userId = widget.userId;
    if (userId == null || _busy) return;
    await _runAction(() async {
      final services = AirmiusServicesScope.of(context);
      await services
          .clientForSession(services.authState.session)
          .unfollowUser(userId);
      if (!mounted) return;
      setState(() => _isFollowing = false);
      _notify(_t('profile.detail.unfollowed'));
    });
  }

  Future<void> _blockUser() async {
    final userId = widget.userId;
    if (userId == null || _busy) return;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        backgroundColor: airmiusSurfaceColor(context),
        title: Text(_t('profile.detail.blockTitle')),
        content: Text(_t('profile.detail.blockQuestion')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(_t('cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(_t('profile.detail.block')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    await _runAction(() async {
      final services = AirmiusServicesScope.of(context);
      await services
          .clientForSession(services.authState.session)
          .blockUser(userId);
      if (!mounted) return;
      setState(() {
        _hasBlocked = true;
        _isFollowing = false;
        _canSendMessage = false;
      });
      _notify(_t('profile.detail.blocked'));
    });
  }

  Future<void> _unblockUser() async {
    final userId = widget.userId;
    if (userId == null || _busy) return;
    await _runAction(() async {
      final services = AirmiusServicesScope.of(context);
      final response = await services
          .clientForSession(services.authState.session)
          .unblockUser(userId);
      final data = response['data'];
      if (!mounted) return;
      setState(() {
        _hasBlocked = false;
        if (data is JsonMap) {
          _canFollow = data['can_follow'] == true;
          _canSendMessage = data['can_send_message'] == true;
        }
      });
      _notify(_t('profile.detail.unblocked'));
    });
  }

  Future<void> _runAction(Future<void> Function() action) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await action();
    } catch (error) {
      if (mounted) _notify(_errorMessage(error), error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _reportProfile() async {
    final userId = widget.userId;
    if (userId == null || _busy || _reported) return;
    final report = await showContentReportDialog(
      context,
      title: _t('profile.detail.reportTitle'),
    );
    if (report == null || !mounted) return;
    await _runAction(() async {
      final services = AirmiusServicesScope.of(context);
      final client = services.clientForSession(services.authState.session);
      await client.reportContent(
        type: 'user',
        id: userId,
        reason: report.reason,
        details: report.details,
      );
      if (!mounted) return;
      setState(() => _reported = true);
      _notify(_t('profile.detail.reportSuccess'));
    });
  }

  void _notify(String message, {bool error = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: error ? AirmiusColors.red : null,
      ),
    );
  }

  String _errorMessage(Object error) {
    return error is AirmiusApiException
        ? error.userMessage
        : _t('common.errorDetails');
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Scaffold(
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: Text(widget.name, style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: widget.name,
        subtitle: widget.body,
        trailing: StatusPill(
          widget.status.isEmpty ? t('profile.role.member') : widget.status,
          color: _relationship == 'friends'
              ? AirmiusColors.green
              : airmiusAccentColor(context),
        ),
        child: FutureBuilder<_ProfileData>(
          future: _dataFuture,
          builder: (context, snapshot) {
            final data = snapshot.data ?? const _ProfileData();
            final profileLoading =
                snapshot.connectionState != ConnectionState.done;
            return Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _hero(t),
                const SizedBox(height: 14),
                if (snapshot.connectionState == ConnectionState.waiting)
                  const Padding(
                    padding: EdgeInsets.all(18),
                    child: Center(child: CircularProgressIndicator()),
                  ),
                if (data.error && data.sportCv == null) ...[
                  AirmiusPanel(
                    child: Text(
                      t('profile.detail.noProfileData'),
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        height: 1.35,
                      ),
                    ),
                  ),
                  const SizedBox(height: 14),
                ],
                if (data.sportCv != null) ...[
                  _metrics(data.sportCv!, t),
                  const SizedBox(height: 14),
                  _sportsPanel(data.sportCv!, t),
                  const SizedBox(height: 14),
                ],
                if (!widget.ownProfile) _relationshipPanel(t),
                if (!widget.ownProfile) const SizedBox(height: 14),
                _actions(t, disabled: profileLoading),
              ],
            );
          },
        ),
      ),
    );
  }

  Widget _hero(String Function(String) t) {
    return AirmiusPanel(
      gradient: true,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AirmiusAvatar(widget.name, large: true),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Eyebrow(widget.context),
                const SizedBox(height: 6),
                Text(
                  widget.name,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontSize: 24,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  widget.body,
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    height: 1.35,
                  ),
                ),
                const SizedBox(height: 12),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    StatusPill(
                      widget.status.isEmpty
                          ? t('profile.role.member')
                          : widget.status,
                    ),
                    if (_relationship == 'friends')
                      StatusPill(
                        t('profile.detail.relationshipFriends'),
                        color: AirmiusColors.green,
                      ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _metrics(JsonMap cv, String Function(String) t) {
    final summary = cv['summary'] is JsonMap
        ? cv['summary'] as JsonMap
        : const <String, dynamic>{};
    final quality = cv['scout_card'] is JsonMap
        ? cv['scout_card'] as JsonMap
        : const <String, dynamic>{};
    final cards = <Widget>[];
    final sportsCount = _number(summary['sports_count']);
    final recommendations = _number(summary['approved_recommendations']);
    final score = _number(quality['score']);
    if (sportsCount != null) {
      cards.add(
        Expanded(
          child: MetricCard(
            value: '$sportsCount',
            label: t('profile.detail.sports'),
          ),
        ),
      );
    }
    if (recommendations != null) {
      if (cards.isNotEmpty) cards.add(const SizedBox(width: 10));
      cards.add(
        Expanded(
          child: MetricCard(
            value: '$recommendations',
            label: t('profile.detail.recommendations'),
          ),
        ),
      );
    }
    if (score != null) {
      if (cards.isNotEmpty) cards.add(const SizedBox(width: 10));
      cards.add(
        Expanded(
          child: MetricCard(
            value: '$score%',
            label: t('profile.detail.quality'),
          ),
        ),
      );
    }
    if (cards.isEmpty) return const SizedBox.shrink();
    return Row(children: cards);
  }

  Widget _sportsPanel(JsonMap cv, String Function(String) t) {
    final sports = _maps(cv['primary_sports']);
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(t('profile.sportsTitle')),
          const SizedBox(height: 10),
          if (sports.isEmpty)
            Text(
              t('profile.detail.noSports'),
              style: TextStyle(color: airmiusMutedColor(context), height: 1.35),
            )
          else
            for (final sport in sports) ...[
              _sportLine(sport, t),
              if (sport != sports.last) Divider(height: 20),
            ],
        ],
      ),
    );
  }

  Widget _sportLine(JsonMap sport, String Function(String) t) {
    final rawSport = sport['sport'];
    final sportMap = rawSport is JsonMap ? rawSport : const <String, dynamic>{};
    final name =
        '${sportMap['name'] ?? sportMap['slug'] ?? t('profile.detail.sport')}';
    final status = _stringOrNull(sport['status']);
    final experience = _stringOrNull(sport['experience_level']);
    final details = [
      if (status != null && status.isNotEmpty) status,
      if (experience != null && experience.isNotEmpty) experience,
    ].join(' · ');
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(Icons.directions_run_outlined, color: airmiusAccentColor(context)),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                name,
                style: TextStyle(
                  color: airmiusTextColor(context),
                  fontWeight: FontWeight.w900,
                ),
              ),
              if (details.isNotEmpty) ...[
                const SizedBox(height: 3),
                Text(
                  details,
                  style: TextStyle(color: airmiusMutedColor(context)),
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }

  Widget _relationshipPanel(String Function(String) t) {
    final label = switch (_relationship) {
      'friends' => t('profile.detail.relationshipFriends'),
      'received' => t('profile.detail.relationshipReceived'),
      'sent' => t('profile.detail.relationshipSent'),
      _ => t('profile.detail.relationshipNone'),
    };
    return AirmiusPanel(
      child: Row(
        children: [
          Icon(
            _relationship == 'friends'
                ? Icons.people_alt_outlined
                : Icons.person_add_alt_1_outlined,
            color: _relationship == 'friends'
                ? AirmiusColors.green
                : airmiusAccentColor(context),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              label,
              style: TextStyle(
                color: airmiusTextColor(context),
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _actions(String Function(String) t, {bool disabled = false}) {
    if (widget.ownProfile) return const SizedBox.shrink();
    final userId = widget.userId;
    final actionDisabled = disabled || _busy;
    final buttons = <Widget>[];
    if (_canSendMessage && !_hasBlocked && !_isBlocked) {
      buttons.add(
        AirmiusButton(
          label: t('profile.detail.message'),
          icon: Icons.chat_bubble_outline,
          onPressed: actionDisabled
              ? null
              : () => Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => NewConversationScreen(
                      initialUserId: userId,
                      initialUserName: widget.name,
                    ),
                  ),
                ),
        ),
      );
    }
    if (_canFollow && !_isBlocked) {
      buttons.add(
        AirmiusButton(
          label: _isFollowing
              ? t('profile.detail.unfollow')
              : t('profile.detail.follow'),
          icon: _isFollowing
              ? Icons.person_remove_alt_1_outlined
              : Icons.person_add_alt_1_outlined,
          secondary: true,
          onPressed: actionDisabled
              ? null
              : (_isFollowing ? _unfollowUser : _followUser),
        ),
      );
    }
    if (_relationship == 'received' && _invitationId != null) {
      buttons.add(
        AirmiusButton(
          label: t('profile.detail.accept'),
          icon: Icons.check_circle_outline,
          onPressed: actionDisabled ? null : _acceptInvitation,
        ),
      );
      buttons.add(
        AirmiusButton(
          label: t('profile.detail.decline'),
          icon: Icons.close_outlined,
          danger: true,
          onPressed: actionDisabled ? null : _declineInvitation,
        ),
      );
    } else if (_relationship == 'friends') {
      buttons.add(
        AirmiusButton(
          label: t('profile.detail.removeFriend'),
          icon: Icons.person_remove_outlined,
          danger: true,
          onPressed: actionDisabled ? null : _removeFriend,
        ),
      );
    } else if (_relationship == 'sent') {
      buttons.add(
        AirmiusButton(
          label: t('friends.withdraw'),
          icon: Icons.undo_outlined,
          secondary: true,
          onPressed: actionDisabled ? null : _withdrawFriendRequest,
        ),
      );
    } else if (userId != null) {
      buttons.add(
        AirmiusButton(
          label: t('profile.detail.sendRequest'),
          icon: Icons.person_add_alt_1_outlined,
          onPressed: actionDisabled ? null : _sendFriendRequest,
        ),
      );
    }
    if (userId != null) {
      buttons.add(
        AirmiusButton(
          label: _hasBlocked
              ? t('profile.detail.unblock')
              : t('profile.detail.block'),
          icon: _hasBlocked ? Icons.lock_open_outlined : Icons.block_outlined,
          danger: !_hasBlocked,
          secondary: _hasBlocked,
          onPressed: actionDisabled
              ? null
              : (_hasBlocked ? _unblockUser : _blockUser),
        ),
      );
      buttons.add(
        AirmiusButton(
          label: _reported
              ? t('profile.detail.reported')
              : t('profile.detail.report'),
          icon: Icons.report_outlined,
          secondary: true,
          onPressed: actionDisabled || _reported ? null : _reportProfile,
        ),
      );
    }
    if (buttons.isEmpty) return const SizedBox.shrink();
    return Wrap(spacing: 10, runSpacing: 10, children: buttons);
  }
}

class _ProfileData {
  const _ProfileData({this.sportCv, this.error = false});

  final JsonMap? sportCv;
  final bool error;
}

List<JsonMap> _maps(Object? value) {
  return value is List ? value.whereType<JsonMap>().toList() : const [];
}

int _id(Object? value) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  return int.tryParse('$value') ?? 0;
}

int? _number(Object? value) {
  if (value is num) return value.toInt();
  final parsed = int.tryParse('$value');
  return parsed;
}

String? _stringOrNull(Object? value) {
  if (value == null) return null;
  final result = '$value'.trim();
  return result.isEmpty ? null : result;
}
