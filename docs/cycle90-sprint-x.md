# Cycle 90X — Show complete appointment time and title

In the compact desktop month grid, appointment cards now place the full time above the full title and wrap the title over multiple lines. Remove nowrap, ellipsis, and hidden overflow on these two fields. Existing expanded hover preview remains available for other appointment details.

To avoid multiple cards crowding a day cell, dynamically show one appointment per date in shorter windows and two when enough vertical space is available, with additional appointments accessible through FullCalendar's +more link. The month layout remains viewport-first, with a scrollbar only when the window is too short to accommodate readable week rows.

Important constraint: an arbitrarily long appointment title cannot be guaranteed to fit within a finite-height calendar cell. This approach prioritizes complete text and permits the existing fallback rather than intentionally truncating. Browser QA needed for long titles, 6-week months, zoom, and +more behavior.
