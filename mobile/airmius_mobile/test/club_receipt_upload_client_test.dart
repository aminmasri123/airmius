import 'dart:convert';
import 'dart:typed_data';

import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_api_repositories.dart';
import 'package:airmius/core/club_receipt_upload_client.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

class _UnusedTransport implements AirmiusApiTransport {
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) =>
      throw StateError('JSON transport must not receive receipt bytes');
}

class _ConfirmTransport implements AirmiusApiTransport {
  AirmiusApiRequest? lastRequest;

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    lastRequest = request;
    return const AirmiusApiResponse(
      statusCode: 200,
      body:
          '{"data":{"management":{"can_manage":true,"receipt_uploads":[{"id":5,"status":"confirmed"}],"finance_entries":[{"id":9,"receipt_file_id":11}]}}}',
    );
  }
}

void main() {
  test('receipt upload uses multipart with auth and keeps confirmation separate', () async {
    final api = AirmiusApiClient(
      transport: _UnusedTransport(),
      baseUrl: 'https://example.test/api/v1/',
      token: 'token-123',
      locale: 'de',
    );
    final requests = <http.Request>[];
    final client = ClubReceiptUploadClient(
      api,
      transport: MockClient((request) async {
        requests.add(request);
        return http.Response(
          '{"data":{"receipt_upload":{"id":5,"status":"pending_confirmation","ocr_suggestion":{"requires_manual_confirmation":true}}}}',
          201,
        );
      }),
    );
    addTearDown(client.close);

    await client.upload(
      clubId: 7,
      bytes: Uint8List.fromList(utf8.encode('%PDF receipt')),
      filename: 'beleg.pdf',
    );

    expect(requests.single.url.path, '/api/v1/clubs/7/receipt-uploads');
    expect(requests.single.headers['Authorization'], 'Bearer token-123');
    expect(requests.single.headers['content-type'], startsWith('multipart/form-data;'));
    expect(requests.single.body, contains('filename="beleg.pdf"'));
    expect(requests.single.body, isNot(contains('name="confirmed"')));
  });

  test('receipt confirmation posts only reviewed booking payload', () async {
    final transport = _ConfirmTransport();
    final api = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://example.test',
      token: 'token-123',
    );
    final repository = AirmiusApiClubRepository(api);

    final management = await repository.confirmClubReceiptUpload(7, 5, {
      'type': 'expense',
      'account': 'bank',
      'title': 'Hallenmiete',
      'amount': '42.50',
      'booked_on': '2026-09-26',
    });

    expect(transport.lastRequest?.method, 'POST');
    expect(
      transport.lastRequest?.path,
      '/api/v1/clubs/7/receipt-uploads/5/confirm',
    );
    expect(transport.lastRequest?.body?['title'], 'Hallenmiete');
    expect(management.receiptUploads.single['status'], 'confirmed');
    expect(management.financeEntries.single['receipt_file_id'], 11);
  });
}
