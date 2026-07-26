import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_http_transport_io.dart';

void main() {
  test(
    'normalizes an already versioned API base before building requests',
    () async {
      final server = await HttpServer.bind(InternetAddress.loopbackIPv4, 0);
      addTearDown(server.close);

      final requestFuture = server.first;
      final responseFuture =
          AirmiusHttpTransport(
            baseUrl: 'http://127.0.0.1:${server.port}/api/v1',
          ).send(
            const AirmiusApiRequest(
              method: 'POST',
              path: '/api/v1/auth/login',
              body: {'email': 'test@example.com', 'password': 'secret'},
              headers: {'Content-Type': 'application/json'},
            ),
          );

      final request = await requestFuture;
      expect(request.uri.path, '/api/v1/auth/login');
      expect(request.method, 'POST');
      expect(
        await utf8.decoder.bind(request).join(),
        '{"email":"test@example.com","password":"secret"}',
      );
      request.response
        ..statusCode = HttpStatus.ok
        ..headers.contentType = ContentType.json
        ..write('{"data":{}}');
      await request.response.close();

      final response = await responseFuture;
      expect(response.statusCode, HttpStatus.ok);
    },
  );
}
