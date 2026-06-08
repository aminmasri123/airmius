import 'dart:convert';

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

  Future<AirmiusJson> me() async {
    try {
      return await _json('GET', '/api/v1/auth/me');
    } on AirmiusApiException catch (error) {
      if (error.statusCode == 404 || error.statusCode == 405 || error.statusCode == 422) {
        return _json('GET', '/friends/me');
      }
      rethrow;
    }
  }

  Future<AirmiusJson> friendsMe() => _json('GET', '/friends/me');

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

  Future<AirmiusJson> search(String query) => _json('GET', '/api/v1/search', query: {'q': query});

  Future<AirmiusJson> sports() => _json('GET', '/api/v1/sports');

  Future<AirmiusJson> clubs({String? query}) => _json('GET', '/api/v1/clubs', query: query == null ? const {} : {'q': query});

  Future<AirmiusJson> clubDetail(int clubId) => _json('GET', '/api/v1/clubs/$clubId');

  Future<AirmiusJson> teams({int page = 1, int perPage = 50}) => _json('GET', '/api/v1/teams', query: {
        'page': '$page',
        'per_page': '$perPage',
      });

  Future<AirmiusJson> teamDetail(int teamId) => _json('GET', '/api/v1/teams/$teamId');

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

  Future<AirmiusJson> notifications() => _json('GET', '/api/v1/notifications');

  Future<AirmiusJson> notification(int notificationId) => _json('GET', '/api/v1/notifications/$notificationId');

  Future<AirmiusJson> markNotificationAsRead(int notificationId) => _json('POST', '/api/v1/notifications/$notificationId/read');

  Future<AirmiusJson> markAllNotificationsAsRead() => _json('POST', '/api/v1/notifications/read-all');

  Future<AirmiusJson> deleteNotification(int notificationId) => _json('DELETE', '/api/v1/notifications/$notificationId');

  Future<AirmiusJson> conversations() => _json('GET', '/api/v1/chat/conversations');

  Future<AirmiusJson> conversation(int conversationId) => _json('GET', '/api/v1/chat/conversations/$conversationId');

  Future<AirmiusJson> conversationMessages(int conversationId, {int page = 1}) => _json('GET', '/api/v1/chat/conversations/$conversationId/messages', query: {'page': '$page'});

  Future<AirmiusJson> sendConversationMessage(int conversationId, String message) => _json('POST', '/api/v1/chat/conversations/$conversationId/messages', body: {'message': message});

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
      return await _deletePostRequest('DELETE', '/api/v1/posts/$postId');
    } on AirmiusApiException catch (error) {
      if (error.statusCode != 404 && error.statusCode != 405) rethrow;
    }

    return _deletePostRequest('POST', '/api/v1/posts/$postId/delete');
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

  Future<AirmiusJson> events({int page = 1}) => _json('GET', '/api/v1/events', query: {'page': '$page'});

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

  @override
  String toString() => 'AirmiusApiException($statusCode, $path): $body';
}
