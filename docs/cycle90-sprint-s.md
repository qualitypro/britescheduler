# Cycle 90S — Restore visible appointment cards

Corrects Cycle 90R's overly constrained month grid. FullCalendar uses contentHeight:auto, dayMaxEvents:false and dayMaxEventRows:false, so appointment cards are not collapsed into +more links merely to fit the viewport. The scheduling Calendar tab becomes the single vertical scroll container when its natural content exceeds available space. The outer document and application content remain non-scrolling on desktop. The card/calendar elements no longer clip overflowing appointment rows.

Tradeoff: months with many appointments can require scrolling within the calendar tab; showing every card and never scrolling cannot both be guaranteed in a finite viewport. Week/Day time-grid scrolling should be browser-tested for any nested scrollbars. No browser/DB test executed remotely.
