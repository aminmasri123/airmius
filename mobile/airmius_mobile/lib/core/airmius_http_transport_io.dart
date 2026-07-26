import 'dart:convert';
import 'dart:io';

import 'airmius_api_client.dart';

class AirmiusHttpTransport implements AirmiusApiTransport {
  const AirmiusHttpTransport({required this.baseUrl});

  final String baseUrl;

  static const _requestTimeout = Duration(seconds: 20);

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    final client = HttpClient()..connectionTimeout = _requestTimeout;
    final uri = _uri(request);
    try {
      final ioRequest = await client
          .openUrl(request.method, uri)
          .timeout(_requestTimeout);
      request.headers.forEach(ioRequest.headers.set);
      if (request.body != null) {
        ioRequest.write(jsonEncode(request.body));
      }
      final response = await ioRequest.close().timeout(_requestTimeout);
      final body = await utf8.decodeStream(response);
      final headers = <String, String>{};
      response.headers.forEach(
        (name, values) => headers[name] = values.join(','),
      );
      return AirmiusApiResponse(
        statusCode: response.statusCode,
        body: body,
        headers: headers,
      );
    } catch (error) {
      if (error is AirmiusApiException) rethrow;
      throw AirmiusApiException(
        statusCode: 599,
        body: jsonEncode({
          'error': 'connection_failed',
          'message': 'The API connection could not be completed.',
        }),
        path: request.path,
      );
    } finally {
      client.close(force: true);
    }
  }

  Uri _uri(AirmiusApiRequest request) {
    final base = _origin(Uri.parse(baseUrl));
    final path = request.path.startsWith('/')
        ? request.path.substring(1)
        : request.path;
    return base.replace(
      path: '${base.path.endsWith('/') ? base.path : '${base.path}/'}$path',
      queryParameters: request.query.isEmpty ? null : request.query,
    );
  }

  Uri _origin(Uri value) {
    var path = value.path.replaceFirst(RegExp(r'/+$'), '');
    if (path == '/api' || path == '/api/v1') {
      path = '';
    }
    final host = value.host.toLowerCase() == 'app.airmius.com'
        ? 'airmius.com'
        : value.host;
    return value.replace(host: host, path: path);
  }
}
