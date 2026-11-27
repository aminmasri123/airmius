import 'package:flutter_test/flutter_test.dart';
import 'package:airmius/screens/club_announcement_screen.dart';

void main() {
  test('announcement club name placeholders use the selected club', () {
    expect(
      resolveAnnouncementClubName(
        'Viele Grüße,\n[Vereinsname]',
        'TSV Beispielstadt',
      ),
      'Viele Grüße,\nTSV Beispielstadt',
    );
    expect(
      resolveAnnouncementClubName('From (Club name)', 'FC Nord'),
      'From FC Nord',
    );
    expect(
      resolveAnnouncementClubName('Ein anderer [Platzhalter]', 'FC Nord'),
      'Ein anderer [Platzhalter]',
    );
    expect(resolveAnnouncementClubName('[Vereinsname]', '  '), '[Vereinsname]');
  });
}
