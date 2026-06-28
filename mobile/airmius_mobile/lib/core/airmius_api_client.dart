import 'dart:convert';

import 'airmius_club_member_invite_form_stub.dart' if (dart.library.html) 'airmius_club_member_invite_form_web.dart';
import 'airmius_team_create_form_stub.dart' if (dart.library.html) 'airmius_team_create_form_web.dart';

typedef AirmiusHeaders = Map<String, String>;
typedef AirmiusJson = Map<String, dynamic>;

class AirmiusApiRequest {
  const AirmiusApiRequest({
    required this.method,
    required this.path,
    this.body,
    this.query = const {},
    this.headers = const {},
  });

  final String method;
  final String path;
  final AirmiusJson? body;
  final Map<String, String> query;
  final AirmiusHeaders headers;
}

class AirmiusApiResponse {
  const AirmiusApiResponse({
    required this.statusCode,
    required this.body,
    this.headers = const {},
  });

  final int statusCode;
  final String body;
  final AirmiusHeaders headers;

  bool get ok => statusCode >= 200 && statusCode < 300;

  AirmiusJson get json {
    final decoded = jsonDecode(body);
    if (decoded is Map<String, dynamic>) return decoded;
    return {'data': decoded};
  }
}

abstract class AirmiusApiTransport {
  Future<AirmiusApiResponse> send(AirmiusApiRequest request);
}

class AirmiusApiClient {
  const AirmiusApiClient({
    required this.transport,
    required this.baseUrl,
    this.token,
    this.locale = 'de',
  });

  final AirmiusApiTransport transport;
  final String baseUrl;
  final String? token;
  final String locale;

  Future<AirmiusJson> login({required String email, required String password}) async {
    try {
      return await _json('POST', '/api/v1/auth/login', body: {'email': email, 'password': password});
    } on AirmiusApiException catch (error) {
      if (error.statusCode == 404 || error.statusCode == 405 || error.statusCode == 422) {
        return _json('POST', '/friends/auth/login', body: {'email': email, 'password': password});
      }
      rethrow;
    }
  }

  Future<AirmiusJson> register(AirmiusJson payload) {
    return _json('POST', '/api/v1/auth/register', body: payload);
  }

  Future<AirmiusJson> registrationEmailStatus({required String email}) {
    return _json('GET', '/api/v1/auth/register/email', query: {'email': email});
  }

  Future<AirmiusJson> me() async {
    try {
      return await _json('GET', '/api/v1/me');
    } on AirmiusApiException catch (error) {
      if (error.statusCode == 404 || error.statusCode == 405 || error.statusCode == 422) {
        try {
          return await _json('GET', '/api/v1/auth/me');
        } on AirmiusApiException catch (legacyError) {
          if (legacyError.statusCode != 404 && legacyError.statusCode != 405 && legacyError.statusCode != 422) {
            rethrow;
          }
        }
        return _json('GET', '/friends/me');
      }
      rethrow;
    }
  }

  Future<AirmiusJson> friendsMe() => _json('GET', '/friends/me');

  Future<AirmiusJson> updateProfile(AirmiusJson payload) {
    return _json('PUT', '/api/v1/me/profile', body: payload);
  }

  Future<AirmiusJson> logout() async {
    try {
      return await _json('POST', '/api/v1/auth/logout');
    } on AirmiusApiException catch (error) {
      if (error.statusCode == 404 || error.statusCode == 405) {
        return _json('POST', '/friends/auth/logout');
      }
      rethrow;
    }
  }

  Future<AirmiusJson> requestAccountDeletionCode({required String password}) {
    return _json('POST', '/api/v1/account/deletion-code', body: {'password': password});
  }

  Future<AirmiusJson> deleteAccount({required String code}) {
    return _json('DELETE', '/api/v1/account', body: {'code': code});
  }

  Future<AirmiusJson> search(String query) => _json('GET', '/api/v1/search', query: {'q': query});

  Future<AirmiusJson> sports() => _json('GET', '/api/v1/sports');

  Future<AirmiusJson> clubs({String? query, bool mine = false}) => _json('GET', '/api/v1/clubs', query: {
        if (query != null) 'q': query,
        if (mine) 'mine': '1',
      });

  Future<AirmiusJson> createClub(AirmiusJson payload) => _json('POST', '/api/v1/clubs', body: payload);
  Future<AirmiusJson> clubDetail(int clubId) => _json('GET', '/api/v1/clubs/$clubId');
  Future<AirmiusJson> updateClubMembershipSettings(int clubId, AirmiusJson payload) => _json('PUT', '/api/v1/clubs/$clubId/membership/settings', body: payload);
  Future<AirmiusJson> createClubMembershipType(int clubId, AirmiusJson payload) => _json('POST', '/api/v1/clubs/$clubId/membership/types', body: payload);
  Future<AirmiusJson> updateClubMembershipType(int clubId, int typeId, AirmiusJson payload) => _json('PUT', '/api/v1/clubs/$clubId/membership/types/$typeId', body: payload);
  Future<AirmiusJson> createClubContributionRule(int clubId, AirmiusJson payload) => _json('POST', '/api/v1/clubs/$clubId/membership/contribution-rules', body: payload);
  Future<AirmiusJson> updateClubContributionRule(int clubId, int ruleId, AirmiusJson payload) => _json('PUT', '/api/v1/clubs/$clubId/membership/contribution-rules/$ruleId', body: payload);
  Future<AirmiusJson> inviteClubMember(int clubId, AirmiusJson payload) async {
    try {
      return await _json('POST', '/api/v1/clubs/$clubId/members/invite', body: payload);
    } on AirmiusApiException catch (error) {
      if (!_shouldTryTeamJoinFallback(error)) rethrow;
      final formResponse = await _sendClubMemberInviteForm(clubId, payload);
      if (formResponse != null) return formResponse;
      rethrow;
    }
  }

  Future<AirmiusJson?> _sendClubMemberInviteForm(int clubId, AirmiusJson payload) {
    final sessionToken = token;
    if (sessionToken == null || sessionToken.isEmpty) return Future.value(null);

    return sendClubMemberInviteForm(
      baseUrl: baseUrl,
      token: sessionToken,
      locale: locale,
      clubId: clubId,
      payload: payload,
    );
  }

  Future<AirmiusJson> updateClubMemberRole(int clubId, int userId, String role) async {
    try {
      return await _json('PUT', '/api/v1/clubs/$clubId/members/$userId/role', body: {'role': role});
    } on AirmiusApiException catch (error) {
      if (!_shouldTryTeamJoinFallback(error)) rethrow;
      await _json('PUT', '/clubs/$clubId/members/$userId', body: {'role': role, 'roles': [role]});
      final detail = await clubDetail(clubId);
      final data = detail['data'];
      return data is Map<String, dynamic> && data['management'] is Map<String, dynamic> ? data['management'] as Map<String, dynamic> : detail;
    }
  }

  Future<AirmiusJson> teams({int page = 1, int perPage = 50}) => _json('GET', '/api/v1/teams', query: {
        'page': '$page',
        'per_page': '$perPage',
      });

  Future<AirmiusJson> teamDetail(int teamId) => _json('GET', '/api/v1/teams/$teamId');
  Future<AirmiusJson> createTeam(AirmiusJson payload) async {
    try {
      return await _json('POST', '/api/v1/teams', body: payload);
    } on AirmiusApiException catch (error) {
      if (!_shouldTryTeamJoinFallback(error)) rethrow;
      final formResponse = await _sendTeamCreateForm(payload);
      if (formResponse != null) return formResponse;
      rethrow;
    }
  }

  Future<AirmiusJson?> _sendTeamCreateForm(AirmiusJson payload) {
    final sessionToken = token;
    if (sessionToken == null || sessionToken.isEmpty) return Future.value(null);

    return sendTeamCreateForm(
      baseUrl: baseUrl,
      token: sessionToken,
      locale: locale,
      payload: payload,
    );
  }

  Future<AirmiusJson> updateTeam(int teamId, AirmiusJson payload) => _json('PUT', '/api/v1/teams/$teamId', body: payload);
  Future<AirmiusJson> deleteTeam(int teamId) => _json('DELETE', '/api/v1/teams/$teamId');
  Future<AirmiusJson> requestTeamJoin(int teamId) => _json('POST', '/api/v1/teams/$teamId/join-requests');
  Future<AirmiusJson> approveTeamJoinRequest(int teamId, int requestId, {String role = 'Player'}) async {
    try {
      return await _json('POST', '/api/v1/team-join-requests/$requestId/approve', body: {'role': role});
    } on AirmiusApiException catch (error) {
      if (!_shouldTryTeamJoinFallback(error)) rethrow;
      try {
        return await _json('POST', '/api/v1/teams/$teamId/join-requests/$requestId/approve', body: {'role': role});
      } on AirmiusApiException catch (nestedError) {
        if (!_shouldTryTeamJoinFallback(nestedError)) rethrow;
        return _json('POST', '/team-join-requests/$requestId/approve', body: {'role': role});
      }
    }
  }

  Future<AirmiusJson> declineTeamJoinRequest(int teamId, int requestId) async {
    try {
      return await _json('POST', '/api/v1/team-join-requests/$requestId/decline');
    } on AirmiusApiException catch (error) {
      if (!_shouldTryTeamJoinFallback(error)) rethrow;
      try {
        return await _json('POST', '/api/v1/teams/$teamId/join-requests/$requestId/decline');
      } on AirmiusApiException catch (nestedError) {
        if (!_shouldTryTeamJoinFallback(nestedError)) rethrow;
        return _json('POST', '/team-join-requests/$requestId/decline');
      }
    }
  }

  bool _shouldTryTeamJoinFallback(AirmiusApiException error) => error.statusCode == 0 || error.statusCode == 404 || error.statusCode == 405 || error.statusCode == 599;

  Future<AirmiusJson> teamInvitations() => _json('GET', '/api/v1/team-invitations');

  Future<AirmiusJson> inviteTeamMember(int teamId, {required String email, required String role}) {
    return _json('POST', '/api/v1/teams/$teamId/invite', body: {
      'email': email,
      'role': role,
    });
  }

  Future<AirmiusJson> teamInvitation(int invitationId) => _json('GET', '/api/v1/team-invitations/$invitationId');

  Future<AirmiusJson> acceptTeamInvitation(int invitationId) => _json('POST', '/api/v1/team-invitations/$invitationId/accept');

  Future<AirmiusJson> declineTeamInvitation(int invitationId) => _json('POST', '/api/v1/team-invitations/$invitationId/decline');

  Future<AirmiusJson> updateTeamMemberRole(int teamId, int userId, String role) async {
    try {
      return await _json('PUT', '/api/v1/teams/$teamId/members/$userId', body: {'role': role});
    } on AirmiusApiException catch (error) {
      if (!_shouldTryTeamJoinFallback(error)) rethrow;
      return _json('PUT', '/teams/$teamId/members/$userId', body: {'role': role});
    }
  }

  Future<AirmiusJson> teamAttendanceStats(int teamId) => _json('GET', '/api/v1/teams/$teamId/attendance-stats');

  Future<AirmiusJson> teamPenalties(int teamId, {int? eventId}) => _json('GET', '/api/v1/teams/$teamId/penalties', query: {
        if (eventId != null) 'event_id': '$eventId',
      });

  Future<AirmiusJson> createTeamPenaltyRule(int teamId, AirmiusJson payload) {
    return _json('POST', '/api/v1/teams/$teamId/penalty-rules', body: payload);
  }

  Future<AirmiusJson> updateTeamPenaltyRule(int teamId, int ruleId, AirmiusJson payload) {
    return _json('PUT', '/api/v1/teams/$teamId/penalty-rules/$ruleId', body: payload);
  }

  Future<AirmiusJson> deactivateTeamPenaltyRule(int teamId, int ruleId) {
    return _json('DELETE', '/api/v1/teams/$teamId/penalty-rules/$ruleId');
  }

  Future<AirmiusJson> createTeamPenaltyFee(int teamId, AirmiusJson payload) {
    return _json('POST', '/api/v1/teams/$teamId/penalty-fees', body: payload);
  }

  Future<AirmiusJson> markTeamPenaltyFeePaid(int teamId, int feeId) {
    return _json('POST', '/api/v1/teams/$teamId/penalty-fees/$feeId/paid');
  }

  Future<AirmiusJson> cancelTeamPenaltyFee(int teamId, int feeId) {
    return _json('POST', '/api/v1/teams/$teamId/penalty-fees/$feeId/cancel');
  }

  Future<AirmiusJson> createClubMembershipRequest(int clubId, AirmiusJson payload) {
    return _json('POST', '/api/v1/clubs/$clubId/membership-requests', body: payload);
  }

  Future<AirmiusJson> clubMembershipRequests(int clubId, {int page = 1}) => _json('GET', '/api/v1/clubs/$clubId/membership-requests', query: {'page': '$page'});

  Future<AirmiusJson> withdrawClubMembershipRequest(int clubId) {
    return _json('DELETE', '/api/v1/clubs/$clubId/membership-requests');
  }

  Future<AirmiusJson> approveClubMembershipRequest(int clubId, int requestId, {String? reviewNote}) {
    return _json('POST', '/api/v1/clubs/$clubId/membership-requests/$requestId/approve', body: {
      if (reviewNote != null && reviewNote.trim().isNotEmpty) 'review_note': reviewNote.trim(),
    });
  }

  Future<AirmiusJson> declineClubMembershipRequest(int clubId, int requestId, {String? reviewNote}) {
    return _json('POST', '/api/v1/clubs/$clubId/membership-requests/$requestId/decline', body: {
      if (reviewNote != null && reviewNote.trim().isNotEmpty) 'review_note': reviewNote.trim(),
    });
  }

  Future<AirmiusJson> membershipApplication(int applicationId) {
    return _json('GET', '/api/v1/membership-applications/$applicationId');
  }

  Future<AirmiusJson> withdrawMembershipApplication(int applicationId) {
    return _json('POST', '/api/v1/membership-applications/$applicationId/withdraw');
  }

  Future<AirmiusJson> uploadIntent({required String scope, required String fileName, required String mimeType}) {
    return _json('POST', '/api/v1/files/upload-intents', body: {'scope': scope, 'file_name': fileName, 'mime_type': mimeType});
  }

  Future<AirmiusJson> fileWorkspace({
    String scope = 'user',
    int? folderId,
    String? search,
    String sort = 'name-asc',
    int page = 1,
    int perPage = 24,
  }) {
    return _json('GET', '/api/v1/files', query: {
      'scope': scope,
      'sort': sort,
      'files_page': '$page',
      'folders_page': '$page',
      'per_page': '$perPage',
      if (folderId != null) 'folder_id': '$folderId',
      if (search != null && search.trim().isNotEmpty) 'search': search.trim(),
    });
  }

  Future<AirmiusJson> createFolder({required String scope, required String name, int? parentId}) {
    return _json('POST', '/api/v1/files/folders', body: {
      'scope': scope,
      'name': name,
      if (parentId != null) 'parent_id': parentId,
    });
  }

  Future<AirmiusJson> renameFolder(int folderId, String name) => _json('PATCH', '/api/v1/files/folders/$folderId', body: {'name': name});

  Future<AirmiusJson> deleteFolder(int folderId) => _json('DELETE', '/api/v1/files/folders/$folderId');

  Future<AirmiusJson> renameFile(int fileId, String name) => _json('PATCH', '/api/v1/uploads/$fileId', body: {'display_name': name});

  Future<AirmiusJson> deleteFile(int fileId) => _json('DELETE', '/api/v1/uploads/$fileId');

  Future<AirmiusJson> notifications() => _json('GET', '/api/v1/notifications');

  Future<AirmiusJson> notification(int notificationId) => _json('GET', '/api/v1/notifications/$notificationId');

  Future<AirmiusJson> markNotificationAsRead(int notificationId) => _json('POST', '/api/v1/notifications/$notificationId/read');

  Future<AirmiusJson> markNotificationAsUnread(int notificationId) => _json('POST', '/api/v1/notifications/$notificationId/unread');

  Future<AirmiusJson> markAllNotificationsAsRead() => _json('POST', '/api/v1/notifications/read-all');

  Future<AirmiusJson> deleteNotification(int notificationId) => _json('DELETE', '/api/v1/notifications/$notificationId');

  Future<AirmiusJson> conversations() => _json('GET', '/api/v1/chat/conversations');

  Future<AirmiusJson> conversation(int conversationId) => _json('GET', '/api/v1/chat/conversations/$conversationId');

  Future<AirmiusJson> conversationMessages(int conversationId, {int page = 1}) => _json('GET', '/api/v1/chat/conversations/$conversationId/messages', query: {'page': '$page'});

  Future<AirmiusJson> sendConversationMessage(int conversationId, String message) => _json('POST', '/api/v1/chat/conversations/$conversationId/messages', body: {'message': message});

  Future<AirmiusJson> reactToMessage(int messageId, String reaction) => _json('POST', '/api/v1/chat/messages/$messageId/reactions', body: {'reaction': reaction});

  Future<AirmiusJson> hideMessage(int messageId) => _json('DELETE', '/api/v1/chat/messages/$messageId/hide');

  Future<AirmiusJson> deleteMessage(int messageId) => _json('DELETE', '/api/v1/chat/messages/$messageId');

  Future<AirmiusJson> feed({int page = 1, int perPage = 20}) => _json('GET', '/api/v1/feed', query: {
        'page': '$page',
        'per_page': '$perPage',
      });

  Future<AirmiusJson> createFeedPost(AirmiusJson payload) => _json('POST', '/api/v1/feed', body: payload);

  Future<AirmiusJson> togglePostLike(int postId) => _json('POST', '/api/v1/posts/$postId/like', body: {'reaction': 'like'});

  Future<AirmiusJson> togglePostHelpful(int postId) => _json('POST', '/api/v1/posts/$postId/helpful', body: {'reaction': 'helpful'});

  Future<AirmiusJson> updatePost(int postId, AirmiusJson body) => _json('PUT', '/api/v1/posts/$postId', body: body);

  Future<AirmiusJson> deletePost(int postId) async {
    try {
      return await _deletePostRequest(
        'POST',
        '/api/v1/posts/$postId',
        body: {'_method': 'DELETE'},
      );
    } on AirmiusApiException catch (error) {
      if (!_canTryPostDeleteFallback(error.statusCode)) rethrow;
    }

    try {
      return await _deletePostRequest('POST', '/api/v1/posts/$postId/delete');
    } on AirmiusApiException catch (error) {
      if (!_canTryPostDeleteFallback(error.statusCode)) rethrow;
    }

    try {
      return await _deletePostRequest('DELETE', '/api/v1/posts/$postId');
    } on AirmiusApiException catch (error) {
      if (error.statusCode == 404 || error.statusCode == 599) return {'deleted': true};
      rethrow;
    }
  }

  Future<AirmiusJson> _deletePostRequest(String method, String path, {AirmiusJson? body}) async {
    final response = await transport.send(
      AirmiusApiRequest(
        method: method,
        path: path,
        body: body,
        headers: _headers,
      ),
    );

    if (response.statusCode >= 200 && response.statusCode < 400) {
      final trimmed = response.body.trim();
      if (trimmed.isEmpty) return {'deleted': true};

      try {
        return response.json;
      } catch (_) {
        return {'deleted': true};
      }
    }

    throw AirmiusApiException(statusCode: response.statusCode, body: response.body, path: path);
  }

  bool _canTryPostDeleteFallback(int statusCode) {
    return statusCode == 404 || statusCode == 405 || statusCode == 419 || statusCode == 599;
  }

  Future<AirmiusJson> reportContent({required String type, required int id, String reason = 'other', String? details}) {
    return _json('POST', '/api/v1/reports', body: {
      'type': type,
      'id': id,
      'reason': reason,
      if (details != null && details.trim().isNotEmpty) 'details': details.trim(),
    });
  }

  Future<AirmiusJson> postComments(int postId, {int page = 1, int perPage = 20}) => _json('GET', '/api/v1/posts/$postId/comments', query: {'page': '$page', 'per_page': '$perPage'});

  Future<AirmiusJson> createPostComment(int postId, String content) => _json('POST', '/api/v1/posts/$postId/comments', body: {'content': content});

  Future<AirmiusJson> updateComment(int commentId, String content) => _json('PUT', '/api/v1/comments/$commentId', body: {'content': content});

  Future<AirmiusJson> deleteComment(int commentId) => _json('DELETE', '/api/v1/comments/$commentId');

  Future<AirmiusJson> stories({int limit = 30}) => _json('GET', '/api/v1/stories', query: {'limit': '$limit'});

  Future<AirmiusJson> markStoryViewed(int storyId) => _json('POST', '/api/v1/stories/$storyId/viewed');

  Future<AirmiusJson> reactToStory(int storyId, String reaction) => _json('POST', '/api/v1/stories/$storyId/react', body: {'reaction': reaction});

  Future<AirmiusJson> deleteStory(int storyId) => _json('DELETE', '/api/v1/stories/$storyId');

  Future<AirmiusJson> events({
    int page = 1,
    DateTime? from,
    DateTime? to,
    String? search,
    String? type,
    String? visibility,
    int? clubId,
    int? teamId,
    String? period,
    String? calendarMonth,
  }) =>
      _json('GET', '/api/v1/events', query: {
        'page': '$page',
        if (from != null) 'from': from.toIso8601String(),
        if (to != null) 'to': to.toIso8601String(),
        if (search != null && search.trim().isNotEmpty) 'search': search.trim(),
        if (type != null && type.isNotEmpty) 'type': type,
        if (visibility != null && visibility.isNotEmpty) 'visibility': visibility,
        if (clubId != null) 'club_id': '$clubId',
        if (teamId != null) 'team_id': '$teamId',
        if (period != null && period.isNotEmpty) 'period': period,
        if (calendarMonth != null && calendarMonth.isNotEmpty) 'calendar_month': calendarMonth,
      });

  Future<AirmiusJson> createEvent(AirmiusJson body) => _json('POST', '/api/v1/events', body: body);

  Future<AirmiusJson> event(int eventId) => _json('GET', '/api/v1/events/$eventId');

  Future<AirmiusJson> respondToEvent(int eventId, String status) => _json('POST', '/api/v1/events/$eventId/participation', body: {'status': status});

  Future<AirmiusJson> leaveEvent(int eventId) => _json('DELETE', '/api/v1/events/$eventId/participation');

  Future<AirmiusJson> invoices() => _json('GET', '/api/v1/billing/invoices');

  Future<AirmiusJson> invoice(int invoiceId) => _json('GET', '/api/v1/billing/invoices/$invoiceId');

  Future<AirmiusJson> _json(String method, String path, {AirmiusJson? body, Map<String, String> query = const {}}) async {
    try {
      final response = await transport.send(AirmiusApiRequest(method: method, path: path, body: body, query: query, headers: _headers));
      if (!response.ok) {
        throw AirmiusApiException(statusCode: response.statusCode, body: response.body, path: path);
      }
      return _normalizeMediaUrls(response.json) as AirmiusJson;
    } catch (error) {
      if (error is AirmiusApiException) rethrow;
      throw AirmiusApiException(
        statusCode: _networkStatus(error),
        body: error.toString(),
        path: path,
      );
    }
  }

  AirmiusHeaders get _headers => {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Airmius-Locale': locale,
        if (token != null && token!.isNotEmpty) 'Authorization': 'Bearer $token',
      };

  int _networkStatus(Object error) {
    final text = error.toString().toLowerCase();
    if (text.contains('status=599') || text.contains('http 599')) return 599;
    if (text.contains('progress')) return 0;
    return 0;
  }

  Object? _normalizeMediaUrls(Object? value) {
    if (value is List) {
      return value.map(_normalizeMediaUrls).toList();
    }
    if (value is Map<String, dynamic>) {
      return value.map((key, item) {
        if (item is String && _isMediaUrlKey(key)) {
          return MapEntry(key, _absoluteMediaUrl(item));
        }
        if (key == 'path' && item is String) {
          return MapEntry(key, _absoluteMediaUrl(item));
        }
        if (key == 'thumbnail_path' && item is String) {
          return MapEntry(key, _absoluteMediaUrl(item));
        }
        if (item is String && key.toLowerCase().endsWith('_path')) {
          return MapEntry(key, _absoluteMediaUrl(item));
        }
        return MapEntry(key, _normalizeMediaUrls(item));
      });
    }
    return value;
  }

  bool _isMediaUrlKey(String key) {
    final normalized = key.toLowerCase();
    return normalized.endsWith('_url') ||
        normalized.endsWith('_thumb') ||
        normalized.endsWith('_path') ||
        normalized == 'url' ||
        normalized == 'path' ||
        normalized == 'avatar_url' ||
        normalized == 'avatar_thumb' ||
        normalized == 'profile_photo_url' ||
        normalized == 'profile_photo_thumb' ||
        normalized == 'media_url' ||
        normalized == 'media_thumbnail_url' ||
        normalized == 'thumbnail_url';
  }

  String _absoluteMediaUrl(String value) {
    final trimmed = value.trim();
    if (trimmed.isEmpty || trimmed.startsWith('data:image/')) return trimmed;

    final origin = Uri.tryParse(baseUrl);
    if (origin == null || !origin.hasScheme || origin.host.isEmpty) return trimmed;

    final absolute = Uri.tryParse(trimmed);
    if (absolute != null && absolute.hasScheme && absolute.host.isNotEmpty) {
      if (_isLocalMediaHost(absolute.host)) {
        return origin
            .replace(
              path: absolute.path,
              query: absolute.query.isEmpty ? null : absolute.query,
              fragment: null,
            )
            .toString();
      }
      return trimmed;
    }

    if (trimmed.startsWith('/')) {
      return origin.replace(path: _withBasePath(origin, trimmed), query: null, fragment: null).toString();
    }

    final cleanPath = trimmed.replaceFirst(RegExp(r'^/+'), '');
    final path = cleanPath.startsWith('storage/') || cleanPath.startsWith('build/') || cleanPath.startsWith('images/')
        ? '/$cleanPath'
        : '/storage/$cleanPath';

    return origin.replace(path: _withBasePath(origin, path), query: null, fragment: null).toString();
  }

  String _withBasePath(Uri base, String path) {
    final cleanBase = base.path == '/' ? '' : base.path.replaceFirst(RegExp(r'/$'), '');
    if (cleanBase.isEmpty || path.startsWith('$cleanBase/')) return path;
    return '$cleanBase$path';
  }

  bool _isLocalMediaHost(String host) {
    final normalized = host.toLowerCase();
    return normalized == 'localhost' || normalized == '127.0.0.1' || normalized == '::1';
  }
}

class AirmiusApiException implements Exception {
  const AirmiusApiException({required this.statusCode, required this.body, required this.path});

  final int statusCode;
  final String body;
  final String path;

  String get userMessage {
    final trimmed = body.trim();
    if (trimmed.isEmpty) return 'Serverfehler ($statusCode).';

    try {
      final decoded = jsonDecode(trimmed);
      if (decoded is Map<String, dynamic>) {
        final errors = decoded['errors'];
        if (errors is Map<String, dynamic>) {
          for (final value in errors.values) {
            if (value is List && value.isNotEmpty) return value.first.toString();
            if (value is String && value.isNotEmpty) return value;
          }
        }

        final message = decoded['message'];
        if (message is String && message.trim().isNotEmpty) return message.trim();
      }
    } catch (_) {
      // Keep the plain text fallback below for non-JSON server responses.
    }

    if (trimmed.length <= 180 && !trimmed.startsWith('<')) return trimmed;
    return 'Serverfehler ($statusCode).';
  }

  @override
  String toString() => 'AirmiusApiException($statusCode, $path): $body';
}
