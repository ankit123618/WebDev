# WebDev

This project is a lightweight PHP template for making web applications with custom routing, a small dependency injection container, session-based authentication, password reset via email, and a MySQL-backed user model.

## Features

- Custom front-controller setup through `index.php`
- Route registration in `routes/web.php`
- Dependency injection with `core/container.php`
- Session-based login and protected dashboard
- User registration
- Forgot-password and reset-password flow
- Two-factor authentication with email codes
- MFA with authenticator app TOTP and QR setup
- QR branding with a generated default logo
- Secure remember-me cookie login
- File-based QR caching
- MySQL access through PDO
- Environment loading with `vlucas/phpdotenv`
- Email sending with `PHPMailer`
- File-based logging under `storage/logs`

## Code Commenting Convention

Application PHP files now follow a simple documentation pattern:

- Each class starts with a multiline docblock that explains its overall responsibility.
- Each function and method has a short docblock describing what it does.
- Bootstrap-style files such as `index.php`, `routes/web.php`, and `test.php` use file-level comments when they do not define classes.

This keeps the codebase easier to scan without adding large or repetitive inline comments.

## Project Structure

```text
assets/
  css/style.css
  js/script.js
controllers/
  auth.php
  dashboard.php
  home.php
core/
  app.php
  auth.php
  container.php
  csrf.php
  database.php
  env.php
  logger.php
  mailer.php
  router.php
helpers/
  auth.php
  data.php
  debug.php
  location.php
  request.php
  string.php
models/
  user.php
routes/
  web.php
views/
  home.php
  login.php
  register.php
  forgot-password.php
  reset-password.php
  dashboard.php
index.php
env_exmaple.txt
composer.json
README.md
TODO
```

## Setup And Run

### Docker

The quickest local setup is Docker Compose. It starts:

- `app`: PHP 8.4 + Apache with `pdo_mysql` and `mod_rewrite`
- `db`: MySQL 8.4 with the required schema auto-created

Run:

```bash
docker compose up --build
```

Then open:

```text
http://localhost:8080
```

Optional database overrides can be provided when starting Compose:

```bash
MYSQL_DATABASE=project_template MYSQL_USER=appuser MYSQL_PASSWORD=apppassword MYSQL_ROOT_PASSWORD=rootpassword docker compose up --build
```

Notes:

- The app container injects `DB_HOST=db`, so it can talk to MySQL without editing your local `.env`
- The project directory is bind-mounted for live code edits during development
- The database schema is initialized from `docker/mysql/init/01-schema.sql` on first startup
- If you need a fresh database, remove the `db_data` volume before restarting
- SMTP settings are still up to your environment and can remain in `.env`

### CI/CD

GitHub Actions workflows are included under `.github/workflows/`.

- `ci-cd.yml` runs on push and pull request
- CI validates Composer, installs dependencies, lints all PHP files, validates Docker Compose, and builds the app image
- CD deploys `main` to a remote server over SSH after CI passes
- `deploy-manual.yml` lets you deploy any git ref manually

Configure these repository secrets before enabling deployment:

- `DEPLOY_HOST`: server hostname or IP
- `DEPLOY_PORT`: SSH port, usually `22`
- `DEPLOY_USER`: SSH user
- `DEPLOY_SSH_KEY`: private key for deployment
- `DEPLOY_PATH`: absolute path to the checked-out app on the server

Server expectations:

- The server already has this repository cloned at `DEPLOY_PATH`
- Docker and Docker Compose are installed on the server
- The server has its own `.env` values ready for production
- The deploy user can run `git` and `docker compose` in that directory

### Requirements

- PHP 8.1+ recommended
- Composer
- MySQL or MariaDB
- SMTP credentials for password reset emails

### 1. Install dependencies

```bash
composer install
```

### 2. Create environment file

The repo includes `env_exmaple.txt` as the sample env file.

```bash
cp env_exmaple.txt .env
```

Update these values in `.env`:

- `APP_URL`
- `APP_TIMEZONE`
- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `MAIL_HOST`
- `MAIL_PORT`
- `MAIL_USER`
- `MAIL_PASS`
- `MAIL_FROM`
- `MAIL_FROM_NAME`
- `MAIL_ENCRYPTION`
- `LOG_DIR`
- `LOG_FILE`
- `CACHE_DIR`

### 3. Create the database

The current code expects two tables: `user` and `password_resets`.

Example schema:

```sql
CREATE TABLE user (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'user',
    two_factor_email_enabled TINYINT(1) NOT NULL DEFAULT 0,
    two_factor_email_code_hash VARCHAR(255) NULL,
    two_factor_email_code_expires_at DATETIME NULL,
    two_factor_totp_secret VARCHAR(64) NULL,
    remember_token_hash CHAR(64) NULL,
    remember_token_expires_at DATETIME NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE password_resets (
    user_id INT UNSIGNED NOT NULL PRIMARY KEY,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_resets_user
        FOREIGN KEY (user_id) REFERENCES user(id)
        ON DELETE CASCADE,
    INDEX idx_password_resets_token_hash (token_hash)
);
```

If you already have the `user` table, you can add the new MFA fields with:

```sql
ALTER TABLE user
    ADD COLUMN two_factor_email_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER role,
    ADD COLUMN two_factor_email_code_hash VARCHAR(255) NULL AFTER two_factor_email_enabled,
    ADD COLUMN two_factor_email_code_expires_at DATETIME NULL AFTER two_factor_email_code_hash,
    ADD COLUMN two_factor_totp_secret VARCHAR(64) NULL AFTER two_factor_email_code_expires_at,
    ADD COLUMN remember_token_hash CHAR(64) NULL AFTER two_factor_totp_secret,
    ADD COLUMN remember_token_expires_at DATETIME NULL AFTER remember_token_hash;
```

Notes:

- `models/user.php` uses `SELECT * FROM user WHERE email = ?`
- Registration inserts `username`, `email`, `password`, and `role`
- Password reset uses `INSERT ... ON DUPLICATE KEY UPDATE`, so `password_resets.user_id` must be unique

### 4. Run the app

Option 1: PHP built-in server

```bash
php -S localhost:8080 index.php
```

Option 2: Apache

- Point the document root to this project
- Make sure `mod_rewrite` is enabled
- Keep `.htaccess` in place so requests reach `index.php`

Then open:

```text
http://localhost:8080
```

## App Flow

### Public pages

- `/` renders the home page
- `/register` shows the registration form
- `/login` shows the login form
- `/forgot-password` shows the password reset request form

### Registration flow

1. User opens `/register`
2. Form posts to `POST /register`
3. `controllers\auth::store()` validates CSRF and password confirmation
4. Password is hashed with `password_hash()`
5. `models\user::save()` inserts the user into the `user` table
6. User is redirected to `/login`

### Login flow

1. User opens `/login`
2. Form posts to `POST /login`
3. `controllers\auth::authenticate()` validates CSRF
4. User record is loaded by email
5. Password is verified with `password_verify()`
6. If MFA is disabled, session keys `user`, `role`, and `email` are stored
7. If MFA is enabled, the app creates a pending login and routes the user into:
8. Email code verification at `/2fa/verify`, or
9. Method selection at `/2fa/select` when both email and authenticator app are enabled
10. After successful verification, the app stores the normal authenticated session and redirects to `/dashboard`

### Remember-me cookie flow

1. The login form includes a `Remember me on this device` checkbox
2. If checked, the app creates a random token and stores only its SHA-256 hash in the database
3. The browser receives an `HttpOnly` cookie with the user id and raw token
4. On later visits, `core\remember_me` checks the cookie before routing
5. If the token hash matches and is not expired, the app restores the session automatically
6. On logout, the app clears both the cookie and the stored remember token

### Development email code

1. In development, email 2FA does not rely on SMTP
2. The app uses a dummy 6-digit code instead
3. Default code: `123456`
4. You can override it with `DEV_2FA_EMAIL_CODE` in `.env`

### Caching

Caching means saving the result of expensive work and reusing it for a while instead of recomputing it on every request.

This app now uses a small file-based cache for TOTP QR generation:

1. When the app builds a QR data URI for the same email + secret, it stores the result under `storage/cache`
2. Reopening the setup screen during the cache window reuses that saved QR instead of regenerating the PNG
3. The cache is temporary and expires automatically
4. The default cache TTL for TOTP QR codes is 10 minutes

This keeps the implementation simple and local:

- no Redis or Memcached required
- safe for development
- useful on repeated setup page loads

### Two-factor setup flow

1. Signed-in users open `/2fa/setup`
2. Email 2FA can be enabled or disabled instantly
3. Authenticator setup generates a TOTP secret and QR code
4. The QR code uses a generated default logo based on the app initials
5. The user scans the QR code with an authenticator app and confirms with a 6-digit code
6. The verified TOTP secret is stored in the `user` table

### Protected dashboard flow

1. User requests `/dashboard`
2. `controllers\dashboard::index()` checks `core\auth::check()`
3. If not authenticated, user is redirected to `/login`
4. If authenticated, dashboard view is rendered with `userId`, `email`, and `role`

### Forgot/reset password flow

1. User opens `/forgot-password`
2. Form posts to `POST /forgot-password`
3. `controllers\auth::sendResetLink()` validates CSRF and looks up the user by email
4. A raw token is generated, then hashed with SHA-256 before storage
5. `models\user::storePasswordResetToken()` saves the hash and expiry time
6. `core\mailer` sends a reset link to `/reset-password/{token}`
7. User opens the emailed link
8. `controllers\auth::resetPasswordForm()` validates the token against the hashed value in the database
9. Form posts to `POST /reset-password/{token}`
10. `controllers\auth::resetPassword()` validates CSRF, verifies the token again, updates the hashed password, deletes reset tokens, and redirects to `/login`

## Database Schema

### `user`

| Column | Type | Purpose |
|---|---|---|
| `id` | int | Primary key |
| `username` | varchar | Display or login name |
| `email` | varchar | Unique user email |
| `password` | varchar | Hashed password |
| `role` | varchar | Authorization role stored in session |
| `two_factor_email_enabled` | tinyint | Enables email-based sign-in verification |
| `two_factor_email_code_hash` | varchar | Stores a hashed 6-digit email verification code |
| `two_factor_email_code_expires_at` | datetime | Expiry for the current email verification code |
| `two_factor_totp_secret` | varchar | Shared secret for authenticator app TOTP |
| `remember_token_hash` | char(64) | SHA-256 hash of the remember-me cookie token |
| `remember_token_expires_at` | datetime | Expiry for the remember-me cookie token |

### `password_resets`

| Column | Type | Purpose |
|---|---|---|
| `user_id` | int | References `user.id`, should be unique |
| `token_hash` | char(64) | SHA-256 hash of reset token |
| `expires_at` | datetime | Token expiry timestamp |

## Code Flow

### Request lifecycle

1. `index.php` starts the session and loads Composer plus project files
2. The service container is created and shared services are registered
3. `core\env` loads `.env`
4. `routes/web.php` registers routes on `core\router`
5. `core\router::run()` matches the current request path and method
6. The router resolves the controller through `core\container`
7. The controller action runs
8. The controller either renders a view, redirects, writes logs, sends email, or talks to the model

### Dependency flow

- `core\container` resolves constructor dependencies automatically using reflection
- `core\app` stores the container globally so helper functions can reach shared services like CSRF
- `core\database` builds a PDO connection from env variables
- `models\user` wraps database operations for users and password resets
- `core\auth` reads authentication state from the session
- `core\mailer` sends SMTP email using env configuration
- `core\logger` writes application logs to `storage/logs`

### Route map

| Method | Path | Controller action |
|---|---|---|
| `GET` | `/` | `controllers\home@index` |
| `GET` | `/login` | `controllers\auth@login` |
| `POST` | `/login` | `controllers\auth@authenticate` |
| `GET` | `/2fa/select` | `controllers\auth@selectTwoFactorMethod` |
| `POST` | `/2fa/select` | `controllers\auth@chooseTwoFactorMethod` |
| `GET` | `/2fa/verify` | `controllers\auth@twoFactorChallenge` |
| `POST` | `/2fa/verify` | `controllers\auth@verifyTwoFactorChallenge` |
| `POST` | `/2fa/email/resend` | `controllers\auth@resendEmailCode` |
| `GET` | `/register` | `controllers\auth@register` |
| `POST` | `/register` | `controllers\auth@store` |
| `GET` | `/dashboard` | `controllers\dashboard@index` |
| `GET` | `/2fa/setup` | `controllers\auth@twoFactorSettings` |
| `POST` | `/2fa/email/enable` | `controllers\auth@enableEmailTwoFactor` |
| `POST` | `/2fa/email/disable` | `controllers\auth@disableEmailTwoFactor` |
| `POST` | `/2fa/totp/setup` | `controllers\auth@prepareTotpSetup` |
| `POST` | `/2fa/totp/confirm` | `controllers\auth@confirmTotpSetup` |
| `POST` | `/2fa/totp/disable` | `controllers\auth@disableTotp` |
| `GET` | `/logout` | `controllers\auth@logout` |
| `GET` | `/forgot-password` | `controllers\auth@forgotPassword` |
| `POST` | `/forgot-password` | `controllers\auth@sendResetLink` |
| `GET` | `/reset-password/{token}` | `controllers\auth@resetPasswordForm` |
| `POST` | `/reset-password/{token}` | `controllers\auth@resetPassword` |

## TODO

Current TODO items from the repo:

- Testing implementation
- 2FA / MFA

Suggested next improvements:

- Add database migrations and seeders
- Add validation helpers for email and password rules
- Add route middleware instead of auth checks inside controllers
- Add rate limiting for login and forgot-password endpoints
- Add email templates instead of inline HTML strings
- Add automated tests for auth, password reset, and routing
- Rename `env_exmaple.txt` to `env_example.txt` for consistency
