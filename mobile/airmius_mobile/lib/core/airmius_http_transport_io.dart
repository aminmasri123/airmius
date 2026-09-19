import 'dart:convert';
import 'dart:io';

import 'airmius_api_client.dart';

class AirmiusHttpTransport implements AirmiusApiTransport {
  const AirmiusHttpTransport({required this.baseUrl});

  final String baseUrl;

  // Production currently needs slightly more than 20 seconds for some auth
  // responses. Keep enough headroom for mobile latency so OAuth callbacks can
  // validate the returned session instead of failing at the transport edge.
  static const _requestTimeout = Duration(seconds: 45);

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
        final bodyBytes = utf8.encode(jsonEncode(request.body));
        ioRequest.contentLength = bodyBytes.length;
        ioRequest.add(bodyBytes);
      }
      final response = await ioRequest.close().timeout(_requestTimeout);
      final responseBytes = await response.fold<List<int>>(
        <int>[],
        (buffer, chunk) => buffer..addAll(chunk),
      );
      final body = utf8.decode(responseBytes, allowMalformed: true);
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
