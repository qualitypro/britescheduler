# Cycle 90U — Fit Month View to Browser Viewport

Desktop Month view now measures the actual visible calendar panel height and sets FullCalendar's height to the available pixels. Month cells remain proportional, with compact event cards and at least two event rows plus an overflow link for unusually crowded dates (dayMaxEventRows:3). FullCalendar's +more popover reveals overflow appointments. The separate expanded hover preview remains attached to document.body and is not clipped.

Week/Day retain natural time-grid scrolling. On view changes, tab activation, and window resize, the calendar recalculates its available height. Smaller/mobile layouts keep the existing responsive behavior.

Tradeoff: a finite viewport cannot display unlimited appointment cards; crowded cells require +more. Browser testing still needed, particularly small screens, 6-week months, and browser zoom. No remote browser test executed.
