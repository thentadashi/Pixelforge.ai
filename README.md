# PixelForge.ai — Laravel + Vue

An editable Laravel 12 / Vue 3 application based on the PixelForge.ai Sites prototype. The public branding, founder profile, and four fictional-data demos are retained. Bookings, content edits, client invitations, quotations, project milestones, approvals, revisions, support conversations, private files, and invoice records now use the Laravel backend.

This application runs on PHP hosting. It is not deployed by pushing to GitHub, and cannot run as PHP inside ChatGPT Sites. The original Sites publication remains a separate prototype.

## Requirements

- PHP 8.2+ with ctype, curl, dom, fileinfo, mbstring, openssl, PDO, tokenizer, XML, and SQLite or MySQL extensions.
- Composer 2, Node 22+, npm.
- SQLite for local setup; MySQL 8 is also supported through standard Laravel `.env` configuration.

## Start locally

```bash
git clone https://github.com/thentadashi/Pixelforge.ai.git
cd Pixelforge.ai
git switch feature/laravel-vue-platform
composer run setup
php artisan pixelforge:admin
composer run dev
```

Open `http://localhost:8000`. The administrator command prompts for your email and password; there are no built-in accounts or sample passwords. `composer run setup` creates a local SQLite database, runs migrations and the content seeder, links public founder photos, and builds the frontend. Run it on a new local installation only; use the deployment instructions for an existing database.

On Windows, Laravel Herd or a configured PHP/Composer installation works. Run the commands from PowerShell in your project directory. `composer run dev` starts Laravel and Vite together. For a built frontend, you can instead use `npm run build` followed by `php artisan serve`.

## Use the application

1. Sign in at `/login` as the administrator. Edit homepage, founder information/photo, booking copy, services, maintenance descriptions, and approved case studies under **Website**.
2. Public visitors request a discovery call at `/book`. Slots are held in the database; competing requests cannot reserve the same time. Review requests under **Bookings**, contact the client, then mark appointments confirmed. Cancelling releases a slot. Booking requests do not send confirmation emails automatically.
3. Add a client under **Clients** and create an invitation. You may copy the link or choose email delivery. Links expire after seven days and are single-use. Active clients use password reset instead of new invitations.
4. Draft a quotation, then set its status to **Sent**. The invited client can accept or decline it. Acceptance creates one planning project. An answered quotation stays immutable; create another for revised terms.
5. Add milestones and project updates. Set deliverables to **Ready for review**. Clients can approve or request revisions; previous revisions remain recorded. Approved milestones determine the displayed progress.
6. Use **Support** for tickets and replies, **Files** for private uploads, and **Invoices** for invoice records. Clients see issued invoices; payments are arranged separately. Client invoice printing supports the browser’s Save as PDF option.

Admin-created records can be edited; project records can be archived. Accepted quotations and client decisions are deliberately retained as history. Updating an approved milestone reopens its review rather than silently changing an approval.

## Email

Local setup uses `MAIL_MAILER=log`. Reset links and explicitly requested invitation emails are written to `storage/logs/laravel.log`; this is not delivery to a recipient. Set your SMTP provider in `.env` for real email. Set `APP_URL` to your real HTTPS origin so links point to the correct host. The UI reports the configured invitation mailer and provides a copyable link if dispatch fails. Do not commit logs or `.env`.

## Public demos

`/demo/business`, `/demo/government`, `/demo/school`, and `/demo/corporate` use fictional data in browser storage. They never write to client projects. Records persist in that browser until **Reset demo** is used. These are workflow demonstrations, not separate production inventory, barangay, enrollment, or HR applications.

## Validation

```bash
php artisan test
npm test
npm run build
```

Feature tests cover authentication, CSRF, role restrictions, project isolation, invitations, booking conflicts, quotation transitions, approvals/revisions, support threads, file access, and editable content. Frontend tests cover CSRF renewal, expired sessions, out-of-order availability responses, persisted booking confirmation, and demo stock behavior. GitHub Actions runs the same tests and production build.

See [architecture](docs/ARCHITECTURE.md), [deployment](docs/DEPLOYMENT.md), and [handoff](docs/HANDOFF.md). The application has been tested with SQLite in this workspace. A production browser walkthrough and MySQL deployment validation remain required on your target server.
