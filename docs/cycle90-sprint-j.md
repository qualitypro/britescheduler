# Cycle 90J — CRM SQL-backed Customer and Opportunity Pages

The CRM Customers and Opportunities tabs now use server-rendered, tenant-scoped SQL pagination (25 records per page) with search across all records. Opportunity list also filters by stage before counting and pagination. Stable order and previous/next links preserve filters.

Legacy capped arrays remain in place for overview widgets and form dropdowns; those are **not** yet complete tenant-wide counts or full-record selectors. The Customer 360 detail link and opportunity stage-change forms remain server-rendered. No migrations or secrets.

Verify PHP syntax, CRM module dispatcher GET routing, POST form flows, searches, tenant isolation, and pagination links in authenticated browser/database tests. No runtime tests were executed.
