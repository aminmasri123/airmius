import 'dart:convert';
import 'dart:html';

import 'airmius_api_client.dart';

Future<AirmiusJson?> sendTeamCreateBridge({
  required String baseUrl,
  required String token,
  required String locale,
  required AirmiusJson payload,
}) async {
  final base = Uri.parse(baseUrl);
  final path = _bridgePath(base);
  final uri = base.replace(path: path, queryParameters: null);
  final formData = FormData()
    ..append('token', token)
    ..append('locale', locale);

  payload.forEach((key, value) {
    if (value != null) formData.append(key, '$value');
  });

  try {
    final xhr = await HttpRequest.request(
      uri.toString(),
      method: 'POST',
      sendData: formData,
    );

    final status = xhr.status ?? 0;
    final body = xhr.responseText ?? '';
    if (status < 200 || status >= 300) {
      throw AirmiusApiException(statusCode: status, body: body, path: '/api/v1/web-bridge/teams');
    }

    if (body.trim().isEmpty) return null;
    final decoded = jsonDecode(body);
    if (decoded is Map<String, dynamic>) return decoded;
    return {'data': decoded};
  } catch (error) {
    if (error is AirmiusApiException) rethrow;
    throw AirmiusApiException(
      statusCode: 599,
      body: jsonEncode({
        'error': 'bridge_failed',
        'message': 'Team konnte auch ueber den Flutter-Web-Bridge-Request nicht erstellt werden.',
        'details': error.toString(),
      }),
      path: '/api/v1/web-bridge/teams',
    );
  }
}

String _bridgePath(Uri base) {
  final cleanBase = base.path.replaceFirst(RegExp(r'/$'), '');
  if (cleanBase.endsWith('/api/v1')) return '$cleanBase/web-bridge/teams';
  if (cleanBase.isEmpty) return '/api/v1/web-bridge/teams';
  return '$cleanBase/api/v1/web-bridge/teams';
}
