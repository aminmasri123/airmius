import 'dart:convert';
import 'dart:html';

import 'airmius_api_client.dart';

Future<AirmiusJson?> sendTeamCreateForm({
  required String baseUrl,
  required String token,
  required String locale,
  required AirmiusJson payload,
}) async {
  final uri = _uri(baseUrl);
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
      throw AirmiusApiException(statusCode: status, body: body, path: '/api/v1/teams');
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
      throw AirmiusApiException(statusCode: status, body: body, path: '/api/v1/teams');
    }
    throw AirmiusApiException(
      statusCode: 599,
      path: '/api/v1/teams',
      body: jsonEncode({
        'error': 'team_form_transport_failed',
        'message': 'Team konnte auch per einfachem Formular-Request nicht erstellt werden.',
        'details': error.toString(),
      }),
    );
  }
}

Uri _uri(String baseUrl) {
  final base = Uri.parse(baseUrl);
  final cleanBase = base.path.replaceFirst(RegExp(r'/$'), '');
  final path = cleanBase.endsWith('/api/v1')
      ? '$cleanBase/teams'
      : cleanBase.isEmpty
          ? '/api/v1/teams'
          : '$cleanBase/api/v1/teams';

  return base.replace(path: path, queryParameters: null, fragment: null);
}
