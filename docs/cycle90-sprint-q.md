# Cycle 90Q — FullCalendar Server-Range Integration

The scheduling calendar now requests the server's tenant-local date window using view=day|week|month, date=YYYY-MM-DD, and week_start=0|1. The authenticated appointments API applies its existing tenant/role filters and returns range metadata.

The client compares the returned range with FullCalendar's actual event-fetch interval. When a calendar grid differs (such as a six-week month display), it automatically fetches the original start/end endpoint to avoid missing appointments. Unrecognized/custom views also keep the legacy feed. Appointment editing, dragging, and booking remain untouched.

No new UI controls or schema migrations. This is a progressive compatibility integration, not server-side pagination of visible calendar events; a date-range calendar should still retrieve all appointments in its visible range.

Validation outstanding: PHP/JS lint, live browser checks for month grids (including 6-week months), Monday/Sunday week starts, timezone boundaries, role-based visibility, and drag/drop. No runtime browser or DB tests executed through GitHub connector.
