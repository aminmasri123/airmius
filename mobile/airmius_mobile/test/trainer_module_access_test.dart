import 'package:flutter_test/flutter_test.dart';
import 'package:airmius/core/airmius_api_models.dart';
import 'package:airmius/core/airmius_module_access.dart';
import 'package:airmius/core/airmius_persona.dart';

void main() {
  AirmiusUser member(String role, String status) => AirmiusUser.fromJson({
    'id': 1, 'name': 'Test', 'email': 'test@example.test', 'role': 'player',
    'clubs': [{'id': 2, 'name': 'Verein', 'membership': {'role': role, 'status': status}}],
  });

  test('active club trainer receives planning and trainer navigation', () {
    final user = member('trainer', 'active');
    expect(AirmiusModuleAccess.canOpen(user, 'Trainingsplanung'), isTrue);
    expect(AirmiusModuleAccess.canOpen(user, 'Trainer-Cockpit'), isTrue);
    expect(AirmiusPersonaResolver.navigationModules(user), contains('Trainingsplanung'));
    // Training/event permissions are still required for that separate module.
    expect(AirmiusModuleAccess.canOpen(user, 'Events & Training'), isFalse);
  });

  test('ordinary members and suspended trainers do not gain trainer navigation', () {
    expect(AirmiusModuleAccess.canOpenTrainerCockpit(member('member', 'active')), isFalse);
    for (final status in ['paused', 'former', 'pending', 'removal_pending', 'removal_objected']) {
      expect(AirmiusModuleAccess.canOpenTrainerCockpit(member('trainer', status)), isFalse);
    }
  });
}
