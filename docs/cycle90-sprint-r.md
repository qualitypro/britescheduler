# Cycle 90R — Single-scroll scheduling calendar

Desktop scheduling now uses a viewport-constrained workspace. The outer document and main content cannot create a second vertical scrollbar. The Calendar tab, card, and FullCalendar fill the remaining space with min-height:0 on flex descendants.

Month view uses FullCalendar height:100%, expandRows and dayMaxEvents so all weeks fit without an outer scrollbar; overflowing events use the built-in +more popover. Week/Day retain the native time-grid scroller, not an additional page scrollbar. Quick Actions remains independently scrollable as needed. The wizard dialog's own scroll behavior is untouched.

The new CSS is scoped to the scheduling page and desktop widths >=900px and viewport heights >=500px; smaller screens retain the existing responsive layout. Calendar dimensions are refreshed when its tab is activated.

Validation still required on a real browser at desktop/mobile sizes, six-week months, week/day events, browser zoom, and short-height windows. No live browser or database tests were run through the GitHub connector.
