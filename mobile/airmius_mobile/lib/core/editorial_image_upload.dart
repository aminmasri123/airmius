import 'dart:convert';
import 'dart:typed_data';

import 'package:http/http.dart' as http;
import 'package:http_parser/http_parser.dart';

import 'airmius_api_client.dart';

Future<AirmiusJson> uploadEditorialImage({
  required AirmiusApiClient api,
  required Uint8List bytes,
  required String filename,
  required String kind,
  String alt = '',
  http.Client? transport,
}) async {
  final extension = filename.split('.').last.toLowerCase();
  if (!['jpg', 'jpeg', 'png', 'webp'].contains(extension) ||
      bytes.isEmpty ||
      bytes.length > 8192 * 1024) {
    throw ArgumentError('Choose a JPEG, PNG or WebP image up to 8 MB.');
  }
  final base = Uri.parse(api.baseUrl);
  var prefix = base.path.replaceFirst(RegExp(r'/+$'), '');
  if (prefix == '/api' || prefix == '/api/v1') prefix = '';
  const path = '/api/v1/editorial/images';
  final uri = base.replace(
    host: base.host == 'app.airmius.com' ? 'airmius.com' : base.host,
    path: '$prefix$path',
    query: '',
    fragment: '',
  );
  final request = http.MultipartRequest('POST', uri)
    ..headers.addAll({
      'Accept': 'application/json',
      'X-Airmius-Locale': api.locale,
      if (api.token != null) 'Authorization': 'Bearer ${api.token}',
    })
    ..fields.addAll({'kind': kind, 'alt': alt})
    ..files.add(
      http.MultipartFile.fromBytes(
        'image',
        bytes,
        filename: filename,
        contentType: MediaType(
          'image',
          extension == 'jpg' ? 'jpeg' : extension,
        ),
      ),
    );
  final client = transport ?? http.Client();
  try {
    final response = await http.Response.fromStream(
      await client.send(request).timeout(const Duration(seconds: 60)),
    );
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw AirmiusApiException(
        statusCode: response.statusCode,
        body: response.body,
        path: path,
      );
    }
    return Map<String, dynamic>.from(jsonDecode(response.body) as Map);
  } finally {
    if (transport == null) client.close();
  }
}
