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
  if (_isLocalFlutterOriginWithLiveApi(base)) {
    throw AirmiusApiException(
      statusCode: 599,
      path: '/api/v1/teams',
      body: jsonEncode({
        'error': 'wrong_api_environment',
        'message':
            'Flutter laeuft lokal, aber AIRMIUS_API_BASE_URL zeigt auf https://airmius.com. Lokale Laravel-Aenderungen sind dort nicht aktiv. Starte lokal mit AIRMIUS_API_BASE_URL=http://localhost oder deploye die API-Aenderungen auf airmius.com.',
      }),
    );
  }

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
      throw AirmiusApiException(statusCode: status, body: body, path: '/api/v1/teams');
    }

    if (body.trim().isEmpty) return null;
    final decoded = jsonDecode(body);
    if (decoded is Map<String, dynamic>) return decoded;
    return {'data': decoded};
  } catch (error) {
    if (error is AirmiusApiException) rethrow;
    throw AirmiusApiException(
      statusCode: 599,
      path: '/api/v1/teams',
      body: jsonEncode({
        'error': 'form_post_failed',
        'message': 'Der einfache Formular-POST auf /api/v1/teams wurde vom Browser oder Server blockiert.',
        'details': error.toString(),
      }),
    );
  }
}

bool _isLocalFlutterOriginWithLiveApi(Uri base) {
  final apiHost = base.host.toLowerCase();
  final appHost = window.location.hostname.toLowerCase();
  final localApp = appHost == 'localhost' || appHost == '127.0.0.1' || appHost == '0.0.0.0' || appHost == '::1';
  final liveApi = apiHost == 'airmius.com' || apiHost == 'www.airmius.com' || apiHost == 'app.airmius.com';
  return localApp && liveApi;
}

String _bridgePath(Uri base) {
  final cleanBase = base.path.replaceFirst(RegExp(r'/$'), '');
  if (cleanBase.endsWith('/api/v1')) return '$cleanBase/teams';
  if (cleanBase.isEmpty) return '/api/v1/teams';
  return '$cleanBase/api/v1/teams';
}
