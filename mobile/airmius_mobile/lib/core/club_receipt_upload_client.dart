import 'dart:typed_data';

import 'package:http/http.dart' as http;

import 'airmius_api_client.dart';

class ClubReceiptUploadClient {
  ClubReceiptUploadClient(this.api, {http.Client? transport})
    : _transport = transport ?? http.Client();

  final AirmiusApiClient api;
  final http.Client _transport;

  Future<AirmiusJson> upload({
    required int clubId,
    required Uint8List bytes,
    String filename = 'receipt.pdf',
  }) async {
    if (bytes.isEmpty || bytes.length > 10 * 1024 * 1024) {
      throw ArgumentError('receipt size');
    }

    const pathTemplate = '/api/v1/clubs/{clubId}/receipt-uploads';
    final path = pathTemplate.replaceFirst('{clubId}', '$clubId');
    final request = http.MultipartRequest('POST', _uri(path))
      ..headers.addAll({
        'Accept': 'application/json',
        'X-Airmius-Locale': api.locale,
        if (api.token != null) 'Authorization': 'Bearer ${api.token}',
      })
      ..files.add(
        http.MultipartFile.fromBytes('file', bytes, filename: filename),
      );

    final response = await http.Response.fromStream(
      await _transport.send(request).timeout(const Duration(seconds: 45)),
    ).timeout(const Duration(seconds: 45));

    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw AirmiusApiException(
        statusCode: response.statusCode,
        body: response.body,
        path: path,
      );
    }

    return AirmiusApiResponse(
      statusCode: response.statusCode,
      body: response.body,
    ).json;
  }

  Uri _uri(String path) {
    final base = Uri.parse(api.baseUrl);
    var prefix = base.path.replaceFirst(RegExp(r'/+$'), '');
    if (prefix == '/api' || prefix == '/api/v1') prefix = '';

    return base.replace(
      host: base.host.toLowerCase() == 'app.airmius.com'
          ? 'airmius.com'
          : base.host,
      path: '$prefix$path',
      query: '',
      fragment: '',
    );
  }

  void close() => _transport.close();
}
