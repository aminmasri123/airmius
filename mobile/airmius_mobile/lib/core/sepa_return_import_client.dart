import 'dart:typed_data';
import 'package:http/http.dart' as http;
import 'airmius_api_client.dart';

/// Financial uploads are sent once, never queued offline or retried implicitly.
class SepaReturnImportClient {
  SepaReturnImportClient(this.api, {http.Client? transport})
    : _transport = transport ?? http.Client();
  final AirmiusApiClient api;
  final http.Client _transport;

  Future<AirmiusJson> send({
    required int clubId,
    required int batchId,
    required Uint8List bytes,
    String? previewToken,
    bool confirmUnlinked = false,
    bool columnsOnly = false,
    Map<String, int>? mapping,
    List<int> ignoredColumns = const [],
    String filename = 'returns.csv',
  }) async {
    final path = columnsOnly
        ? '/api/v1/clubs/$clubId/sepa-batches/$batchId/returns/columns'
        : previewToken == null
        ? '/api/v1/clubs/$clubId/sepa-batches/$batchId/returns/preview'
        : '/api/v1/clubs/$clubId/sepa-batches/$batchId/returns/import';
    if (bytes.isEmpty || bytes.length > 2 * 1024 * 1024) {
      throw ArgumentError('CSV size');
    }
    final base = Uri.parse(api.baseUrl);
    var prefix = base.path.replaceFirst(RegExp(r'/+$'), '');
    if (prefix == '/api' || prefix == '/api/v1') prefix = '';
    final uri = base.replace(
      host: base.host.toLowerCase() == 'app.airmius.com'
          ? 'airmius.com'
          : base.host,
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
      ..files.add(
        http.MultipartFile.fromBytes('file', bytes, filename: filename),
      );
    if (previewToken != null) {
      request.fields.addAll({
        'preview_token': previewToken,
        'confirmed': '1',
        'confirm_unlinked': confirmUnlinked ? '1' : '0',
      });
    } else if (!columnsOnly && mapping != null) {
      for (final entry in mapping.entries) {
        request.fields['mapping[${entry.key}]'] = '${entry.value}';
      }
      for (var index = 0; index < ignoredColumns.length; index++) {
        request.fields['ignored_columns[$index]'] = '${ignoredColumns[index]}';
      }
    }
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

  void close() => _transport.close();
}
