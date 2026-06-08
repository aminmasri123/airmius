import 'dart:convert';
import 'dart:io';

import 'airmius_api_client.dart';

class AirmiusHttpTransport implements AirmiusApiTransport {
  const AirmiusHttpTransport({required this.baseUrl});

  final String baseUrl;

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    final client = HttpClient();
    try {
      final uri = _uri(request);
      final ioRequest = await client.openUrl(request.method, uri);
      request.headers.forEach(ioRequest.headers.set);
      if (request.body != null) {
        ioRequest.write(jsonEncode(request.body));
      }
      final response = await ioRequest.close();
      final body = await utf8.decodeStream(response);
      final headers = <String, String>{};
      response.headers.forEach((name, values) => headers[name] = values.join(','));
      return AirmiusApiResponse(statusCode: response.statusCode, body: body, headers: headers);
    } finally {
      client.close(force: true);
    }
  }

  Uri _uri(AirmiusApiRequest request) {
    final base = Uri.parse(baseUrl);
    final path = request.path.startsWith('/') ? request.path.substring(1) : request.path;
    return base.replace(path: '${base.path.endsWith('/') ? base.path : '${base.path}/'}$path', queryParameters: request.query.isEmpty ? null : request.query);
  }
}
