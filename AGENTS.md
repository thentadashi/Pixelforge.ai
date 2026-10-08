# PixelForge working instructions

This is a Laravel 12 + Vue 3 application. Read `docs/ARCHITECTURE.md`, `docs/HANDOFF.md`, and the relevant code before making changes. Inspect current Git status first. Existing application code and verified tests take precedence over old chat summaries or prototype screenshots.

Keep architecture and handoff documentation current whenever workflows, roles, state transitions, schema, or deployment requirements change. Report what changed, what was tested, and what remains unverified. Be precise about source completion versus deployed behavior.

Protect client project boundaries, session/CSRF behavior, one-time invitations, booking uniqueness, and immutable accepted quotations. Never accept role changes from client input or expose private file storage. Do not place credentials, `.env`, logs, real client records, or database files in Git.

For production-related work, use a scoped branch and inspect migrations before applying them. Do not run destructive database resets, replace app keys, modify another application’s database, or deploy to an unrelated server. Use isolated test data. Do not deploy or merge unless authorized by the current task.

Run checks appropriate to your change: `php artisan test`, `npm test`, and `npm run build` for workflow changes. Do not claim browser or production verification without performing it. Keep fictional public demos separate from the real client portal.
