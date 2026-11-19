import 'dart:convert';
import 'dart:typed_data';
import 'package:http/http.dart' as http;
import 'package:http_parser/http_parser.dart';
import 'airmius_api_client.dart';

Future<String> uploadLearningCertificateLogo({
  required AirmiusApiClient api,
  required Uint8List bytes,
  required String filename,
  http.Client? transport,
}) async {
  final extension = filename.split('.').last.toLowerCase();
  if (!['jpg', 'jpeg', 'png', 'webp'].contains(extension) ||
      bytes.isEmpty ||
      bytes.length > 5 * 1024 * 1024) {
    throw ArgumentError('Invalid certificate logo');
  }
  final base = Uri.parse(api.baseUrl);
  var prefix = base.path.replaceFirst(RegExp(r'/+$'), '');
  if (prefix == '/api' || prefix == '/api/v1') prefix = '';
  const path = '/api/v1/learning-studio/certificate-logo';
  final request =
      http.MultipartRequest(
          'POST',
          base.replace(path: '$prefix$path', query: '', fragment: ''),
        )
        ..headers.addAll({
          'Accept': 'application/json',
          'X-Airmius-Locale': api.locale,
          if (api.token != null) 'Authorization': 'Bearer ${api.token}',
        })
        ..files.add(
          http.MultipartFile.fromBytes(
            'file',
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
    final result = jsonDecode(response.body) as Map;
    if (result['url'] is! String || (result['url'] as String).isEmpty) {
      throw const FormatException('Missing logo URL');
    }
    return result['url'] as String;
  } finally {
    if (transport == null) client.close();
  }
}
