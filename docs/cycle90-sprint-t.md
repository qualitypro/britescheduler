# Cycle 90T — Expanded calendar card hover preview

Restores a readable expanded appointment card on mouse hover or keyboard focus. The preview clones the existing calendar event card into a fixed-position, pointer-transparent overlay attached to the document body, so FullCalendar's day-cell overflow and the viewport scrolling container cannot clip it. It retains the event's status color only in the original card; the preview uses a high-contrast surface.

The preview displays the existing time, title, client, contractors and status fields without ellipsis; remains within the viewport; closes on mouse leave, focus loss, scrolling, resizing or Escape. Clicks still reach the original event and open the appointment detail dialog. Touch devices keep the existing tap-to-open behavior.

This is a source change only; local browser verification remains required for month/week/day, dense cells, keyboard focus, touch, and viewport edges.
