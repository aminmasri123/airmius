import 'airmius_api_client.dart';
import 'airmius_api_models.dart';

class AirmiusApiAuthRepository implements AirmiusAuthRepository {
  const AirmiusApiAuthRepository(this.client);

  final AirmiusApiClient client;

  @override
  Future<AirmiusUser> currentUser() async {
    final payload = await client.me();
    final user = _extractUserObject(payload);
    return AirmiusUser.fromJson(user);
  }

  @override
  Future<AirmiusUser> login({required String email, required String password}) async {
    final json = await client.login(email: email, password: password);
    final user = _extractUserObject(json);
    return AirmiusUser.fromJson(user);
  }
}

JsonMap _extractUserObject(JsonMap json) {
  final directUser = json['user'];
  if (directUser is JsonMap) return directUser;

  final data = json['data'];
  if (data is JsonMap) {
    final nestedUser = data['user'];
    if (nestedUser is JsonMap) {
      return nestedUser;
    }
    if (data.containsKey('id') || data.containsKey('name') || data.containsKey('email')) {
      return data;
    }
  }

  return json;
}

class AirmiusApiClubRepository implements AirmiusClubRepository {
  const AirmiusApiClubRepository(this.client);

  final AirmiusApiClient client;

  @override
  Future<AirmiusPage<AirmiusClub>> searchClubs({String? query, int page = 1}) async {
    final json = await client.clubs(query: query);
    return AirmiusPage<AirmiusClub>.fromJson(_paged(json, page), AirmiusClub.fromJson);
  }

  @override
  Future<AirmiusClub> club(int id) async {
    final json = await client.clubDetail(id);
    final data = json['data'];
    return AirmiusClub.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<AirmiusPage<AirmiusTeam>> teams({int page = 1}) async {
    final json = await client.teams(page: page);
    return AirmiusPage<AirmiusTeam>.fromJson(_paged(json, page), AirmiusTeam.fromJson);
  }

  @override
  Future<AirmiusTeam> team(int id) async {
    final json = await client.teamDetail(id);
    final data = json['data'];
    return AirmiusTeam.fromJson(data is JsonMap ? data : json);
  }
}

class AirmiusApiSportRepository implements AirmiusSportRepository {
  const AirmiusApiSportRepository(this.client);

  final AirmiusApiClient client;

  @override
  Future<AirmiusPage<AirmiusSport>> sports({int page = 1}) async {
    try {
      final json = await client.sports();
      return AirmiusPage<AirmiusSport>.fromJson(_paged(json, page), AirmiusSport.fromJson);
    } on AirmiusApiException {
      return AirmiusPage<AirmiusSport>(items: const [], currentPage: page, lastPage: page);
    }
  }
}

class AirmiusApiMembershipRepository implements AirmiusMembershipRepository {
  const AirmiusApiMembershipRepository(this.client);

  final AirmiusApiClient client;

  @override
  Future<AirmiusClubMembershipRequest> applyToClub(int clubId, JsonMap payload) async {
    final json = await client.createClubMembershipRequest(clubId, payload);
    final data = json['data'];
    return AirmiusClubMembershipRequest.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<AirmiusMembershipApplication> application(int applicationId) async {
    return AirmiusMembershipApplication.fromJson(await client.membershipApplication(applicationId));
  }

  @override
  Future<AirmiusMembershipApplication> withdraw(int applicationId) async {
    return AirmiusMembershipApplication.fromJson(await client.withdrawMembershipApplication(applicationId));
  }

  @override
  Future<AirmiusPage<AirmiusClubMembershipRequest>> clubRequests(int clubId, {int page = 1}) async {
    final json = await client.clubMembershipRequests(clubId, page: page);
    return AirmiusPage<AirmiusClubMembershipRequest>.fromJson(_paged(json, page), AirmiusClubMembershipRequest.fromJson);
  }

  @override
  Future<AirmiusClubMembershipRequest> withdrawClubRequest(int clubId) async {
    final json = await client.withdrawClubMembershipRequest(clubId);
    final data = json['data'];
    return AirmiusClubMembershipRequest.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<AirmiusClubMembershipRequest> approveClubRequest(int clubId, int requestId, {String? reviewNote}) async {
    final json = await client.approveClubMembershipRequest(clubId, requestId, reviewNote: reviewNote);
    final data = json['data'];
    return AirmiusClubMembershipRequest.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<AirmiusClubMembershipRequest> declineClubRequest(int clubId, int requestId, {String? reviewNote}) async {
    final json = await client.declineClubMembershipRequest(clubId, requestId, reviewNote: reviewNote);
    final data = json['data'];
    return AirmiusClubMembershipRequest.fromJson(data is JsonMap ? data : json);
  }
}

class AirmiusApiFileRepository implements AirmiusFileRepository {
  const AirmiusApiFileRepository(this.client);

  final AirmiusApiClient client;

  @override
  Future<JsonMap> createUploadIntent({required String scope, required String fileName, required String mimeType}) {
    return client.uploadIntent(scope: scope, fileName: fileName, mimeType: mimeType);
  }
}

class AirmiusApiEventRepository implements AirmiusEventRepository {
  const AirmiusApiEventRepository(this.client);

  final AirmiusApiClient client;

  @override
  Future<AirmiusPage<AirmiusEvent>> events({int page = 1}) async {
    final json = await client.events(page: page);
    return AirmiusPage<AirmiusEvent>.fromJson(_paged(json, page), AirmiusEvent.fromJson);
  }

  @override
  Future<AirmiusEvent> event(int eventId) async {
    final json = await client.event(eventId);
    final data = json['data'];
    return AirmiusEvent.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<AirmiusEvent> respond(int eventId, String status) async {
    final json = await client.respondToEvent(eventId, status);
    final data = json['data'];
    return AirmiusEvent.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<AirmiusEvent> leave(int eventId) async {
    final json = await client.leaveEvent(eventId);
    final data = json['data'];
    return AirmiusEvent.fromJson(data is JsonMap ? data : json);
  }
}

class AirmiusApiBillingRepository implements AirmiusBillingRepository {
  const AirmiusApiBillingRepository(this.client);

  final AirmiusApiClient client;

  @override
  Future<AirmiusPage<AirmiusInvoice>> invoices({int page = 1}) async {
    final json = await client.invoices();
    return AirmiusPage<AirmiusInvoice>.fromJson(_paged(json, page), AirmiusInvoice.fromJson);
  }

  @override
  Future<AirmiusInvoice> invoice(int invoiceId) async => AirmiusInvoice.fromJson(await client.invoice(invoiceId));
}

class AirmiusApiNotificationRepository implements AirmiusNotificationRepository {
  const AirmiusApiNotificationRepository(this.client);

  final AirmiusApiClient client;

  @override
  Future<AirmiusPage<AirmiusNotification>> notifications({int page = 1}) async {
    final json = await client.notifications();
    return AirmiusPage<AirmiusNotification>.fromJson(_paged(json, page), AirmiusNotification.fromJson);
  }

  @override
  Future<AirmiusNotification> notification(int notificationId) async => AirmiusNotification.fromJson(await client.notification(notificationId));

  @override
  Future<AirmiusNotification> markAsRead(int notificationId) async {
    final json = await client.markNotificationAsRead(notificationId);
    final data = json['data'];
    return AirmiusNotification.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<void> markAllAsRead() async {
    await client.markAllNotificationsAsRead();
  }

  @override
  Future<void> delete(int notificationId) async {
    await client.deleteNotification(notificationId);
  }
}

class AirmiusApiConversationRepository implements AirmiusConversationRepository {
  const AirmiusApiConversationRepository(this.client);

  final AirmiusApiClient client;

  @override
  Future<AirmiusPage<AirmiusConversation>> conversations({int page = 1}) async {
    final json = await client.conversations();
    return AirmiusPage<AirmiusConversation>.fromJson(_paged(json, page), AirmiusConversation.fromJson);
  }

  @override
  Future<AirmiusConversation> conversation(int conversationId) async => AirmiusConversation.fromJson(await client.conversation(conversationId));

  @override
  Future<AirmiusPage<AirmiusMessage>> messages(int conversationId, {int page = 1}) async {
    final json = await client.conversationMessages(conversationId, page: page);
    return AirmiusPage<AirmiusMessage>.fromJson(_paged(json, page), AirmiusMessage.fromJson);
  }

  @override
  Future<AirmiusMessage> sendMessage(int conversationId, String message) async {
    final json = await client.sendConversationMessage(conversationId, message);
    final data = json['data'];
    return AirmiusMessage.fromJson(data is JsonMap ? data : json);
  }
}

class AirmiusApiFeedRepository implements AirmiusFeedRepository {
  const AirmiusApiFeedRepository(this.client);

  final AirmiusApiClient client;

  @override
  Future<AirmiusPage<AirmiusPost>> feed({int page = 1}) async {
    final json = await client.feed(page: page);
    return AirmiusPage<AirmiusPost>.fromJson(_paged(json, page), AirmiusPost.fromJson);
  }

  @override
  Future<AirmiusPost> create({required String content, required String visibility}) async {
    final json = await client.createFeedPost({
      'content': content,
      'visibility': visibility,
      'post_type': 'normal',
    });
    final data = json['data'];
    return AirmiusPost.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<AirmiusPost> toggleLike(int postId) async {
    final json = await client.togglePostLike(postId);
    final data = json['data'];
    return AirmiusPost.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<AirmiusPost> toggleHelpful(int postId) async {
    final json = await client.togglePostHelpful(postId);
    final data = json['data'];
    return AirmiusPost.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<AirmiusPost> updatePost(
    int postId, {
    required String content,
    required String visibility,
    required String postType,
    required String contentOrigin,
    int? clubId,
    int? teamId,
    int? sportId,
    List<int> sportSkillIds = const [],
  }) async {
    final json = await client.updatePost(postId, {
      'content': content,
      'visibility': visibility,
      'post_type': postType,
      'content_origin': contentOrigin,
      'club_id': clubId,
      'team_id': teamId,
      'sport_id': sportId,
      'sport_skill_ids': sportSkillIds,
    });
    final data = json['data'];
    return AirmiusPost.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<void> deletePost(int postId) async {
    await client.deletePost(postId);
  }

  @override
  Future<void> reportContent({required String type, required int id, String reason = 'other', String? details}) async {
    await client.reportContent(type: type, id: id, reason: reason, details: details);
  }

  @override
  Future<AirmiusPage<AirmiusComment>> comments(int postId, {int page = 1, int perPage = 20}) async {
    final json = await client.postComments(postId, page: page, perPage: perPage);
    return AirmiusPage<AirmiusComment>.fromJson(_paged(json, page), AirmiusComment.fromJson);
  }

  @override
  Future<AirmiusComment> createComment(int postId, String content) async {
    final json = await client.createPostComment(postId, content);
    final data = json['data'];
    return AirmiusComment.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<AirmiusComment> updateComment(int commentId, String content) async {
    final json = await client.updateComment(commentId, content);
    final data = json['data'];
    return AirmiusComment.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<void> deleteComment(int commentId) async {
    await client.deleteComment(commentId);
  }

  @override
  Future<List<AirmiusStory>> stories() async {
    final json = await client.stories();
    final raw = json['data'];
    return raw is List ? raw.whereType<JsonMap>().map(AirmiusStory.fromJson).toList() : const [];
  }

  @override
  Future<AirmiusStory> markStoryViewed(int storyId) async {
    final json = await client.markStoryViewed(storyId);
    final data = json['data'];
    return AirmiusStory.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<AirmiusStory> reactToStory(int storyId, String reaction) async {
    final json = await client.reactToStory(storyId, reaction);
    final data = json['data'];
    return AirmiusStory.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<void> deleteStory(int storyId) async {
    await client.deleteStory(storyId);
  }
}

class AirmiusApiSearchRepository implements AirmiusSearchRepository {
  const AirmiusApiSearchRepository(this.client);

  final AirmiusApiClient client;

  @override
  Future<AirmiusPage<AirmiusSearchResult>> search({required String query, int page = 1}) async {
    final json = await client.search(query);
    return AirmiusPage<AirmiusSearchResult>.fromJson(_paged(json, page), AirmiusSearchResult.fromJson);
  }
}

class AirmiusRepositoryBundle {
  const AirmiusRepositoryBundle({
    required this.auth,
    required this.clubs,
    required this.sports,
    required this.memberships,
    required this.files,
    required this.events,
    required this.billing,
    required this.notifications,
    required this.conversations,
    required this.feed,
    required this.search,
  });

  factory AirmiusRepositoryBundle.api(AirmiusApiClient client) => AirmiusRepositoryBundle(
        auth: AirmiusApiAuthRepository(client),
        clubs: AirmiusApiClubRepository(client),
        sports: AirmiusApiSportRepository(client),
        memberships: AirmiusApiMembershipRepository(client),
        files: AirmiusApiFileRepository(client),
        events: AirmiusApiEventRepository(client),
        billing: AirmiusApiBillingRepository(client),
        notifications: AirmiusApiNotificationRepository(client),
        conversations: AirmiusApiConversationRepository(client),
        feed: AirmiusApiFeedRepository(client),
        search: AirmiusApiSearchRepository(client),
      );

  final AirmiusAuthRepository auth;
  final AirmiusClubRepository clubs;
  final AirmiusSportRepository sports;
  final AirmiusMembershipRepository memberships;
  final AirmiusFileRepository files;
  final AirmiusEventRepository events;
  final AirmiusBillingRepository billing;
  final AirmiusNotificationRepository notifications;
  final AirmiusConversationRepository conversations;
  final AirmiusFeedRepository feed;
  final AirmiusSearchRepository search;
}

JsonMap _paged(JsonMap json, int page) {
  if (json['data'] is List) return json;
  final data = json['items'] ?? json['results'] ?? json['search_results'] ?? json['clubs'] ?? json['sports'] ?? json['events'] ?? json['invoices'] ?? json['notifications'] ?? json['conversations'] ?? json['feed'] ?? json['posts'];
  if (data is List) {
    return {
      'data': data,
      'current_page': json['current_page'] ?? page,
      'last_page': json['last_page'] ?? page,
    };
  }
  return {
    'data': [json],
    'current_page': page,
    'last_page': page,
  };
}
