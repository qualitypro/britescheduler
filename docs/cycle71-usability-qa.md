# Cycle 71 — Usability foundations (medium sprint)

## Delivered
- Mobile sidebar: Escape closes and returns focus to the menu button, Tab and Shift+Tab remain inside open navigation, and backdrop click closes and restores focus.
- Shared directory pagination: selectable 15 / 30 / 50 rows per page, resets to page 1 on change, and announces the total count in the pagination landmark.
- Shared application shell: visible keyboard focus, clearer disabled states, mobile pagination tap targets, and reduced-motion sidebar behavior.

## Manual acceptance matrix
1. At a mobile viewport (<=900px), open the menu, press Tab repeatedly, and confirm focus wraps inside the sidebar.
2. Press Escape: menu closes and focus returns to its toggle.
3. Click backdrop: menu closes and focus returns to its toggle.
4. Follow a sidebar link: navigation proceeds normally; no unexpected focus reset.
5. Resize from mobile to desktop with menu open: sidebar overlay closes.
6. In each directory using BritePagination, change rows per page from 15 to 30 and 50; verify visible counts, page count, and first-page reset.
7. Test pagination with 0, 1, 15, 16, 30, 31, and 51 records; verify button disabled states.
8. Test at 375px width and 200% browser zoom: pagination remains usable.
9. Keyboard-tab through shared buttons, links, and form fields: focus is visible.
10. Enable reduced motion in preferences: sidebar transition is removed.
11. Verify scheduling, Marketplace, and role-based dashboard screens remain functional.

## Local source checks
Run `node --check assets/js/brite-app-shell.js` and `node --check assets/js/brite-pagination.js`, then `git diff --check HEAD`. No database migration or API change is required.

## Limits
This sprint is a targeted shared-component usability improvement, **not** a complete UX audit of every screen. Runtime, keyboard, responsive, and accessibility acceptance checks are pending until tested locally. No licensing changes were made.
