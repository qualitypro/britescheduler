# Cycle 90Z — Appointment hover preview above +more popover

FullCalendar's +more popover could paint over the custom expanded appointment hover preview because the preview used z-index:1200. Raise the body-mounted, fixed-position preview to z-index:2147483000 so it stays above FullCalendar popovers and app layers. Pointer-events:none remains intact, preserving click/hover behavior for the underlying appointment cards. No browser test performed; verify hover on cards inside an open +more popover.
