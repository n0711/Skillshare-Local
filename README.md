# SkillShare Local 🌱

Course project for **ACSC476: Web Programming**.

SkillShare Local is a small web platform where neighbours teach each other
sustainable skills: composting, bike repair, home energy saving, water hacks,
and so on. Instructors create sessions, learners book and rate them, and the
system estimates the climate impact of the knowledge shared.

## 1. Project overview

Roles:

- **Learner**: browses sessions, requests bookings, rates attended sessions.
- **Instructor**: creates and manages sessions, accepts or rejects bookings,
  marks attendance.
- **Admin**: manages user accounts and monitors sustainability impact.

How sustainability is measured:

- Each category (Gardening, DIY & Repair, Water Saving, etc.) has an entry in
  the `impact_factors` table: **kg CO₂ saved per participant**, a rough
  educational estimate.
- For every **attended booking**, the platform multiplies the number of
  participants by the category's impact factor.
- The **Impact Dashboard** adds this up per category and shows:
  - total kg and tons of CO₂ saved
  - a table of categories, participants and impact
  - a Chart.js graph (bars for CO₂ per category, a line for participants)

The UI states clearly that these numbers are **approximate educational
estimates**, not precise carbon accounting, and references SDG 4 (Quality
Education) and SDG 12 (Responsible Consumption and Production).

## 2. Main features

### Authentication and roles

- Registration and login for **Learner**, **Instructor** and **Admin**
- Passwords hashed with `password_hash()` and checked with `password_verify()`
- User status (`active` or `suspended`) stored in `users.status`. Suspended
  users cannot log in (checked at login time).
- Session-based helpers in `includes/auth.php`: `current_user()`,
  `require_login()`, `is_role($role)`

### Sessions

- Instructors can **create, edit and unpublish** their own sessions:
  - title and description (including a sustainability explanation)
  - category (foreign key to `categories`)
  - mode: `online` or `in-person`
  - city and address (for in-person)
  - date and time, duration, capacity
  - fee type: `free` or `paid`, plus the amount
  - optional photo upload, stored under `uploads/`
- Instructor **My Sessions** dashboard:
  - a status badge per session: **Active** (future and published),
    **Completed** (past date), **Unpublished** (status is inactive)
  - bookings against capacity
  - actions: **Edit**, **Publish / Unpublish**, **View bookings**

### Browsing and booking

- Learner browse page (`sessions_browse.php`):
  - card layout with photo, category, instructor name, mode or city, date and
    time
  - badges for free or paid, capacity, and current bookings
  - **average rating** and rating count
- Live filters with jQuery (`assets/js/browse_sessions.js`):
  - title search
  - category
  - city or online
  - fee type
  - a live "Showing **X** sessions" counter
- Session detail page:
  - large image, full description, SDG badges
  - badges for date and time, mode, capacity and bookings
  - average rating summary
  - a booking button that only works when the user is a learner, the session
    is not full, not already booked, and not in the past

### Bookings and ratings

- Learners view and manage their bookings (pending, accepted, rejected,
  cancelled).
- Instructors approve or reject bookings for their sessions and mark accepted
  bookings as **attended**.
- Learners can rate a session only after attending it. A rating has a score and
  an optional comment, and ratings show on the browse cards and the session
  page.

### Impact Dashboard (admin)

- Reads `categories`, `impact_factors` (kg CO₂ per participant per category)
  and attended `bookings`.
- For each category: participants is the count of attended bookings, and impact
  in kg is participants × impact factor.
- Shows total participants, total kg and tons of CO₂, a table per category, and
  the Chart.js graph.
- Reachable from the navbar for the **admin** role.

### Admin users panel

- Lists all users with name, email, role, city, status and created date.
- Admins can **suspend or activate** accounts, and suspended users see a
  friendly message when they try to log in.

## 3. Technology stack

- PHP 8.x (tested on XAMPP with MariaDB)
- MySQL or MariaDB with `mysqli` and prepared statements
- Bootstrap 5 for layout (from a CDN)
- Custom CSS in `assets/css/style.css` (hero cards, gradients, hover effects,
  badges, auth cards)
- jQuery and custom JS in `assets/js/`:
  - `app.js`: global behaviour (tooltips, etc.)
  - `home.js`: homepage stat counters and the impact toggle
  - `browse_sessions.js`: search, filters, and the live session count
  - `register.js`: password strength meter and client-side validation
- Chart.js (from a CDN) for the impact graphs

## 4. Folder structure

- `config/db.php`: database connection
- `includes/auth.php`: authentication helpers
- `includes/header.php`, `includes/footer.php`: layout shell, navbar, footer
- `public/`
  - `index.php`: landing page with stats and the impact intro
  - `login.php`, `register.php`, `logout.php`
  - `sessions_browse.php`, `session_view.php`
  - `session_create.php`, `session_edit.php`, `session_toggle_status.php`,
    `my_sessions.php`
  - `booking_manage.php`, `rating_form.php`
  - `impact_dashboard.php`
  - `admin_users.php`
- `assets/css/style.css`: custom styling
- `assets/js/`: jQuery logic split by page
- `uploads/`: session photos, created on first upload and ignored by git

## 5. Getting started

You need XAMPP (or any PHP 8 + MySQL/MariaDB setup) and the database export
`skillshare_local.sql` (schema plus seed data). **That export is not in this
repository**, it was handed in with the course submission. Without it the app
has no tables to read.

1. Create a database called `skillshare_local`.
2. Import `skillshare_local.sql` with phpMyAdmin.
3. Put this folder in your web root, for example
   `C:\xampp\htdocs\skillshare_local`.
4. Check that the credentials in `config/db.php` match your MySQL setup (the
   default is `root` with an empty password).
5. Visit `http://localhost/skillshare_local/public/index.php`.

The pages load Bootstrap and Chart.js from a CDN, so the browser needs internet
access.

## 6. Default accounts

If you use the seed data from the export:

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@example.com` | `Admin123!` |
| Instructor | `inst@example.com` | `Inst123!` |
| Learner | `learner@example.com` | `Learner123!` |

Check the `users` table if these have been changed.

## 7. Security and validation

- All database access uses prepared statements through `mysqli->prepare()` to
  reduce the risk of SQL injection.
- Passwords are never stored in plain text. `password_hash()` and
  `password_verify()` handle them.
- Authentication state lives in PHP sessions, and helper functions guard the
  protected pages.
- Forms are validated twice:
  - **server-side** (PHP) for every critical operation
  - **client-side** (jQuery), for example password length and confirmation,
    the password strength meter, and the session filters

## 8. JavaScript behaviour

The project uses jQuery in an unobtrusive way:

- **Homepage**: animated counters and a "More about how impact is calculated"
  toggle.
- **Browse sessions**: live filtering by title, category, city or online, and
  fee, plus a live result counter.
- **Registration**: a password strength meter and client-side checks for
  password length and confirmation.

All custom scripts live in `assets/js/` and load only on the pages that need
them.
