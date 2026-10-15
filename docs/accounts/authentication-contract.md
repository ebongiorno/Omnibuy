# Accounts & Authentication Contract

**Owner:** Kenneth Ly (UC-01 Register, UC-02 Login / Log Out, UC-03 Reset Password, UC-04 Edit Profile, UC-05 Privacy Settings)
**Code:** `includes/auth.php` (UC-01/02), `includes/password-reset.php` + `includes/mailer.php` (UC-03), `includes/account.php` (UC-04/05, change password)
**Pages** (`public/pages/`): `signup.php`, `login.php`, `logout.php`, `forgot-password.php`, `reset-password.php`, `account.php`, `edit-profile.php`, `privacy-settings.php`, `change-password.php`
**Tests:** `tests/Unit/AuthValidationTest.php`, `tests/Unit/AccountValidationTest.php`, `tests/Integration/AuthenticationTest.php`, `tests/Integration/PasswordResetTest.php`, `tests/Integration/AccountManagementTest.php`, `tests/Functional/AuthenticationFlowTest.php`, `tests/Functional/AccountManagementFlowTest.php`

This is what other features can rely on from the accounts subsystem.

## 1. Who is logged in: session keys

After a successful login or sign-up, these session values are set. Pages that already read them (`messaging.php`, `my-listings.php`, `create-listing-details.php`) now get real values.

| Key | Type | Value |
|---|---|---|
| `$_SESSION['user_id']` | int | `users.user_id` of the logged-in user. **Use this as the user's identity.** |
| `$_SESSION['username']` | string | `users.username` |
| `$_SESSION['avatar_url']` | string | `users.profile_image_url`, or `/assets/icons/default-avatar.png` |
| `$_SESSION['is_seller']` | bool | `true` if the user has a `seller_profiles` row (checked at login) |

Logged out means `$_SESSION['user_id']` is not set. Never trust a user ID from a form field or URL for "who am I". Always use the session.

## 2. Helpers for other pages

```php
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();          // logged-out visitors go to the login page, then come back here
$me = currentUser();     // ['user_id', 'username', 'avatar_url', 'is_seller'] or null
isLoggedIn();            // bool
startSession();          // start the session with secure cookie settings (safe to call twice)
```

- **Protecting a page:** call `requireLogin()` before any output. Example: `public/pages/account.php`.
- **Header:** if a page includes `components/header.php` without setting `$isLoggedIn` / `$currentUser`, the header now fills them in from the session. Pages must start the session first (`startSession()` or `requireLogin()`).
- **Log out:** POST to `/pages/logout.php` (the header's account menu already does this). GET requests do not log out.
- **Showing a user's name to someone else** (seller cards, messages, reviews): use `publicDisplayName($firstName, $lastName, $isLastNameHidden)` from `includes/account.php`. It returns only the first name when the user hid their last name (UC-05). Seller search already follows this rule in its SQL.
- **Profile photo:** `users.profile_image_url` is either `null` (show `/assets/icons/default-avatar.png`) or a path such as `/uploads/avatars/<random>.png`. Uploaded photos are stored in the `profile_uploads` Docker volume.

## 2a. Email (UC-03)

`sendMail($to, $subject, $body)` in `includes/mailer.php` sends plain-text email. Locally every email goes to **Mailpit**: open http://localhost:8025 to read it. Without Mailpit (no `MAIL_HOST`), the email is written to `docker compose logs web` instead.

## 3. Database: `users`

| Column | Rules |
|---|---|
| `user_id` | `BIGINT UNSIGNED`, auto-increment primary key. Foreign keys must use the same type. |
| `username` | Unique (case-insensitive). 3–30 characters: letters, numbers, `_`, `.` |
| `email` | Unique (case-insensitive), stored lowercase |
| `password_hash` | `password_hash()` output. **Never select it into pages or send it to the browser.** |
| `first_name`, `last_name` | Required, ≤ 100 characters |
| `phone_number` | Required |
| `birth_date` | Required, not in the future (database trigger) |
| `profile_image_url` | `null` or an uploaded photo path (UC-04) |
| `bio` | Optional, ≤ 500 characters, may contain line breaks (UC-04). Escape it when showing it. |
| `is_last_name_hidden` | `TRUE` = show only the first name to other users, and don't match searches on the last name (UC-05) |
| `is_email_verified` | Reset to `FALSE` when the user changes their email |
| `account_status` | `active` (default), `suspended`, `restricted`. Only `active` accounts can log in. |

**Tables that reference `users.user_id`** (verified in the database, 2026-10-12):

| Feature | Tables (columns) |
|---|---|
| Listings / selling (Bryant) | `seller_profiles` (user_id), `bids` (bidder_user_id), `listing_views` (user_id) |
| Discovery (Chantal) | `search_history` (user_id) |
| Orders / checkout | `carts` (user_id), `orders` (buyer_user_id), `addresses` (user_id) |
| Favorites, messaging, safety (Riley) | `wishlist_items` (user_id), `conversations` (buyer_id, seller_id), `messages` (sender_user_id), `user_blocks` (blocker/blocked_user_id), `user_reports` (reporter/reported_user_id) |
| Reviews (Kenneth, UC-31) | `user_reviews` (reviewer/reviewed_user_id), `listing_reviews` (reviewer_user_id) |

## 4. Security behavior

| Protection | How |
|---|---|
| Password storage | `password_hash()` (bcrypt), rehashed automatically if the algorithm changes |
| Password rules | ≥ 8 characters, a letter and a number, ≤ 72 bytes (bcrypt limit) |
| Account enumeration | Wrong password and unknown account show the same message and take the same time |
| Session fixation | Session ID is regenerated on login |
| Session cookie | `HttpOnly`, `SameSite=Lax`, `Secure` when served over HTTPS; strict session mode |
| CSRF | Every account form carries a per-session token; logout is POST-only |
| Password reset tokens | 256-bit random, only the SHA-256 hash stored, expire after 30 minutes, work once; a new request or a password change cancels older links; at most 3 emails per account per 15 minutes |
| Reset email link | Built from `SITE_URL`, never the request's `Host` header; the reset page sends `Referrer-Policy: no-referrer` |
| Account enumeration (reset) | "Forgot password" shows the same message whether or not the email has an account |
| Changing email / password | Requires the current password |
| Photo uploads | Checked by file contents (JPEG/PNG/WebP only, ≤ 2 MB), saved under a random name, never under the user's file name |
| Open redirect | `?redirect=` only accepts paths on this site |
| SQL injection | Prepared statements only |
| XSS | All echoed values go through `htmlspecialchars()` |

## 5. Follow-ups for teammates

These pages still use placeholder login data and can switch to the real session now:

| Page | Owner | Current | Suggested change |
|---|---|---|---|
| `create-listing.php` | Bryant | `$isLoggedIn = true` and a hard-coded user | `requireLogin(); $currentUser = currentUser();` |
| `wishlist.php` | Riley | `$isLoggedIn = true` and a hard-coded user | `requireLogin();` |
| `messaging.php` | Riley | Falls back to user ID `1`; the login check is commented out | `requireLogin();` and drop the `?? 1` fallback |

## 6. Database: `password_resets` (UC-03)

One row per emailed reset link: `user_id`, `token_hash` (SHA-256 of the token in the link), `created_at`, `expires_at`, `used_at` (set when the link is used or cancelled). Rows are deleted with their user. Existing local databases need `database/migrations/2026-10-15-password-resets.sql` (see `DOCKER_SETUP.md`).

Not yet built (later sprints): login rate limiting, email verification (`is_email_verified`), logging out other devices after a password change.
