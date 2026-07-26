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
  Future<AirmiusUser> login({
    required String email,
    required String password,
  }) async {
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
    if (data.containsKey('id') ||
        data.containsKey('name') ||
        data.containsKey('email')) {
      return data;
    }
  }

  return json;
}

class AirmiusApiClubRepository implements AirmiusClubRepository {
  const AirmiusApiClubRepository(this.client);

  final AirmiusApiClient client;

  @override
  Future<AirmiusPage<AirmiusClub>> searchClubs({
    String? query,
    int page = 1,
    bool mine = false,
  }) async {
    final json = await client.clubs(query: query, mine: mine);
    return AirmiusPage<AirmiusClub>.fromJson(
      _paged(json, page),
      AirmiusClub.fromJson,
    );
  }

  @override
  Future<AirmiusClub> club(int id) async {
    final json = await client.clubDetail(id);
    final data = json['data'];
    return AirmiusClub.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<List<AirmiusClubSurvey>> surveys(int clubId) async {
    final json = await client.clubSurveys(clubId);
    final data = json['data'];
    return data is List
        ? data.whereType<JsonMap>().map(AirmiusClubSurvey.fromJson).toList()
        : const [];
  }

  @override
  Future<AirmiusClubSurvey> createSurvey(int clubId, JsonMap payload) async {
    final json = await client.createClubSurvey(clubId, payload);
    final data = json['data'];
    return AirmiusClubSurvey.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<JsonMap> voteSurvey(int clubId, int surveyId, int optionId) async {
    final json = await client.voteClubSurvey(clubId, surveyId, optionId);
    final data = json['data'];
    return data is JsonMap ? data : json;
  }

  @override
  Future<void> closeSurvey(int clubId, int surveyId) async {
    await client.closeClubSurvey(clubId, surveyId);
  }

  @override
  Future<List<AirmiusClubAnnouncement>> announcements(int clubId) async {
    final json = await client.clubAnnouncements(clubId);
    final data = json['data'];
    return data is List
        ? data
              .whereType<JsonMap>()
              .map(AirmiusClubAnnouncement.fromJson)
              .toList()
        : const [];
  }

  @override
  Future<AirmiusClubAnnouncement> createAnnouncement(
    int clubId,
    JsonMap payload,
  ) async {
    final json = await client.createClubAnnouncement(clubId, payload);
    final data = json['data'];
    return AirmiusClubAnnouncement.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<void> acknowledgeAnnouncement(int clubId, int announcementId) async {
    await client.acknowledgeClubAnnouncement(clubId, announcementId);
  }

  @override
  Future<AirmiusClub> createClub(JsonMap payload) async {
    final json = await client.createClub(payload);
    final data = json['data'];
    return AirmiusClub.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<AirmiusClub> updateClub(int id, JsonMap payload) async {
    final json = await client.updateClub(id, payload);
    final data = json['data'];
    return AirmiusClub.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<void> deleteClub(int id) async {
    await client.deleteClub(id);
  }

  AirmiusClubManagement _managementFromJson(JsonMap json) {
    final data = json['data'];
    return AirmiusClubManagement.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<AirmiusClubManagement> updateMembershipSettings(
    int clubId,
    JsonMap payload,
  ) async {
    return _managementFromJson(
      await client.updateClubMembershipSettings(clubId, payload),
    );
  }

  @override
  Future<AirmiusClubManagement> createMembershipType(
    int clubId,
    JsonMap payload,
  ) async {
    return _managementFromJson(
      await client.createClubMembershipType(clubId, payload),
    );
  }

  @override
  Future<AirmiusClubManagement> updateMembershipType(
    int clubId,
    int typeId,
    JsonMap payload,
  ) async {
    return _managementFromJson(
      await client.updateClubMembershipType(clubId, typeId, payload),
    );
  }

  @override
  Future<AirmiusClubManagement> createContributionRule(
    int clubId,
    JsonMap payload,
  ) async {
    return _managementFromJson(
      await client.createClubContributionRule(clubId, payload),
    );
  }

  @override
  Future<AirmiusClubManagement> updateContributionRule(
    int clubId,
    int ruleId,
    JsonMap payload,
  ) async {
    return _managementFromJson(
      await client.updateClubContributionRule(clubId, ruleId, payload),
    );
  }

  @override
  Future<AirmiusClubManagement> recordMembershipPayment(
    int clubId,
    int invoiceId,
    JsonMap payload,
  ) async {
    return _managementFromJson(
      await client.recordClubMembershipPayment(clubId, invoiceId, payload),
    );
  }

  @override
  Future<AirmiusClubManagement> recordDonation(
    int clubId,
    JsonMap payload,
  ) async {
    return _managementFromJson(
      await client.recordClubDonation(clubId, payload),
    );
  }

  @override
  Future<AirmiusClubManagement> recordPrepayment(
    int clubId,
    JsonMap payload,
  ) async {
    return _managementFromJson(
      await client.recordClubPrepayment(clubId, payload),
    );
  }

  @override
  Future<AirmiusClubManagement> updatePayment(
    int clubId,
    int paymentId,
    JsonMap payload,
  ) async {
    return _managementFromJson(
      await client.updateClubPayment(clubId, paymentId, payload),
    );
  }

  @override
  Future<AirmiusClubManagement> createFinanceEntry(
    int clubId,
    JsonMap payload,
  ) async {
    return _managementFromJson(
      await client.createClubFinanceEntry(clubId, payload),
    );
  }

  @override
  Future<AirmiusClubManagement> updateFinanceEntry(
    int clubId,
    int entryId,
    JsonMap payload,
  ) async {
    return _managementFromJson(
      await client.updateClubFinanceEntry(clubId, entryId, payload),
    );
  }

  @override
  Future<AirmiusClubManagement> inviteClubMember(
    int clubId,
    JsonMap payload,
  ) async {
    return _managementFromJson(await client.inviteClubMember(clubId, payload));
  }

  @override
  Future<AirmiusClubManagement> updateClubExternalMember(
    int clubId,
    int externalMemberId,
    JsonMap payload,
  ) async {
    return _managementFromJson(
      await client.updateClubExternalMember(clubId, externalMemberId, payload),
    );
  }

  @override
  Future<AirmiusClubManagement> inviteClubExternalMember(
    int clubId,
    int externalMemberId,
  ) async {
    return _managementFromJson(
      await client.inviteClubExternalMember(clubId, externalMemberId),
    );
  }

  @override
  Future<AirmiusClubManagement> removeClubExternalMember(
    int clubId,
    int externalMemberId,
  ) async {
    return _managementFromJson(
      await client.removeClubExternalMember(clubId, externalMemberId),
    );
  }

  @override
  Future<JsonMap> clubExternalInvitationByToken(String token) async {
    final json = await client.clubExternalInvitationByToken(token);
    final data = json['data'];
    return data is JsonMap ? data : json;
  }

  @override
  Future<JsonMap> acceptClubExternalInvitation(String token) async {
    return await client.acceptClubExternalInvitation(token);
  }

  @override
  Future<JsonMap> declineClubExternalInvitation(String token) async {
    final json = await client.declineClubExternalInvitation(token);
    final data = json['data'];
    return data is JsonMap ? data : json;
  }

  @override
  Future<AirmiusClubManagement> updateClubMemberRole(
    int clubId,
    int userId,
    String role,
  ) async {
    final json = await client.updateClubMemberRole(clubId, userId, role);
    return _managementFromJson(json);
  }

  @override
  Future<AirmiusClubManagement> updateClubMember(
    int clubId,
    int userId,
    JsonMap payload,
  ) async {
    return _managementFromJson(
      await client.updateClubMember(clubId, userId, payload),
    );
  }

  @override
  Future<JsonMap> clubMemberPermissions(int clubId, int userId) async {
    final json = await client.clubMemberPermissions(clubId, userId);
    final data = json['data'];
    return data is JsonMap ? data : json;
  }

  @override
  Future<JsonMap> updateClubMemberPermissions(
    int clubId,
    int userId,
    JsonMap payload,
  ) async {
    final json = await client.updateClubMemberPermissions(
      clubId,
      userId,
      payload,
    );
    final data = json['data'];
    return data is JsonMap ? data : json;
  }

  @override
  Future<AirmiusClubManagement> removeClubMember(int clubId, int userId) async {
    return _managementFromJson(await client.removeClubMember(clubId, userId));
  }

  @override
  Future<AirmiusClubManagement> generateClubMemberNumber(
    int clubId,
    int userId,
  ) async {
    return _managementFromJson(
      await client.generateClubMemberNumber(clubId, userId),
    );
  }

  @override
  Future<AirmiusClubManagement> createClubMemberInvoice(
    int clubId,
    int userId,
    JsonMap payload,
  ) async {
    return _managementFromJson(
      await client.createClubMemberInvoice(clubId, userId, payload),
    );
  }

  @override
  Future<AirmiusClubManagement> updateClubInvoiceStatus(
    int clubId,
    int invoiceId,
    String status,
  ) async {
    return _managementFromJson(
      await client.updateClubInvoiceStatus(clubId, invoiceId, status),
    );
  }

  @override
  Future<AirmiusClubManagement> sendClubInvoiceReminder(
    int clubId,
    int invoiceId,
  ) async {
    return _managementFromJson(
      await client.sendClubInvoiceReminder(clubId, invoiceId),
    );
  }

  @override
  Future<AirmiusClubManagement> updateClubSepaSettings(
    int clubId,
    JsonMap payload,
  ) async {
    return _managementFromJson(
      await client.updateClubSepaSettings(clubId, payload),
    );
  }

  @override
  Future<AirmiusClubManagement> updateClubDatevSettings(
    int clubId,
    JsonMap payload,
  ) async {
    return _managementFromJson(
      await client.updateClubDatevSettings(clubId, payload),
    );
  }

  @override
  Future<AirmiusClubManagement> confirmClubBankTransaction(
    int clubId,
    int transactionId,
  ) async {
    return _managementFromJson(
      await client.confirmClubBankTransaction(clubId, transactionId),
    );
  }

  @override
  Future<AirmiusPage<AirmiusTeam>> teams({int page = 1}) async {
    final json = await client.teams(page: page);
    return AirmiusPage<AirmiusTeam>.fromJson(
      _paged(json, page),
      AirmiusTeam.fromJson,
    );
  }

  @override
  Future<AirmiusTeam> team(int id) async {
    final json = await client.teamDetail(id);
    final data = json['data'];
    return AirmiusTeam.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<AirmiusTeam> createTeam(JsonMap payload) async {
    final json = await client.createTeam(payload);
    final data = json['data'];
    return AirmiusTeam.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<AirmiusTeam> updateTeam(int id, JsonMap payload) async {
    final json = await client.updateTeam(id, payload);
    final data = json['data'];
    return AirmiusTeam.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<void> deleteTeam(int id) async {
    await client.deleteTeam(id);
  }

  @override
  Future<AirmiusTeam> requestTeamJoin(int id) async {
    final json = await client.requestTeamJoin(id);
    final data = json['data'];
    return AirmiusTeam.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<AirmiusTeam> approveTeamJoinRequest(
    int teamId,
    int requestId, {
    String role = 'Player',
  }) async {
    final json = await client.approveTeamJoinRequest(
      teamId,
      requestId,
      role: role,
    );
    final data = json['data'];
    return AirmiusTeam.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<AirmiusTeam> declineTeamJoinRequest(int teamId, int requestId) async {
    final json = await client.declineTeamJoinRequest(teamId, requestId);
    final data = json['data'];
    return AirmiusTeam.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<AirmiusTeamInvitation> inviteTeamMember(
    int teamId, {
    required String email,
    required String role,
  }) async {
    final json = await client.inviteTeamMember(
      teamId,
      email: email,
      role: role,
    );
    final data = json['data'];
    return AirmiusTeamInvitation.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<List<AirmiusTeamInvitation>> teamInvitations() async {
    final json = await client.teamInvitations();
    final data = json['data'];
    final items = data is List ? data : const [];
    return items
        .whereType<JsonMap>()
        .map(AirmiusTeamInvitation.fromJson)
        .toList();
  }

  @override
  Future<AirmiusTeamInvitation> teamInvitation(int invitationId) async {
    final json = await client.teamInvitation(invitationId);
    final data = json['data'];
    return AirmiusTeamInvitation.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<AirmiusTeamInvitation> teamInvitationByToken(String token) async {
    final json = await client.teamInvitationByToken(token);
    final data = json['data'];
    return AirmiusTeamInvitation.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<AirmiusTeam> acceptTeamInvitation(int invitationId) async {
    final json = await client.acceptTeamInvitation(invitationId);
    final data = json['data'];
    return AirmiusTeam.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<AirmiusTeam> acceptTeamInvitationByToken(String token) async {
    final json = await client.acceptTeamInvitationByToken(token);
    final data = json['data'];
    return AirmiusTeam.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<AirmiusTeamInvitation> declineTeamInvitation(int invitationId) async {
    final json = await client.declineTeamInvitation(invitationId);
    final data = json['data'];
    return AirmiusTeamInvitation.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<AirmiusTeamInvitation> declineTeamInvitationByToken(
    String token,
  ) async {
    final json = await client.declineTeamInvitationByToken(token);
    final data = json['data'];
    return AirmiusTeamInvitation.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<AirmiusTeam> updateTeamMemberRole(
    int teamId,
    int userId,
    String role,
  ) async {
    final json = await client.updateTeamMemberRole(teamId, userId, role);
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
      return AirmiusPage<AirmiusSport>.fromJson(
        _paged(json, page),
        AirmiusSport.fromJson,
      );
    } on AirmiusApiException {
      return AirmiusPage<AirmiusSport>(
        items: const [],
        currentPage: page,
        lastPage: page,
      );
    }
  }
}

class AirmiusApiMembershipRepository implements AirmiusMembershipRepository {
  const AirmiusApiMembershipRepository(this.client);

  final AirmiusApiClient client;

  @override
  Future<AirmiusClubMembershipRequest> applyToClub(
    int clubId,
    JsonMap payload,
  ) async {
    final json = await client.createClubMembershipRequest(clubId, payload);
    final data = json['data'];
    return AirmiusClubMembershipRequest.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<AirmiusMembershipApplication> application(int applicationId) async {
    final json = await client.membershipApplication(applicationId);
    final data = json['data'];
    return AirmiusMembershipApplication.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<AirmiusMembershipApplication> withdraw(int applicationId) async {
    final json = await client.withdrawMembershipApplication(applicationId);
    final data = json['data'];
    return AirmiusMembershipApplication.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<AirmiusPage<AirmiusClubMembershipRequest>> clubRequests(
    int clubId, {
    int page = 1,
  }) async {
    final json = await client.clubMembershipRequests(clubId, page: page);
    return AirmiusPage<AirmiusClubMembershipRequest>.fromJson(
      _paged(json, page),
      AirmiusClubMembershipRequest.fromJson,
    );
  }

  @override
  Future<AirmiusClubMembershipRequest> withdrawClubRequest(int clubId) async {
    final json = await client.withdrawClubMembershipRequest(clubId);
    final data = json['data'];
    return AirmiusClubMembershipRequest.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<AirmiusClubMembershipRequest> approveClubRequest(
    int clubId,
    int requestId, {
    String? reviewNote,
  }) async {
    final json = await client.approveClubMembershipRequest(
      clubId,
      requestId,
      reviewNote: reviewNote,
    );
    final data = json['data'];
    return AirmiusClubMembershipRequest.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<AirmiusClubMembershipRequest> declineClubRequest(
    int clubId,
    int requestId, {
    String? reviewNote,
  }) async {
    final json = await client.declineClubMembershipRequest(
      clubId,
      requestId,
      reviewNote: reviewNote,
    );
    final data = json['data'];
    return AirmiusClubMembershipRequest.fromJson(data is JsonMap ? data : json);
  }
}

class AirmiusApiFileRepository implements AirmiusFileRepository {
  const AirmiusApiFileRepository(this.client);

  final AirmiusApiClient client;

  @override
  Future<JsonMap> createUploadIntent({
    required String scope,
    required String fileName,
    required String mimeType,
  }) {
    return client.uploadIntent(
      scope: scope,
      fileName: fileName,
      mimeType: mimeType,
    );
  }

  @override
  Future<AirmiusFileWorkspace> workspace({
    String scope = 'user',
    int? folderId,
    int? clubId,
    int? teamId,
    int? eventId,
    String? search,
    String sort = 'name-asc',
    int page = 1,
  }) async {
    return AirmiusFileWorkspace.fromJson(
      await client.fileWorkspace(
        scope: scope,
        folderId: folderId,
        clubId: clubId,
        teamId: teamId,
        eventId: eventId,
        search: search,
        sort: sort,
        page: page,
      ),
    );
  }

  @override
  Future<AirmiusFolder> createFolder({
    required String scope,
    required String name,
    int? parentId,
    int? clubId,
    int? teamId,
    int? eventId,
  }) async {
    final json = await client.createFolder(
      scope: scope,
      name: name,
      parentId: parentId,
      clubId: clubId,
      teamId: teamId,
      eventId: eventId,
    );
    final data = json['data'];
    return AirmiusFolder.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<AirmiusFolder> renameFolder(int folderId, String name) async {
    final json = await client.renameFolder(folderId, name);
    final data = json['data'];
    return AirmiusFolder.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<void> deleteFolder(int folderId) async {
    await client.deleteFolder(folderId);
  }

  @override
  Future<JsonMap> shareFolder(int folderId, int targetUserId) async {
    final json = await client.shareFolder(folderId, targetUserId);
    final data = json['data'];
    return data is JsonMap ? data : json;
  }

  @override
  Future<AirmiusManagedFile> renameFile(int fileId, String name) async {
    final json = await client.renameFile(fileId, name);
    final data = json['data'];
    return AirmiusManagedFile.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<void> deleteFile(int fileId) async {
    await client.deleteFile(fileId);
  }

  @override
  Future<JsonMap> createFileShare(int fileId, {int expiresInDays = 14}) async {
    final json = await client.createFileShare(
      fileId,
      expiresInDays: expiresInDays,
    );
    final data = json['data'];
    return data is JsonMap ? data : json;
  }
}

class AirmiusApiEventRepository implements AirmiusEventRepository {
  const AirmiusApiEventRepository(this.client);

  final AirmiusApiClient client;

  @override
  Future<AirmiusPage<AirmiusEvent>> events({
    int page = 1,
    DateTime? from,
    DateTime? to,
  }) async {
    final json = await client.events(page: page, from: from, to: to);
    return AirmiusPage<AirmiusEvent>.fromJson(
      _paged(json, page),
      AirmiusEvent.fromJson,
    );
  }

  @override
  Future<AirmiusEventWorkspace> workspace({
    int page = 1,
    String? search,
    String? type,
    String? visibility,
    int? clubId,
    int? teamId,
    String? period,
    String? calendarMonth,
  }) async {
    final json = await client.events(
      page: page,
      search: search,
      type: type,
      visibility: visibility,
      clubId: clubId,
      teamId: teamId,
      period: period,
      calendarMonth: calendarMonth,
    );
    return AirmiusEventWorkspace.fromJson(json);
  }

  @override
  Future<AirmiusEvent> create(JsonMap payload) async {
    final json = await client.createEvent(payload);
    final data = json['data'];
    return AirmiusEvent.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<AirmiusEvent> event(int eventId) async {
    final json = await client.event(eventId);
    final data = json['data'];
    return AirmiusEvent.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<AirmiusPage<AirmiusEventComment>> comments(
    int eventId, {
    int page = 1,
  }) async {
    final json = await client.eventComments(eventId, page: page);
    return AirmiusPage<AirmiusEventComment>.fromJson(
      _paged(json, page),
      AirmiusEventComment.fromJson,
    );
  }

  @override
  Future<AirmiusEventComment> createComment(int eventId, String content) async {
    final json = await client.createEventComment(eventId, content);
    final data = json['data'];
    return AirmiusEventComment.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<AirmiusEvent> update(int eventId, JsonMap payload) async {
    final json = await client.updateEvent(eventId, payload);
    final data = json['data'];
    return AirmiusEvent.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<AirmiusEvent> cancel(int eventId, {String? reason}) async {
    final json = await client.cancelEvent(eventId, reason: reason);
    final data = json['data'];
    return AirmiusEvent.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<void> delete(int eventId) async {
    await client.deleteEvent(eventId);
  }

  @override
  Future<List<AirmiusEventAttendanceMember>> attendance(int eventId) async {
    final json = await client.eventAttendance(eventId);
    final data = json['data'];
    return data is List
        ? data
              .whereType<JsonMap>()
              .map(AirmiusEventAttendanceMember.fromJson)
              .toList()
        : const [];
  }

  @override
  Future<AirmiusEvent> recordAttendance(
    int eventId,
    List<JsonMap> attendance,
  ) async {
    final json = await client.recordEventAttendance(eventId, attendance);
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

  @override
  Future<List<AirmiusEventDecision>> decisions(int eventId) async {
    final json = await client.eventDecisions(eventId);
    final data = json['data'];
    return data is List
        ? data.whereType<JsonMap>().map(AirmiusEventDecision.fromJson).toList()
        : const [];
  }

  @override
  Future<AirmiusEventDecision> createDecision(
    int eventId,
    JsonMap payload,
  ) async {
    final json = await client.createEventDecision(eventId, payload);
    final data = json['data'];
    return AirmiusEventDecision.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<JsonMap> castDecisionVote(
    int eventId,
    int decisionId,
    int optionId,
  ) async {
    final json = await client.castEventDecisionVote(
      eventId,
      decisionId,
      optionId,
    );
    final data = json['data'];
    return data is JsonMap ? data : json;
  }

  @override
  Future<void> closeDecision(int eventId, int decisionId) async {
    await client.closeEventDecision(eventId, decisionId);
  }
}

class AirmiusApiBillingRepository implements AirmiusBillingRepository {
  const AirmiusApiBillingRepository(this.client);

  final AirmiusApiClient client;

  @override
  Future<AirmiusPage<AirmiusInvoice>> invoices({int page = 1}) async {
    final json = await client.invoices();
    return AirmiusPage<AirmiusInvoice>.fromJson(
      _paged(json, page),
      AirmiusInvoice.fromJson,
    );
  }

  @override
  Future<AirmiusInvoice> invoice(int invoiceId) async {
    final json = await client.invoice(invoiceId);
    final data = json['data'];
    return AirmiusInvoice.fromJson(data is JsonMap ? data : json);
  }
}

class AirmiusApiNotificationRepository
    implements AirmiusNotificationRepository {
  const AirmiusApiNotificationRepository(this.client);

  final AirmiusApiClient client;

  @override
  Future<AirmiusPage<AirmiusNotification>> notifications({int page = 1}) async {
    final json = await client.notifications(page: page);
    return AirmiusPage<AirmiusNotification>.fromJson(
      _paged(json, page),
      AirmiusNotification.fromJson,
    );
  }

  @override
  Future<AirmiusNotification> notification(int notificationId) async {
    final json = await client.notification(notificationId);
    final data = json['data'];
    return AirmiusNotification.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<AirmiusNotification> markAsRead(int notificationId) async {
    final json = await client.markNotificationAsRead(notificationId);
    final data = json['data'];
    return AirmiusNotification.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<AirmiusNotification> markAsUnread(int notificationId) async {
    final json = await client.markNotificationAsUnread(notificationId);
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

class AirmiusApiConversationRepository
    implements AirmiusConversationRepository {
  const AirmiusApiConversationRepository(this.client);

  final AirmiusApiClient client;

  @override
  Future<AirmiusPage<AirmiusConversation>> conversations({
    int page = 1,
    int? teamId,
  }) async {
    final json = await client.conversations(page: page, teamId: teamId);
    return AirmiusPage<AirmiusConversation>.fromJson(
      _paged(json, page),
      AirmiusConversation.fromJson,
    );
  }

  @override
  Future<AirmiusConversation> createConversation({
    required String type,
    List<int> participantIds = const [],
    int? teamId,
    String? name,
    String? description,
    String? message,
  }) async {
    final json = await client.createConversation(
      type: type,
      participantIds: participantIds,
      teamId: teamId,
      name: name,
      description: description,
      message: message,
    );
    final data = json['data'];
    return AirmiusConversation.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<AirmiusConversation> conversation(int conversationId) async =>
      _conversationFromJson(await client.conversation(conversationId));

  @override
  Future<AirmiusMessage> message(int messageId) async {
    final json = await client.message(messageId);
    final data = json['data'];
    return AirmiusMessage.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<AirmiusPage<AirmiusMessage>> messages(
    int conversationId, {
    int page = 1,
  }) async {
    final json = await client.conversationMessages(conversationId, page: page);
    return AirmiusPage<AirmiusMessage>.fromJson(
      _paged(json, page),
      AirmiusMessage.fromJson,
    );
  }

  @override
  Future<AirmiusMessage> sendMessage(int conversationId, String message) async {
    final json = await client.sendConversationMessage(conversationId, message);
    final data = json['data'];
    return AirmiusMessage.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<void> markRead(int conversationId) async {
    await client.markConversationRead(conversationId);
  }

  @override
  Future<void> sendTyping(int conversationId, bool typing) async {
    await client.sendConversationTyping(conversationId, typing);
  }

  AirmiusConversation _conversationFromJson(JsonMap json) {
    final data = json['data'];
    return AirmiusConversation.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<AirmiusConversation> updateConversation(
    int conversationId,
    JsonMap payload,
  ) async {
    return _conversationFromJson(
      await client.updateConversation(conversationId, payload),
    );
  }

  @override
  Future<AirmiusConversation> muteConversation(
    int conversationId,
    int minutes,
  ) async {
    return _conversationFromJson(
      await client.muteConversation(conversationId, minutes),
    );
  }

  @override
  Future<void> leaveConversation(int conversationId) async {
    await client.leaveConversation(conversationId);
  }

  @override
  Future<AirmiusConversation> inviteConversationMembers(
    int conversationId,
    List<int> participantIds,
  ) async {
    return _conversationFromJson(
      await client.inviteConversationMembers(conversationId, participantIds),
    );
  }

  @override
  Future<AirmiusConversation> removeConversationMember(
    int conversationId,
    int userId,
  ) async {
    return _conversationFromJson(
      await client.removeConversationMember(conversationId, userId),
    );
  }

  @override
  Future<AirmiusConversation> transferConversationOwner(
    int conversationId,
    int userId,
  ) async {
    return _conversationFromJson(
      await client.transferConversationOwner(conversationId, userId),
    );
  }
}

class AirmiusApiFeedRepository implements AirmiusFeedRepository {
  const AirmiusApiFeedRepository(this.client);

  final AirmiusApiClient client;

  @override
  Future<AirmiusPage<AirmiusPost>> feed({int page = 1}) async {
    final json = await client.feed(page: page);
    return AirmiusPage<AirmiusPost>.fromJson(
      _paged(json, page),
      AirmiusPost.fromJson,
    );
  }

  @override
  Future<AirmiusPost> post(int postId) async {
    final json = await client.feedPost(postId);
    final data = json['data'];
    return AirmiusPost.fromJson(data is JsonMap ? data : json);
  }

  @override
  Future<AirmiusPost> create({
    required String content,
    required String visibility,
  }) async {
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
  Future<void> reportContent({
    required String type,
    required int id,
    String reason = 'other',
    String? details,
  }) async {
    await client.reportContent(
      type: type,
      id: id,
      reason: reason,
      details: details,
    );
  }

  @override
  Future<AirmiusPage<AirmiusComment>> comments(
    int postId, {
    int page = 1,
    int perPage = 20,
  }) async {
    final json = await client.postComments(
      postId,
      page: page,
      perPage: perPage,
    );
    return AirmiusPage<AirmiusComment>.fromJson(
      _paged(json, page),
      AirmiusComment.fromJson,
    );
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
    return raw is List
        ? raw.whereType<JsonMap>().map(AirmiusStory.fromJson).toList()
        : const [];
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
  Future<AirmiusPage<AirmiusSearchResult>> search({
    required String query,
    int page = 1,
  }) async {
    final json = await client.search(query);
    return AirmiusPage<AirmiusSearchResult>.fromJson(
      _paged(json, page),
      AirmiusSearchResult.fromJson,
    );
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

  factory AirmiusRepositoryBundle.api(AirmiusApiClient client) =>
      AirmiusRepositoryBundle(
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
  final data =
      json['items'] ??
      json['results'] ??
      json['search_results'] ??
      json['clubs'] ??
      json['sports'] ??
      json['events'] ??
      json['invoices'] ??
      json['notifications'] ??
      json['conversations'] ??
      json['feed'] ??
      json['posts'];
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
