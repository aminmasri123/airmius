import 'dart:convert';
import 'dart:html';
import 'dart:js_util' as js_util;

import 'airmius_api_client.dart';

bool sendTeamCreateBridge({
  required String baseUrl,
  required String token,
  required String locale,
  required AirmiusJson payload,
}) {
  final base = Uri.parse(baseUrl);
  final path = base.path.endsWith('/') ? '${base.path}api/v1/web-bridge/teams' : '${base.path}/api/v1/web-bridge/teams';
  final uri = base.replace(path: path, queryParameters: null);
  final body = jsonEncode({
    ...payload,
    'token': token,
    'locale': locale,
  });

  try {
    return js_util.callMethod<bool>(window.navigator, 'sendBeacon', [uri.toString(), body]);
  } catch (_) {
    return false;
  }
}
