# Cycle 90C — Shared Server Pagination UI and Notification History

- Added `BriteServerPagination` to the existing `assets/js/brite-pagination.js` alongside the unchanged client-side `BritePagination`.
- The remote component expects `load({page,per_page})` to return `{records,pagination}`, invokes `render(records)`, and provides Previous/Next, page size and accessible loading/error text.
- Added opt-in `page`/`per_page` pagination to `api/user_notifications.php`. Counts and records are scoped to the authenticated tenant and user, and the original `all`/`offset` interface remains available.
- Client and contractor list APIs already support the same pagination metadata (Cycles 90A/90B). Existing screens continue to use client-side pagination until explicitly migrated.
- No secrets, migrations, or automatic changes to notification state.

## Follow-up integration
Migrate screen-specific list consumers one by one; they must preserve search and filter semantics on the server, and avoid paginating dropdown lookups or calendar grids. Test authenticated tenant isolation, unread count, notification history and browser pagination before merge.
