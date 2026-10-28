# Directorate of Youth Affairs (KP) — Management System

## Module 1: Authentication, User Administration & Role/Permission Management

This module is the shared security foundation for the whole system. Activity Management, Budget & Planning, Jawan Markaz Management, Hostel Booking Management and Report Management all use this login, these user accounts and these permission checks.

**Stack:** Laravel 9 (PHP 8.0+), MySQL/MariaDB, Bootstrap 5 (served locally, no CDN), Blade templates.

> **Status.** The roles, the default permission matrix and the organizational scope model are a **draft proposal** (spec §4, §9, §17). The Directorate must confirm them before deployment. Everything is configurable without code changes, except the catalogue in `config/access.php`.

---

## Features

| Area | What is implemented |
|---|---|
| Authentication | Login/logout, session regenerated at login, configurable idle timeout, "remember me" (configurable or disabled), account-status check on **every** request, generic error that never reveals whether an account exists, timing-safe check for unknown accounts |
| Login protection | Per-account lockout after N failed attempts (auto-unlock after the lockout period, or manual unlock by an admin), per-email+IP rate limiting, optional CAPTCHA |
| Passwords | Configurable policy (length, upper/lower/number/special, expiry); bcrypt hashing; change requires the current password and signs out other sessions; temporary admin-set passwords must be changed at first sign-in |
| Password reset / onboarding | Single-use, time-limited, hashed tokens (Laravel password broker). Self-service "Forgot password" is **off by default** until the Directorate confirms the channel. Admins can always email a setup/reset link and never see the password |
| User administration | Search/filter directory, create/edit, roles, organizational scope (district/office/Jawan Markaz/hostel tree), activate/deactivate with a required reason, unlock, account history |
| Roles & permissions | Role CRUD, role-permission matrix (modules × actions plus system administration), system role protection |
| Authorization | Laravel Gate/Policies on the server for every route. Menus are hidden only for convenience |
| Organizational scope | `HasOrganizationalScope` trait and `ScopedRecordPolicy` base class, ready for the business modules |
| Delegation rules | Admins cannot assign roles, permissions or scope beyond their own. Admins cannot manage accounts more privileged than themselves, or their own roles and status. The last active Super Admin cannot be deactivated or lose the role. Super Admin keeps its essential permissions |
| Separation of duties | `ScopedRecordPolicy` blocks approving or paying your own submission by default |
| Audit log | Logins (success/failure/blocked), lockouts, password events, user/role/permission/scope/settings changes, denied access, exports. Before/after values with secrets stripped. Append-only model. Scoped viewers see only their authorized subset. CSV export (Excel-ready, formula-injection safe) |
| Initial setup | No default administrator is shipped. The first Super Admin is created from the server console |

---

## Local setup (Windows + XAMPP)

XAMPP's `php.ini` uses drive-relative paths (`\xampp\...`), so run PHP from the **C:** drive, or use a copy of `php.ini` with absolute paths (`C:\xampp\...`) set through `PHPRC`.

```bash
composer install
cp .env.example .env            # set DB_* for your MySQL
php artisan key:generate
# create the database first:  CREATE DATABASE dya_kp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
php artisan migrate --seed      # in APP_ENV=local this also loads demo data
php artisan serve
```

### Demo accounts (local only)

The demo seeders run only when `APP_ENV=local`. Every demo account uses the password **`Demo@12345`**. All accounts except the Super Admin use an `@dya.test` address, so no real mailbox is contacted. Change the Super Admin's password after the first sign-in.

| Name | Email | Role | Scope |
|---|---|---|---|
| Syed Awais | alsyedk123@gmail.com | Super Admin | All |
| Sana Ullah | sana.ullah@dya.test | Directorate Administrator | Peshawar, Charsadda |
| Asma Riaz | asma.riaz@dya.test | Directorate Officer / Approver | All |
| Ahmed Khan | ahmed.khan@dya.test | Activity Officer | Peshawar |
| Ayesha Tariq | ayesha.tariq@dya.test | Finance Staff | All |
| Abdul Wahab | abdul.wahab@dya.test | Jawan Markaz Staff | Jawan Markaz Mardan |
| Bilal Shah | bilal.shah@dya.test | Jawan Markaz Staff | Jawan Markaz Peshawar |
| Usman Khan | usman.khan@dya.test | Hostel Staff | Peshawar & Mardan Youth Hostels |
| Faisal Rehman | faisal.rehman@dya.test | Hostel Staff | Swat Youth Hostel |
| Zara Khan | zara.khan@dya.test | Report Viewer | Swat |
| Imran Ali | imran.ali@dya.test | Jawan Markaz Staff (inactive) | Jawan Markaz Peshawar |

### Tests

```bash
php artisan test
```

41 feature tests cover the spec §15 acceptance checklist. They run on in-memory SQLite, so they need no MySQL.

---

## Production deployment checklist

1. `APP_ENV=production`, `APP_DEBUG=false`, a fresh `APP_KEY`, serve **only over HTTPS**, `SESSION_SECURE_COOKIE=true`.
2. Keep `.env` out of source control. It holds the database credentials and app key.
3. `composer install --no-dev --optimize-autoloader`, then `php artisan migrate --force` and `php artisan db:seed --class=AccessControlSeeder --force`.
4. Create the first Super Admin: **`php artisan dya:create-super-admin`**. It refuses to run once an active Super Admin exists.
5. Add the real districts, offices, Jawan Markaz centers and hostels under **Organization Units**.
6. Configure outgoing mail (`MAIL_*`) before using password setup links or self-service reset.
7. `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
8. Set up database backups and document the recovery steps (spec §16.9).
9. **Upgrade note:** PHP 8.0 and Laravel 9 no longer receive security fixes. This project installs on XAMPP's PHP 8.0.30 with `audit.block-insecure` disabled in `composer.json`. Before production, move the server to PHP 8.2+ and upgrade Laravel (10 → 11/12), then remove that setting and run `composer audit`.

Replace the placeholder emblem by putting the approved logo at `public/images/logo.png`. An optional login photo goes at `public/images/login-hero.jpg`.

---

## How the business modules plug in

```php
// 1. Model: records belong to an organizational unit
class Expense extends Model {
    use \App\Support\Concerns\HasOrganizationalScope;
    protected string $scopeUnit = 'center';      // district | office | center | hostel
    protected string $scopeColumn = 'center_id';
}

// 2. Policy: permission + scope + separation of duties
class ExpensePolicy extends \App\Policies\ScopedRecordPolicy {
    protected string $module = 'jawan_markaz';
}

// 3. Controllers, reports and exports
$this->authorize('viewAny', Expense::class);
$expenses = Expense::visibleTo($request->user())->paginate();   // list only what the user may see
$this->authorize('approve', $expense);                          // denies own submissions
Route::get(...)->middleware('can:jawan_markaz.export');         // plain permission checks
\App\Support\Audit::log('expense.approved', $expense, ['before' => ..., 'after' => ...]);
```

Permission keys follow the pattern `module.action`. The available modules, actions and default role grants live in [config/access.php](config/access.php). After adding a permission there, run `php artisan db:seed --class=AccessControlSeeder` (new roles get their defaults, existing roles are untouched, Super Admin always gets everything). To re-apply the whole draft matrix, use `ACCESS_RESET_MATRIX=true`.

## Key files

| Path | Purpose |
|---|---|
| `config/access.php` | Modules, actions, proposed roles, draft matrix, security defaults |
| `app/Support/Authority.php` | Delegation rules (who may grant or manage what) |
| `app/Support/ScopeCoverage.php` | Organizational scope resolution and containment |
| `app/Support/Audit.php` | Audit writer with secret stripping |
| `app/Policies/` | `UserPolicy`, `RolePolicy`, `ScopedRecordPolicy` |
| `app/Http/Middleware/EnsureAccountIsUsable.php` | Status check and idle timeout on every request |
| `app/Http/Middleware/RequirePasswordChange.php` | Forced change for temporary or expired passwords |
| `app/Console/Commands/CreateSuperAdmin.php` | Controlled first-admin setup |

## Items still requiring Directorate confirmation (spec §17)

- Final role names and the authority of each role (the permission matrix screen can be changed at any time).
- Whether users may hold multiple roles (the system allows it; the form warns that it needs approval).
- Scope granularity: district, office, Jawan Markaz, hostel, or a combination (all four are supported).
- Who approves activity proposals, expenses, bookings and payments.
- The password reset channel: email (built in, off by default), SMS (not built), or administrator-issued links (built in).
- The onboarding and identity-verification procedure.
- Audit-log retention period and who may view or export it. Rows are currently kept indefinitely.
- Whether MFA is required for Super Admin, finance and approval users (not yet implemented).
