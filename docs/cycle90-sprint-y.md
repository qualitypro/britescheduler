# Cycle 90Y — Visible appointment overflow controls and reduced scrollbars

FullCalendar's +more links now render as filled blue appointment-like cards with white text and hover, active and keyboard-focus states. The existing native +more click opens FullCalendar's appointment popover. The month grid suppresses FullCalendar's nested scroller so it does not add an extra scrollbar inside the viewport-fitted panel. The calendar panel itself still permits a last-resort scrollbar for unusually small desktop heights.

Month appointment time/title wrapping and expanded hover previews remain unchanged. Browser QA is required for month weeks, zoom, long titles, popover access and keyboard interactions.
