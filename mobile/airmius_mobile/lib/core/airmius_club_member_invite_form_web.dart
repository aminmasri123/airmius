// ignore_for_file: deprecated_member_use, avoid_web_libraries_in_flutter

import 'dart:convert';
import 'dart:html';

import 'airmius_api_client.dart';

Future<AirmiusJson?> sendClubMemberInviteForm({
  required String baseUrl,
  required String token,
  required String locale,
  required int clubId,
  required AirmiusJson payload,
}) async {
  final uri = _uri(baseUrl, clubId);
  final formData = FormData()
    ..append('token', token)
    ..append('locale', locale);

  payload.forEach((key, value) {
    if (value != null) formData.append(key, '$value');
  });

  final xhr = HttpRequest();
  try {
    xhr
      ..open('POST', uri.toString())
      ..setRequestHeader('Accept', 'application/json')
      ..send(formData);

    await xhr.onLoadEnd.first;
    final status = xhr.status ?? 0;
    final body = xhr.responseText ?? '';

    if (status < 200 || status >= 300) {
      throw AirmiusApiException(statusCode: status, body: body, path: '/api/v1/clubs/$clubId/members/invite-token');
    }

    if (body.trim().isEmpty) return null;
    final decoded = jsonDecode(body);
    if (decoded is Map<String, dynamic>) return decoded;
    return {'data': decoded};
  } catch (error) {
    if (error is AirmiusApiException) rethrow;
    final status = xhr.status ?? 0;
    final body = xhr.responseText ?? '';
    if (status > 0 || body.trim().isNotEmpty) {
      throw AirmiusApiException(statusCode: status, body: body, path: '/api/v1/clubs/$clubId/members/invite-token');
    }
    throw AirmiusApiException(
      statusCode: 599,
      path: '/api/v1/clubs/$clubId/members/invite-token',
      body: jsonEncode({
        'error': 'club_member_invite_form_transport_failed',
        'message': 'Vereinseinladung konnte auch per einfachem Formular-Request nicht gesendet werden.',
        'details': error.toString(),
      }),
    );
  }
}

Uri _uri(String baseUrl, int clubId) {
  final base = Uri.parse(baseUrl);
  final cleanBase = base.path.replaceFirst(RegExp(r'/$'), '');
  final path = cleanBase.endsWith('/api/v1')
      ? '$cleanBase/clubs/$clubId/members/invite-token'
      : cleanBase.isEmpty
          ? '/api/v1/clubs/$clubId/members/invite-token'
          : '$cleanBase/api/v1/clubs/$clubId/members/invite-token';

  return base.replace(path: path, queryParameters: null, fragment: null);
}
