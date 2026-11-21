import 'dart:convert';
import 'package:file_picker/file_picker.dart';
import 'package:http/http.dart' as http;
import 'airmius_api_client.dart';

Future<AirmiusJson> uploadClubPolicyFile(
  AirmiusApiClient api,
  int clubId,
  String type,
  PlatformFile file, {
  http.Client? transport,
}) async {
  const path = '/api/v1/uploads';
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
        ..fields.addAll({
          'scope': 'club',
          'club_id': '$clubId',
          'policy_document_type': type,
        });
  if (file.bytes != null) {
    request.files.add(
      http.MultipartFile.fromBytes('file', file.bytes!, filename: file.name),
    );
  } else if (file.path != null) {
    request.files.add(
      await http.MultipartFile.fromPath(
        'file',
        file.path!,
        filename: file.name,
      ),
    );
  } else {
    throw const AirmiusApiException(
      statusCode: 0,
      body: 'File could not be read',
      path: path,
    );
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
    if (decoded is! Map<String, dynamic> ||
        decoded['data'] is! Map<String, dynamic>) {
      throw StateError('Invalid upload response');
    }
    return decoded['data'] as Map<String, dynamic>;
  } finally {
    if (transport == null) client.close();
  }
}
