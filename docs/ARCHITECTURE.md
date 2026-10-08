# Architecture

## Runtime and source

Laravel 12 serves a Vue 3 SPA through `resources/views/app.blade.php`. Vue Router uses ordinary URLs and Laravel returns the SPA for known frontend navigation. `/api/*` routes live in `routes/web.php`, retaining Laravel session cookies and CSRF middleware. API errors return JSON. Vite builds frontend assets into `public/build`.

The website has three areas:

- Public pages: editable studio content, discovery-call requests, and isolated fictional-data demos.
- Team workspace: administrator-only client/content/booking/project management.
- Client portal: invitation-based login with only that client’s quotations, projects, files, invoices, approvals, and support threads.

## Main files

| Responsibility | Location |
|---|---|
| Route contracts | `routes/web.php` |
| Authentication and invitation activation | `app/Http/Controllers/AuthController.php` |
| Administrator role boundary | `app/Http/Middleware/AdminOnly.php` |
| Project ownership boundary | `app/Services/ProjectAccess.php` |
| Default public content | `app/Services/SiteContent.php` |
| Bookings and unique slot reservation | `app/Http/Controllers/BookingController.php` |
| Admin record validation and editing | `app/Http/Controllers/AdminController.php` |
| Client workspace and decisions | `app/Http/Controllers/WorkspaceController.php` |
| Private upload/download authorization | `app/Http/Controllers/FileController.php` |
| Database schema | `database/migrations/2026_10_08_000001_create_platform_tables.php` |
| Shared API and CSRF renewal | `resources/js/api.js` |
| Workspace page | `resources/js/pages/WorkspacePage.vue` |
| Structured record fields | `resources/js/resource-schema.js` |
| Public website content editor | `resources/js/components/ContentEditor.vue` |

## Data and transitions

`users.role` is admin or client; browser requests cannot assign roles. The first admin is created with `pixelforge:admin`. Invitations store a SHA-256 digest, expiration, and a one-time activation token. Login regenerates the session and returns the fresh CSRF token to Vue. Passwords are hashed using Laravel.

A booking stores a UUID reference and a unique `date/slot` reservation key. Cancelled bookings have a null key. Uniqueness is enforced by the database so competing submissions cannot both reserve a time. Times are in Asia/Manila; dates are accepted up to three months ahead.

Quotations belong to clients. A draft becomes sent; the client chooses accepted or declined. Acceptance locks the quotation row in a transaction and creates a project with a unique quotation ID. Repeat decisions are rejected. Answered quotations cannot be edited.

Projects own milestones, updates, tickets, files, and invoices. `ProjectAccess` and the workspace queries enforce the client boundary. Existing project records cannot be moved between projects. Quotation-linked projects cannot be reassigned to another client. Administrator project status includes archived; history is preserved.

Milestones move from planned/in progress to ready for review. Only the owning client can approve or request a revision. Decisions lock the milestone row. Revision notes are append-only. Administrator edits clear the previous approval timestamp and return approved/revised deliverables to review when submitted through the form. Project progress counts approved milestones only.

Files live in `storage/app/private` and are downloaded only through authenticated, ownership-checked routes. Founder photos intentionally live in public storage. Never expose private storage with a public symlink. Invoice amounts use database decimals, and draft invoices are hidden from clients. No payment gateway is included.

## Keeping context current

Update this document and `docs/HANDOFF.md` when changing architecture, routes, state transitions, role rules, migrations, or deployment requirements. Treat actual code and current tests as authoritative; summaries and prototype behavior are supporting context. Keep changes scoped to this application and do not reuse credentials or production databases from other projects.
