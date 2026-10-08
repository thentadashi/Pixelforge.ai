# Implementation handoff

## Included

- Laravel 12, Vue 3, Vue Router, Vite, reproducible Composer/npm lockfiles.
- Preserved PixelForge navy/blue layout, service cards, founder profile, and sector demos.
- Persistent website content editor with services/case-study cards and founder-photo upload.
- Database-backed discovery requests, available-time filtering, unique slot reservation, and admin booking notes/status.
- Administrator bootstrap command, session login/logout, password reset, and expiring single-use invitations.
- Admin/client quotation views with immutable responses and automatic project creation on acceptance.
- Projects, ordered milestones, updates, client approvals/revision history, computed progress.
- Support tickets, threaded replies, resolve/reopen actions.
- Private 10 MB file uploads with project-scoped download and removal rules.
- Invoice records, client visibility for issued invoices, client printing / browser Save as PDF.
- Automated feature/frontend checks and CI, architecture and deployment instructions.

## Deliberate boundaries

The public mini-apps remain fictional-data demos in browser storage. Invoice payments happen outside the portal. Meeting requests require team confirmation/contact; no calendar, video-call link, or automatic booking email is integrated. Email defaults to local logs until SMTP is configured. There is no external payment gateway, real-time chat, SMS provider, or deployment automation.

Active client accounts use password reset; account invitation is for initial activation. Accepted/declined quotations and client revision notes stay as history. Project archival replaces destructive deletion of project history.

## Validation and release status

PHP feature tests, frontend tests, Vite production compilation, and fresh SQLite migrations are run during implementation. Record the final passing counts in the pull request. A graphical browser walkthrough was not available in this workspace; run the deployment document’s target-server checks before client launch. MySQL is supported by the schema and drivers, but was not connected during implementation.

This is editable application source delivered for review. The initial implementation does not replace the existing Sites publication or deploy PHP to a production server.
