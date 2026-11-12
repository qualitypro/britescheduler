# Cycle 90AA — Remove month-view vertical scrollbar

Fix the month panel's nested height calculation: previously the card, calendar wrapper and FullCalendar were all set to height:100%, while the card also had padding, producing an oversized layout. The card now uses border-box sizing and flex-column layout, with the calendar as a shrinking flex child. FullCalendar receives the exact available height measured to the panel/viewport bottom minus actual card padding. Remove the per-week threshold that switched the month panel to overflow-y:auto even when the calendar was supposed to fit. The compact month still uses +more for excess events, preserving full visible appointment time/title.

No browser test performed; verify six-week months, zoom, varying toolbar heights, popovers and small screens.
