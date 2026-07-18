# Freelance Services Marketplace

A server-rendered marketplace prototype for clients purchasing fixed-price freelance services and freelancers managing listings and deliveries. It demonstrates a complete coursework-scale order lifecycle with role-aware screens, while leaving deployment configuration and several production security controls to be completed.

The application itself is under `std1221175/`.

## Verified features

- Client and freelancer registration with server-side validation
- Password hashing and verification through PHP’s password API
- Session-based login, inactivity tracking, and session-scoped login throttling
- Service browsing, filtering, details, and view counts
- Three-step freelancer service creation and service editing
- Session-backed client cart and multi-step checkout
- Order placement, status tracking, cancellation, completion, delivery, and revision requests
- Profile editing and password changes
- Requirement, service, profile, revision, and deliverable file uploads
- Seeded categories, users, services, orders, revisions, and attachment records

Payment methods are recorded as order data; no payment gateway or financial transaction integration is present. The “forgot password” link points to a file that is not checked in.

## Technology

- PHP with PDO and sessions; no framework or Composer dependencies
- MySQL/InnoDB with `utf8mb4`
- HTML5 and custom CSS
- PHP extensions required by the source: `pdo_mysql` and `fileinfo`

No JavaScript build system, package manifest, container definition, or automated test framework is included.

## Architecture and structure

```text
std1221175/
├── auth/                  # Login, registration, and logout
├── includes/
│   ├── components/        # Reusable service card
│   ├── models/            # Service data access
│   ├── config.php         # URL and upload-path constants
│   ├── functions.php      # Session, upload, and validation helpers
│   └── layout.php         # Shared page rendering
├── orders/                # Order lifecycle and revisions
├── purches/               # Cart and checkout (directory name as checked in)
├── services/              # Browse, details, create, and edit
├── profile/               # Account and freelancer dashboard
├── pages/                 # Contact, privacy, and terms
├── sql/dbschema_1221175.sql
├── db.php.inc             # PDO bootstrap
├── main.php               # Main dynamic page
└── index.html             # Entry redirect/page
```

Pages include the shared PDO bootstrap and layout helpers. `ServiceModel` contains service queries; most other workflows issue prepared PDO statements directly in their page controllers.

## Local setup

### Requirements

- PHP with PDO MySQL and Fileinfo enabled
- MySQL or MariaDB
- A web server that permits PHP file uploads and writes to application upload directories

### Database

The schema is destructive on import: it creates/uses `freelance_marketplace`, drops six tables if present, recreates them, and inserts demonstration data. Use a disposable local database:

```powershell
mysql -u root -p < .\std1221175\sql\dbschema_1221175.sql
```

Do not import it into a database containing data you need.

### Application configuration

`db.php.inc` reads `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD`, with local-development defaults. `.env.example` documents the values, but the application does not load `.env` files itself. Export the variables in the process that starts PHP, for example:

```powershell
$env:DB_HOST = "localhost"
$env:DB_NAME = "freelance_marketplace"
$env:DB_USER = "root"
$env:DB_PASSWORD = "replace-with-local-password"
php -S localhost:8000
```

`includes/config.php` fixes `BASE_URL` to `/std1221175`, so serve the repository root as the document root. After safe database configuration, a development server can be started from the repository root:

```powershell
php -S localhost:8000
```

Then open:

```text
http://localhost:8000/std1221175/main.php
```

Create writable directories for the configured upload paths if they do not exist:

```text
std1221175/uploads/profiles
std1221175/uploads/services
std1221175/uploads/orders
```

The repository does not contain these directories or the `Images/` assets referenced by configuration and seed records, so default and seeded images will be missing until appropriate assets are supplied.

## Usage

Register as a Client to browse services, add items to the cart, check out, and manage orders. Register as a Freelancer to create services, review incoming work, upload deliverables, and handle revisions.

The SQL contains obviously synthetic seed accounts, but no plaintext seed password is documented in the repository; therefore no verified test credential is provided here.

## Configuration and security notes

- **Exposed database secret in Git history:** the repository’s committed version of `std1221175/db.php.inc` contained a live-looking remote hostname, username, and plaintext password. The working copy now uses environment variables, but the old credential must still be treated as compromised: rotate it and purge it from published history where appropriate.
- The current database error handler logs exception details server-side and returns a generic message to the browser. Protect production logs because they may contain infrastructure details.
- No CSRF token mechanism was found for state-changing forms or action endpoints.
- Login success does not regenerate the session ID. Cookie `Secure`, `HttpOnly`, and `SameSite` attributes are not configured in the checked-in session helper.
- Login throttling is stored only in the current PHP session, so clearing cookies or starting a new session bypasses it.
- Upload code performs MIME and size validation in several paths, but uploaded files are stored below the web root. The deployment should prevent script execution, generate server-side names consistently, and apply least-privilege permissions.
- `BASE_URL` and upload paths are hardcoded for one deployment layout.
- The seed script drops tables and includes synthetic personal/order data; keep it out of production migrations.
- The schema contains attachment rows for files that are not present. The repository also lacks the image and upload assets referenced by code and seed data.
- No generated dependency/build directories were found tracked, and no automated tests are checked in.

## Realistic next steps

1. Rotate and remove the exposed database credential, then introduce environment-based configuration.
2. Add CSRF protection, secure session cookie settings, and session ID regeneration after login.
3. Move uploads outside the public document root and harden download authorization.
4. Add migrations and fixtures that separate schema changes from destructive demo seeding.
5. Add integration tests for authorization, checkout, order transitions, uploads, and concurrent ID generation.
6. Restore or replace referenced image assets and add a real password-reset workflow.
