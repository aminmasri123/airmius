import 'package:flutter_test/flutter_test.dart';
import 'package:airmius/navigation/airmius_deep_link_navigator.dart';
import 'package:airmius/screens/admin_trainer_applications_screen.dart';
import '../tool/check_trainer_application_links.dart' as pipeline;
void main() {
  test('API response preserves trainer link parameters', pipeline.main);
  test('trainer link opens the specific application screen', () {
    final target = AirmiusDeepLinkNavigator.resolve('/admin/trainer-applications?application_id=4');
    final screen = AirmiusDeepLinkNavigator.screenFor(target);
    expect(screen, isA<AdminTrainerApplicationsScreen>());
    expect((screen as AdminTrainerApplicationsScreen).applicationId, 4);
    expect(screen.sponsor, false);
    expect(target.requiresAuth, true);
  });
}
