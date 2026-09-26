import 'dart:convert';
import 'dart:typed_data';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/screens/club_sepa_return_import.dart';

class _Picker extends FilePicker {
  _Picker({this.name = 'returns.csv'});

  final String name;
  List<String>? requestedExtensions;

  @override
  Future<FilePickerResult?> pickFiles({
    String? dialogTitle,
    String? initialDirectory,
    FileType type = FileType.any,
    List<String>? allowedExtensions,
    Function(FilePickerStatus)? onFileLoading,
    bool allowCompression = false,
    int compressionQuality = 0,
    bool allowMultiple = false,
    bool withData = false,
    bool withReadStream = false,
    bool lockParentWindow = false,
    bool readSequential = false,
  }) async {
    requestedExtensions = allowedExtensions;
    return FilePickerResult([
      PlatformFile(
        name: name,
        size: 4,
        bytes: Uint8List.fromList([65, 66, 67, 10]),
      ),
    ]);
  }
}

void registerXmlSelectionTest() {
  testWidgets('XML selection shows detected format without CSV column mapping', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(390, 1200));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final picker = _Picker(name: 'returns.xml');
    FilePicker.platform = picker;
    final requests = <http.Request>[];
    final transport = MockClient((request) async {
      requests.add(request);
      if (request.url.path.endsWith('/columns')) {
        return http.Response(
          jsonEncode({
            'data': {
              'columns': [
                'end_to_end_id',
                'booking_date',
                'amount',
                'currency',
                'reference',
                'reason',
                'iban',
              ],
              'row_count': 1,
              'format': 'pain.002',
            },
          }),
          200,
        );
      }
      expect(request.body, isNot(contains('name="mapping[')));
      return http.Response(
        '{"data":{"format":"pain.002","can_import":false,"preview_token":"proof","unlinked_count":0,"ignored_columns":[],"rows":[]}}',
        200,
      );
    });
    await tester.pumpWidget(
      AirmiusScope(
        language: AirmiusLanguage.de,
        setLanguage: (_) {},
        child: MaterialApp(
          home: ClubSepaReturnImport(
            client: AirmiusApiClient(
              transport: _Unused(),
              baseUrl: 'https://example.test',
            ),
            clubId: 2,
            batchId: 7,
            uploadTransport: transport,
          ),
        ),
      ),
    );
    await tester.tap(find.text('CSV- oder XML-Datei'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Datei prüfen / Spalten zuordnen'));
    await tester.pumpAndSettle();
    expect(find.text('Erkanntes Format: pain.002'), findsOneWidget);
    expect(find.byType(DropdownButtonFormField<int>), findsNothing);
    await tester.tap(find.text('Vorschau prüfen'));
    await tester.pumpAndSettle();
    expect(requests, hasLength(2));
    expect(
      requests.every(
        (request) => request.body.contains('filename="returns.xml"'),
      ),
      isTrue,
    );
    expect(tester.takeException(), isNull);
  });
}

class _Unused extends AirmiusApiTransport {
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) =>
      throw UnimplementedError();
}

void main() {
  registerXmlSelectionTest();
  for (final mapped in [false, true]) {
    for (final fail in [true, false]) {
      testWidgets(
        'unlinked import requires both confirmations; transport failure: $fail, mapped: $mapped',
        (tester) async {
          await tester.binding.setSurfaceSize(Size(390, mapped ? 2500 : 1800));
          addTearDown(() => tester.binding.setSurfaceSize(null));
          final picker = _Picker();
          FilePicker.platform = picker;
          final requests = <http.Request>[];
          bool? completed;
          final transport = MockClient((request) async {
            requests.add(request);
            if (request.url.path.endsWith('/columns')) {
              return http.Response(
                jsonEncode({
                  'data': {
                    'columns': [
                      'end_to_end_id',
                      'booking_date',
                      'amount',
                      'currency',
                      'reference',
                      'reason',
                      'fee',
                    ],
                    'row_count': 1,
                  },
                }),
                200,
              );
            }
            if (mapped && request.url.path.endsWith('/preview')) {
              expect(request.body, contains('name="mapping[amount]"\r\n\r\n2'));
              expect(
                request.body,
                contains('name="ignored_columns[0]"\r\n\r\n6'),
              );
            }
            if (request.url.path.endsWith('/import')) {
              if (fail) throw http.ClientException('lost response');
              return http.Response('{"data":{"imported":1}}', 200);
            }
            return http.Response(
              jsonEncode({
                'data': {
                  'can_import': true,
                  'preview_token': 'proof',
                  'unlinked_count': 1,
                  'ignored_columns': mapped ? ['fee'] : [],
                  'rows': [
                    {
                      'row': 2,
                      'end_to_end_id': 'R-1',
                      'booking_date': '2026-10-11',
                      'amount_cents': -8000,
                      'currency': 'EUR',
                      'reference': 'BANK',
                      'reason': 'MS03',
                      'status': 'ready',
                      'has_linked_receipt': false,
                      'errors': [],
                    },
                  ],
                },
              }),
              200,
            );
          });
          await tester.pumpWidget(
            AirmiusScope(
              language: AirmiusLanguage.de,
              setLanguage: (_) {},
              child: MaterialApp(
                home: Builder(
                  builder: (context) => Scaffold(
                    body: TextButton(
                      onPressed: () async {
                        completed = await Navigator.of(context).push<bool>(
                          MaterialPageRoute(
                            builder: (_) => ClubSepaReturnImport(
                              client: AirmiusApiClient(
                                transport: _Unused(),
                                baseUrl: 'https://example.test',
                              ),
                              clubId: 2,
                              batchId: 7,
                              uploadTransport: transport,
                            ),
                          ),
                        );
                      },
                      child: const Text('Open'),
                    ),
                  ),
                ),
              ),
            ),
          );
          await tester.tap(find.text('Open'));
          await tester.pumpAndSettle();
          await tester.tap(find.text('CSV- oder XML-Datei'));
          await tester.pumpAndSettle();
          expect(picker.requestedExtensions, ['csv', 'xml']);
          if (mapped) {
            await tester.tap(find.text('Datei prüfen / Spalten zuordnen'));
            await tester.pumpAndSettle();
            expect(
              tester
                  .widget<FilledButton>(
                    find.widgetWithText(FilledButton, 'Vorschau prüfen'),
                  )
                  .onPressed,
              isNull,
            );
            await tester.tap(find.byType(CheckboxListTile));
            await tester.pumpAndSettle();
          }
          await tester.tap(find.text('Vorschau prüfen'));
          await tester.pumpAndSettle();
          final apply = find.widgetWithText(
            FilledButton,
            'Rückgaben importieren',
          );
          expect(tester.widget<FilledButton>(apply).onPressed, isNull);
          final checkboxes = find.byWidgetPredicate(
            (widget) =>
                widget is CheckboxListTile &&
                !(widget.title as Text).data!.startsWith(
                  'Ich bestätige, dass diese Spalten',
                ),
          );
          await tester.ensureVisible(checkboxes.first);
          await tester.tap(checkboxes.first);
          await tester.pumpAndSettle();
          expect(tester.widget<FilledButton>(apply).onPressed, isNull);
          await tester.ensureVisible(checkboxes.last);
          await tester.tap(checkboxes.last);
          await tester.pumpAndSettle();
          await tester.ensureVisible(apply);
          await tester.tap(apply);
          await tester.pumpAndSettle();
          expect(completed, fail ? isNull : isTrue);
          expect(requests.length, mapped ? 3 : 2);
          expect(
            requests.last.body,
            contains('name="preview_token"\r\n\r\nproof'),
          );
          expect(
            find.byType(CheckboxListTile),
            mapped && fail ? findsOneWidget : findsNothing,
          );
          expect(find.text('Rückgaben importieren'), findsNothing);
          expect(tester.takeException(), isNull);
        },
      );
    }
  }
}
