# Draggable Widget Framework — Sprint 5 Validation

Branch: `feature/cycle88-crm-responsive-workspaces`

## Preconditions

- Apply `migrations/030_dashboard_widget_layouts.sql` to the `britescheduler` database.
- Ensure `dashboard_widget_layouts` exists.
- Test with authenticated owner/admin, contractor, and client accounts in separate browser sessions.
- Enable Intelligence for a test tenant; use owner/admin/accounting to test Financial.

## Static checks

Run PHP lint on `dashboard.v2.php`, `client-dashboard.php`, `contractor-dashboard.php`, `api/dashboard_widget_layout.php`, and `modules/intelligence/index.php`.
Run `node --check assets/js/brite-widget-layout.js` if Node.js is installed.
Do not deploy if any lint or syntax check fails.

## Browser acceptance checklist

1. In Operations, Contractor, Client, Intelligence Performance and Financial, open **Customize widgets**. Confirm buttons appear and ordinary chart/table controls still function.
2. Drag a widget by its **Move** handle using a mouse. Reload and confirm order persists.
3. Repeat drag on a touchscreen (or mobile device) using the handle. Check scrolling outside the handle remains possible.
4. Focus a Move handle and press **Alt+ArrowLeft/Right/Up/Down**. Confirm order changes and focus remains usable.
5. Resize a widget. Verify responsive grid wrapping and chart readability at 1440, 1024, 768, 390 and 320 CSS pixels.
6. Hide a widget. Confirm a **Show ...** control appears while customizing; restore it individually.
7. Reset layout. Confirm all widgets reappear in their original order and sizes after reload.
8. Reload the page, sign in using another browser, and confirm the server-saved layout follows the same account.
9. Disable the Intelligence module and confirm Intelligence widget API requests return 403. Confirm Financial requests are denied for nonfinancial roles.
10. Confirm tenant/user isolation by changing tenants and accounts; layouts must not leak.
11. In Intelligence, confirm chart PNG/JPG/PDF exports, CSV, saved BI views, and the internally scrollable Available Series selector still work after widget reordering and resizing.
12. Confirm reduced-motion mode, keyboard focus visibility, and live announcements.

## Notes

- The current framework offers compact and wide grid spans, not arbitrary pixel resizing.
- The UI's account-sync fallback can mask an unapplied migration. Verify an actual database row and cross-browser restore, not merely reload in the same browser.
- This checklist documents required testing; it does not assert the tests have been executed.
