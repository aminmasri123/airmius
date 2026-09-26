# Event visibility contract

This note inventories the event surfaces covered by `Event::visibleTo()` and the API/Web event views. It intentionally lives outside the main MVP checklist.

## Views

- Public view: `visibility = public`; visible to every authenticated viewer and eligible for public discovery/calendar listing.
- Club view: `visibility = organization`; visible to members of the owning club, not to unrelated users or participant-only users.
- Team view: `visibility = private` with `team_id`; visible to the owner, team members, and explicitly recorded participants. Club membership alone must not reveal the team event.
- Personal view: owner-created private events without club/team context; visible to the owner and explicitly recorded participants only.

## Functional inventory

- Series: `EventService` expands daily, weekly, biweekly, and monthly recurrences into concrete event rows and records a domain outbox series event.
- Participation: web and mobile flows write `event_participants` RSVP records and expose `my_participation_status`.
- Calendar: web and mobile indexes return paginated list rows plus an unpaginated month window (`calendarEvents` / `calendar_events`).
- Check-in: participant pivots carry `checked_in_at` and `check_in_method`; the API event resource exposes both fields when participants are loaded.
- Attendance: team/club staff use `EventAttendance` to constrain attendance rows to eligible team, club, or participant users.

## Known gaps

- Recurring rows do not yet retain a shared immutable series identifier for bulk edit/cancel.
- Public guest access is still routed through authenticated event APIs; anonymous public event discovery remains separate from this contract.
- Check-in is represented in the participant contract, but a dedicated QR check-in endpoint is not yet separated from attendance writes.
