# Cycle 90W — Adaptive month grid sizing

Refines the scheduling month view to fit all four, five or six weeks within the actual visible panel, instead of relying on a fixed 700px minimum or a constant event row count. Compact cells use reduced date/event padding and a smaller event font, and the month grid adapts event-row density to available pixels. The normal appointment card retains time/title; the full existing appointment content remains available via the expanded hover preview. Additional appointments use FullCalendar's +more popover.

The month panel avoids scrolling under normal desktop dimensions. If the available height per week becomes too small to display a usable calendar, a scrollbar is permitted as the last fallback. Week/Day behavior and mobile layout remain unchanged.

No live browser or database test was performed. Validate six-week months, small desktop heights, zoom, dense appointments and hover.
