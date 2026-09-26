import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';

import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_api_models.dart';

void main() {
  test('membership type change uses the dedicated club endpoint', () async {
    final transport = _MembershipChangeTransport();
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://example.test',
      token: 'token',
    );

    final response = await client.requestClubMembershipChange(7, {
      'club_membership_type_id': 12,
      'club_department_id': 4,
      'message': 'Please change my plan.',
    });

    expect(response['data']['type'], 'membership_change');
    expect(transport.request?.method, 'POST');
    expect(
      transport.request?.path,
      '/api/v1/clubs/7/membership-change-requests',
    );
    expect(transport.request?.body?['club_membership_type_id'], 12);
    expect(transport.request?.body?['club_department_id'], 4);
    expect(transport.request?.body?['message'], 'Please change my plan.');
  });

  test('club membership exposes the current type and department', () {
    final club = AirmiusClub.fromJson({
      'id': 7,
      'name': 'Airmius Club',
      'city': 'Berlin',
      'membership': {
        'club_membership_type_id': 12,
        'club_department_id': 4,
        'change_requested': false,
      },
    });

    expect(club.membershipTypeId, 12);
    expect(club.membershipDepartmentId, 4);
    expect(club.membershipChangeRequested, isFalse);
  });
}

class _MembershipChangeTransport implements AirmiusApiTransport {
  AirmiusApiRequest? request;

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    this.request = request;
    return AirmiusApiResponse(
      statusCode: 201,
      body: jsonEncode({
        'data': {
          'id': 19,
          'club_id': 7,
          'user_id': 23,
          'club_membership_type_id': 12,
          'type': 'membership_change',
          'status': 'pending',
          'created_at': '2026-09-26T10:00:00Z',
        },
      }),
    );
  }
}
