# Brite Scheduler — Software Operator's Manual

**File:** `manual.md`  
**Audience:** Tenant owners, administrators, schedulers, accounting users, contractors, clients, and designated system operators  
**Applies to:** `qualitypro/britescheduler`, development branch `feature/cycle88-crm-responsive-workspaces`  
**Edition:** November 2026  
**Scope:** Base application operations, with optional modules identified separately

> This is an operating guide, not an installation script, security certification, or guarantee that every screen is enabled for every user. The application is under active development. Your tenant's role permissions, installed migrations, and activated modules determine what you see. Screen labels and workflows can change between releases.

## 1. Purpose and operating model

Brite Scheduler is a browser-based, multi-tenant service operations application. A **tenant** is a business or organization with its own users, clients, contractors, service catalog, appointments, billing records, and settings. Users sign in to a tenant context. The server determines tenant membership and role; selecting a different record in the browser does not grant access to another tenant's data.

A typical service workflow is:

1. Configure business settings, users, and service offerings.
2. Add clients and their service locations.
3. Add contractors, their availability, service offerings, qualifications, and coverage areas.
4. Create a booking request or appointment.
5. Review scheduling conflicts and, when available, advisory contractor recommendations.
6. Assign a contractor directly or manage an unassigned appointment through Marketplace.
7. Track appointment and assignment status.
8. Record invoices and payments, review notifications, and inspect operational reports.

### Terms used in this manual

| Term | Meaning |
| --- | --- |
| Tenant | A business account whose operational records are logically separated from other businesses |
| Client | Person or customer requesting services |
| Contractor | Service provider who may offer services, accept assignments, and bid |
| Service | Tenant-managed item in the service catalog |
| Client location | Saved address where service may be performed |
| Appointment | Scheduled service record with a date/time, service, client, and possibly an assignment |
| Assignment | Contractor relationship and decision associated with an appointment |
| Marketplace | Bidding workflow for eligible appointment listings |
| Smart Dispatch | Advisory contractor recommendations, not an automatic assignment decision |
| Module | Optional server-installed feature that must be enabled for a tenant and permitted for the role |

## 2. Signing in and finding your workspace

1. Open the Brite Scheduler URL supplied by your organization.
2. Sign in with your account credentials. Never share credentials.
3. Use the sidebar to open the screens available to your role. Your dashboard may differ from another user's.
4. Use the account controls to review your profile/preferences or sign out.
5. When finished on a shared device, **sign out**; simply closing a browser tab is not an equivalent access-control step.

The application routes clients to the **Client Dashboard**, contractors to the **Contractor Dashboard**, and other roles to the main operations dashboard. The sidebar includes role-appropriate links; optional module links appear only when the module is active and the role is allowed.

**Navigation tips:** The app provides keyboard focus styles, a **Skip to main content** link, contextual tooltips, and responsive panels. Some forms display compact value summaries; reopen the underlying control to edit it. On narrow screens, use the responsive navigation and scroll within data panels where needed.

## 3. Roles and responsibilities

| Role | Typical tasks |
| --- | --- |
| Owner | Business configuration, operational oversight, contractor/client management, Marketplace awards, and reporting |
| Admin | Delegated administration and operational management |
| Scheduler | Appointment creation, booking coordination, availability and assignment review |
| Accounting | Invoice/payment workflows and authorized financial reporting |
| Contractor | Availability, qualifications, offerings, service area, profile, bids, and assigned jobs |
| Client | Own service locations, booking requests, appointments, and eligible Marketplace information |

**Important:** This table is descriptive, not a full permission matrix. Every protected action remains subject to endpoint-specific role, tenant, and relationship checks. A missing menu item usually indicates a permission or activation difference; do not work around it by manually changing URLs.

## 4. First-day setup checklist for owners and administrators

- Confirm the correct tenant/business name and contact information in business settings.
- Review authorized users and roles. Give each person only the access required for their work.
- Set up the service catalog and verify service names, prices, and availability rules.
- Add or verify client records and service locations.
- Add contractor records and confirm qualifications, service offerings, pricing, availability, and geographic coverage.
- Review scheduling settings and notification preferences.
- Confirm the invoice and payment recording process.
- Enable only trusted optional modules needed by the business.
- Test a sample booking through assignment and completion before relying on the workflow operationally.

For tenant onboarding, verify that required database migrations have been applied. A feature appearing in the navigation does not prove that its backing schema is installed.

## 5. Client directory and service locations

### Create or update a client

1. Open **Clients** from the authorized operations navigation.
2. Search for an existing record before creating a new one to avoid duplicates.
3. Create or open the client record; enter the required contact details.
4. Save, then confirm the updated client appears in the directory.
5. Review associated locations and appointment history where the screen makes them available.

The client directory supports sortable information such as client name, email, creation/edited timestamps, and status. Exact fields and available actions depend on the current release and role.

### Manage service locations

1. Open **Locations** or **My Locations**, as appropriate for your role.
2. Add a location with a meaningful label and complete address.
3. Save it, then designate the primary location if appropriate.
4. Edit or remove outdated locations only after checking for related appointments.
5. When booking a service, select the intended saved client location.

**Location accuracy:** Geocoding can resolve an address to coordinates, but a match is not a guarantee that the address or entrance is correct. Review address details with the client. Some failed or pending geocoding results may be eligible for a controlled retry.

## 6. Service catalog and contractor setup

### Services

Maintain the tenant's service catalog with understandable descriptions and pricing. Review the service selection before scheduling; a service price may later be used in operational estimates. A catalog price is **not** necessarily the amount finally quoted, invoiced, or collected.

### Contractor records and availability

1. Open the contractor directory and select or create the correct contractor.
2. Review contractor status and contact/assignment details available to your role.
3. Configure or confirm working availability.
4. Set service offerings and, where supported, hourly or flat-rate prices.
5. Review qualifications and their verification state.
6. Configure ZIP/postal-code coverage or geographic radius as applicable.
7. Save changes and confirm that scheduling recommendations reflect the intended settings.

A **verified qualification** represents a review within the platform; it is not independent proof of a government license or certification.

### Contractor profile and portfolio

Contractors can maintain a professional headline, biography, specialties, experience, and an HTTPS portfolio link from the contractor profile editor. Where the portfolio feature and its migration are installed, contractors may manage JPEG/PNG portfolio photos.

The documented portfolio limits are **20 photos per contractor** and **5 MB per image**. Portfolio uploads require PHP GD and a configured private upload directory outside the web root. Uploaded images are re-encoded; never treat uploads as a substitute for verifying contractor qualifications.

## 7. Scheduling and appointments

### Create an appointment

1. Open **Scheduling** or the applicable booking interface.
2. Choose the client and the service.
3. Choose the correct saved service location.
4. Set the service date and start/end times; verify the displayed timezone.
5. Review availability and any scheduling conflict warnings.
6. Select an appropriate contractor if assignment is intended, or leave unassigned when the workflow allows it.
7. Review the wizard's final summary and save.
8. Verify that the appointment appears in the calendar/list and has the expected status.

**Do not ignore conflicts.** If the wizard requests confirmation because recommendations could not be refreshed or issues were detected, inspect the warning before proceeding. The application performs separate validation when saving; advisory recommendations are not a guarantee of successful assignment.

### Smart Dispatch recommendations

For authorized scheduling roles, Smart Dispatch can suggest active contractors using service offerings, qualification review, availability/conflicts, geographic matching, and workload. It may show a score, ZIP/radius match, and straight-line distance.

Use recommendations to support a decision, **not** as authorization to assign. Straight-line distance is not driving distance. An unavailable or outside-area contractor may still participate in Marketplace bidding under that separate workflow.

### Changes, cancellation, and completion

Open the existing appointment to review available actions for changing its schedule, assignment, or status. Confirm whether related contractor notifications, bids, or invoices require follow-up. Do not assume changing an appointment automatically updates every downstream business record.

## 8. Client booking workflow

Clients can use **Book a Service** from their dashboard, choose available options, and submit a booking request. Availability shown before submission may change if another booking is saved. After submission, review the resulting booking status and any activity notifications. Booking requests include duplicate-submission protections, but users should still avoid repeatedly submitting the same request.

Clients should keep their own service locations current and contact the business when an address or requested service is incorrect.

## 9. Marketplace and contractor bids

Marketplace supports an alternative to direct assignment for eligible appointments.

### For owners/admins

1. Open **Marketplace** and review the available listings.
2. Check the underlying appointment, requested service, date, and location.
3. Review bids and relevant contractor details.
4. Confirm the chosen contractor and use the authorized award/assignment action.
5. Verify the resulting listing, bid, and assignment statuses.
6. Communicate changes when the client or contractor needs clarification.

### For contractors

1. Open **Marketplace** and review listings visible to your tenant/role.
2. Read the appointment requirements and assess your own ability to perform the job.
3. Submit or manage a bid using the controls provided.
4. Monitor bid status and assignment notifications.
5. Do not treat a submitted bid as an awarded job.

Marketplace may permit bids even when ordinary service-area or availability constraints would affect direct recommendations. The contractor remains responsible for evaluating practical availability and travel.

### For clients

Open **My Marketplace Requests** or the Marketplace view available from your dashboard to review eligible activity on your own requests. Contractor profile details may be visible when a bid or assignment creates an authorized relationship. Withdrawn bids and unrelated contractor profiles are not generally available for client inspection.

## 10. Invoices, payments, and financial records

1. Open the authorized billing/invoice area.
2. Select the correct client and, where applicable, linked appointment.
3. Review invoice line items, totals, status, and outstanding balance.
4. Save and verify the invoice.
5. Record payments only when confirmed by the actual payment method or external processor.
6. Reconcile ledger entries against external records regularly.

**Critical accounting distinction:** The built-in payment functionality is a **ledger**, not a PCI-compliant payment-card processor. Never enter or store card numbers, CVV values, or other payment credentials in application notes or fields.

An invoice total, an outstanding balance, an estimated service opportunity, and a received payment are different measures. Do not add them together as if they were equivalent revenue.

## 11. Notifications and Activity Hub

The application includes an **Activity Hub** indicator near signed-in user details and a full **Activity Hub** page.

1. Open the notification indicator or Activity Hub.
2. Switch between outstanding and all activity where supported.
3. Open a related appointment or review link when available.
4. Mark or dismiss activity only after handling it.
5. If an expected notification is absent, verify the underlying appointment, bid, or assignment directly.

Notifications are aids to operations, not an authoritative replacement for checking the underlying record.

## 12. Preferences and accessibility

Open the user preferences area to adjust available settings:

- Color palette (including Ocean, Forest, Midnight, and Slate)
- Calendar view and week start
- 12/24-hour time format
- Reduced motion
- High contrast
- In-app notifications

Settings are user-specific where supported. The high-contrast default applies to newly created preference records in the relevant migration; it does not necessarily overwrite existing users' choices.

Use keyboard navigation, visible focus indicators, and the skip link when needed. If a dialog opens for photo inspection or export preview, close it through its provided controls before returning to the underlying page.

## 13. Booking trends (base application)

The **Trends** workspace provides a rolling three-month view centered on the selected month, with appointment-status filters and daily, weekly, or monthly grouping.

Available series include appointment and booking-request counts, assigned/unassigned activity, contractor assignment decisions, and **Gross Booked Value**. Select the chart type and series, then inspect the summary and plot.

**Interpretation:** Gross Booked Value is an estimate derived from eligible invoice totals or service catalog prices. It is not the same as collected cash or recognized revenue. Cancelled and no-show appointments do not contribute booked value in the documented calculation.

## 14. Optional modules — separate from the base software

Modules are installed by a trusted server operator under `modules/<module-key>/` with a valid `module.json` manifest. Owners/admins can activate eligible modules through tenant settings. Access additionally depends on module role permissions.

**Do not install arbitrary third-party PHP modules.** Installed module code executes on the application server and must be reviewed and trusted.

### CRM module

Where activated, CRM provides lead management, Customer 360, follow-ups, quote integration, audience/segment management, and campaign workflows. Use CRM for prospect and relationship tracking; do not assume a CRM lead is already a base-app client until the relevant conversion/linking workflow has been completed.

### Intelligence module

Where activated, **Intelligence** offers a Performance workspace and a Financial workspace (the latter limited to authorized owner, admin, and accounting roles).

- Select a date range, grouping interval, and metrics to update charts and tables.
- Save and reload personal views.
- Compare Performance metrics against the preceding equal-length period.
- Inspect KPI totals and calculated completion/cancellation/no-show rates.
- Export currently displayed data as CSV or chart images as PNG/JPG/PDF.
- Enable the optional Financial next-period outlook to view a simple recent-period average estimate.

**Reporting cautions:** Rate summaries are weighted by appointment counts. Rate changes are shown in percentage points. Financial outstanding balances represent balances on invoices created during the selected period, not an historical as-of balance. Opportunity values use current service list prices, not contracted historical amounts. Financial outlooks are illustrative, not reliable cash forecasts.

**Not yet a supported operator workflow:** Automatic scheduled reports, emailed report delivery, shared dashboards, and drag-and-drop dashboard persistence should not be assumed available.

### Sample Test Module

A reference module demonstrates activation, role-scoped navigation, and authenticated-user-specific content. It is intended for demonstration and operator verification, not production business records.

## 15. Common operating problems

| Symptom | What to check |
| --- | --- |
| Cannot sign in | Correct account, URL, password, session/browser settings; contact tenant administrator |
| Missing menu or module | Role permissions, tenant module activation, installed module manifest |
| Client/contractor record missing | Search terms, correct tenant, record status, user access |
| Appointment will not save | Required fields, time order, client location, service selection, scheduling conflicts |
| Contractor not recommended | Availability, service offering, qualification, geographic coverage, saved location |
| Bid/profile not visible | Listing status, contractor relationship, role and tenant access |
| Map or location unresolved | Address accuracy, geocoding status, provider configuration; retry only when eligible |
| Financial report differs from ledger | Selected dates, invoice/payment statuses, current balances vs historical measures |
| Saved view fails | Metric selection, permitted role, date range, Intelligence schema migration |
| Portfolio upload fails | JPEG/PNG type, size/count limits, PHP GD, private storage configuration |
| Interface looks incorrect | Refresh page, check responsive layout, user palette/high contrast, browser console |

If a task fails, record the time, screen, intended action, visible error, and affected record identifier **without including passwords, session cookies, or other secrets**. Escalate to your tenant administrator or application maintainer.

## 16. Operator safety and data handling

- Use individual accounts and least-privilege roles.
- Confirm tenant, client, contractor, and appointment before changing or deleting records.
- Avoid duplicate clients, bookings, invoices, and payments.
- Keep client contact details and service addresses confidential.
- Do not export financial or personal data to unmanaged devices or public folders.
- Never place secrets, private uploads, production backups, or diagnostic dumps in Git.
- Confirm external payment receipt before marking ledger payments successful.
- Review data and permissions after any module activation or major update.

## 17. Technical operations appendix (system operators)

This section is for personnel responsible for the application runtime, not ordinary tenant users.

### Stack and directories

The project uses PHP, MariaDB, browser JavaScript, and server-rendered PHP pages. The documented development environment uses Fedora Linux and a LAMPP-style installation. Repository areas include:

| Path | Purpose |
| --- | --- |
| `api/` | Session-authenticated JSON endpoints |
| `app/` | Authentication, database, domain services, validation, authorization |
| `assets/` | Frontend styles and scripts |
| `migrations/` | Ordered schema changes |
| `modules/` | Trusted optional module code |
| `partials/` | Shared layout and navigation |
| `setup/` | Initial setup utilities |
| `docs/` | Supplemental project documents |

### Deployment and maintenance checklist

1. Verify the intended Git branch and review changes before deployment.
2. Back up the database and application-specific private storage.
3. Confirm secrets and `.env` remain outside source control.
4. Review and apply outstanding migrations **in numeric order** using the project's supported process. Do not blindly re-run SQL scripts.
5. Verify database connectivity and required PHP extensions; portfolio uploads require GD.
6. Keep `BRITE_PRIVATE_UPLOAD_DIR` outside the public web root and ensure correct filesystem permissions.
7. Confirm HTTPS, session-cookie security, least-privilege database credentials, and server error logging.
8. Run PHP lint and appropriate application tests.
9. Verify sign-in, role navigation, tenant isolation, booking, assignment, Marketplace, billing, and module activation with authorized test accounts.
10. Review logs after deployment and maintain a tested rollback procedure.

**Database compatibility:** The qualifications migration documents MariaDB 10.4 or later. Verify other runtime requirements against the actual deployed source.

### Backup and recovery

Back up both the database and any private upload storage. Record backup timestamps, restore procedures, encryption/access controls, and retention policy. Test restoration in an isolated environment before relying on a backup strategy. Do not restore a database over live production data without a reviewed recovery plan.

### Operational monitoring

Monitor HTTP/PHP errors, database availability, failed logins, migration state, storage capacity, and geocoding provider behavior. For incident investigation, preserve relevant sanitized logs and record the deployed commit SHA. Avoid recording secrets or sensitive customer details in diagnostic output.

### Change control

Keep feature work and deployment changes reviewable in Git. Do not push `.env`, credential files, local backups, private uploaded photos, or temporary debugging artifacts. New modules and migrations require additional review because they affect executable server code and persistent tenant data.

## 18. Documentation maintenance

Update this manual whenever a user-visible workflow, permission rule, navigation label, or operational prerequisite changes. The repository's `README.md` covers architecture and installation context; this file focuses on **how people operate the software**.

**Document limitations:** This edition is grounded in the repository's documented capabilities and reviewed authentication/navigation/module/reporting source. It has not been validated against every live screen or tenant configuration. Treat UI wording as guidance where exact labels vary by release.

---

**Brite Scheduler — Software Operator's Manual**  
**Maintained in:** `manual.md`  
**Repository:** https://github.com/qualitypro/britescheduler
