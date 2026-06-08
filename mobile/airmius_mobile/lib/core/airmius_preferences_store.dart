import 'airmius_preferences_store_stub.dart'
    if (dart.library.html) 'airmius_preferences_store_web.dart'
    if (dart.library.io) 'airmius_preferences_store_io.dart';
import 'airmius_preferences_store_base.dart';

export 'airmius_preferences_store_base.dart';

AirmiusPreferencesStore createAirmiusPreferencesStore() {
  return createPlatformPreferencesStore();
}
