import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/widgets/content_report_dialog.dart';
import 'package:airmius/widgets/airmius_widgets.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('content report dialog follows the selected RTL language', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: AirmiusScope(
          language: AirmiusLanguage.ar,
          setLanguage: (_) {},
          child: Directionality(
            textDirection: TextDirection.rtl,
            child: Builder(
              builder: (context) => Scaffold(
                body: Center(
                  child: ElevatedButton(
                    onPressed: () => showContentReportDialog(
                      context,
                      title: 'الإبلاغ عن المحتوى',
                    ),
                    child: const Text('فتح'),
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );

    await tester.tap(find.text('فتح'));
    await tester.pumpAndSettle();

    expect(find.text('لماذا يجب مراجعة هذا المحتوى؟'), findsOneWidget);
    expect(find.text('السبب'), findsOneWidget);
    expect(find.text('إهانة'), findsOneWidget);
    expect(find.text('التفاصيل'), findsOneWidget);
    expect(find.text('إرسال البلاغ'), findsOneWidget);
    expect(find.text('إلغاء'), findsOneWidget);
  });

  testWidgets('danger confirmation uses a localized neutral default', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: AirmiusScope(
          language: AirmiusLanguage.ar,
          setLanguage: (_) {},
          child: Directionality(
            textDirection: TextDirection.rtl,
            child: Builder(
              builder: (context) => Scaffold(
                body: Center(
                  child: ElevatedButton(
                    onPressed: () => confirmDanger(
                      context,
                      'تأكيد الإجراء',
                      'هل تريد المتابعة؟',
                    ),
                    child: const Text('فتح'),
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );

    await tester.tap(find.text('فتح'));
    await tester.pumpAndSettle();

    expect(find.text('تأكيد'), findsOneWidget);
    expect(find.text('إلغاء'), findsOneWidget);
    expect(find.text('تأكيد الإجراء'), findsOneWidget);
  });
}
