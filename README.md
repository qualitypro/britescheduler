<div align="center">

# ✨ Brite Scheduler

### Multi-Tenant Scheduling, Service Operations & Contractor Marketplace

**Manage clients, service locations, appointments, contractor availability, qualifications, bids, and billing in one platform.**

`PHP` · `MariaDB` · `JavaScript` · `HTML/CSS` · `Multi-Tenant Architecture`

**Documentation version:** v1.3 · **Repository:** [qualitypro/britescheduler](https://github.com/qualitypro/britescheduler)

</div>

---

## 📚 Contents

- [📖 Overview](#-overview)
- [✨ Features](#-features)
- [👥 Roles and access](#-roles-and-access)
- [🏗️ Architecture](#️-architecture)
- [🧰 Technology stack](#-technology-stack)
- [🗂️ Repository structure](#️-repository-structure)
- [🗄️ Database and migrations](#️-database-and-migrations)
- [🔌 API overview](#-api-overview)
- [⚙️ Installation and configuration](#️-installation-and-configuration)
- [🔐 Security model](#-security-model)
- [🗺️ Roadmap](#️-roadmap)
- [🤝 Contributing and licensing](#-contributing-and-licensing)

## 📖 Overview

**Brite Scheduler** is a browser-based, multi-tenant service operations platform designed for organizations coordinating clients, contractors, appointments, and related financial workflows. It combines a scheduling workspace with contractor eligibility, geographic service coverage, and an appointment bidding marketplace.

A **tenant** represents a business or organization. Tenant-scoped data and role-based access help separate each organization's operational records. The server resolves tenant membership from the authenticated session rather than trusting a tenant identifier supplied by the browser.

> **Project scope:** This guide describes Brite Scheduler's application capabilities and configuration on the `feature/cycle88-crm-responsive-workspaces` development branch. Availability of optional modules depends on tenant activation and role permissions.

## ✨ Features

### 📅 Scheduling and appointments

- Calendar and appointment management for service operations.
- Client appointment booking and available-slot lookup.
- Contractor availability, assignment validation, and conflict checks.
- Appointment-to-client-service-location relationships.
- Booking request idempotency to reduce accidental duplicate submissions.
- Client and contractor dashboards tailored to their workflows.

### 🪪 Contractor profiles and portfolios

Contractors can edit their own professional headline, biography, specialties, years of experience, and HTTPS portfolio URL from `contractor-profile.php` (linked from the contractor dashboard). The `api/my_contractor_profile.php` GET/PUT endpoint resolves the active contractor from the authenticated tenant/user session, validates inputs, and requires CSRF protection for updates. No user-supplied contractor ID is accepted for profile edits.

Apply **`migrations/020_contractor_profiles.sql` once** before opening the editor. It adds a separate tenant-scoped profile table without changing existing contractor rows. The existing `api/marketplace_contractor_profile.php` adds these fields only **after** its existing appointment/bid/assignment authorization check; email and phone remain excluded. The initial release uses the existing account identity, not image uploads.

**Contractor portfolio photos:** Contractors can upload and manage JPEG/PNG portfolio images, subject to authenticated access and tenant restrictions. Portfolio storage requires `migrations/021_contractor_portfolio_photos.sql`, PHP GD, and a writable `BRITE_PRIVATE_UPLOAD_DIR` outside the web document root. The portfolio supports up to 20 images per contractor, 5 MB per image; uploaded images are re-encoded to remove original metadata. Keep the private upload directory and its contents outside version control.

### 🛠️ Contractor management

- Contractor records, profiles, and skills.
- Contractor service qualifications with review and verification states.
- Per-contractor, per-service **hourly** or **flat-rate** pricing.
- Contractor availability and geographic service-area configuration.
- Eligibility, matching, and ranking components supporting contractor selection.

> **Qualification terminology:** A platform-verified service qualification records a tenant-authorized review. It does **not** independently certify a professional license or regulatory compliance.

### 🧭 Smart Dispatch

The scheduling wizard includes **read-only, advisory contractor recommendations** for owner, admin, and scheduler roles. The recommendation API (`api/contractor_recommendations.php`) requires a tenant-owned saved client location and valid appointment interval; an active service can be supplied to evaluate qualifications and offerings.

- **Service offering and pricing:** show whether a contractor offers the selected service and their configured hourly or flat rate. A missing offering is advisory and is not itself a new assignment prohibition.
- **Explainable eligibility and geography:** display ZIP/radius matches, straight-line distance from a configured radius center, availability/conflict information, and qualification/service-offering review issues.
- **Interactive filters:** filter recommendations by dispatch status, verified qualification, confirmed service offering, availability, and a maximum numeric rate. Filtering runs in the browser against the returned recommendation set and does **not** modify ranking or assignment eligibility. Hourly and flat-rate amounts are not directly comparable appointment estimates.
- **Appointment review integration:** show selected contractors in the final wizard step and request fresh advisory recommendations before save. Review warnings and failed refreshes require explicit user confirmation to continue; the existing scheduling conflict check and appointment API validation still apply. Saving an appointment with no contractor selected remains supported.

**Important distinctions:** The eligibility engine only recommends active contractors with a matching configured service area; Marketplace bidding has separate rules and can include contractors outside normal coverage/availability. A recommendation score is **not** authorization to assign. The ranking uses qualification (up to 35 points), availability/conflicts (30), geographic match (20), and workload (up to 15). Geographic distance is straight-line, not travel distance. The recommendation endpoint never auto-assigns contractors.

### 🤝 Marketplace and bidding

- Appointment bid listings and contractor bid submissions.
- Owner/admin bid review and award workflows.
- Contractor-facing marketplace and bid management.
- Client-facing **My Marketplace Requests** and eligible bid visibility.
- Contractor profile links from eligible bids and assigned appointments.
- Profile display of business information, skills, and applicable service qualifications.
- Relationship- and tenant-scoped access controls for client profile viewing.

### 📍 Client locations and geographic intelligence

- Multiple saved client service locations and primary-location selection.
- Appointment association with saved client locations.
- Geocoding cache, status tracking, and retry diagnostics.
- Contractor service areas based on postal codes or geographic radius.
- Service-location map views and service-area matching components.

### 🖥️ Application shell, directories, and accessibility

- Shared role-aware application header and sidebar, with a **notification indicator beside the signed-in user details** instead of a bottom-corner floating Activity Hub bubble.
- **Activity Hub drawer** with outstanding/all views, unread counts, dismissal controls, and appointment review links where supported by the notification type. The full Activity Hub page remains available.
- **Contextual tooltips** for navigation links, buttons, sorting controls, and expandable details, available on pointer hover or keyboard focus.
- **Progressive value pills** summarize selected or entered form values while preserving the underlying controls and submissions. Users can reopen the editor; screens can opt out using `data-brite-pills="off"`. Dropdown labels exclude their option lists.
- **Sortable, paginated lists** and expandable directory records where supported. The Contractors directory exposes Contractor, Created On, Last Edited, and Status sortable headings, with Status last. The Clients directory includes client name, email, created/edited timestamps, and status.
- Persisted UI preferences include palette, calendar view, week start, time format, reduced motion, high contrast, and in-app notifications. Migration `015` defaults new high-contrast preference records to enabled without overriding existing preferences.

### 📈 Booking and scheduling trends

The `trends.php` workspace and `api/trends.php` provide a **rolling three-month window** (previous, selected, and following month) with appointment-status filtering, daily/weekly/monthly aggregation, and line/bar chart views.

- Selectable series: appointments, booking requests, assigned/unassigned appointments, accepted/declined contractor assignments, and **Gross Booked Value (USD)**.
- Gross Booked Value is recalculated from the **union of selected appointment categories** using server-provided per-day membership masks; appointments shared by several categories contribute only once.
- The **Appointments** checkbox represents the all-bookings baseline and is mutually exclusive with narrower appointment categories. The Gross Booked Value checkbox controls chart visibility; its summary metric remains visible.
- Booked value uses non-void linked invoice totals where available, otherwise catalog service prices. Cancelled and no-show appointments contribute no booked value. **This is an estimate/booking metric, not collected cash or recognized revenue.**
- Assignment decision counts reflect current assignment states on the appointment's service date, not historical decision timestamps.

### 📊 Brite Intelligence — performance and financial analytics

The optional **Intelligence** module provides tenant-scoped business intelligence in a compact, responsive workspace with separate icon-labeled **Performance** and **Financial** tabs.

- **Performance:** Compare configurable appointment, operational, client, contractor, and billing series. The comparison chart normalizes each selected series for visualization, while the accompanying table retains actual values.
- **Financial:** Explore dollar-valued series for payments received, invoices issued, outstanding invoice balances, completed service opportunities, open opportunities, and cancelled/no-show opportunities.
- **Interactive controls:** Choose a date range, daily/weekly/monthly grouping, and the series to display. Charts, legends, data tables, and financial summary totals refresh automatically as controls change.
- **Saved views:** Save named chart configurations independently for Performance and Financial, select a saved view to load it immediately, or delete a view when no longer needed.
- **Exports:** Preview and download either chart as **PNG, JPG, or PDF**.
- **Accessible interface:** Compact native controls, keyboard-accessible tabs, a dedicated Intelligence navigation icon, and responsive layouts.

Financial data distinguishes **payments received** from **invoiced amounts**. Outstanding balances are current balances for invoices created during the selected period, rather than historical as-of snapshots. Opportunity values are **estimates based on current service list prices**; they are not confirmed quotes, collected revenue, or recognized income. The Financial tab is available to authorized owner, admin, and accounting roles; other Intelligence capabilities follow module permissions.

### 🧩 Optional modules

Brite Scheduler discovers trusted, server-installed modules from the `modules/` directory. Tenant administrators can activate available modules through application settings, with module-defined role access and tenant-scoped authorization. Modules can provide their own pages, navigation entries, APIs, and configuration without requiring every capability to live in the core scheduling interface.

Included modules include **CRM** (leads, Customer 360, follow-ups, quote integration, audiences and campaigns), **Intelligence** (performance and financial analytics), and **Sample Test Module** (a reference implementation demonstrating activation, role-based menu visibility, and authenticated role-specific messages). Module activation is distinct from permission to access an individual feature.

### 💼 Business operations and billing

- Tenant business profile and business contact information.
- Client, contractor, and service catalog management.
- Invoices and payment ledger workflows.
- Notifications and appointment-linked activity.
- Persistent user interface preferences, including calendar settings and accessibility options.

> **Payments:** The included payment functionality is an **accounting ledger**, not a PCI-compliant card processor. Do not store payment card numbers, CVV values, or card credentials. Any future card payment integration should use a compliant hosted checkout or tokenization provider and retain only appropriate provider references.

## 👥 Roles and access

Tenant membership determines a user's role. The application defines these roles:

| Role | General responsibility |
| --- | --- |
| **Owner** | Business administration, operations, Marketplace review, and award decisions |
| **Admin** | Administrative management and authorized Marketplace oversight |
| **Scheduler** | Scheduling and operational coordination |
| **Accounting** | Billing and finance-related workflows |
| **Contractor** | Availability, service offerings, Marketplace participation, and assigned work |
| **Client** | Service locations, booking requests, own appointments, and eligible Marketplace activity |

**Permissions are endpoint-specific.** This table describes broad responsibilities, not a guarantee of access to every screen or API.

## 🏗️ Architecture

```mermaid
flowchart TD
    B[Browser: PHP pages and JavaScript] --> P[PHP application and API endpoints]
    P --> A[Session authentication and role checks]
    A --> S[Service and validation classes]
    S --> D[(MariaDB tenant-scoped data)]
    P --> D
    S --> G[Geocoding and geographic matching]
```

The application uses server-rendered PHP entry points, JavaScript for interactive workflows, PHP API endpoints, and a MariaDB relational database. Core application classes cover authentication, database access, CSRF handling, scheduling validation, contractor eligibility, ranking, service-area matching, and geocoding.

### Core design principles

1. **Tenant isolation:** business records carry tenant context; authorization resolves tenant membership server-side.
2. **Role-based authorization:** protected endpoints verify authenticated users and applicable tenant roles.
3. **Relational integrity:** migrations add foreign keys, unique constraints, and tenant-aware relationships.
4. **Separation of responsibilities:** UI pages, API endpoints, and domain logic are organized in distinct directories.
5. **Incremental schema evolution:** numbered SQL migrations track database changes.

## 🧰 Technology stack

| Layer | Technology |
| --- | --- |
| Backend | PHP |
| Database | MariaDB / MySQL-compatible SQL |
| Frontend | HTML, CSS, JavaScript |
| Authentication | PHP sessions and tenant membership |
| API | PHP HTTP endpoints, primarily JSON responses |
| Database evolution | Numbered SQL migration files |
| Development environment used by the project | Fedora Linux with a local Apache/PHP/MariaDB stack |

**Version note:** Migration `011_contractor_service_qualifications.sql` explicitly requires **MariaDB 10.4+**. Confirm runtime compatibility with your deployment environment.

## 🗂️ Repository structure

```text
britescheduler/
├── api/                 # Authentication and domain API endpoints
│   └── auth/            # Sign-in, sign-out, registration
├── app/                 # Shared PHP services, validation, authorization
├── assets/              # JavaScript and other frontend assets
├── docs/                # Additional project documentation
├── events/              # Legacy event-related files
├── migrations/          # Ordered SQL schema migrations
├── modules/             # Optional tenant-activated application modules
│   ├── crm/             # Leads, Customer 360, follow-ups, segments and campaigns
│   ├── intelligence/    # Performance/financial BI, saved views and exports
│   └── sample-test/     # Sample module: activation, roles and user-specific content
├── partials/            # Shared application layout components
├── setup/               # Initial owner/bootstrap utilities
├── .env.example         # Environment variable template
├── README-BRITE-V2.md   # Earlier architecture/setup notes
├── module.php           # Authenticated module entry point
├── trends.php           # Booking/scheduling charts and booked value
├── activity-hub.php     # Full notification activity view
├── contractors.v2.php   # Contractor directory and qualifications
├── contractor-service-areas.php # ZIP/radius coverage and matching
├── bidding.php          # Marketplace interface
├── scheduling.php       # Scheduling interface
├── client-dashboard.php # Client workspace
└── contractor-dashboard.php # Contractor workspace
```

Other root-level pages provide billing, service locations, contractor service areas, preferences, and administrative workflows. Refer to the tracked source tree for the complete inventory.

## 🗄️ Database and migrations

The schema uses a `tenants` table, user records, tenant memberships, clients, contractors, services, appointments, and related operational records. Recent migrations introduce additional functionality:

| Migration | Purpose |
| --- | --- |
| `001_core.sql` | Core schema and migration tracking |
| `002_legacy_events_import.sql` | Legacy import compatibility marker; **does not import automatically** |
| `003_invoice_appointment_unique.sql` | Invoice/appointment uniqueness |
| `004_tenant_business_profile.sql` | Tenant business details |
| `005`–`007` | Client locations, integrity, appointment location links |
| `008`–`009` | Geocoding cache and location status |
| `010` | Contractor geographic service areas |
| `011` | Contractor service qualifications |
| `012` | Client booking request idempotency |
| `013` | Notification-to-appointment references |
| `014`–`015` | User preferences and high-contrast defaults |
| `016` | Contractor service pricing |
| `017` | Appointment bidding marketplace |
| `018` | Additional schema changes; inspect the actual migration before applying |
| `019_contractor_service_offerings.sql` | Tenant-scoped contractor service offerings and hourly/flat-rate pricing |
| `020_contractor_profiles.sql` | Tenant-scoped contractor professional profiles |
| `021_contractor_portfolio_photos.sql` | Contractor portfolio image metadata |

**Apply migrations in numeric order** using the project's supported migration workflow. Do not assume all migrations are safe to re-run on an existing database; back up the database first and check the migration history.

**Legacy events:** Older event records are **not automatically imported**. Their original user IDs may not safely map to current tenant memberships. Export, review, and map records to the correct tenant/client/contractor before any manual import.

## 🔌 API overview

The `api/` directory groups HTTP endpoints by capability:

| Domain | Representative endpoints |
| --- | --- |
| Authentication | `api/auth/login.php`, `logout.php`, `register.php` |
| Appointments | `api/appointments.php`, `client_bookings.php`, `client_available_slots.php` |
| Marketplace | `appointment_bid_listings.php`, `contractor_bids.php`, `appointment_bid_award.php`, `appointment_bid_activity.php` |
| Client Marketplace | `marketplace_client_listings.php`, `marketplace_contractor_profile.php` |
| Contractors | `contractors.php`, `contractor_availability.php`, `contractor_service_pricing.php`, `contractor_service_qualifications.php`, `contractor_service_offerings.php` |
| Smart Dispatch | `contractor_recommendations.php`, `contractor_conflict_check.php` |
| Geographic services | `client_locations.php`, `contractor_service_areas.php`, `service_locations_map.php` |
| Finance | `invoices.php`, `payments.php` |
| Notifications | `user_notifications.php`, `user_notification_read.php`, `owner_notifications.php` |
| Preferences | `user_preferences.php` |
| Trends | `trends.php` (rolling booking/assignment series and revenue membership masks) |
| Intelligence | `modules/intelligence/metrics.php`, `financial.php`, `views.php` (tenant-scoped analytics and personal saved views) |

This is a **directory guide, not a complete public API contract**. HTTP methods, request bodies, response schemas, and permissions should be checked in each endpoint before integration. Authentication is session-based; do not assume these are anonymous/public APIs.

## ⚙️ Installation and configuration

### Prerequisites

- A web server capable of running PHP (the project has been developed using a local Apache/PHP stack).
- MariaDB **10.4+** for the qualification schema.
- Database credentials for a dedicated, least-privileged application account.
- A PHP environment with the extensions required by the source code (extension inventory still to be verified).

### Initial configuration

1. Clone the repository and configure the web server document root/path for the application.
2. Copy `.env.example` to `.env` and configure the local environment. **Never commit `.env` or secrets.**
3. Create the application database and a **non-root** database account with appropriate privileges.
4. Apply the SQL migrations in ascending numeric order using the project wizard's `--migrate` workflow or a reviewed manual migration process.
5. Create the initial tenant owner using the existing setup command below, substituting your own values:

```bash
BRITE_ADMIN_PASSWORD='replace-with-a-long-unique-password' \
  php setup/create_admin.php acme "Acme LLC" owner@example.com First Last
```

6. If using the legacy setup wizard, review its `--activate` option before enabling the v2 pages.
7. Sign in and configure tenant settings, roles, and optional modules as needed.

> **Important:** Review deployment settings and migration requirements for your environment. Back up application data before applying schema changes.

### Environment variables

The tracked `.env.example` documents these configuration names:

| Variable | Purpose |
| --- | --- |
| `APP_ENV` | Application environment |
| `APP_URL` | Base application URL |
| `APP_TIMEZONE` | Application timezone |
| `SESSION_NAME` | Session cookie name |
| `DB_HOST` | Database hostname |
| `DB_PORT` | Database port |
| `DB_NAME` | Database name |
| `DB_USER` | Database username |
| `DB_PASS` | Database password |

Geocoding and initial-admin settings may be referenced by additional PHP environment lookups; check the implementation before deployment.

## 🔐 Security model

- Session-based authentication and tenant-membership role checks.
- Server-side tenant resolution rather than trusting client-provided tenant identifiers.
- Tenant-scoped SQL relationships and authorization checks.
- CSRF support through the application's `Csrf` component.
- Relationship-scoped contractor profile visibility for clients.
- Restricted bid visibility for eligible statuses, excluding withdrawn bids.
- No storage of payment-card credentials in the accounting ledger.

**Security disclaimer:** Architectural safeguards are not substitutes for a full production security assessment. Review session settings, HTTPS, secrets management, deployment hardening, and access-control coverage before public deployment.

## 🗺️ Roadmap

Potential future enhancements include richer map-based discovery, proximity filtering, formal API integration contracts, expanded deployment guidance, additional financial reporting and valuation options, and optional hosted/tokenized payment processing. These are prospective capabilities, not commitments or claims of existing functionality.

## 🤝 Contributing and licensing

This project is under active development. Contributions should follow existing conventions, preserve tenant isolation and role-based access, and avoid introducing unsafe dependencies. Avoid committing credentials, local environment files, backups, or generated debug artifacts.

**License:** No license file was identified in the repository audit. Until the project owner selects and publishes a license, **do not assume the repository is open-source licensed** merely because the code is publicly accessible.

---

<div align="center">

**Brite Scheduler** · Scheduling, service operations, Marketplace, and Intelligence

*README v1.3 — updated November 2026*

</div>
