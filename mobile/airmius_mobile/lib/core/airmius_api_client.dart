import 'dart:convert';

import 'airmius_club_member_invite_form_stub.dart'
    if (dart.library.html) 'airmius_club_member_invite_form_web.dart';
import 'airmius_team_create_form_stub.dart'
    if (dart.library.html) 'airmius_team_create_form_web.dart';

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

  Future<AirmiusJson> login({
    required String email,
    required String password,
    String deviceName = 'Airmius Mobile App',
  }) {
    return _json(
      'POST',
      '/api/v1/auth/login',
      body: {'email': email, 'password': password, 'device_name': deviceName},
    );
  }

  Future<AirmiusJson> completeTwoFactorChallenge({
    required String challengeToken,
    String? code,
    String? recoveryCode,
    String? emailCode,
  }) {
    return _json(
      'POST',
      '/api/v1/auth/two-factor-challenge',
      body: {
        'challenge_token': challengeToken,
        'code': ?code,
        'recovery_code': ?recoveryCode,
        'email_code': ?emailCode,
      },
    );
  }

  Future<AirmiusJson> requestTwoFactorEmailCode({
    required String challengeToken,
  }) {
    return _json(
      'POST',
      '/api/v1/auth/two-factor-challenge/email-code',
      body: {'challenge_token': challengeToken},
    );
  }

  Future<AirmiusJson> register(AirmiusJson payload) {
    return _json('POST', '/api/v1/auth/register', body: payload);
  }

  Future<AirmiusJson> registrationEmailStatus({required String email}) {
    return _json('GET', '/api/v1/auth/register/email', query: {'email': email});
  }

  Future<AirmiusJson> requestPasswordReset({required String email}) {
    return _json(
      'POST',
      '/api/v1/auth/forgot-password',
      body: {'email': email},
    );
  }

  Future<AirmiusJson> resetPassword({
    required String token,
    required String email,
    required String password,
    required String passwordConfirmation,
  }) {
    return _json(
      'POST',
      '/api/v1/auth/reset-password',
      body: {
        'token': token,
        'email': email,
        'password': password,
        'password_confirmation': passwordConfirmation,
      },
    );
  }

  Future<AirmiusJson> me() => _json('GET', '/api/v1/me');

  Future<AirmiusJson> resendEmailVerification() =>
      _json('POST', '/api/v1/me/email/verification-notification');

  Future<AirmiusJson> verifyEmailLink({
    required int userId,
    required String hash,
    required Map<String, String> query,
  }) => _json(
    'GET',
    '/api/v1/auth/verify-email/$userId/${Uri.encodeComponent(hash)}',
    query: query,
  );

  Future<AirmiusJson> twoFactorStatus() =>
      _json('GET', '/api/v1/me/two-factor-authentication');

  Future<AirmiusJson> enableTwoFactor({required String currentPassword}) =>
      _json(
        'POST',
        '/api/v1/me/two-factor-authentication',
        body: {'current_password': currentPassword},
      );

  Future<AirmiusJson> confirmTwoFactor({required String code}) => _json(
    'POST',
    '/api/v1/me/two-factor-authentication/confirm',
    body: {'code': code},
  );

  Future<AirmiusJson> disableTwoFactor({required String currentPassword}) =>
      _json(
        'DELETE',
        '/api/v1/me/two-factor-authentication',
        body: {'current_password': currentPassword},
      );

  Future<AirmiusJson> regenerateTwoFactorRecoveryCodes({
    required String currentPassword,
  }) => _json(
    'POST',
    '/api/v1/me/two-factor-recovery-codes',
    body: {'current_password': currentPassword},
  );

  Future<AirmiusJson> friendsMe() => me();

  Future<AirmiusJson> updateProfile(AirmiusJson payload) {
    return _json('PUT', '/api/v1/me/profile', body: payload);
  }

  Future<AirmiusJson> updatePassword({
    required String currentPassword,
    required String password,
    required String passwordConfirmation,
  }) {
    return _json(
      'PUT',
      '/api/v1/me/password',
      body: {
        'current_password': currentPassword,
        'password': password,
        'password_confirmation': passwordConfirmation,
      },
    );
  }

  Future<AirmiusJson> accountSessions() => _json('GET', '/api/v1/me/sessions');

  Future<AirmiusJson> endAccountSession(int tokenId) =>
      _json('DELETE', '/api/v1/me/sessions/$tokenId');

  Future<AirmiusJson> endOtherAccountSessions() =>
      _json('DELETE', '/api/v1/me/sessions/others');

  Future<AirmiusJson> deleteProfilePhoto() =>
      _json('DELETE', '/api/v1/me/profile-photo');

  Future<AirmiusJson> logout() => _json('POST', '/api/v1/auth/logout');

  Future<AirmiusJson> apiMeta() => _json('GET', '/api/v1/meta');

  Future<AirmiusJson> requestAccountDeletionCode({required String password}) {
    return _json(
      'POST',
      '/api/v1/account/deletion-code',
      body: {'password': password},
    );
  }

  Future<AirmiusJson> deleteAccount({required String code}) {
    return _json('DELETE', '/api/v1/account', body: {'code': code});
  }

  Future<AirmiusJson> guardianChildren() =>
      _json('GET', '/api/v1/guardian/children');

  Future<AirmiusJson> guardianChildOverview(int childId) =>
      _json('GET', '/api/v1/guardian/children/$childId');

  Future<AirmiusJson> approveGuardianChild(int childId) =>
      _json('POST', '/api/v1/guardian/children/$childId/approve');

  Future<AirmiusJson> revokeGuardianChild(int childId) =>
      _json('POST', '/api/v1/guardian/children/$childId/revoke');

  Future<AirmiusJson> resendGuardianChildConsent(int childId) =>
      _json('POST', '/api/v1/guardian/children/$childId/resend');

  Future<AirmiusJson> guardianConsentStatus() =>
      _json('GET', '/api/v1/guardian/consent');

  Future<AirmiusJson> resendOwnGuardianConsent() =>
      _json('POST', '/api/v1/guardian/consent/resend');

  Future<AirmiusJson> sportIntegrations() =>
      _json('GET', '/api/v1/sport-integrations');

  Future<AirmiusJson> requestSportIntegration(String provider) => _json(
    'POST',
    '/api/v1/sport-integrations/${Uri.encodeComponent(provider)}/request',
  );

  Future<AirmiusJson> syncSportIntegration(int accountId) =>
      _json('POST', '/api/v1/sport-integrations/accounts/$accountId/sync');

  Future<AirmiusJson> disconnectSportIntegration(int accountId) =>
      _json('DELETE', '/api/v1/sport-integrations/accounts/$accountId');

  Future<AirmiusJson> importSportActivity({
    required String provider,
    required String externalId,
    required String startedAt,
    String? title,
    String? activityType,
    int? durationSeconds,
    int? distanceMeters,
    int? calories,
    String? endedAt,
    Map<String, dynamic>? summary,
    List<Map<String, dynamic>>? samples,
  }) => _json(
    'POST',
    '/api/v1/sport-integrations/activities/import',
    body: {
      'provider': provider,
      'external_id': externalId,
      'started_at': startedAt,
      'title': ?title,
      'activity_type': ?activityType,
      'duration_seconds': ?durationSeconds,
      'distance_meters': ?distanceMeters,
      'calories': ?calories,
      'ended_at': ?endedAt,
      'summary': ?summary,
      'samples': ?samples,
    },
  );

  Future<AirmiusJson> updateSportActivity(int activityId, String title) =>
      _json(
        'PUT',
        '/api/v1/sport-integrations/activities/$activityId',
        body: {'title': title},
      );

  Future<AirmiusJson> deleteSportActivity(int activityId) =>
      _json('DELETE', '/api/v1/sport-integrations/activities/$activityId');

  Future<AirmiusJson> search(String query) =>
      _json('GET', '/api/v1/search', query: {'q': query});

  Future<AirmiusJson> sports() => _json('GET', '/api/v1/sports');

  Future<AirmiusJson> submitRoleApplication({
    required String type,
    String? message,
    AirmiusJson? applicationData,
  }) => _json(
    'POST',
    '/api/v1/role-applications',
    body: {
      'type': type,
      if (message != null && message.trim().isNotEmpty)
        'message': message.trim(),
      if (applicationData != null && applicationData.isNotEmpty)
        'application_data': applicationData,
    },
  );

  Future<AirmiusJson> clubs({String? query, bool mine = false}) => _json(
    'GET',
    '/api/v1/clubs',
    query: {
      if (query != null && query.trim().isNotEmpty) 'q': query.trim(),
      if (mine) 'mine': '1',
    },
  );

  Future<AirmiusJson> createClub(AirmiusJson payload) =>
      _json('POST', '/api/v1/clubs', body: payload);
  Future<AirmiusJson> updateClub(int clubId, AirmiusJson payload) =>
      _json('PUT', '/api/v1/clubs/$clubId', body: payload);
  Future<AirmiusJson> deleteClub(int clubId) =>
      _json('DELETE', '/api/v1/clubs/$clubId');
  Future<AirmiusJson> clubDetail(int clubId) =>
      _json('GET', '/api/v1/clubs/$clubId');

  Future<AirmiusJson> clubSurveys(int clubId) =>
      _json('GET', '/api/v1/clubs/$clubId/surveys');

  Future<AirmiusJson> createClubSurvey(int clubId, AirmiusJson body) =>
      _json('POST', '/api/v1/clubs/$clubId/surveys', body: body);

  Future<AirmiusJson> voteClubSurvey(int clubId, int surveyId, int optionId) =>
      _json(
        'POST',
        '/api/v1/clubs/$clubId/surveys/$surveyId/vote',
        body: {'option_id': optionId},
      );

  Future<AirmiusJson> closeClubSurvey(int clubId, int surveyId) =>
      _json('POST', '/api/v1/clubs/$clubId/surveys/$surveyId/close');

  Future<AirmiusJson> clubAnnouncements(int clubId) =>
      _json('GET', '/api/v1/clubs/$clubId/announcements');

  Future<AirmiusJson> createClubAnnouncement(int clubId, AirmiusJson body) =>
      _json('POST', '/api/v1/clubs/$clubId/announcements', body: body);

  Future<AirmiusJson> acknowledgeClubAnnouncement(
    int clubId,
    int announcementId,
  ) =>
      _json('POST', '/api/v1/clubs/$clubId/announcements/$announcementId/read');
  Future<AirmiusJson> updateClubMembershipSettings(
    int clubId,
    AirmiusJson payload,
  ) => _json('PUT', '/api/v1/clubs/$clubId/membership/settings', body: payload);
  Future<AirmiusJson> createClubMembershipType(
    int clubId,
    AirmiusJson payload,
  ) => _json('POST', '/api/v1/clubs/$clubId/membership/types', body: payload);
  Future<AirmiusJson> updateClubMembershipType(
    int clubId,
    int typeId,
    AirmiusJson payload,
  ) => _json(
    'PUT',
    '/api/v1/clubs/$clubId/membership/types/$typeId',
    body: payload,
  );
  Future<AirmiusJson> createClubContributionRule(
    int clubId,
    AirmiusJson payload,
  ) => _json(
    'POST',
    '/api/v1/clubs/$clubId/membership/contribution-rules',
    body: payload,
  );
  Future<AirmiusJson> updateClubContributionRule(
    int clubId,
    int ruleId,
    AirmiusJson payload,
  ) => _json(
    'PUT',
    '/api/v1/clubs/$clubId/membership/contribution-rules/$ruleId',
    body: payload,
  );
  Future<AirmiusJson> recordClubMembershipPayment(
    int clubId,
    int invoiceId,
    AirmiusJson payload,
  ) => _json(
    'POST',
    '/api/v1/clubs/$clubId/membership-invoices/$invoiceId/payments',
    body: payload,
  );
  Future<AirmiusJson> recordClubDonation(int clubId, AirmiusJson payload) =>
      _json('POST', '/api/v1/clubs/$clubId/donations', body: payload);
  Future<AirmiusJson> recordClubPrepayment(int clubId, AirmiusJson payload) =>
      _json('POST', '/api/v1/clubs/$clubId/prepayments', body: payload);
  Future<AirmiusJson> updateClubPayment(
    int clubId,
    int paymentId,
    AirmiusJson payload,
  ) => _json('PUT', '/api/v1/clubs/$clubId/payments/$paymentId', body: payload);
  Future<AirmiusJson> createClubFinanceEntry(int clubId, AirmiusJson payload) =>
      _json('POST', '/api/v1/clubs/$clubId/finance-entries', body: payload);
  Future<AirmiusJson> updateClubFinanceEntry(
    int clubId,
    int entryId,
    AirmiusJson payload,
  ) => _json(
    'PUT',
    '/api/v1/clubs/$clubId/finance-entries/$entryId',
    body: payload,
  );
  Future<AirmiusJson> inviteClubMember(int clubId, AirmiusJson payload) async {
    try {
      return await _json(
        'POST',
        '/api/v1/clubs/$clubId/members/invite',
        body: payload,
      );
    } on AirmiusApiException catch (error) {
      if (!_shouldTryTeamJoinFallback(error)) rethrow;
      final formResponse = await _sendClubMemberInviteForm(clubId, payload);
      if (formResponse != null) return formResponse;
      rethrow;
    }
  }

  Future<AirmiusJson> updateClubExternalMember(
    int clubId,
    int externalMemberId,
    AirmiusJson payload,
  ) => _json(
    'PUT',
    '/api/v1/clubs/$clubId/external-members/$externalMemberId',
    body: payload,
  );

  Future<AirmiusJson> inviteClubExternalMember(
    int clubId,
    int externalMemberId,
  ) => _json(
    'POST',
    '/api/v1/clubs/$clubId/external-members/$externalMemberId/invite',
  );

  Future<AirmiusJson> removeClubExternalMember(
    int clubId,
    int externalMemberId, {
    required String reason,
  }) => _json(
    'DELETE',
    '/api/v1/clubs/$clubId/external-members/$externalMemberId',
    body: {'reason': reason.trim()},
  );

  Future<AirmiusJson?> _sendClubMemberInviteForm(
    int clubId,
    AirmiusJson payload,
  ) {
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

  Future<AirmiusJson> updateClubMemberRole(
    int clubId,
    int userId,
    String role,
  ) async {
    try {
      return await _json(
        'PUT',
        '/api/v1/clubs/$clubId/members/$userId/role',
        body: {'role': role},
      );
    } on AirmiusApiException catch (error) {
      if (!_shouldTryTeamJoinFallback(error)) rethrow;
      await _json(
        'PUT',
        '/clubs/$clubId/members/$userId',
        body: {
          'role': role,
          'roles': [role],
        },
      );
      final detail = await clubDetail(clubId);
      final data = detail['data'];
      return data is Map<String, dynamic> &&
              data['management'] is Map<String, dynamic>
          ? data['management'] as Map<String, dynamic>
          : detail;
    }
  }

  Future<AirmiusJson> updateClubMember(
    int clubId,
    int userId,
    AirmiusJson payload,
  ) => _json('PUT', '/api/v1/clubs/$clubId/members/$userId', body: payload);

  Future<AirmiusJson> clubMemberPermissions(int clubId, int userId) =>
      _json('GET', '/api/v1/clubs/$clubId/members/$userId/permissions');

  Future<AirmiusJson> updateClubMemberPermissions(
    int clubId,
    int userId,
    AirmiusJson payload,
  ) => _json(
    'PUT',
    '/api/v1/clubs/$clubId/members/$userId/permissions',
    body: payload,
  );

  Future<AirmiusJson> removeClubMember(
    int clubId,
    int userId, {
    required String reason,
  }) => _json(
    'DELETE',
    '/api/v1/clubs/$clubId/members/$userId',
    body: {'reason': reason.trim()},
  );

  Future<AirmiusJson> generateClubMemberNumber(int clubId, int userId) =>
      _json('POST', '/api/v1/clubs/$clubId/members/$userId/member-number');

  Future<AirmiusJson> createClubMemberInvoice(
    int clubId,
    int userId,
    AirmiusJson payload,
  ) => _json(
    'POST',
    '/api/v1/clubs/$clubId/members/$userId/invoices',
    body: payload,
  );

  Future<AirmiusJson> updateClubInvoiceStatus(
    int clubId,
    int invoiceId,
    String status,
  ) => _json(
    'PUT',
    '/api/v1/clubs/$clubId/membership-invoices/$invoiceId/status',
    body: {'status': status},
  );

  Future<AirmiusJson> sendClubInvoiceReminder(int clubId, int invoiceId) =>
      _json(
        'POST',
        '/api/v1/clubs/$clubId/membership-invoices/$invoiceId/reminder',
      );

  Future<AirmiusJson> updateClubSepaSettings(int clubId, AirmiusJson payload) =>
      _json(
        'PUT',
        '/api/v1/clubs/$clubId/membership/sepa-settings',
        body: payload,
      );

  Future<AirmiusJson> updateClubDatevSettings(
    int clubId,
    AirmiusJson payload,
  ) => _json(
    'PUT',
    '/api/v1/clubs/$clubId/membership/datev-settings',
    body: payload,
  );

  Future<AirmiusJson> confirmClubBankTransaction(
    int clubId,
    int transactionId,
  ) => _json(
    'POST',
    '/api/v1/clubs/$clubId/bank-transactions/$transactionId/confirm',
  );

  Future<AirmiusJson> leaveClub(int clubId) =>
      _json('POST', '/api/v1/clubs/$clubId/leave');

  Future<AirmiusJson> objectToClubRemoval(int clubId, AirmiusJson payload) =>
      _json('POST', '/api/v1/clubs/$clubId/removal-objections', body: payload);

  Future<AirmiusJson> requestClubMembershipPause(
    int clubId,
    AirmiusJson payload,
  ) => _json('POST', '/api/v1/clubs/$clubId/pause-requests', body: payload);

  Future<AirmiusJson> requestClubMembershipTermination(
    int clubId,
    AirmiusJson payload,
  ) => _json(
    'POST',
    '/api/v1/clubs/$clubId/termination-requests',
    body: payload,
  );

  Future<AirmiusJson> clubMemberCard(int clubId) =>
      _json('GET', '/api/v1/clubs/$clubId/member-card');

  Future<AirmiusJson> rotateClubMemberCard(int clubId) =>
      _json('POST', '/api/v1/clubs/$clubId/member-card/rotate');

  Future<AirmiusJson> verifyClubMemberCard(int clubId, AirmiusJson payload) =>
      _json('POST', '/api/v1/clubs/$clubId/member-card/verify', body: payload);

  Future<AirmiusJson> clubInventory(int clubId) =>
      _json('GET', '/api/v1/clubs/$clubId/inventory');

  Future<AirmiusJson> createClubInventoryItem(
    int clubId,
    AirmiusJson payload,
  ) => _json('POST', '/api/v1/clubs/$clubId/inventory', body: payload);

  Future<AirmiusJson> updateClubInventoryItem(
    int clubId,
    int itemId,
    AirmiusJson payload,
  ) => _json('PUT', '/api/v1/clubs/$clubId/inventory/$itemId', body: payload);

  Future<AirmiusJson> scanClubInventoryItem(int clubId, String qrToken) =>
      _json(
        'POST',
        '/api/v1/clubs/$clubId/inventory/scan',
        body: {'qr_token': qrToken},
      );

  Future<AirmiusJson> checkoutClubInventoryItem(
    int clubId,
    int itemId,
    AirmiusJson payload,
  ) => _json(
    'POST',
    '/api/v1/clubs/$clubId/inventory/$itemId/checkout',
    body: payload,
  );

  Future<AirmiusJson> approveClubInventoryLoan(int clubId, int loanId) =>
      _json('POST', '/api/v1/clubs/$clubId/inventory/loans/$loanId/approve');

  Future<AirmiusJson> rejectClubInventoryLoan(int clubId, int loanId) =>
      _json('POST', '/api/v1/clubs/$clubId/inventory/loans/$loanId/reject');

  Future<AirmiusJson> returnClubInventoryLoan(
    int clubId,
    int loanId, {
    String returnCondition = 'good',
  }) => _json(
    'POST',
    '/api/v1/clubs/$clubId/inventory/loans/$loanId/return',
    body: {'return_condition': returnCondition},
  );

  Future<AirmiusJson> createClubInventoryMaintenance(
    int clubId,
    int itemId,
    AirmiusJson payload,
  ) => _json(
    'POST',
    '/api/v1/clubs/$clubId/inventory/$itemId/maintenance',
    body: payload,
  );

  Future<AirmiusJson> updateClubInventoryMaintenance(
    int clubId,
    int maintenanceId,
    AirmiusJson payload,
  ) => _json(
    'PUT',
    '/api/v1/clubs/$clubId/inventory/maintenance/$maintenanceId',
    body: payload,
  );

  Future<AirmiusJson> acceptClubExternalInvitation(
    String invitationToken,
  ) => _json(
    'POST',
    '/api/v1/club-external-invitations/${Uri.encodeComponent(invitationToken)}/accept',
  );

  Future<AirmiusJson> clubExternalInvitationByToken(
    String invitationToken,
  ) => _json(
    'GET',
    '/api/v1/club-external-invitations/${Uri.encodeComponent(invitationToken)}',
  );

  Future<AirmiusJson> declineClubExternalInvitation(
    String invitationToken,
  ) => _json(
    'POST',
    '/api/v1/club-external-invitations/${Uri.encodeComponent(invitationToken)}/decline',
  );

  Future<AirmiusJson> teams({int page = 1, int perPage = 50}) => _json(
    'GET',
    '/api/v1/teams',
    query: {'page': '$page', 'per_page': '$perPage'},
  );

  Future<AirmiusJson> teamDetail(int teamId) =>
      _json('GET', '/api/v1/teams/$teamId');
  Future<AirmiusJson> teamCompetitivenessInsights(int teamId) =>
      _json('GET', '/api/v1/teams/$teamId/competitiveness/insights');
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

  Future<AirmiusJson> updateTeam(int teamId, AirmiusJson payload) =>
      _json('PUT', '/api/v1/teams/$teamId', body: payload);
  Future<AirmiusJson> deleteTeam(int teamId) =>
      _json('DELETE', '/api/v1/teams/$teamId');
  Future<AirmiusJson> requestTeamJoin(int teamId) =>
      _json('POST', '/api/v1/teams/$teamId/join-requests');
  Future<AirmiusJson> approveTeamJoinRequest(
    int teamId,
    int requestId, {
    String role = 'Player',
  }) async {
    try {
      return await _json(
        'POST',
        '/api/v1/team-join-requests/$requestId/approve',
        body: {'role': role},
      );
    } on AirmiusApiException catch (error) {
      if (!_shouldTryTeamJoinFallback(error)) rethrow;
      try {
        return await _json(
          'POST',
          '/api/v1/teams/$teamId/join-requests/$requestId/approve',
          body: {'role': role},
        );
      } on AirmiusApiException catch (nestedError) {
        if (!_shouldTryTeamJoinFallback(nestedError)) rethrow;
        return _json(
          'POST',
          '/team-join-requests/$requestId/approve',
          body: {'role': role},
        );
      }
    }
  }

  Future<AirmiusJson> declineTeamJoinRequest(int teamId, int requestId) async {
    try {
      return await _json(
        'POST',
        '/api/v1/team-join-requests/$requestId/decline',
      );
    } on AirmiusApiException catch (error) {
      if (!_shouldTryTeamJoinFallback(error)) rethrow;
      try {
        return await _json(
          'POST',
          '/api/v1/teams/$teamId/join-requests/$requestId/decline',
        );
      } on AirmiusApiException catch (nestedError) {
        if (!_shouldTryTeamJoinFallback(nestedError)) rethrow;
        return _json('POST', '/team-join-requests/$requestId/decline');
      }
    }
  }

  bool _shouldTryTeamJoinFallback(AirmiusApiException error) =>
      error.statusCode == 0 ||
      error.statusCode == 404 ||
      error.statusCode == 405 ||
      error.statusCode == 599;

  Future<AirmiusJson> teamInvitations() =>
      _json('GET', '/api/v1/team-invitations');

  Future<AirmiusJson> inviteTeamMember(
    int teamId, {
    required String email,
    required String role,
  }) {
    return _json(
      'POST',
      '/api/v1/teams/$teamId/invite',
      body: {'email': email, 'role': role},
    );
  }

  Future<AirmiusJson> teamInvitation(int invitationId) =>
      _json('GET', '/api/v1/team-invitations/$invitationId');

  Future<AirmiusJson> teamInvitationByToken(String token) => _json(
    'GET',
    '/api/v1/team-invitations/token/${Uri.encodeComponent(token)}',
  );

  Future<AirmiusJson> acceptTeamInvitation(int invitationId) =>
      _json('POST', '/api/v1/team-invitations/$invitationId/accept');

  Future<AirmiusJson> acceptTeamInvitationByToken(String token) => _json(
    'POST',
    '/api/v1/team-invitations/token/${Uri.encodeComponent(token)}/accept',
  );

  Future<AirmiusJson> declineTeamInvitation(int invitationId) =>
      _json('POST', '/api/v1/team-invitations/$invitationId/decline');

  Future<AirmiusJson> declineTeamInvitationByToken(String token) => _json(
    'POST',
    '/api/v1/team-invitations/token/${Uri.encodeComponent(token)}/decline',
  );

  Future<AirmiusJson> updateTeamMemberRole(
    int teamId,
    int userId,
    String role,
  ) async {
    try {
      return await _json(
        'PUT',
        '/api/v1/teams/$teamId/members/$userId',
        body: {'role': role},
      );
    } on AirmiusApiException catch (error) {
      if (!_shouldTryTeamJoinFallback(error)) rethrow;
      return _json(
        'PUT',
        '/teams/$teamId/members/$userId',
        body: {'role': role},
      );
    }
  }

  Future<AirmiusJson> teamAttendanceStats(int teamId) =>
      _json('GET', '/api/v1/teams/$teamId/attendance-stats');

  Future<AirmiusJson> teamPenalties(int teamId, {int? eventId}) => _json(
    'GET',
    '/api/v1/teams/$teamId/penalties',
    query: {if (eventId != null) 'event_id': '$eventId'},
  );

  Future<AirmiusJson> createTeamPenaltyRule(int teamId, AirmiusJson payload) {
    return _json('POST', '/api/v1/teams/$teamId/penalty-rules', body: payload);
  }

  Future<AirmiusJson> updateTeamPenaltyRule(
    int teamId,
    int ruleId,
    AirmiusJson payload,
  ) {
    return _json(
      'PUT',
      '/api/v1/teams/$teamId/penalty-rules/$ruleId',
      body: payload,
    );
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

  Future<AirmiusJson> createClubMembershipRequest(
    int clubId,
    AirmiusJson payload,
  ) {
    return _json(
      'POST',
      '/api/v1/clubs/$clubId/membership-requests',
      body: payload,
    );
  }

  Future<AirmiusJson> clubMembershipRequests(int clubId, {int page = 1}) =>
      _json(
        'GET',
        '/api/v1/clubs/$clubId/membership-requests',
        query: {'page': '$page'},
      );

  Future<AirmiusJson> withdrawClubMembershipRequest(int clubId) {
    return _json('DELETE', '/api/v1/clubs/$clubId/membership-requests');
  }

  Future<AirmiusJson> approveClubMembershipRequest(
    int clubId,
    int requestId, {
    String? reviewNote,
  }) {
    return _json(
      'POST',
      '/api/v1/clubs/$clubId/membership-requests/$requestId/approve',
      body: {
        if (reviewNote != null && reviewNote.trim().isNotEmpty)
          'review_note': reviewNote.trim(),
      },
    );
  }

  Future<AirmiusJson> declineClubMembershipRequest(
    int clubId,
    int requestId, {
    String? reviewNote,
  }) {
    return _json(
      'POST',
      '/api/v1/clubs/$clubId/membership-requests/$requestId/decline',
      body: {
        if (reviewNote != null && reviewNote.trim().isNotEmpty)
          'review_note': reviewNote.trim(),
      },
    );
  }

  Future<AirmiusJson> membershipApplication(int applicationId) {
    return _json('GET', '/api/v1/membership-applications/$applicationId');
  }

  Future<AirmiusJson> withdrawMembershipApplication(int applicationId) {
    return _json(
      'POST',
      '/api/v1/membership-applications/$applicationId/withdraw',
    );
  }

  Future<AirmiusJson> uploadIntent({
    required String scope,
    required String fileName,
    required String mimeType,
  }) {
    return _json(
      'POST',
      '/api/v1/files/upload-intents',
      body: {'scope': scope, 'file_name': fileName, 'mime_type': mimeType},
    );
  }

  Future<AirmiusJson> fileWorkspace({
    String scope = 'user',
    int? folderId,
    int? clubId,
    int? teamId,
    int? eventId,
    String? search,
    String sort = 'name-asc',
    int page = 1,
    int perPage = 24,
  }) {
    return _json(
      'GET',
      '/api/v1/files',
      query: {
        'scope': scope,
        'sort': sort,
        'files_page': '$page',
        'folders_page': '$page',
        'per_page': '$perPage',
        if (folderId != null) 'folder_id': '$folderId',
        if (clubId != null) 'club_id': '$clubId',
        if (teamId != null) 'team_id': '$teamId',
        if (eventId != null) 'event_id': '$eventId',
        if (search != null && search.trim().isNotEmpty) 'search': search.trim(),
      },
    );
  }

  Future<AirmiusJson> createFolder({
    required String scope,
    required String name,
    int? parentId,
    int? clubId,
    int? teamId,
    int? eventId,
  }) {
    return _json(
      'POST',
      '/api/v1/files/folders',
      body: {
        'scope': scope,
        'name': name,
        'parent_id': ?parentId,
        'club_id': ?clubId,
        'team_id': ?teamId,
        'event_id': ?eventId,
      },
    );
  }

  Future<AirmiusJson> renameFolder(int folderId, String name) =>
      _json('PATCH', '/api/v1/files/folders/$folderId', body: {'name': name});

  Future<AirmiusJson> deleteFolder(int folderId) =>
      _json('DELETE', '/api/v1/files/folders/$folderId');

  Future<AirmiusJson> shareFolder(int folderId, int targetUserId) => _json(
    'POST',
    '/api/v1/files/folders/$folderId/share',
    body: {'target_id': targetUserId},
  );

  Future<AirmiusJson> renameFile(int fileId, String name) =>
      _json('PATCH', '/api/v1/uploads/$fileId', body: {'display_name': name});

  Future<AirmiusJson> deleteFile(int fileId) =>
      _json('DELETE', '/api/v1/uploads/$fileId');

  Future<AirmiusJson> shareFile(int fileId, int targetUserId) => _json(
    'POST',
    '/api/v1/uploads/$fileId/share',
    body: {'target_user_id': targetUserId},
  );

  Future<AirmiusJson> notifications({int page = 1, bool unreadOnly = false}) =>
      _json(
        'GET',
        '/api/v1/notifications',
        query: {'page': page.toString(), if (unreadOnly) 'unread_only': '1'},
      );

  Future<AirmiusJson> notification(int notificationId) =>
      _json('GET', '/api/v1/notifications/$notificationId');

  Future<AirmiusJson> markNotificationAsRead(int notificationId) =>
      _json('POST', '/api/v1/notifications/$notificationId/read');

  Future<AirmiusJson> markNotificationAsUnread(int notificationId) =>
      _json('POST', '/api/v1/notifications/$notificationId/unread');

  Future<AirmiusJson> markAllNotificationsAsRead() =>
      _json('POST', '/api/v1/notifications/read-all');

  Future<AirmiusJson> deleteNotification(int notificationId) =>
      _json('DELETE', '/api/v1/notifications/$notificationId');

  Future<AirmiusJson> conversations({int page = 1, int? teamId}) => _json(
    'GET',
    '/api/v1/chat/conversations',
    query: {'page': '$page', if (teamId != null) 'team_id': '$teamId'},
  );

  Future<AirmiusJson> sportMatchings({
    String mode = 'partner',
    String? city,
    int? sportId,
    int? radiusKm,
    String? skillLevel,
  }) => _json(
    'GET',
    '/api/v1/sport-matching',
    query: {
      'mode': mode,
      'per_page': '50',
      if (city != null && city.trim().isNotEmpty) 'city': city.trim(),
      if (sportId != null) 'sport_id': '$sportId',
      if (radiusKm != null) 'radius_km': '$radiusKm',
      if (skillLevel != null &&
          skillLevel.trim().isNotEmpty &&
          skillLevel != 'all')
        'skill_level': skillLevel.trim(),
    },
  );

  Future<AirmiusJson> createSportMatching(AirmiusJson body) =>
      _json('POST', '/api/v1/sport-matching', body: body);

  Future<AirmiusJson> applyForSportMatching(
    int matchingId, {
    int? teamId,
    String? message,
  }) => _json(
    'POST',
    '/api/v1/sport-matching/$matchingId/apply',
    body: {
      'team_id': ?teamId,
      if (message != null && message.trim().isNotEmpty)
        'message': message.trim(),
    },
  );

  Future<AirmiusJson> dismissSportMatching(
    int matchingId, {
    bool dismissed = true,
  }) => _json(
    'POST',
    '/api/v1/sport-matching/$matchingId/dismiss',
    body: {'dismissed': dismissed},
  );

  Future<AirmiusJson> decideSportMatchingApplication(
    int matchingId,
    int applicationId,
    String status,
  ) => _json(
    'PUT',
    '/api/v1/sport-matching/$matchingId/applications/$applicationId',
    body: {'status': status},
  );

  Future<AirmiusJson> updateSportMatchingAttendance(
    int matchingId,
    String action,
  ) => _json(
    'PUT',
    '/api/v1/sport-matching/$matchingId/attendance',
    body: {'action': action},
  );

  Future<AirmiusJson> reportSportMatchingNoShow(
    int matchingId,
    int targetUserId, {
    String? reason,
  }) => _json(
    'POST',
    '/api/v1/sport-matching/$matchingId/attendance/no-show',
    body: {
      'target_user_id': targetUserId,
      if (reason != null && reason.trim().isNotEmpty) 'reason': reason.trim(),
    },
  );

  Future<AirmiusJson> cancelSportMatching(int matchingId) =>
      _json('POST', '/api/v1/sport-matching/$matchingId/cancel');

  Future<AirmiusJson> challenges({String? status}) => _json(
    'GET',
    '/api/v1/challenges',
    query: {if (status != null && status.isNotEmpty) 'status': status},
  );

  Future<AirmiusJson> challenge(int challengeId) =>
      _json('GET', '/api/v1/challenges/$challengeId');

  Future<AirmiusJson> createChallenge(AirmiusJson body) =>
      _json('POST', '/api/v1/challenges', body: body);

  Future<AirmiusJson> joinChallenge(int challengeId) =>
      _json('POST', '/api/v1/challenges/$challengeId/join');

  Future<AirmiusJson> respondToChallenge(int challengeId, String status) =>
      _json(
        'PUT',
        '/api/v1/challenges/$challengeId/invitation',
        body: {'status': status},
      );

  Future<AirmiusJson> checkInChallenge(
    int challengeId,
    String date, {
    bool completed = true,
    num? value,
    String? note,
    String slot = 'anytime',
  }) => _json(
    'PUT',
    '/api/v1/challenges/$challengeId/check-ins/$date',
    body: {
      'completed': completed,
      'slot': slot,
      'value': ?value,
      if (note != null && note.trim().isNotEmpty) 'note': note.trim(),
    },
  );

  Future<AirmiusJson> challengeComments(int challengeId) =>
      _json('GET', '/api/v1/challenges/$challengeId/comments');

  Future<AirmiusJson> addChallengeComment(int challengeId, String content) =>
      _json(
        'POST',
        '/api/v1/challenges/$challengeId/comments',
        body: {'content': content.trim()},
      );

  Future<AirmiusJson> cancelChallenge(int challengeId) =>
      _json('POST', '/api/v1/challenges/$challengeId/cancel');

  Future<AirmiusJson> createConversation({
    required String type,
    List<int> participantIds = const [],
    int? clubId,
    int? teamId,
    String? name,
    String? description,
    String? message,
  }) => _json(
    'POST',
    '/api/v1/chat/conversations',
    body: {
      'type': type,
      if (participantIds.isNotEmpty) 'participant_ids': participantIds,
      'club_id': ?clubId,
      'team_id': ?teamId,
      if (name != null && name.trim().isNotEmpty) 'name': name.trim(),
      if (description != null && description.trim().isNotEmpty)
        'description': description.trim(),
      if (message != null && message.trim().isNotEmpty)
        'message': message.trim(),
    },
  );

  Future<AirmiusJson> conversation(int conversationId) =>
      _json('GET', '/api/v1/chat/conversations/$conversationId');

  Future<AirmiusJson> message(int messageId) =>
      _json('GET', '/api/v1/chat/messages/$messageId');

  Future<AirmiusJson> conversationMessages(
    int conversationId, {
    int page = 1,
  }) => _json(
    'GET',
    '/api/v1/chat/conversations/$conversationId/messages',
    query: {'page': '$page'},
  );

  Future<AirmiusJson> sendConversationMessage(
    int conversationId,
    String message,
  ) => _json(
    'POST',
    '/api/v1/chat/conversations/$conversationId/messages',
    body: {'message': message},
  );

  Future<AirmiusJson> markConversationRead(int conversationId) =>
      _json('POST', '/api/v1/chat/conversations/$conversationId/read');

  Future<AirmiusJson> sendConversationTyping(int conversationId, bool typing) =>
      _json(
        'POST',
        '/api/v1/chat/conversations/$conversationId/typing',
        body: {'typing': typing},
      );

  Future<AirmiusJson> updateConversation(
    int conversationId,
    AirmiusJson payload,
  ) =>
      _json('PUT', '/api/v1/chat/conversations/$conversationId', body: payload);

  Future<AirmiusJson> muteConversation(int conversationId, int minutes) =>
      _json(
        'PUT',
        '/api/v1/chat/conversations/$conversationId/mute',
        body: {'minutes': minutes},
      );

  Future<AirmiusJson> leaveConversation(int conversationId) =>
      _json('DELETE', '/api/v1/chat/conversations/$conversationId/leave');

  Future<AirmiusJson> inviteConversationMembers(
    int conversationId,
    List<int> participantIds,
  ) => _json(
    'POST',
    '/api/v1/chat/conversations/$conversationId/members',
    body: {'participant_ids': participantIds},
  );

  Future<AirmiusJson> removeConversationMember(
    int conversationId,
    int userId,
  ) => _json(
    'DELETE',
    '/api/v1/chat/conversations/$conversationId/members/$userId',
  );

  Future<AirmiusJson> transferConversationOwner(
    int conversationId,
    int userId,
  ) => _json(
    'PUT',
    '/api/v1/chat/conversations/$conversationId/owner',
    body: {'user_id': userId},
  );

  Future<AirmiusJson> acceptConversationInvitation(int invitationId) => _json(
    'POST',
    '/api/v1/chat/conversation-invitations/$invitationId/accept',
  );

  Future<AirmiusJson> conversationInvitations() =>
      _json('GET', '/api/v1/chat/conversation-invitations');

  Future<AirmiusJson> declineConversationInvitation(int invitationId) => _json(
    'POST',
    '/api/v1/chat/conversation-invitations/$invitationId/decline',
  );

  Future<AirmiusJson> reactToMessage(int messageId, String reaction) => _json(
    'POST',
    '/api/v1/chat/messages/$messageId/reactions',
    body: {'reaction': reaction},
  );

  Future<AirmiusJson> hideMessage(int messageId) =>
      _json('DELETE', '/api/v1/chat/messages/$messageId/hide');

  Future<AirmiusJson> deleteMessage(int messageId) =>
      _json('DELETE', '/api/v1/chat/messages/$messageId');

  Future<AirmiusJson> feed({int page = 1, int perPage = 20}) => _json(
    'GET',
    '/api/v1/feed',
    query: {'page': '$page', 'per_page': '$perPage'},
  );

  Future<AirmiusJson> profilePosts(
    int userId, {
    int page = 1,
    int perPage = 8,
  }) => _json(
    'GET',
    '/api/v1/users/$userId/posts',
    query: {'page': '$page', 'per_page': '$perPage'},
  );

  Future<AirmiusJson> feedPost(int postId) =>
      _json('GET', '/api/v1/posts/$postId');

  Future<AirmiusJson> createFeedPost(AirmiusJson payload) =>
      _json('POST', '/api/v1/feed', body: payload);

  Future<AirmiusJson> togglePostLike(int postId) =>
      _json('POST', '/api/v1/posts/$postId/like', body: {'reaction': 'like'});

  Future<AirmiusJson> togglePostHelpful(int postId) => _json(
    'POST',
    '/api/v1/posts/$postId/helpful',
    body: {'reaction': 'helpful'},
  );

  Future<AirmiusJson> updatePost(int postId, AirmiusJson body) =>
      _json('PUT', '/api/v1/posts/$postId', body: body);

  Future<AirmiusJson> deletePost(int postId) async {
    try {
      return await _deletePostRequest(
        'POST',
        '/api/v1/posts/$postId',
        body: {'_method': 'DELETE'},
      );
    } on AirmiusApiException catch (error) {
      if (!_canTryPostDeleteFallback(error.statusCode)) {
        rethrow;
      }
    }

    try {
      return await _deletePostRequest('POST', '/api/v1/posts/$postId/delete');
    } on AirmiusApiException catch (error) {
      if (!_canTryPostDeleteFallback(error.statusCode)) {
        rethrow;
      }
    }

    try {
      return await _deletePostRequest('DELETE', '/api/v1/posts/$postId');
    } on AirmiusApiException catch (error) {
      if (error.statusCode == 404 || error.statusCode == 599) {
        return {'deleted': true};
      }
      rethrow;
    }
  }

  Future<AirmiusJson> _deletePostRequest(
    String method,
    String path, {
    AirmiusJson? body,
  }) async {
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

    throw AirmiusApiException(
      statusCode: response.statusCode,
      body: response.body,
      path: path,
    );
  }

  bool _canTryPostDeleteFallback(int statusCode) {
    return statusCode == 404 ||
        statusCode == 405 ||
        statusCode == 419 ||
        statusCode == 599;
  }

  Future<AirmiusJson> reportContent({
    required String type,
    required int id,
    String reason = 'other',
    String? details,
  }) {
    return _json(
      'POST',
      '/api/v1/reports',
      body: {
        'type': type,
        'id': id,
        'reason': reason,
        if (details != null && details.trim().isNotEmpty)
          'details': details.trim(),
      },
    );
  }

  Future<AirmiusJson> postComments(
    int postId, {
    int page = 1,
    int perPage = 20,
  }) => _json(
    'GET',
    '/api/v1/posts/$postId/comments',
    query: {'page': '$page', 'per_page': '$perPage'},
  );

  Future<AirmiusJson> createPostComment(int postId, String content) => _json(
    'POST',
    '/api/v1/posts/$postId/comments',
    body: {'content': content},
  );

  Future<AirmiusJson> updateComment(int commentId, String content) =>
      _json('PUT', '/api/v1/comments/$commentId', body: {'content': content});

  Future<AirmiusJson> deleteComment(int commentId) =>
      _json('DELETE', '/api/v1/comments/$commentId');

  Future<AirmiusJson> stories({int limit = 30}) =>
      _json('GET', '/api/v1/stories', query: {'limit': '$limit'});

  Future<AirmiusJson> markStoryViewed(int storyId) =>
      _json('POST', '/api/v1/stories/$storyId/viewed');

  Future<AirmiusJson> reactToStory(int storyId, String reaction) => _json(
    'POST',
    '/api/v1/stories/$storyId/react',
    body: {'reaction': reaction},
  );

  Future<AirmiusJson> deleteStory(int storyId) =>
      _json('DELETE', '/api/v1/stories/$storyId');

  /// Returns the authenticated user's live dashboard flow.  The endpoint
  /// intentionally contains only resources visible to that user (training,
  /// events, nutrition and notifications), so the home screen never has to
  /// invent placeholder metrics.
  Future<AirmiusJson> dashboardDailyFlow() =>
      _json('GET', '/api/v1/dashboard/daily-flow');

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
  }) => _json(
    'GET',
    '/api/v1/events',
    query: {
      'page': '$page',
      if (from != null) 'from': from.toIso8601String(),
      if (to != null) 'to': to.toIso8601String(),
      if (search != null && search.trim().isNotEmpty) 'search': search.trim(),
      if (type != null && type.isNotEmpty) 'type': type,
      if (visibility != null && visibility.isNotEmpty) 'visibility': visibility,
      if (clubId != null) 'club_id': '$clubId',
      if (teamId != null) 'team_id': '$teamId',
      if (period != null && period.isNotEmpty) 'period': period,
      if (calendarMonth != null && calendarMonth.isNotEmpty)
        'calendar_month': calendarMonth,
    },
  );

  Future<AirmiusJson> createEvent(AirmiusJson body) =>
      _json('POST', '/api/v1/events', body: body);

  Future<AirmiusJson> event(int eventId) =>
      _json('GET', '/api/v1/events/$eventId');

  Future<AirmiusJson> eventComments(int eventId, {int page = 1}) => _json(
    'GET',
    '/api/v1/events/$eventId/comments',
    query: {'page': '$page'},
  );

  Future<AirmiusJson> createEventComment(int eventId, String content) => _json(
    'POST',
    '/api/v1/events/$eventId/comments',
    body: {'content': content},
  );

  Future<AirmiusJson> updateEvent(int eventId, AirmiusJson body) =>
      _json('PUT', '/api/v1/events/$eventId', body: body);

  Future<AirmiusJson> cancelEvent(int eventId, {String? reason}) => _json(
    'POST',
    '/api/v1/events/$eventId/cancel',
    body: {
      if (reason != null && reason.trim().isNotEmpty) 'reason': reason.trim(),
    },
  );

  Future<AirmiusJson> deleteEvent(int eventId) =>
      _json('DELETE', '/api/v1/events/$eventId');

  Future<AirmiusJson> eventAttendance(int eventId) =>
      _json('GET', '/api/v1/events/$eventId/attendance');

  Future<AirmiusJson> recordEventAttendance(
    int eventId,
    List<AirmiusJson> attendance,
  ) => _json(
    'PUT',
    '/api/v1/events/$eventId/attendance',
    body: {'attendance': attendance},
  );

  Future<AirmiusJson> respondToEvent(int eventId, String status) => _json(
    'POST',
    '/api/v1/events/$eventId/participation',
    body: {'status': status},
  );

  Future<AirmiusJson> leaveEvent(int eventId) =>
      _json('DELETE', '/api/v1/events/$eventId/participation');

  Future<AirmiusJson> eventDecisions(int eventId) =>
      _json('GET', '/api/v1/events/$eventId/decisions');

  Future<AirmiusJson> createEventDecision(int eventId, AirmiusJson body) =>
      _json('POST', '/api/v1/events/$eventId/decisions', body: body);

  Future<AirmiusJson> castEventDecisionVote(
    int eventId,
    int decisionId,
    int optionId,
  ) => _json(
    'POST',
    '/api/v1/events/$eventId/decisions/$decisionId/vote',
    body: {'option_id': optionId},
  );

  Future<AirmiusJson> closeEventDecision(int eventId, int decisionId) =>
      _json('POST', '/api/v1/events/$eventId/decisions/$decisionId/close');

  Future<AirmiusJson> trainerCockpit() =>
      _json('GET', '/api/v1/trainer-cockpit');

  Future<AirmiusJson> sendTrainerFeedback(int logId, String body) => _json(
    'POST',
    '/api/v1/training/logs/$logId/feedback',
    body: {'body': body.trim()},
  );

  Future<AirmiusJson> trainingAvailability({int? userId}) => _json(
    'GET',
    '/api/v1/training/availability',
    query: {if (userId != null) 'user_id': '$userId'},
  );

  Future<AirmiusJson> createTrainingAvailability(AirmiusJson body) =>
      _json('POST', '/api/v1/training/availability', body: body);

  Future<AirmiusJson> updateTrainingAvailability(
    int statusId,
    AirmiusJson body,
  ) => _json('PUT', '/api/v1/training/availability/$statusId', body: body);

  Future<AirmiusJson> clearTrainingAvailability(int statusId) =>
      _json('DELETE', '/api/v1/training/availability/$statusId');

  Future<AirmiusJson> trainingPlans({
    int page = 1,
    int perPage = 30,
    bool includeItems = true,
  }) => _json(
    'GET',
    '/api/v1/training/plans',
    query: {
      'page': '$page',
      'per_page': '$perPage',
      if (includeItems) 'include_items': '1',
    },
  );

  Future<AirmiusJson> trainingRouteOptions() =>
      _json('GET', '/api/v1/training/route-options');

  Future<AirmiusJson> trainingTemplates({int page = 1, int perPage = 30}) =>
      _json(
        'GET',
        '/api/v1/training/templates',
        query: {'page': '$page', 'per_page': '$perPage'},
      );

  Future<AirmiusJson> trainingPlan(int planId) =>
      _json('GET', '/api/v1/training/plans/$planId');

  Future<AirmiusJson> createTrainingPlan(AirmiusJson body) =>
      _json('POST', '/api/v1/training/plans', body: body);

  Future<AirmiusJson> updateTrainingPlan(int planId, AirmiusJson body) =>
      _json('PUT', '/api/v1/training/plans/$planId', body: body);

  Future<AirmiusJson> deleteTrainingPlan(int planId) =>
      _json('DELETE', '/api/v1/training/plans/$planId');

  Future<AirmiusJson> publishTrainingPlan(int planId) =>
      _json('POST', '/api/v1/training/plans/$planId/publish');

  Future<AirmiusJson> duplicateTrainingPlan(int planId) =>
      _json('POST', '/api/v1/training/plans/$planId/duplicate');

  Future<AirmiusJson> createTrainingTemplate(int planId, {String? title}) =>
      _json(
        'POST',
        '/api/v1/training/plans/$planId/template',
        body: {
          if (title != null && title.trim().isNotEmpty) 'title': title.trim(),
        },
      );

  Future<AirmiusJson> instantiateTrainingTemplate(
    int templateId, {
    String? title,
    String? startsOn,
    String? endsOn,
  }) => _json(
    'POST',
    '/api/v1/training/templates/$templateId/instantiate',
    body: {
      if (title != null && title.trim().isNotEmpty) 'title': title.trim(),
      if (startsOn != null && startsOn.trim().isNotEmpty)
        'starts_on': startsOn.trim(),
      if (endsOn != null && endsOn.trim().isNotEmpty) 'ends_on': endsOn.trim(),
    },
  );

  Future<AirmiusJson> createTrainingPlanItem(int planId, AirmiusJson body) =>
      _json('POST', '/api/v1/training/plans/$planId/items', body: body);

  Future<AirmiusJson> updateTrainingPlanItem(
    int planId,
    int itemId,
    AirmiusJson body,
  ) => _json('PUT', '/api/v1/training/plans/$planId/items/$itemId', body: body);

  Future<AirmiusJson> deleteTrainingPlanItem(int planId, int itemId) =>
      _json('DELETE', '/api/v1/training/plans/$planId/items/$itemId');

  Future<AirmiusJson> duplicateTrainingPlanItem(int planId, int itemId) =>
      _json('POST', '/api/v1/training/plans/$planId/items/$itemId/duplicate');

  Future<AirmiusJson> markTrainingPlanItemMissed(
    int planId,
    int itemId, {
    required String reason,
    String? notes,
    int? userId,
  }) => _json(
    'POST',
    '/api/v1/training/plans/$planId/items/$itemId/missed',
    body: {
      'reason': reason,
      if (notes != null && notes.trim().isNotEmpty) 'notes': notes.trim(),
      'user_id': ?userId,
    },
  );

  Future<AirmiusJson> previewAiTrainingPlan(AirmiusJson body) =>
      _json('POST', '/api/v1/training/ai/plans/preview', body: body);

  Future<AirmiusJson> saveAiTrainingPlan(AirmiusJson body) =>
      _json('POST', '/api/v1/training/ai/plans', body: body);

  Future<AirmiusJson> trainingLogs({int page = 1, int perPage = 30}) => _json(
    'GET',
    '/api/v1/training/logs',
    query: {'page': '$page', 'per_page': '$perPage'},
  );

  Future<AirmiusJson> trainingLog(int logId) =>
      _json('GET', '/api/v1/training/logs/$logId');

  Future<AirmiusJson> createTrainingLog(AirmiusJson body) =>
      _json('POST', '/api/v1/training/logs', body: body);

  Future<AirmiusJson> updateTrainingLog(int logId, AirmiusJson body) =>
      _json('PUT', '/api/v1/training/logs/$logId', body: body);

  Future<AirmiusJson> deleteTrainingLog(int logId) =>
      _json('DELETE', '/api/v1/training/logs/$logId');

  Future<AirmiusJson> trainingAnalytics({int? userId, int days = 28}) => _json(
    'GET',
    '/api/v1/training/analytics',
    query: {'days': '$days', if (userId != null) 'user_id': '$userId'},
  );

  Future<AirmiusJson> trainingExercises({String? query, String? sportType}) =>
      _json(
        'GET',
        '/api/v1/training/exercises',
        query: {
          if (query != null && query.trim().isNotEmpty) 'q': query.trim(),
          if (sportType != null && sportType.trim().isNotEmpty)
            'sport_type': sportType.trim(),
        },
      );

  Future<AirmiusJson> trainingExercise(int exerciseId) =>
      _json('GET', '/api/v1/training/exercises/$exerciseId');

  Future<AirmiusJson> createTrainingExercise(AirmiusJson body) =>
      _json('POST', '/api/v1/training/exercises', body: body);

  Future<AirmiusJson> updateTrainingExercise(
    int exerciseId,
    AirmiusJson body,
  ) => _json('PUT', '/api/v1/training/exercises/$exerciseId', body: body);

  Future<AirmiusJson> deleteTrainingExercise(int exerciseId) =>
      _json('DELETE', '/api/v1/training/exercises/$exerciseId');

  Future<AirmiusJson> addTrainingExerciseToPlan(
    int exerciseId,
    int trainingPlanId,
  ) => _json(
    'POST',
    '/api/v1/training/exercises/$exerciseId/add-to-plan',
    body: {'training_plan_id': trainingPlanId},
  );

  Future<AirmiusJson> nutrition({required String date}) =>
      _json('GET', '/api/v1/nutrition', query: {'date': date});

  Future<AirmiusJson> updateNutritionGoal(AirmiusJson body) =>
      _json('PATCH', '/api/v1/nutrition/goal', body: body);

  Future<AirmiusJson> searchNutritionFoods(String query) =>
      _json('GET', '/api/v1/nutrition/foods/search', query: {'q': query});

  Future<AirmiusJson> lookupNutritionBarcode(String barcode) => _json(
    'GET',
    '/api/v1/nutrition/foods/barcode',
    query: {'barcode': barcode},
  );

  Future<AirmiusJson> createNutritionMeal(AirmiusJson body) =>
      _json('POST', '/api/v1/nutrition/meals', body: body);

  Future<AirmiusJson> updateNutritionMeal(int mealId, AirmiusJson body) =>
      _json('PATCH', '/api/v1/nutrition/meals/$mealId', body: body);

  Future<AirmiusJson> deleteNutritionMeal(int mealId) =>
      _json('DELETE', '/api/v1/nutrition/meals/$mealId');

  Future<AirmiusJson> logNutritionWater({
    required String date,
    required int amountMl,
    String? title,
  }) => _json(
    'POST',
    '/api/v1/nutrition/water',
    body: {
      'eaten_on': date,
      'amount_ml': amountMl,
      if (title != null && title.trim().isNotEmpty) 'title': title.trim(),
    },
  );

  Future<AirmiusJson> sportRoutes({int page = 1, int perPage = 50}) => _json(
    'GET',
    '/api/v1/sport-routes',
    query: {'page': '$page', 'per_page': '$perPage'},
  );

  Future<AirmiusJson> sportRoute(int routeId) =>
      _json('GET', '/api/v1/sport-routes/$routeId');

  Future<AirmiusJson> createSportRoute(AirmiusJson body) =>
      _json('POST', '/api/v1/sport-routes', body: body);

  Future<AirmiusJson> updateSportRoute(int routeId, AirmiusJson body) =>
      _json('PATCH', '/api/v1/sport-routes/$routeId', body: body);

  Future<AirmiusJson> duplicateSportRoute(int routeId) =>
      _json('POST', '/api/v1/sport-routes/$routeId/duplicate');

  Future<AirmiusJson> generateSportRouteProposal(AirmiusJson body) =>
      _json('POST', '/api/v1/sport-route-proposals', body: body);

  Future<AirmiusJson> deleteSportRoute(int routeId) =>
      _json('DELETE', '/api/v1/sport-routes/$routeId');

  Future<AirmiusJson> sportTracks({int page = 1, int perPage = 50}) => _json(
    'GET',
    '/api/v1/sport-tracks',
    query: {'page': '$page', 'per_page': '$perPage'},
  );

  Future<AirmiusJson> createSportTrack(AirmiusJson body) =>
      _json('POST', '/api/v1/sport-tracks', body: body);

  Future<AirmiusJson> updateSportTrack(int trackId, AirmiusJson body) =>
      _json('PATCH', '/api/v1/sport-tracks/$trackId', body: body);

  Future<AirmiusJson> appendSportTrackPoints(
    int trackId,
    List<AirmiusJson> points,
  ) => _json(
    'POST',
    '/api/v1/sport-tracks/$trackId/points',
    body: {'track_points': points},
  );

  Future<AirmiusJson> completeSportTrack(
    int trackId, {
    int? activeDurationSeconds,
  }) => _json(
    'POST',
    '/api/v1/sport-tracks/$trackId/complete',
    body: {'active_duration_seconds': ?activeDurationSeconds},
  );

  Future<AirmiusJson> deleteSportTrack(int trackId) =>
      _json('DELETE', '/api/v1/sport-tracks/$trackId');

  Future<AirmiusJson> sportPlaces({int page = 1, int perPage = 50}) => _json(
    'GET',
    '/api/v1/sport-places',
    query: {'page': '$page', 'per_page': '$perPage'},
  );

  Future<AirmiusJson> sportPlace(int placeId) =>
      _json('GET', '/api/v1/sport-places/$placeId');

  Future<AirmiusJson> sportProfiles() => _json('GET', '/api/v1/sport-profiles');

  Future<AirmiusJson> sportCv() => _json('GET', '/api/v1/users/me/sport-cv');

  Future<AirmiusJson> sportCvForUser(int userId) =>
      _json('GET', '/api/v1/users/$userId/sport-cv');

  Future<AirmiusJson> followUser(int userId) =>
      _json('POST', '/api/v1/users/$userId/follow');

  Future<AirmiusJson> unfollowUser(int userId) =>
      _json('DELETE', '/api/v1/users/$userId/follow');

  Future<AirmiusJson> blockUser(int userId) =>
      _json('POST', '/api/v1/users/$userId/block');

  Future<AirmiusJson> unblockUser(int userId) =>
      _json('DELETE', '/api/v1/users/$userId/block');

  Future<AirmiusJson> updateSportProfile(int sportId, AirmiusJson body) =>
      _json('PUT', '/api/v1/sport-profiles/$sportId', body: body);

  Future<AirmiusJson> deleteSportProfile(int sportId) =>
      _json('DELETE', '/api/v1/sport-profiles/$sportId');

  Future<AirmiusJson> updateSportSkill(int skillId, AirmiusJson body) =>
      _json('PATCH', '/api/v1/sport-skills/$skillId', body: body);

  Future<AirmiusJson> createSportPlace(AirmiusJson body) =>
      _json('POST', '/api/v1/sport-places', body: body);

  Future<AirmiusJson> updateSportPlace(int placeId, AirmiusJson body) =>
      _json('PATCH', '/api/v1/sport-places/$placeId', body: body);

  Future<AirmiusJson> deleteSportPlace(int placeId) =>
      _json('DELETE', '/api/v1/sport-places/$placeId');

  Future<AirmiusJson> friends() => _json('GET', '/api/v1/friends');

  /// Returns the authenticated user's maturity and onboarding overview.
  ///
  /// The endpoint is intentionally read-only and scoped server-side to the
  /// current account; no profile or moderation data is accepted from the
  /// client.
  Future<AirmiusJson> maturityOverview() =>
      _json('GET', '/api/v1/maturity/overview');

  Future<AirmiusJson> badges({int page = 1, int perPage = 50}) => _json(
    'GET',
    '/api/v1/badges',
    query: {'page': '$page', 'per_page': '$perPage'},
  );

  Future<AirmiusJson> badge(int awardId) =>
      _json('GET', '/api/v1/badges/$awardId');

  Future<AirmiusJson> learning({
    String? query,
    String? category,
    String? level,
    String? price,
  }) => _json(
    'GET',
    '/api/v1/learning',
    query: {
      if (query != null && query.trim().isNotEmpty) 'q': query.trim(),
      if (category != null && category.trim().isNotEmpty)
        'category': category.trim(),
      if (level != null && level.trim().isNotEmpty) 'level': level.trim(),
      if (price != null && price.trim().isNotEmpty) 'price': price.trim(),
    },
  );

  Future<AirmiusJson> learningCourse(int courseId) =>
      _json('GET', '/api/v1/learning/courses/$courseId');

  Future<AirmiusJson> enrollLearningCourse(int courseId) =>
      _json('POST', '/api/v1/learning/courses/$courseId/enroll');

  Future<AirmiusJson> completeLearningLesson(int courseId, int lessonId) =>
      _json(
        'PUT',
        '/api/v1/learning/courses/$courseId/lessons/$lessonId/complete',
      );

  Future<AirmiusJson> trackLearningLesson(
    int courseId,
    int lessonId,
    int watchSeconds,
  ) => _json(
    'PUT',
    '/api/v1/learning/courses/$courseId/lessons/$lessonId/progress',
    body: {'watch_seconds': watchSeconds},
  );

  Future<AirmiusJson> addLearningNote(
    int courseId,
    int lessonId,
    String body,
  ) => _json(
    'POST',
    '/api/v1/learning/courses/$courseId/lessons/$lessonId/notes',
    body: {'body': body},
  );

  Future<AirmiusJson> addLearningComment(
    int courseId,
    int lessonId,
    String body,
  ) => _json(
    'POST',
    '/api/v1/learning/courses/$courseId/lessons/$lessonId/comments',
    body: {'body': body},
  );

  Future<AirmiusJson> submitLearningQuiz(
    int courseId,
    int quizId,
    AirmiusJson answers,
  ) => _json(
    'POST',
    '/api/v1/learning/courses/$courseId/quizzes/$quizId/attempts',
    body: {'answers': answers},
  );

  Future<AirmiusJson> submitLearningAssignment(
    int courseId,
    int assignmentId, {
    String? body,
    String? attachmentUrl,
  }) => _json(
    'POST',
    '/api/v1/learning/courses/$courseId/assignments/$assignmentId/submissions',
    body: {
      if (body != null && body.trim().isNotEmpty) 'body': body.trim(),
      if (attachmentUrl != null && attachmentUrl.trim().isNotEmpty)
        'attachment_url': attachmentUrl.trim(),
    },
  );

  Future<AirmiusJson> reviewLearningCourse(
    int courseId, {
    required int rating,
    String? body,
  }) => _json(
    'POST',
    '/api/v1/learning/courses/$courseId/reviews',
    body: {
      'rating': rating,
      if (body != null && body.trim().isNotEmpty) 'body': body.trim(),
    },
  );

  Future<AirmiusJson> learningCertificate(int certificateId) =>
      _json('GET', '/api/v1/learning/certificates/$certificateId');

  Future<AirmiusJson> learningStudio({int? courseId}) => _json(
    'GET',
    '/api/v1/learning-studio',
    query: {if (courseId != null) 'course': '$courseId'},
  );

  Future<AirmiusJson> createLearningStudioCourse(AirmiusJson body) =>
      _json('POST', '/api/v1/learning-studio/courses', body: body);

  Future<AirmiusJson> updateLearningStudioCourse(
    int courseId,
    AirmiusJson body,
  ) => _json('PUT', '/api/v1/learning-studio/courses/$courseId', body: body);

  Future<AirmiusJson> createLearningStudioSection(
    int courseId,
    AirmiusJson body,
  ) => _json(
    'POST',
    '/api/v1/learning-studio/courses/$courseId/sections',
    body: body,
  );

  Future<AirmiusJson> createLearningStudioLesson(
    int courseId,
    AirmiusJson body,
  ) => _json(
    'POST',
    '/api/v1/learning-studio/courses/$courseId/lessons',
    body: body,
  );

  Future<AirmiusJson> updateLearningStudioLesson(
    int courseId,
    int lessonId,
    AirmiusJson body,
  ) => _json(
    'PUT',
    '/api/v1/learning-studio/courses/$courseId/lessons/$lessonId',
    body: body,
  );

  Future<AirmiusJson> deleteLearningStudioLesson(int courseId, int lessonId) =>
      _json(
        'DELETE',
        '/api/v1/learning-studio/courses/$courseId/lessons/$lessonId',
      );

  Future<AirmiusJson> reorderLearningStudioLessons(
    int courseId,
    List<AirmiusJson> lessons,
  ) => _json(
    'PUT',
    '/api/v1/learning-studio/courses/$courseId/lessons/reorder',
    body: {'lessons': lessons},
  );

  Future<AirmiusJson> updateLearningStudioQuestion(
    int courseId,
    int commentId,
    String status,
  ) => _json(
    'PUT',
    '/api/v1/learning-studio/courses/$courseId/comments/$commentId',
    body: {'status': status},
  );

  Future<AirmiusJson> replyLearningStudioQuestion(
    int courseId,
    int commentId,
    String body,
  ) => _json(
    'POST',
    '/api/v1/learning-studio/courses/$courseId/comments/$commentId/replies',
    body: {'body': body.trim()},
  );

  Future<AirmiusJson> createLearningStudioQuiz(
    int courseId,
    AirmiusJson body,
  ) => _json(
    'POST',
    '/api/v1/learning-studio/courses/$courseId/quizzes',
    body: body,
  );

  Future<AirmiusJson> deleteLearningStudioQuiz(int courseId, int quizId) =>
      _json(
        'DELETE',
        '/api/v1/learning-studio/courses/$courseId/quizzes/$quizId',
      );

  Future<AirmiusJson> createLearningStudioAssignment(
    int courseId,
    AirmiusJson body,
  ) => _json(
    'POST',
    '/api/v1/learning-studio/courses/$courseId/assignments',
    body: body,
  );

  Future<AirmiusJson> gradeLearningStudioAssignment(
    int courseId,
    int submissionId,
    AirmiusJson body,
  ) => _json(
    'PUT',
    '/api/v1/learning-studio/courses/$courseId/assignment-submissions/$submissionId',
    body: body,
  );

  Future<AirmiusJson> createLearningStudioCoupon(
    int courseId,
    AirmiusJson body,
  ) => _json(
    'POST',
    '/api/v1/learning-studio/courses/$courseId/coupons',
    body: body,
  );

  Future<AirmiusJson> grantLearningStudioEnrollment(
    int courseId,
    String email,
  ) => _json(
    'POST',
    '/api/v1/learning-studio/courses/$courseId/enrollments',
    body: {'email': email.trim()},
  );

  Future<AirmiusJson> revokeLearningStudioEnrollment(
    int courseId,
    int enrollmentId,
  ) => _json(
    'PUT',
    '/api/v1/learning-studio/courses/$courseId/enrollments/$enrollmentId/revoke',
  );

  Future<AirmiusJson> publicBlog({
    String? query,
    String? category,
    int page = 1,
  }) => _json(
    'GET',
    '/api/v1/public/blog',
    query: {
      'page': '$page',
      if (query != null && query.trim().isNotEmpty) 'q': query.trim(),
      if (category != null && category.trim().isNotEmpty)
        'category': category.trim(),
    },
  );

  Future<AirmiusJson> publicBlogPost(String slug) =>
      _json('GET', '/api/v1/public/blog/${Uri.encodeComponent(slug)}');

  Future<AirmiusJson> publicSponsors() =>
      _json('GET', '/api/v1/public/sponsors');

  Future<AirmiusJson> publicActiveAd({String placement = 'feed'}) =>
      _json('GET', '/ads/active', query: {'placement': placement});

  Future<AirmiusJson> publicRecruitingJobs({
    String? query,
    String? type,
    String? sportType,
    String? address,
    String? sort,
    int page = 1,
  }) => _json(
    'GET',
    '/api/v1/public/recruiting/jobs',
    query: {
      'page': '$page',
      if (query != null && query.trim().isNotEmpty) 'q': query.trim(),
      if (type != null && type.trim().isNotEmpty) 'type': type.trim(),
      if (sportType != null && sportType.trim().isNotEmpty)
        'sport_type': sportType.trim(),
      if (address != null && address.trim().isNotEmpty)
        'address': address.trim(),
      if (sort != null && sort.trim().isNotEmpty) 'sort': sort.trim(),
    },
  );

  Future<AirmiusJson> submitPublicRecruitingInterest(
    int jobId,
    AirmiusJson body, {
    String? idempotencyKey,
  }) => _json(
    'POST',
    '/api/v1/public/recruiting/jobs/$jobId/interest',
    body: body,
    headers: {
      if (idempotencyKey != null && idempotencyKey.trim().isNotEmpty)
        'Idempotency-Key': idempotencyKey.trim(),
    },
  );

  Future<AirmiusJson> submitPublicAgencyRequest(
    AirmiusJson body, {
    String? idempotencyKey,
  }) => _json(
    'POST',
    '/api/v1/public/agency/requests',
    body: body,
    headers: {
      if (idempotencyKey != null && idempotencyKey.trim().isNotEmpty)
        'Idempotency-Key': idempotencyKey.trim(),
    },
  );

  Future<AirmiusJson> sponsorWorkspace() =>
      _json('GET', '/api/v1/sponsor-workspace');

  Future<AirmiusJson> updateSponsorWorkspaceProfile(AirmiusJson body) =>
      _json('PUT', '/api/v1/sponsor-workspace/profile', body: body);

  Future<AirmiusJson> recruitingPipeline({
    String? status,
    int? jobId,
    String? query,
    int page = 1,
  }) => _json(
    'GET',
    '/api/v1/recruiting-pipeline',
    query: {
      'page': '$page',
      if (status != null && status.isNotEmpty) 'status': status,
      if (jobId != null && jobId > 0) 'job_id': '$jobId',
      if (query != null && query.trim().isNotEmpty) 'q': query.trim(),
    },
  );

  Future<AirmiusJson> updateRecruitingApplication(
    int applicationId,
    AirmiusJson body,
  ) => _json(
    'PUT',
    '/api/v1/recruiting-pipeline/applications/$applicationId',
    body: body,
  );

  Future<AirmiusJson> deleteRecruitingApplication(int applicationId) => _json(
    'DELETE',
    '/api/v1/recruiting-pipeline/applications/$applicationId',
  );

  Future<AirmiusJson> openRecruitingApplicationChat(int applicationId) => _json(
    'POST',
    '/api/v1/recruiting-pipeline/applications/$applicationId/chat',
  );

  Future<AirmiusJson> publicClubs({
    String? query,
    String? sport,
    String? location,
  }) => _json(
    'GET',
    '/api/v1/public/clubs',
    query: {
      if (query != null && query.trim().isNotEmpty) 'q': query.trim(),
      if (sport != null && sport.trim().isNotEmpty) 'sport': sport.trim(),
      if (location != null && location.trim().isNotEmpty)
        'location': location.trim(),
    },
  );

  Future<AirmiusJson> publicMarketplace({
    String? query,
    String? category,
    String? sort,
    int page = 1,
  }) => _json(
    'GET',
    '/api/v1/public/marketplace',
    query: {
      'page': '$page',
      if (query != null && query.trim().isNotEmpty) 'search': query.trim(),
      if (category != null && category.trim().isNotEmpty)
        'category': category.trim(),
      if (sort != null && sort.trim().isNotEmpty) 'sort': sort.trim(),
    },
  );

  Future<AirmiusJson> publicLearningCourses({
    String? query,
    String? category,
    String? level,
    String? price,
    int page = 1,
  }) => _json(
    'GET',
    '/api/v1/public/learning/courses',
    query: {
      'page': '$page',
      if (query != null && query.trim().isNotEmpty) 'q': query.trim(),
      if (category != null && category.trim().isNotEmpty)
        'category': category.trim(),
      if (level != null && level.trim().isNotEmpty) 'level': level.trim(),
      if (price != null && price.trim().isNotEmpty) 'price': price.trim(),
    },
  );

  Future<AirmiusJson> publicCertificate(String code) => _json(
    'GET',
    '/api/v1/public/learning/certificates/${Uri.encodeComponent(code.trim())}',
  );

  Future<AirmiusJson> editorialPosts({String? query, String? status}) => _json(
    'GET',
    '/api/v1/editorial/posts',
    query: {
      if (query != null && query.trim().isNotEmpty) 'q': query.trim(),
      if (status != null && status.trim().isNotEmpty) 'status': status.trim(),
    },
  );

  Future<AirmiusJson> createEditorialPost(AirmiusJson body) =>
      _json('POST', '/api/v1/editorial/posts', body: body);

  Future<AirmiusJson> updateEditorialPost(int postId, AirmiusJson body) =>
      _json('PUT', '/api/v1/editorial/posts/$postId', body: body);

  Future<AirmiusJson> deleteEditorialPost(int postId) =>
      _json('DELETE', '/api/v1/editorial/posts/$postId');

  Future<AirmiusJson> createEditorialCategory(AirmiusJson body) =>
      _json('POST', '/api/v1/editorial/categories', body: body);

  Future<AirmiusJson> updateEditorialCategory(
    int categoryId,
    AirmiusJson body,
  ) => _json('PUT', '/api/v1/editorial/categories/$categoryId', body: body);

  Future<AirmiusJson> deleteEditorialCategory(int categoryId) =>
      _json('DELETE', '/api/v1/editorial/categories/$categoryId');

  Future<AirmiusJson> sponsorManagement() =>
      _json('GET', '/api/v1/sponsor-management');

  Future<AirmiusJson> createManagedSponsor(AirmiusJson body) =>
      _json('POST', '/api/v1/sponsor-management', body: body);

  Future<AirmiusJson> updateManagedSponsor(int sponsorId, AirmiusJson body) =>
      _json('PUT', '/api/v1/sponsor-management/$sponsorId', body: body);

  Future<AirmiusJson> deleteManagedSponsor(int sponsorId) =>
      _json('DELETE', '/api/v1/sponsor-management/$sponsorId');

  Future<AirmiusJson> rides() => _json('GET', '/api/v1/rides');

  Future<AirmiusJson> createRide(AirmiusJson body) =>
      _json('POST', '/api/v1/rides', body: body);

  Future<AirmiusJson> updateRide(int rideId, AirmiusJson body) =>
      _json('PUT', '/api/v1/rides/$rideId', body: body);

  Future<AirmiusJson> requestRide(int rideId, {String? message}) => _json(
    'POST',
    '/api/v1/rides/$rideId/join',
    body: {
      if (message != null && message.trim().isNotEmpty)
        'message': message.trim(),
    },
  );

  Future<AirmiusJson> leaveRide(int rideId) =>
      _json('POST', '/api/v1/rides/$rideId/leave');

  Future<AirmiusJson> approveRideRequest(int rideId, int userId) =>
      _json('POST', '/api/v1/rides/$rideId/requests/$userId/approve');

  Future<AirmiusJson> rejectRideRequest(int rideId, int userId) =>
      _json('POST', '/api/v1/rides/$rideId/requests/$userId/reject');

  Future<AirmiusJson> removeRideMember(int rideId, int userId) =>
      _json('DELETE', '/api/v1/rides/$rideId/members/$userId');

  Future<AirmiusJson> deleteRide(int rideId) =>
      _json('DELETE', '/api/v1/rides/$rideId');

  Future<AirmiusJson> inviteFriend({String? email, int? userId}) => _json(
    'POST',
    '/api/v1/friends/invitations',
    body: {
      if (email != null && email.trim().isNotEmpty) 'email': email.trim(),
      'user_id': ?userId,
    },
  );

  Future<AirmiusJson> friendInvitationByToken(String token) => _json(
    'GET',
    '/api/v1/friends/invitations/token/${Uri.encodeComponent(token)}',
  );

  Future<AirmiusJson> acceptFriendInvitationByToken(String token) => _json(
    'POST',
    '/api/v1/friends/invitations/token/${Uri.encodeComponent(token)}/accept',
  );

  Future<AirmiusJson> declineFriendInvitationByToken(String token) => _json(
    'POST',
    '/api/v1/friends/invitations/token/${Uri.encodeComponent(token)}/decline',
  );

  Future<AirmiusJson> acceptFriendInvitation(int invitationId) =>
      _json('POST', '/api/v1/friends/invitations/$invitationId/accept');

  Future<AirmiusJson> declineFriendInvitation(int invitationId) =>
      _json('POST', '/api/v1/friends/invitations/$invitationId/decline');

  Future<AirmiusJson> withdrawFriendInvitation(int invitationId) =>
      _json('DELETE', '/api/v1/friends/invitations/$invitationId');

  Future<AirmiusJson> removeFriend(int userId) =>
      _json('DELETE', '/api/v1/friends/$userId');

  Future<AirmiusJson> commerceProducts({int page = 1, int perPage = 50}) =>
      _json(
        'GET',
        '/api/v1/commerce/products',
        query: {'page': '$page', 'per_page': '$perPage'},
      );

  Future<AirmiusJson> commerceProduct(int productId) =>
      _json('GET', '/api/v1/commerce/products/$productId');

  Future<AirmiusJson> commerceProductReviews(int productId, {int page = 1}) =>
      _json(
        'GET',
        '/api/v1/commerce/products/$productId/reviews',
        query: {'page': '$page'},
      );

  Future<AirmiusJson> createCommerceProductReview(
    int productId, {
    required int rating,
    String? title,
    String? body,
  }) => _json(
    'POST',
    '/api/v1/commerce/products/$productId/reviews',
    body: {
      'rating': rating,
      if (title != null && title.trim().isNotEmpty) 'title': title.trim(),
      if (body != null && body.trim().isNotEmpty) 'body': body.trim(),
    },
  );

  Future<AirmiusJson> commerceWishlist({int page = 1, int perPage = 50}) =>
      _json(
        'GET',
        '/api/v1/commerce/wishlist',
        query: {'page': '$page', 'per_page': '$perPage'},
      );

  Future<AirmiusJson> addCommerceWishlist(int productId) =>
      _json('POST', '/api/v1/commerce/products/$productId/wishlist');

  Future<AirmiusJson> removeCommerceWishlist(int productId) =>
      _json('DELETE', '/api/v1/commerce/products/$productId/wishlist');

  Future<AirmiusJson> commerceCart() => _json('GET', '/api/v1/commerce/cart');

  Future<AirmiusJson> addCommerceCartItem(int productId, {int quantity = 1}) =>
      _json(
        'POST',
        '/api/v1/commerce/cart/items/$productId',
        body: {'quantity': quantity},
      );

  Future<AirmiusJson> updateCommerceCartItem(
    int itemId, {
    required int quantity,
  }) => _json(
    'PATCH',
    '/api/v1/commerce/cart/items/$itemId',
    body: {'quantity': quantity},
  );

  Future<AirmiusJson> removeCommerceCartItem(int itemId) =>
      _json('DELETE', '/api/v1/commerce/cart/items/$itemId');

  Future<AirmiusJson> checkoutCommerceCart(AirmiusJson body) =>
      _json('POST', '/api/v1/commerce/cart/checkout', body: body);

  Future<AirmiusJson> commerceOrders({int page = 1, int perPage = 50}) => _json(
    'GET',
    '/api/v1/commerce/orders',
    query: {'page': '$page', 'per_page': '$perPage'},
  );

  Future<AirmiusJson> commerceOrder(int orderId) =>
      _json('GET', '/api/v1/commerce/orders/$orderId');

  Future<AirmiusJson> cancelCommerceOrder(int orderId) =>
      _json('POST', '/api/v1/commerce/orders/$orderId/cancel');

  Future<AirmiusJson> reportCommerceOrderIssue(
    int orderId, {
    required String note,
  }) => _json(
    'POST',
    '/api/v1/commerce/orders/$orderId/issue',
    body: {'issue_note': note.trim()},
  );

  Future<AirmiusJson> requestCommerceOrderReturn(
    int orderId, {
    required String reason,
    int? itemId,
    int quantity = 1,
  }) => _json(
    'POST',
    '/api/v1/commerce/orders/$orderId/returns',
    body: {
      'reason': reason.trim(),
      'quantity': quantity,
      'commerce_order_item_id': ?itemId,
    },
  );

  Future<AirmiusJson> commerceSellerDashboard() =>
      _json('GET', '/api/v1/commerce/seller');

  Future<AirmiusJson> submitCommerceSellerApplication(AirmiusJson body) =>
      _json('POST', '/api/v1/commerce/seller/application', body: body);

  Future<AirmiusJson> updateCommerceProviderProfile(AirmiusJson body) =>
      _json('PUT', '/api/v1/commerce/seller/provider-profile', body: body);

  Future<AirmiusJson> createCommerceProviderLocation(AirmiusJson body) =>
      _json('POST', '/api/v1/commerce/seller/provider-locations', body: body);

  Future<AirmiusJson> updateCommerceProviderLocation(
    int locationId,
    AirmiusJson body,
  ) => _json(
    'PUT',
    '/api/v1/commerce/seller/provider-locations/$locationId',
    body: body,
  );

  Future<AirmiusJson> deleteCommerceProviderLocation(int locationId) =>
      _json('DELETE', '/api/v1/commerce/seller/provider-locations/$locationId');

  Future<AirmiusJson> updateCommercePayoutProfile(AirmiusJson body) =>
      _json('PUT', '/api/v1/commerce/seller/payout-profile', body: body);

  Future<AirmiusJson> requestCommercePayout({
    required String method,
    String? notes,
  }) => _json(
    'POST',
    '/api/v1/commerce/seller/payouts',
    body: {
      'method': method,
      if (notes != null && notes.trim().isNotEmpty) 'notes': notes.trim(),
    },
  );

  Future<AirmiusJson> createCommerceSellerProduct(AirmiusJson body) =>
      _json('POST', '/api/v1/commerce/seller/products', body: body);

  Future<AirmiusJson> updateCommerceSellerProduct(
    int productId,
    AirmiusJson body,
  ) => _json('PUT', '/api/v1/commerce/seller/products/$productId', body: body);

  Future<AirmiusJson> updateCommerceSellerProductStatus(
    int productId,
    String status,
  ) => _json(
    'PATCH',
    '/api/v1/commerce/seller/products/$productId/status',
    body: {'status': status},
  );

  Future<AirmiusJson> deleteCommerceSellerProduct(int productId) =>
      _json('DELETE', '/api/v1/commerce/seller/products/$productId');

  Future<AirmiusJson> createCommerceCampaign(AirmiusJson body) =>
      _json('POST', '/api/v1/commerce/seller/campaigns', body: body);

  Future<AirmiusJson> updateCommerceCampaign(
    int campaignId,
    AirmiusJson body,
  ) =>
      _json('PUT', '/api/v1/commerce/seller/campaigns/$campaignId', body: body);

  Future<AirmiusJson> updateCommerceCampaignStatus(
    int campaignId,
    String status,
  ) => _json(
    'PATCH',
    '/api/v1/commerce/seller/campaigns/$campaignId/status',
    body: {'status': status},
  );

  Future<AirmiusJson> deleteCommerceCampaign(int campaignId) => _json(
    'DELETE',
    '/api/v1/commerce/seller/campaigns/$campaignId',
    body: {'confirmation': 'delete'},
  );

  Future<AirmiusJson> createCommerceWebsiteRequest(AirmiusJson body) =>
      _json('POST', '/api/v1/commerce/seller/website-requests', body: body);

  Future<AirmiusJson> sendSupportContact(AirmiusJson body) =>
      _json('POST', '/api/v1/support/contact', body: body);

  Future<AirmiusJson> supportTickets() =>
      _json('GET', '/api/v1/support/tickets');

  Future<AirmiusJson> createSupportTicket(AirmiusJson body) =>
      _json('POST', '/api/v1/support/tickets', body: body);

  Future<AirmiusJson> adminSupportTickets({
    String? status,
    String? priority,
    bool overdue = false,
  }) => _json(
    'GET',
    '/api/v1/admin/support/tickets',
    query: {
      if (status != null && status.isNotEmpty) 'status': status,
      if (priority != null && priority.isNotEmpty) 'priority': priority,
      if (overdue) 'overdue': '1',
    },
  );

  Future<AirmiusJson> adminUpdateSupportTicket(
    int ticketId,
    AirmiusJson body,
  ) => _json('PATCH', '/api/v1/admin/support/tickets/$ticketId', body: body);

  Future<AirmiusJson> sendPublicContact(AirmiusJson body) =>
      _json('POST', '/api/v1/public/contact', body: body);

  Future<AirmiusJson> adminCommerceDashboard() =>
      _json('GET', '/api/v1/admin/commerce');

  Future<AirmiusJson> adminPlatformDashboard() =>
      _json('GET', '/api/v1/admin/platform');

  Future<AirmiusJson> adminCreatePlatformUser(AirmiusJson body) =>
      _json('POST', '/api/v1/admin/platform/users', body: body);

  Future<AirmiusJson> adminUpdateUserStatus(int userId, AirmiusJson body) =>
      _json('PATCH', '/api/v1/admin/platform/users/$userId/status', body: body);

  Future<AirmiusJson> adminApproveClub(int clubId, AirmiusJson body) => _json(
    'PATCH',
    '/api/v1/admin/platform/clubs/$clubId/approve',
    body: body,
  );

  Future<AirmiusJson> adminRejectClub(int clubId, AirmiusJson body) =>
      _json('PATCH', '/api/v1/admin/platform/clubs/$clubId/reject', body: body);

  Future<AirmiusJson> adminCreateSport(AirmiusJson body) =>
      _json('POST', '/api/v1/admin/platform/sports', body: body);

  Future<AirmiusJson> adminUpdateSport(int sportId, AirmiusJson body) =>
      _json('PATCH', '/api/v1/admin/platform/sports/$sportId', body: body);

  Future<AirmiusJson> adminDeleteSport(int sportId) => _json(
    'DELETE',
    '/api/v1/admin/platform/sports/$sportId',
    body: {'confirmation': 'delete'},
  );

  Future<AirmiusJson> adminCreateBadge(AirmiusJson body) =>
      _json('POST', '/api/v1/admin/platform/badges', body: body);

  Future<AirmiusJson> adminUpdateBadge(int badgeId, AirmiusJson body) =>
      _json('PATCH', '/api/v1/admin/platform/badges/$badgeId', body: body);

  Future<AirmiusJson> adminDeleteBadge(int badgeId) =>
      _json('DELETE', '/api/v1/admin/platform/badges/$badgeId');

  Future<AirmiusJson> adminCreateRole(AirmiusJson body) =>
      _json('POST', '/api/v1/admin/platform/roles', body: body);

  Future<AirmiusJson> adminUpdateRole(int roleId, AirmiusJson body) =>
      _json('PATCH', '/api/v1/admin/platform/roles/$roleId', body: body);

  Future<AirmiusJson> adminDeleteRole(int roleId) =>
      _json('DELETE', '/api/v1/admin/platform/roles/$roleId');

  Future<AirmiusJson> adminCreatePermission(AirmiusJson body) =>
      _json('POST', '/api/v1/admin/platform/permissions', body: body);

  Future<AirmiusJson> adminUpdateModerationFlag(int flagId, AirmiusJson body) =>
      _json(
        'PATCH',
        '/api/v1/admin/platform/moderation/flags/$flagId',
        body: body,
      );

  Future<AirmiusJson> adminUpdateModerationReport(
    int reportId,
    AirmiusJson body,
  ) => _json(
    'PATCH',
    '/api/v1/admin/platform/moderation/reports/$reportId',
    body: body,
  );

  Future<AirmiusJson> adminDecideModerationAppeal(
    int reportId,
    AirmiusJson body,
  ) => _json(
    'PATCH',
    '/api/v1/admin/platform/moderation/reports/$reportId/appeal',
    body: body,
  );

  Future<AirmiusJson> adminUpdateGamificationRule(
    int ruleId,
    AirmiusJson body,
  ) => _json(
    'PATCH',
    '/api/v1/admin/platform/gamification-rules/$ruleId',
    body: body,
  );

  Future<AirmiusJson> adminCommerceCatalog() =>
      _json('GET', '/api/v1/admin/commerce/catalog');

  Future<AirmiusJson> adminUpdatePublicContactRequest(
    int requestId,
    AirmiusJson body,
  ) => _json(
    'PATCH',
    '/api/v1/admin/commerce/public-contact-requests/$requestId',
    body: body,
  );

  Future<AirmiusJson> adminCommerceExport() =>
      _json('GET', '/api/v1/admin/commerce/export');

  Future<AirmiusJson> adminCreateCommerceProduct(AirmiusJson body) =>
      _json('POST', '/api/v1/admin/commerce/products', body: body);

  Future<AirmiusJson> adminEditCommerceProduct(
    int productId,
    AirmiusJson body,
  ) => _json('PUT', '/api/v1/admin/commerce/products/$productId', body: body);

  Future<AirmiusJson> adminDeleteCommerceProduct(int productId) =>
      _json('DELETE', '/api/v1/admin/commerce/products/$productId');

  Future<AirmiusJson> adminAdjustCommerceProductStock(
    int productId,
    AirmiusJson body,
  ) => _json(
    'POST',
    '/api/v1/admin/commerce/products/$productId/stock',
    body: body,
  );

  Future<AirmiusJson> adminUpdateCommerceProduct(
    int productId,
    AirmiusJson body,
  ) => _json(
    'PATCH',
    '/api/v1/admin/commerce/products/$productId/status',
    body: body,
  );

  Future<AirmiusJson> adminCreateCommerceCoupon(AirmiusJson body) =>
      _json('POST', '/api/v1/admin/commerce/coupons', body: body);

  Future<AirmiusJson> adminUpdateCommerceCoupon(
    int couponId,
    AirmiusJson body,
  ) => _json('PATCH', '/api/v1/admin/commerce/coupons/$couponId', body: body);

  Future<AirmiusJson> adminCreateCommerceAddon(AirmiusJson body) =>
      _json('POST', '/api/v1/admin/commerce/addons', body: body);

  Future<AirmiusJson> adminUpdateCommerceAddon(int addonId, AirmiusJson body) =>
      _json('PATCH', '/api/v1/admin/commerce/addons/$addonId', body: body);

  Future<AirmiusJson> adminCreateCommerceTaxRate(AirmiusJson body) =>
      _json('POST', '/api/v1/admin/commerce/tax-rates', body: body);

  Future<AirmiusJson> adminUpdateCommerceTaxRate(
    int taxRateId,
    AirmiusJson body,
  ) =>
      _json('PATCH', '/api/v1/admin/commerce/tax-rates/$taxRateId', body: body);

  Future<AirmiusJson> adminCreateCommerceShippingRate(AirmiusJson body) =>
      _json('POST', '/api/v1/admin/commerce/shipping-rates', body: body);

  Future<AirmiusJson> adminUpdateCommerceShippingRate(
    int shippingRateId,
    AirmiusJson body,
  ) => _json(
    'PATCH',
    '/api/v1/admin/commerce/shipping-rates/$shippingRateId',
    body: body,
  );

  Future<AirmiusJson> adminUpdateSellerApplication(
    int applicationId,
    AirmiusJson body,
  ) => _json(
    'PATCH',
    '/api/v1/admin/commerce/seller-applications/$applicationId',
    body: body,
  );

  Future<AirmiusJson> adminUpdateWebsiteRequest(
    int requestId,
    AirmiusJson body,
  ) => _json(
    'PATCH',
    '/api/v1/admin/commerce/website-requests/$requestId',
    body: body,
  );

  Future<AirmiusJson> adminCreateCommerceCampaign(AirmiusJson body) =>
      _json('POST', '/api/v1/admin/commerce/campaigns', body: body);

  Future<AirmiusJson> adminEditCommerceCampaign(
    int campaignId,
    AirmiusJson body,
  ) => _json('PUT', '/api/v1/admin/commerce/campaigns/$campaignId', body: body);

  Future<AirmiusJson> adminUpdateCampaign(int campaignId, AirmiusJson body) =>
      _json(
        'PATCH',
        '/api/v1/admin/commerce/campaigns/$campaignId/status',
        body: body,
      );

  Future<AirmiusJson> adminMarkCommerceOrderPaid(int orderId) =>
      _json('POST', '/api/v1/admin/commerce/orders/$orderId/mark-paid');

  Future<AirmiusJson> adminUpdateCommerceShipping(
    int orderId,
    AirmiusJson body,
  ) => _json(
    'PATCH',
    '/api/v1/admin/commerce/orders/$orderId/shipping',
    body: body,
  );

  Future<AirmiusJson> adminUpdateCommerceOrderIssue(
    int orderId,
    AirmiusJson body,
  ) => _json(
    'PATCH',
    '/api/v1/admin/commerce/orders/$orderId/issue',
    body: body,
  );

  Future<AirmiusJson> adminReplyCommerceOrderIssue(
    int orderId,
    AirmiusJson body,
  ) => _json(
    'POST',
    '/api/v1/admin/commerce/orders/$orderId/issue/reply',
    body: body,
  );

  Future<AirmiusJson> adminRefundCommerceOrder(int orderId, AirmiusJson body) =>
      _json(
        'POST',
        '/api/v1/admin/commerce/orders/$orderId/refund',
        body: body,
      );

  Future<AirmiusJson> adminCommerceOrderDocuments(int orderId) =>
      _json('GET', '/api/v1/admin/commerce/orders/$orderId/documents');

  Future<AirmiusJson> adminUpdateCommerceReturn(
    int returnId,
    AirmiusJson body,
  ) => _json('PATCH', '/api/v1/admin/commerce/returns/$returnId', body: body);

  Future<AirmiusJson> adminCreateCommercePayout(int userId, AirmiusJson body) =>
      _json('POST', '/api/v1/admin/commerce/payouts/users/$userId', body: body);

  Future<AirmiusJson> adminMarkCommercePayoutPaid(
    int payoutId, {
    String? notes,
  }) => _json(
    'PATCH',
    '/api/v1/admin/commerce/payouts/$payoutId/paid',
    body: {'notes': notes},
  );

  Future<AirmiusJson> adminUpdateCommercePayoutProfile(
    int profileId,
    AirmiusJson body,
  ) => _json(
    'PATCH',
    '/api/v1/admin/commerce/payout-profiles/$profileId',
    body: body,
  );

  Future<AirmiusJson> adminUpdateCommerceVisuals(AirmiusJson body) =>
      _json('PUT', '/api/v1/admin/commerce/marketplace-visuals', body: body);

  Future<AirmiusJson> adminUpdateCommerceCommissions(AirmiusJson body) => _json(
    'PUT',
    '/api/v1/admin/commerce/marketplace-commissions',
    body: body,
  );

  Future<AirmiusJson> adminUpdateCommerceSettings(AirmiusJson body) =>
      _json('PUT', '/api/v1/admin/commerce/settings', body: body);

  Future<AirmiusJson> adminBackofficeDashboard() =>
      _json('GET', '/api/v1/admin/backoffice');

  Future<AirmiusJson> adminUpdateSubscriptionPlan(
    int planId,
    AirmiusJson body,
  ) => _json('PATCH', '/api/v1/admin/backoffice/plans/$planId', body: body);

  Future<AirmiusJson> adminAssignUserSubscription(
    int userId,
    AirmiusJson body,
  ) => _json(
    'PUT',
    '/api/v1/admin/backoffice/users/$userId/subscription',
    body: body,
  );

  Future<AirmiusJson> adminAssignClubSubscription(
    int clubId,
    AirmiusJson body,
  ) => _json(
    'PUT',
    '/api/v1/admin/backoffice/clubs/$clubId/subscription',
    body: body,
  );

  Future<AirmiusJson> adminCancelUserSubscription(
    int subscriptionId, {
    String mode = 'period_end',
  }) => _json(
    'POST',
    '/api/v1/admin/backoffice/user-subscriptions/$subscriptionId/cancel',
    body: {'mode': mode},
  );

  Future<AirmiusJson> adminRenewUserSubscription(
    int subscriptionId, {
    int months = 1,
  }) => _json(
    'POST',
    '/api/v1/admin/backoffice/user-subscriptions/$subscriptionId/renew',
    body: {'months': months},
  );

  Future<AirmiusJson> adminCancelClubSubscription(
    int subscriptionId, {
    String mode = 'period_end',
  }) => _json(
    'POST',
    '/api/v1/admin/backoffice/club-subscriptions/$subscriptionId/cancel',
    body: {'mode': mode},
  );

  Future<AirmiusJson> adminRenewClubSubscription(
    int subscriptionId, {
    int months = 1,
  }) => _json(
    'POST',
    '/api/v1/admin/backoffice/club-subscriptions/$subscriptionId/renew',
    body: {'months': months},
  );

  Future<AirmiusJson> adminMarkSubscriptionTransferPaid(int checkoutId) =>
      _json('POST', '/api/v1/admin/backoffice/transfers/$checkoutId/mark-paid');

  Future<AirmiusJson> adminMarkSubscriptionInvoicePaid(int invoiceId) => _json(
    'POST',
    '/api/v1/admin/backoffice/subscription-invoices/$invoiceId/mark-paid',
  );

  Future<AirmiusJson> adminCreatePayment(AirmiusJson body) =>
      _json('POST', '/api/v1/admin/backoffice/payments', body: body);

  Future<AirmiusJson> adminDeletePayment(int paymentId) =>
      _json('DELETE', '/api/v1/admin/backoffice/payments/$paymentId');

  Future<AirmiusJson> adminCreateInvoice(AirmiusJson body) =>
      _json('POST', '/api/v1/admin/backoffice/invoices', body: body);

  Future<AirmiusJson> adminUpdateInvoiceStatus(int invoiceId, String status) =>
      _json(
        'PATCH',
        '/api/v1/admin/backoffice/invoices/$invoiceId/status',
        body: {'status': status},
      );

  Future<AirmiusJson> adminDeleteInvoice(int invoiceId) =>
      _json('DELETE', '/api/v1/admin/backoffice/invoices/$invoiceId');

  Future<AirmiusJson> adminCreateContract(AirmiusJson body) =>
      _json('POST', '/api/v1/admin/backoffice/contracts', body: body);

  Future<AirmiusJson> adminUpdateContract(int contractId, AirmiusJson body) =>
      _json(
        'PATCH',
        '/api/v1/admin/backoffice/contracts/$contractId',
        body: body,
      );

  Future<AirmiusJson> adminDeleteContract(int contractId) =>
      _json('DELETE', '/api/v1/admin/backoffice/contracts/$contractId');

  Future<AirmiusJson> adminOutfitDashboard() =>
      _json('GET', '/api/v1/admin/outfits');

  Future<AirmiusJson> adminCreateOutfitPlan(AirmiusJson body) =>
      _json('POST', '/api/v1/admin/outfits/plans', body: body);

  Future<AirmiusJson> adminUpdateOutfitPlan(int planId, AirmiusJson body) =>
      _json('PUT', '/api/v1/admin/outfits/plans/$planId', body: body);

  Future<AirmiusJson> adminDeleteOutfitPlan(int planId) =>
      _json('DELETE', '/api/v1/admin/outfits/plans/$planId');

  Future<AirmiusJson> adminUpdateOutfitVisuals(String heroSource) => _json(
    'POST',
    '/api/v1/admin/outfits/visuals',
    body: {'hero_source': heroSource},
  );

  Future<AirmiusJson> adminMarkOutfitSubscriptionPaid(
    int subscriptionId, {
    String? note,
  }) => _json(
    'POST',
    '/api/v1/admin/outfits/subscriptions/$subscriptionId/mark-paid',
    body: {'payment_note': ?note},
  );

  Future<AirmiusJson> adminMarkOutfitSubscriptionUnpaid(
    int subscriptionId, {
    String? dueAt,
    String? reason,
  }) => _json(
    'POST',
    '/api/v1/admin/outfits/subscriptions/$subscriptionId/mark-unpaid',
    body: {'payment_due_at': ?dueAt, 'reason': ?reason},
  );

  Future<AirmiusJson> adminUpdateOutfitShippingAddress(
    int subscriptionId,
    AirmiusJson body,
  ) => _json(
    'PUT',
    '/api/v1/admin/outfits/subscriptions/$subscriptionId/shipping-address',
    body: body,
  );

  Future<AirmiusJson> adminSendOutfitPaymentReminder(int subscriptionId) =>
      _json(
        'POST',
        '/api/v1/admin/outfits/subscriptions/$subscriptionId/payment-reminder',
      );

  Future<AirmiusJson> adminCancelOutfitSubscription(
    int subscriptionId, {
    String? reason,
  }) => _json(
    'POST',
    '/api/v1/admin/outfits/subscriptions/$subscriptionId/cancel',
    body: {'reason': ?reason},
  );

  Future<AirmiusJson> adminDeleteOutfitSubscription(int subscriptionId) =>
      _json(
        'DELETE',
        '/api/v1/admin/outfits/subscriptions/$subscriptionId',
        body: {'confirmation': 'delete'},
      );

  Future<AirmiusJson> adminUpdateOutfitDelivery(
    int deliveryId,
    AirmiusJson body,
  ) => _json('PUT', '/api/v1/admin/outfits/deliveries/$deliveryId', body: body);

  Future<AirmiusJson> adminUpdateOutfitDeliveryIssue(
    int deliveryId,
    AirmiusJson body,
  ) => _json(
    'PUT',
    '/api/v1/admin/outfits/deliveries/$deliveryId/issue',
    body: body,
  );

  Future<AirmiusJson> adminMarkOutfitDeliveryShipped(
    int deliveryId,
    AirmiusJson body,
  ) => _json(
    'POST',
    '/api/v1/admin/outfits/deliveries/$deliveryId/shipped',
    body: body,
  );

  Future<AirmiusJson> adminMarkOutfitDeliveryDelivered(int deliveryId) =>
      _json('POST', '/api/v1/admin/outfits/deliveries/$deliveryId/delivered');

  Future<AirmiusJson> adminDeleteOutfitDelivery(int deliveryId) => _json(
    'DELETE',
    '/api/v1/admin/outfits/deliveries/$deliveryId',
    body: {'confirmation': 'delete'},
  );

  Future<AirmiusJson> adminMailDashboard({String? status, String? type}) =>
      _json(
        'GET',
        '/api/v1/admin/mail',
        query: {
          if (status != null && status.isNotEmpty) 'status': status,
          if (type != null && type.isNotEmpty) 'type': type,
        },
      );

  Future<AirmiusJson> adminUpdateMailPreferences(AirmiusJson body) =>
      _json('PUT', '/api/v1/admin/mail/preferences', body: body);

  Future<AirmiusJson> adminUpdateMailSender(
    String category,
    AirmiusJson body,
  ) => _json('PUT', '/api/v1/admin/mail/senders/$category', body: body);

  Future<AirmiusJson> adminTestMailSender(String category) =>
      _json('POST', '/api/v1/admin/mail/senders/$category/test');

  Future<AirmiusJson> adminResendMailDelivery(
    int deliveryId,
    String category,
  ) => _json(
    'POST',
    '/api/v1/admin/mail/deliveries/$deliveryId/resend',
    body: {'category': category},
  );

  Future<AirmiusJson> adminResolveMailDelivery(int deliveryId) =>
      _json('PUT', '/api/v1/admin/mail/deliveries/$deliveryId/resolve');

  Future<AirmiusJson> adminSystemDashboard({String? month}) => _json(
    'GET',
    '/api/v1/admin/system',
    query: {if (month != null && month.isNotEmpty) 'month': month},
  );

  Future<AirmiusJson> adminUpdateSystemSettings(AirmiusJson body) =>
      _json('PUT', '/api/v1/admin/system/settings', body: body);

  Future<AirmiusJson> outfitSubscriptions() =>
      _json('GET', '/api/v1/outfit-subscriptions');

  Future<AirmiusJson> updateOutfitStyleProfile(AirmiusJson body) =>
      _json('PUT', '/api/v1/outfit-subscriptions/style-profile', body: body);

  Future<AirmiusJson> subscribeOutfitPlan(int planId, AirmiusJson body) =>
      _json('POST', '/api/v1/outfit-subscriptions/plans/$planId', body: body);

  Future<AirmiusJson> pauseOutfitSubscription(int subscriptionId) =>
      _json('POST', '/api/v1/outfit-subscriptions/$subscriptionId/pause');

  Future<AirmiusJson> resumeOutfitSubscription(int subscriptionId) =>
      _json('POST', '/api/v1/outfit-subscriptions/$subscriptionId/resume');

  Future<AirmiusJson> cancelOutfitSubscription(int subscriptionId) =>
      _json('POST', '/api/v1/outfit-subscriptions/$subscriptionId/cancel');

  Future<AirmiusJson> reportOutfitDeliveryIssue(
    int deliveryId, {
    required String type,
    required String description,
    String? requestedResolution,
    String? exchangeSize,
  }) => _json(
    'POST',
    '/api/v1/outfit-deliveries/$deliveryId/issue',
    body: {
      'issue_type': type,
      'issue_description': description.trim(),
      if (requestedResolution != null && requestedResolution.trim().isNotEmpty)
        'issue_requested_resolution': requestedResolution.trim(),
      if (exchangeSize != null && exchangeSize.trim().isNotEmpty)
        'issue_exchange_size': exchangeSize.trim(),
    },
  );

  Future<AirmiusJson> settings() => _json('GET', '/api/v1/settings');

  Future<AirmiusJson> privacyCenter() => _json('GET', '/api/v1/privacy');

  Future<AirmiusJson> updateSettings(AirmiusJson body) =>
      _json('PATCH', '/api/v1/settings', body: body);

  Future<AirmiusJson> privacyExport() => _json('GET', '/api/v1/privacy/export');

  Future<AirmiusJson> correctPrivacy(AirmiusJson body) =>
      _json('PATCH', '/api/v1/privacy/correction', body: body);

  Future<AirmiusJson> withdrawPrivacyConsents(List<String> consents) => _json(
    'POST',
    '/api/v1/privacy/withdraw-consents',
    body: {'consents': consents},
  );

  Future<AirmiusJson> requestDataErasureCode({
    required String identity,
    required List<String> categories,
  }) => _json(
    'POST',
    '/api/v1/privacy/data-erasure-code',
    body: {'identity': identity.trim(), 'categories': categories},
  );

  Future<AirmiusJson> erasePersonalData({
    required String code,
    required List<String> categories,
  }) => _json(
    'POST',
    '/api/v1/privacy/data-erasure',
    body: {'code': code.trim(), 'categories': categories},
  );

  Future<AirmiusJson> subscriptionPlans({String? targetActor}) => _json(
    'GET',
    '/api/v1/subscription-plans',
    query: {
      if (targetActor != null && targetActor.trim().isNotEmpty)
        'target_actor': targetActor.trim(),
    },
  );

  Future<AirmiusJson> subscriptions() => _json('GET', '/api/v1/subscriptions');

  Future<AirmiusJson> startSubscriptionCheckout(
    int planId, {
    required String provider,
    required String billingInterval,
    required bool acceptedTerms,
    int? clubId,
  }) => _json(
    'POST',
    '/api/v1/subscription-plans/$planId/checkout',
    body: {
      'provider': provider,
      'billing_interval': billingInterval,
      'accepted_terms': acceptedTerms,
      'club_id': ?clubId,
    },
  );

  Future<AirmiusJson> subscriptionCheckout(int checkoutId) =>
      _json('GET', '/api/v1/subscription-checkouts/$checkoutId');

  Future<AirmiusJson> cancelSubscriptionCheckout(int checkoutId) =>
      _json('POST', '/api/v1/subscription-checkouts/$checkoutId/cancel');

  Future<AirmiusJson> cancelUserSubscription(
    int subscriptionId, {
    String mode = 'period_end',
  }) => _json(
    'POST',
    '/api/v1/subscriptions/user/$subscriptionId/cancel',
    body: {'mode': mode},
  );

  Future<AirmiusJson> renewUserSubscription(
    int subscriptionId, {
    int months = 1,
  }) => _json(
    'POST',
    '/api/v1/subscriptions/user/$subscriptionId/renew',
    body: {'months': months},
  );

  Future<AirmiusJson> cancelClubSubscription(
    int clubId,
    int subscriptionId, {
    String mode = 'period_end',
  }) => _json(
    'POST',
    '/api/v1/clubs/$clubId/subscriptions/$subscriptionId/cancel',
    body: {'mode': mode},
  );

  Future<AirmiusJson> renewClubSubscription(
    int clubId,
    int subscriptionId, {
    int months = 1,
  }) => _json(
    'POST',
    '/api/v1/clubs/$clubId/subscriptions/$subscriptionId/renew',
    body: {'months': months},
  );

  Future<AirmiusJson> invoices() => _json('GET', '/api/v1/billing/invoices');

  Future<AirmiusJson> invoice(int invoiceId) =>
      _json('GET', '/api/v1/billing/invoices/$invoiceId');

  Future<AirmiusJson> mobileSync({AirmiusJson? body}) =>
      _json(body == null ? 'GET' : 'POST', '/api/v1/mobile/sync', body: body);

  Future<AirmiusJson> registerPushDevice(AirmiusJson body) =>
      _json('POST', '/api/v1/mobile/push-devices', body: body);

  Future<AirmiusJson> unregisterPushDevice(String deviceId) => _json(
    'DELETE',
    '/api/v1/mobile/push-devices/${Uri.encodeComponent(deviceId)}',
  );

  Future<AirmiusJson> _json(
    String method,
    String path, {
    AirmiusJson? body,
    Map<String, String> query = const {},
    AirmiusHeaders headers = const {},
  }) async {
    try {
      final response = await transport.send(
        AirmiusApiRequest(
          method: method,
          path: path,
          body: body,
          query: query,
          headers: {..._headers, ...headers},
        ),
      );
      if (!response.ok) {
        throw AirmiusApiException(
          statusCode: response.statusCode,
          body: response.body,
          path: path,
        );
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
    'Content-Type': 'application/json; charset=utf-8',
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
    if (origin == null || !origin.hasScheme || origin.host.isEmpty) {
      return trimmed;
    }

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
      return origin
          .replace(
            path: _withBasePath(origin, trimmed),
            query: null,
            fragment: null,
          )
          .toString();
    }

    final cleanPath = trimmed.replaceFirst(RegExp(r'^/+'), '');
    final path =
        cleanPath.startsWith('storage/') ||
            cleanPath.startsWith('build/') ||
            cleanPath.startsWith('images/')
        ? '/$cleanPath'
        : '/storage/$cleanPath';

    return origin
        .replace(path: _withBasePath(origin, path), query: null, fragment: null)
        .toString();
  }

  String _withBasePath(Uri base, String path) {
    final cleanBase = base.path == '/'
        ? ''
        : base.path.replaceFirst(RegExp(r'/$'), '');
    if (cleanBase.isEmpty || path.startsWith('$cleanBase/')) return path;
    return '$cleanBase$path';
  }

  bool _isLocalMediaHost(String host) {
    final normalized = host.toLowerCase();
    return normalized == 'localhost' ||
        normalized == '127.0.0.1' ||
        normalized == '::1';
  }
}

class AirmiusApiException implements Exception {
  const AirmiusApiException({
    required this.statusCode,
    required this.body,
    required this.path,
  });

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
            if (value is List && value.isNotEmpty) {
              final message = _safeServerMessage(value.first.toString());
              if (message != null) return message;
            }
            if (value is String && value.isNotEmpty) {
              final message = _safeServerMessage(value);
              if (message != null) return message;
            }
          }
        }

        final message = decoded['message'];
        if (message is String && message.trim().isNotEmpty) {
          final safe = _safeServerMessage(message);
          if (safe != null) return safe;
        }
      }
    } catch (_) {
      // Keep the plain text fallback below for non-JSON server responses.
    }

    if (trimmed.length <= 180 && !trimmed.startsWith('<')) {
      return _safeServerMessage(trimmed) ?? 'Serverfehler ($statusCode).';
    }
    return 'Serverfehler ($statusCode).';
  }

  /// Do not surface framework diagnostics, SQL fragments or filesystem paths
  /// in a mobile toast. Validation messages remain available, while accidental
  /// debug responses fall back to a generic status-only message.
  String? _safeServerMessage(String value) {
    final trimmed = value.trim();
    if (trimmed.isEmpty || trimmed.length > 180 || trimmed.startsWith('<')) {
      return null;
    }
    final lower = trimmed.toLowerCase();
    final looksTechnical = RegExp(
      r'(sqlstate|stack\s*trace|traceback|fatal\s+error|\bexception\b|vendor[/\\]|\bat\s+[/\\]|select\s+.+\s+from|insert\s+into|update\s+.+\s+set|delete\s+from)',
    ).hasMatch(lower);
    return looksTechnical ? null : trimmed;
  }

  @override
  String toString() => 'AirmiusApiException($statusCode, $path): $body';
}
