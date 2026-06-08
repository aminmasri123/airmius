import 'package:airmius_mobile/airmius_app.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('Airmius app starts', (WidgetTester tester) async {
    await tester.pumpWidget(const AirmiusApp());
    expect(find.byType(AirmiusApp), findsOneWidget);
  });
}
