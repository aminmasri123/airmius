import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';

import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_api_repositories.dart';

void main() {
  test(
    'membership request follow-up actions use the club-scoped API',
    () async {
      final transport = _MembershipFollowUpTransport();
      final repository = AirmiusApiMembershipRepository(
        AirmiusApiClient(
          transport: transport,
          baseUrl: 'https://example.test',
          token: 'token',
        ),
      );

      final information = await repository.requestClubRequestInformation(
        7,
        19,
        message: 'Please add the consent form.',
      );
      final response = await repository.respondToClubRequestInformation(
        7,
        19,
        message: 'The consent is confirmed.',
      );
      final waitlisted = await repository.waitlistClubRequest(
        7,
        19,
        reviewNote: 'Next place in October.',
      );

      expect(information.status, 'information_requested');
      expect(
        information.informationRequestMessage,
        'Please add the consent form.',
      );
      expect(response.status, 'pending');
      expect(response.applicantResponseMessage, 'The consent is confirmed.');
      expect(waitlisted.status, 'waitlisted');
      expect(waitlisted.waitlistedAt, isNotNull);
      expect(transport.requests, hasLength(3));
      expect(
        transport.requests[0].path,
        '/api/v1/clubs/7/membership-requests/19/request-information',
      );
      expect(
        transport.requests[1].path,
        '/api/v1/clubs/7/membership-requests/19/respond',
      );
      expect(
        transport.requests[2].path,
        '/api/v1/clubs/7/membership-requests/19/waitlist',
      );
    },
  );
}

class _MembershipFollowUpTransport implements AirmiusApiTransport {
  final List<AirmiusApiRequest> requests = [];

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    requests.add(request);
    final status = request.path.endsWith('/request-information')
        ? 'information_requested'
        : request.path.endsWith('/waitlist')
        ? 'waitlisted'
        : 'pending';
    final body = <String, Object?>{
      'data': <String, Object?>{
        'id': 19,
        'club_id': 7,
        'user_id': 23,
        'type': 'membership',
        'status': status,
        'created_at': '2026-09-26T10:00:00Z',
        if (status == 'information_requested')
          'information_request_message': 'Please add the consent form.',
        if (status == 'pending')
          'applicant_response_message': 'The consent is confirmed.',
        if (status == 'waitlisted') 'waitlisted_at': '2026-09-26T11:00:00Z',
      },
    };

    return AirmiusApiResponse(statusCode: 200, body: jsonEncode(body));
  }
}
