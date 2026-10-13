# Campus Lost and Found Portal (KZ-WEB-03)

BSc IT / BCA Final-Year Capstone — Smart matching between lost and found items using keyword extraction and Jaccard similarity.

---

## Stack

| Layer      | Technology |
|------------|------------|
| Backend    | PHP 8.x, PDO (prepared statements only) |
| Database   | MySQL 8 |
| Frontend   | Bootstrap 5 (CDN), vanilla JS |
| Email      | PHPMailer 6.x via Composer (Gmail App-Password SMTP) |
| Charts     | Chart.js 4 (CDN) |
| Server     | Apache / XAMPP |

---

## XAMPP Setup

### 1. Prerequisites
- XAMPP ≥ 8.0 installed (Apache + MySQL + PHP)
- Composer (or use the included `composer.phar`)

### 2. Copy project
```
C:\xampp\htdocs\lost-found\   ← place the project here
```

### 3. Start XAMPP
Open **XAMPP Control Panel** → Start **Apache** and **MySQL**.

### 4. Create the database
Open `http://localhost/phpmyadmin` → New → Name: `lostfound` → Create.

*Or from command line:*
```bash
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE lostfound CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 5. Import the schema
```bash
C:\xampp\mysql\bin\mysql.exe -u root lostfound < sql/lostfound.sql
```

### 6. Seed sample data
```bash
C:\xampp\php\php.exe sql/seed.php
```
This creates 5 users with bcrypt passwords, 13 items, 6 matches, and 3 claims.

### 7. Install PHP dependencies (PHPMailer)
```bash
# If Composer is installed globally:
composer install --no-dev

# Otherwise use the included phar:
C:\xampp\php\php.exe composer.phar install --no-dev
```

### 8. Open the portal
```
http://localhost/lost-found/
```

---

## Default Login Credentials

| Role  | Email                       | Password   |
|-------|-----------------------------|------------|
| Admin | admin@college.edu           | Admin@123  |
| User  | john.doe@college.edu        | User@123   |
| User  | jane.smith@college.edu      | User@123   |
| User  | mike.jones@college.edu      | User@123   |
| User  | sara.wilson@college.edu     | User@123   |

---

## SMTP / Email Configuration

Edit `config/mail.php`:

```php
define('MAIL_ENABLED',  true);                  // flip from false → true
define('MAIL_HOST',     'smtp.gmail.com');
define('MAIL_PORT',     587);
define('MAIL_USERNAME', 'your@gmail.com');
define('MAIL_PASSWORD', 'xxxx xxxx xxxx xxxx'); // 16-char Google App Password
define('MAIL_FROM',     'your@gmail.com');
```

**Generate a Gmail App Password:**
Google Account → Security → 2-Step Verification → App Passwords → Mail.

When `MAIL_ENABLED = false` (default), all mail activity is logged to `logs/mail_errors.log` and never sent. Email failure never breaks posting or claiming.

---

## College Email Domain

Edit `config/db.php`:
```php
define('COLLEGE_EMAIL_DOMAIN', 'college.edu'); // change to your domain
```

---

## Running the Tests

```bash
C:\xampp\php\php.exe tests/match_test.php
```

Expected output:
```
=== Campus Lost & Found — Matching Engine Tests ===

[PASS] KW01 ...
[PASS] KW02 ...
...
[PASS] TC02a — Calculator score >= 0.5
[PASS] TC03a — Bottle vs ID card score < 0.5
...
All tests passed.
```

---

## Manual Test Checklist (TC01–TC05)

### TC01 — Registration & Login
- [ ] Register with a `college.edu` email → succeeds
- [ ] Register with `gmail.com` → error message shown
- [ ] Login with wrong password → generic error (no "email not found" leak)
- [ ] Login with correct credentials → redirect to homepage
- [ ] Logout → session destroyed, redirected to login

### TC02 — Matching: Calculator
- [ ] Log in as `john.doe@college.edu`
- [ ] Post a **Lost** item: "Black casio calculator", category Electronics, date 2026-10-01
- [ ] System should auto-match against seed found item "Found Casio fx-991 calculator…"
- [ ] Go to **My Matches** → see the match with score ≥ 50%
- [ ] Run `php tests/match_test.php` → TC02 **PASS**

### TC03 — No Match: Bottle vs ID Card
- [ ] Run `php tests/match_test.php` → TC03 **PASS** (score < 0.5)

### TC04 — Claim Workflow
- [ ] As `john.doe@college.edu`, open Matches → click **Raise a Claim** on the calculator match
- [ ] Answer the verification question (correct answer: `john`)
- [ ] Claim appears as **Pending**
- [ ] Log in as `admin@college.edu`
- [ ] Go to Admin → Claims → find the pending claim
- [ ] See **Match** badge next to the answer (answer `john` = expected `john`)
- [ ] Click **Approve** → items become Returned, both users are emailed (check `logs/mail_errors.log` if SMTP is off)

### TC05 — Admin Moderation
- [ ] Log in as admin
- [ ] Go to Admin → Users → Block a user → log in as that user → see "blocked" error
- [ ] Go to Admin → Items → change an item's status → verify badge updates
- [ ] Go to Admin → Dashboard → charts render correctly

---

## Project Structure

```
lost-found/
├── config/
│   ├── db.php          # PDO singleton, constants
│   └── mail.php        # SMTP config
├── includes/
│   ├── auth.php        # Session, login/logout helpers
│   ├── functions.php   # h(), CSRF, flash, upload, paginate
│   ├── match.php       # Matching engine (extract_keywords, jaccard, run_matching)
│   ├── header.php      # Bootstrap navbar
│   └── footer.php      # Closing HTML
├── user/
│   ├── post_item.php   # Post lost/found item
│   ├── my_items.php    # User's own items
│   ├── matches.php     # Smart match results
│   └── claim.php       # Raise a claim
├── admin/
│   ├── dashboard.php   # Stats + Chart.js
│   ├── claims.php      # Review, approve, reject claims
│   ├── items.php       # Moderate items
│   └── users.php       # Block/unblock users
├── uploads/            # Photos (PHP execution blocked by .htaccess)
├── logs/               # mail_errors.log, app_errors.log (web access blocked)
├── sql/
│   ├── lostfound.sql   # Schema
│   └── seed.php        # Sample data with bcrypt passwords
├── tests/
│   └── match_test.php  # TC02, TC03 + edge cases
├── index.php           # Homepage
├── search.php          # Browse & filter
├── login.php
├── register.php
├── logout.php
└── composer.json       # PHPMailer ^6.9
```

---

## Matching Algorithm

```
score = 0.6 × jaccard(keywords_A, keywords_B)
      + 0.3  (if same category)
      + 0.1  (if |date_A − date_B| ≤ 7 days)

Threshold: score ≥ 0.5  →  match saved
```

Keywords are extracted by lowercasing, stripping punctuation, splitting on whitespace, and removing a stopword list (`a, an, the, is, my, lost, found, …`).

---

## Security Notes

- All DB queries use PDO prepared statements
- All output escaped with `htmlspecialchars` via `h()`
- CSRF token on every POST form
- Ownership checks on every user-facing resource
- Uploads: MIME verified with `finfo`, random filenames, `.htaccess` blocks PHP execution
- Blocked users are kicked out on every request
- Sensitive data (verify_answer, contact details) never exposed to non-admin users
