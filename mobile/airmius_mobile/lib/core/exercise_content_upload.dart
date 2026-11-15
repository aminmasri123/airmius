import 'dart:convert';
import 'package:file_picker/file_picker.dart';
import 'package:http/http.dart' as http;
import 'package:http_parser/http_parser.dart';
import 'airmius_api_client.dart';

/// The same multipart field names and retained image IDs as the web editor.
Future<AirmiusJson> saveExerciseContent({
  required AirmiusApiClient client,
  required String path,
  required AirmiusJson payload,
  bool updating = false,
  http.Client? httpClient,
}) async {
  bool hasFiles(Object? value) =>
      value is PlatformFile ||
      (value is Map && value.values.any(hasFiles)) ||
      (value is Iterable && value.any(hasFiles));
  if (!hasFiles(payload)) {
    final response = await client.transport.send(
      AirmiusApiRequest(
        method: updating ? 'PUT' : 'POST',
        path: path,
        body: payload,
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json; charset=utf-8',
          'X-Airmius-Locale': client.locale,
          if (client.token != null) 'Authorization': 'Bearer ${client.token}',
        },
      ),
    );
    if (!response.ok) {
      throw AirmiusApiException(
        statusCode: response.statusCode,
        body: response.body,
        path: path,
      );
    }
    return response.json;
  }
  final base = Uri.parse(
    client.baseUrl.endsWith('/') ? client.baseUrl : '${client.baseUrl}/',
  );
  final request =
      http.MultipartRequest(
          'POST',
          base.resolve(path.startsWith('/') ? path.substring(1) : path),
        )
        ..headers.addAll({
          'Accept': 'application/json',
          'X-Airmius-Locale': client.locale,
          if (client.token != null) 'Authorization': 'Bearer ${client.token}',
        });
  if (updating) request.fields['_method'] = 'PUT';
  Future<void> addField(String key, Object? value) async {
    if (value is PlatformFile) {
      final ext = value.extension?.toLowerCase();
      final mime = ext == 'png'
          ? 'png'
          : ext == 'webp'
          ? 'webp'
          : 'jpeg';
      if (value.bytes != null) {
        request.files.add(
          http.MultipartFile.fromBytes(
            key,
            value.bytes!,
            filename: value.name,
            contentType: MediaType('image', mime),
          ),
        );
      } else if (value.path != null) {
        request.files.add(
          await http.MultipartFile.fromPath(
            key,
            value.path!,
            filename: value.name,
            contentType: MediaType('image', mime),
          ),
        );
      } else {
        throw AirmiusApiException(
          statusCode: 0,
          body: 'Image could not be read.',
          path: path,
        );
      }
    } else if (value is Map) {
      for (final entry in value.entries) {
        await addField('$key[${entry.key}]', entry.value);
      }
    } else if (value is Iterable) {
      var index = 0;
      for (final entry in value) {
        await addField('$key[${index++}]', entry);
      }
    } else {
      request.fields[key] = value == null ? '' : '$value';
    }
  }

  var emptyIndex = 0;
  for (final entry in payload.entries) {
    if ((entry.value is Iterable && (entry.value as Iterable).isEmpty) ||
        (entry.value is Map && (entry.value as Map).isEmpty)) {
      request.fields['content_empty_arrays[${emptyIndex++}]'] = entry.key;
    }
    await addField(entry.key, entry.value);
  }
  final sender = httpClient ?? http.Client();
  try {
    final response = await http.Response.fromStream(
      await sender.send(request).timeout(const Duration(minutes: 3)),
    );
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw AirmiusApiException(
        statusCode: response.statusCode,
        body: response.body,
        path: path,
      );
    }
    final decoded = jsonDecode(response.body);
    return decoded is AirmiusJson ? decoded : {'data': decoded};
  } finally {
    if (httpClient == null) sender.close();
  }
}
