import 'package:flutter_test/flutter_test.dart';
import 'package:airmius/core/airmius_api_models.dart';
import 'package:airmius/core/airmius_deep_links.dart';
import 'package:airmius/navigation/airmius_deep_link_navigator.dart';
import 'package:airmius/screens/support_ticket_service_center_suite_screen.dart';

void main() {
  test('support inbox notifications open the exact authenticated ticket', () {
    for (final url in [
      'airmius://support?ticket=42',
      'https://airmius.com/support?tab=operations&inbox=safety&ticket=42',
    ]) {
      final notification = AirmiusNotification.fromJson({
        'id': 1,
        'type': 'support.inbox.new',
        'data': {'url': url},
      });
      final target = const AirmiusDeepLinkResolver().resolve(
        notification.actionUrl!,
      );
      expect(target.type, AirmiusDeepLinkTargetType.support);
      expect(target.requiresAuth, isTrue);
      final screen =
          AirmiusDeepLinkNavigator.screenFor(target)
              as SupportTicketServiceCenterSuiteScreen;
      expect(screen.ticketId, 42);
      expect(screen.operations, url.contains('operations'));
      expect(screen.inbox, url.contains('safety') ? 'safety' : 'general');
    }
  });

  test('view-only support ticket cannot offer assignment', () {
    final ticket = AirmiusSupportTicket.fromJson({
      'id': 42,
      'assignee': {'id': 7, 'name': 'Test'},
    });
    expect(ticket.canAssign, isFalse);
    expect(ticket.assigneeId, 7);
  });
}
