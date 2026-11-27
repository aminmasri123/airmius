import 'dart:convert';
import 'package:file_picker/file_picker.dart';
import 'package:http/http.dart' as http;
import 'airmius_api_client.dart';

Future<void> sendWebsiteProjectFiles(
  AirmiusApiClient api,
  int projectId,
  String body,
  String requestKey,
  List<PlatformFile> files, {
  http.Client? transport,
}) async {
  final path = '/api/v1/commerce/website-requests/$projectId/messages';
  final base = Uri.parse(api.baseUrl);
  var prefix = base.path.replaceFirst(RegExp(r'/+$'), '');
  if (prefix == '/api' || prefix == '/api/v1') prefix = '';
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
        ..fields.addAll({'body': body, 'request_key': requestKey});
  for (final file in files) {
    if (file.bytes != null) {
      request.files.add(
        http.MultipartFile.fromBytes(
          'files[]',
          file.bytes!,
          filename: file.name,
        ),
      );
    } else if (file.path != null) {
      request.files.add(
        await http.MultipartFile.fromPath(
          'files[]',
          file.path!,
          filename: file.name,
        ),
      );
    } else {
      throw AirmiusApiException(
        statusCode: 0,
        body: 'File could not be read',
        path: path,
      );
    }
  }
  final client = transport ?? http.Client();
  try {
    final response = await http.Response.fromStream(
      await client.send(request).timeout(const Duration(seconds: 90)),
    );
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw AirmiusApiException(
        statusCode: response.statusCode,
        body: response.body,
        path: path,
      );
    }
    final decoded = jsonDecode(response.body);
    if (decoded is! Map || decoded['ok'] != true) {
      throw StateError('Message not confirmed');
    }
  } finally {
    if (transport == null) client.close();
  }
}
