# Deployment to PHP hosting

This repository is separate from the Sites prototype. Use a new app directory, database, and domain/subdomain for the first deployment. Do not reuse the WCC licensing app directory or database.

## First installation

Provision PHP 8.2+ / PHP-FPM, required extensions, a database, and an HTTPS virtual host with its document root set to this checkout’s **public/** directory. Laravel 12 does not run on PHP 8.1. Set PHP `upload_max_filesize` above 10 MB and `post_max_size` above that (for example 12M and 16M), and configure the reverse proxy’s body-size limit accordingly.

```bash
git clone --branch feature/laravel-vue-platform https://github.com/thentadashi/Pixelforge.ai.git /var/www/pixelforge
cd /var/www/pixelforge
composer install --no-dev --prefer-dist --optimize-autoloader
cp .env.example .env
```

Configure `.env` before migrations:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
SESSION_SECURE_COOKIE=true
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pixelforge
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
MAIL_MAILER=smtp
MAIL_HOST=your_smtp_host
MAIL_PORT=587
MAIL_USERNAME=your_smtp_username
MAIL_PASSWORD=your_smtp_password
MAIL_FROM_ADDRESS=your_verified_sender
MAIL_FROM_NAME=PixelForge
```

Use real values only in the server’s `.env`; never commit them. Follow your provider’s encryption/authentication settings in `config/mail.php`. SMTP dispatch is synchronous; invitation sending may take a few seconds. The rest of the app does not require a queue worker for current features.

```bash
php artisan key:generate
php artisan migrate --seed --force
php artisan storage:link
php artisan pixelforge:admin
npm ci
npm run build
php artisan config:cache
php artisan view:cache
```

Grant the PHP-FPM user write access to `storage/` and `bootstrap/cache/`. Public founder photos use the `public/storage` link; private project files do not. Do not serve the checkout root. Restrict server access to `.env`, logs, backups, source, and database files by keeping them outside the public document root.

If a reverse proxy terminates TLS, configure Laravel trusted proxies to match your actual proxy addresses and forwarded headers before using secure session cookies. Do not blanket-trust unverified proxy headers.

## Updating an existing deployment

Back up the database, `.env`, and storage first. Review migrations and the branch changes. Deploy a reviewed commit; retain the previous commit for rollback. Do not run `migrate:fresh` or re-create the application key.

```bash
php artisan down
git pull --ff-only
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
php artisan up
```

If a command fails, inspect the failure before resuming service. Database migrations need a separate rollback plan; reverting a Git commit does not reverse data changes. `route:cache` is not required for this initial version.

## Target-server checks

- Public navigation and a fresh booking, including conflicting-slot feedback and cancellation/rebooking.
- Administrator login, content/photo edits, and client invitation/reset email delivered to a controlled test address.
- Client quotation acceptance creates one project; another client cannot access it.
- Milestone revision, re-review, approval, ticket replies, file upload/download, and invoice print-to-PDF.
- Mobile navigation, keyboard operation, and expired-session recovery.
- MySQL migrations and tests in an isolated test database before first live use.
- `/up` returns healthy; HTTPS and secure cookies work on the intended domain.

No DNS change, server credential use, payment integration, or Laravel production deployment is performed by the initial source-code pull request.
