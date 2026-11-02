import 'dart:async';
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
    final responseTimeout = request.path == '/api/v1/training/ai/plans/preview'
        ? const Duration(seconds: 210)
        : _requestTimeout;
    final client = HttpClient()..connectionTimeout = _requestTimeout;
    final uri = _uri(request);
    final elapsed = Stopwatch()..start();
    var phase = 'connect';
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
      phase = 'response';
      final response = await ioRequest.close().timeout(responseTimeout);
      phase = 'body';
      final responseBytes = await response.fold<List<int>>(
        <int>[],
        (buffer, chunk) => buffer..addAll(chunk),
      ).timeout(responseTimeout);
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
      final code = error is TimeoutException
          ? 'connection_timeout'
          : error is HandshakeException
          ? 'connection_tls_failed'
          : error is SocketException || error is HttpException
          ? 'connection_interrupted'
          : 'connection_failed';
      final message = switch (code) {
        'connection_timeout' => 'Zeitüberschreitung: Die Serverantwort ist nicht rechtzeitig angekommen.',
        'connection_tls_failed' => 'Die sichere Verbindung zum Server konnte nicht hergestellt werden.',
        'connection_interrupted' => 'Die Verbindung zum Server wurde unterbrochen. Die Erstellung kann auf dem Server noch laufen.',
        _ => 'Die Serverantwort konnte nicht vollständig empfangen werden.',
      };
      throw AirmiusApiException(
        statusCode: 599,
        body: jsonEncode({
          'error': code,
          'message': '$message (${elapsed.elapsed.inSeconds} s, $phase)',
          'phase': phase,
          'elapsed_seconds': elapsed.elapsed.inSeconds,
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
