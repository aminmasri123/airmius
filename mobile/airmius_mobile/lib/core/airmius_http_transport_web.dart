import 'dart:convert';
import 'dart:html';

import 'airmius_api_client.dart';

class AirmiusHttpTransport implements AirmiusApiTransport {
  const AirmiusHttpTransport({required this.baseUrl});

  final String baseUrl;

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    final xhr = await HttpRequest.request(
      _uri(request).toString(),
      method: request.method,
      requestHeaders: request.headers,
      sendData: request.body == null ? null : jsonEncode(request.body),
    );
    final headers = <String, String>{};
    for (final line in (xhr.getAllResponseHeaders() ?? '').split('\n')) {
      final separator = line.indexOf(':');
      if (separator > 0) headers[line.substring(0, separator).trim()] = line.substring(separator + 1).trim();
    }
    return AirmiusApiResponse(statusCode: xhr.status ?? 0, body: xhr.responseText ?? '', headers: headers);
  }

  Uri _uri(AirmiusApiRequest request) {
    final base = Uri.parse(baseUrl);
    final path = request.path.startsWith('/') ? request.path.substring(1) : request.path;
    return base.replace(path: '${base.path.endsWith('/') ? base.path : '${base.path}/'}$path', queryParameters: request.query.isEmpty ? null : request.query);
  }
}
