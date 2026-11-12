# Cycle 90L — CRM Leads and Activities List Pagination

CRM Leads and Activities tabs now use tenant-scoped SQL pagination (25 per page) and search before count and limit. Leads search name/email/phone/source; activities search details/type/related lead, customer, or opportunity. Deterministic ordering and shared previous/next navigation preserve the query. Existing lead conversion, status updates, activity completion, and creation forms are unchanged.

Existing capped arrays remain for form dropdowns and unrelated CRM screens. No migrations or secrets. Runtime PHP syntax, browser interactions, tenant isolation, and database tests have not been executed and must be completed before merge.
