# Cycle 90AI — Fix blank area below month grid

Root-cause hypothesis: FullCalendar's outer .fc has height:auto from page styles while the surrounding calendar card is viewport-fitted. expandRows alone cannot stretch the grid if the calendar root does not occupy the available height.

Month-only CSS now gives .fc an explicit 100% height and flexes its view harness. The existing viewport-fit routine assigns the measured pixel height to #calendar as well as FullCalendar. When leaving desktop month view the inline host height is removed. Preserves variable 4/5/6-week counts, non-current date hiding, cards, popovers and actions.

Browser verification still required: confirm no blank area or clipped final row at different heights, 4/5/6 week months, and Day/Week transitions.
