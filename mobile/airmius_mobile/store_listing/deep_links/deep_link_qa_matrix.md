# Airmius Mobile Deep Link QA Matrix

## Supported route shapes

| Link | Expected target | Auth required |
| --- | --- | --- |
| `airmius://clubs/26` | Club profile | No |
| `https://app.airmius.com/clubs/26` | Club profile | No |
| `airmius://membership-applications/1001` | Membership request status | Yes |
| `airmius://events/1` | Event/training detail | Yes |
| `airmius://messages/4` | Conversation detail | Yes |
| `airmius://notifications/8` | Notification detail | Yes |
| `airmius://profile/privacy` | Profile/settings section | Yes |

## QA scenarios before release

- Cold start while logged out.
- Cold start while logged in.
- Warm app foreground.
- App in background.
- Expired session.
- Missing club/event/message ID.
- Unknown route fallback.
- RTL language active.
- Push notification payload with deep link.
- Email link with HTTPS app link.

## Routing expectations

- Public club links may open in guest mode.
- Membership, message, notification and profile links require auth.
- If auth is missing, store the pending target and resume after login.
- Unknown links should open the dashboard with a friendly message.
- Link analytics should use `AirmiusDeepLinkTarget.analyticsName`.

## Remaining implementation work

- Wire native incoming-link events to `AirmiusDeepLinkResolver`.
- Add pending deep-link storage in auth state.
- Add target-to-screen navigation after login.
- Add route-specific loading/error screens.
