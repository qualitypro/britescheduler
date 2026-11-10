# Cycle 90AJ — Distribute month height across week rows

The desktop month-fit routine now calculates the available week-grid height after measuring FullCalendar's toolbar and weekday header. It sets a month-only week-row height based on the actual rendered 4/5/6 rows, and the daygrid table fills the available grid height. This targets the blank area below content-sized week rows, not merely the outer calendar height.

The row-height override is scoped to the desktop month-fit class and is removed when leaving that mode. No appointment logic, event sources, +more, hover previews or month boundary behavior changed.

Browser QA pending: compare 4-, 5-, 6-week months; resize viewport; switch day/week/month; inspect overflow on busy days.
