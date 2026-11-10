# Cycle 90V — Month grid visibility correction

The scheduling page still had a legacy #calendar min-height:700px. On shorter browser windows that minimum defeated the measured FullCalendar viewport height and clipped the bottom weeks. This change overrides the minimum to zero in desktop month-fit mode, tightens month header/date padding, and calculates event row limits according to available height and actual week-row count. The Calendar panel may scroll as a safety fallback instead of hiding the final week if the viewport is exceptionally short or zoomed.

Appointment hover preview remains intact. No browser test has been performed; check four-, five-, and six-week months at normal and zoomed desktop resolutions.
