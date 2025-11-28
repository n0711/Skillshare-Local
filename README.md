# SkillShare Local 🌱

Course project for **ACSC476 – Web Programming**.

SkillShare Local is a small web platform where neighbours can teach each other sustainable
skills (composting, bike repair, home energy saving, water hacks, etc.). Instructors create
sessions, learners book and rate them, and the system estimates the climate impact of the
knowledge shared.

---

## 1. Project overview

Roles:

- **Learner** – browses sessions, requests bookings, rates attended sessions.
- **Instructor** – creates and manages sessions, accepts/rejects bookings, marks attendance.
- **Admin** – manages user accounts and monitors sustainability impact.

Sustainability link:

- Each category (Gardening, DIY & Repair, Water Saving, etc.) has an entry in the
  `impact_factors` table: **kg CO₂ saved per participant** (rough educational estimate).
- For every **attended booking**, the platform multiplies the number of participants by the
  relevant impact factor.
- The **Impact Dashboard** aggregates this per category and visualises:
  - Total kg and tons of CO₂ saved
  - A table of categories, participants and impact
  - A Chart.js graph (bar = CO₂ per category, line = participants)

The UI clearly states that these numbers are **approximate educational estimates**, not
precise carbon accounting, and references SDG 4 (Quality Education) and SDG 12 (Responsible
Consumption & Production).

---

## 2. Main features

### Authentication & roles

- Registration & login for **Learner**, **Instructor**, **Admin**
- Passwords hashed with `password_hash()` and checked via `password_verify()`
- User status (`active` / `suspended`) stored in `users.status`
  - Suspended users cannot log in (checked at login time)
- Session-based authentication helpers in `includes/auth.php`:
  - `current_user()`, `require_login()`, `is_role($role)`

### Sessions

- Instructors can **create / edit / unpublish** their own sessions:
  - title, description (including sustainability explanation)
  - category (FK to `categories`)
  - mode: `online` or `in-person`
  - city + address (for in-person)
  - date & time, duration, capacity
  - fee type: `free` or `paid` (+ amount)
  - optional photo upload (stored under `uploads/`)
- Instructor **My Sessions** dashboard:
  - shows status badge per session:
    - **Active** (future & published)
    - **Completed** (past date)
    - **Unpublished** (status = inactive)
  - shows bookings vs capacity
  - actions: **Edit**, **Publish / Unpublish**, **View bookings**

### Browsing & booking

- Learner browse page (`sessions_browse.php`):
  - card layout with photo, category, instructor name, mode/city/online, date/time
  - badges for free/paid, capacity and current bookings
  - display of **average rating** and rating count
- Dynamic filters (via jQuery, `assets/js/browse_sessions.js`):
  - title search
  - category select
  - city / online select
  - fee type select
  - live “Showing **X** sessions” counter
- Session detail page:
  - large image, full description, SDG badges
  - badges for date/time, mode, capacity and bookings
  - average rating summary
  - booking button with rules:
    - must be learner
    - not full
    - not already booked
    - not in the past

### Bookings & ratings

- Learners:
  - view and manage their bookings (status: pending, accepted, rejected, cancelled)
- Instructors:
  - approve / reject bookings for their sessions
  - mark accepted bookings as **attended**
- Ratings:
  - learners can submit rating only after attendance
  - ratings table includes score and optional comment
  - ratings feed back into:
    - session cards in browse
    - session detail view

### Impact Dashboard (admin)

- Reads from:
  - `categories`
  - `impact_factors` (kg CO₂ per participant per category)
  - `bookings` (attended)
- For each category:
  - participants = count of attended bookings
  - impact (kg) = participants × impact factor
- Shows:
  - total participants
  - total kg CO₂ and tons CO₂
  - table per category
  - Chart.js graph (bar + line)
- Accessible to **admin** role via navbar.

### Admin users panel

- Lists all users with:
  - name, email, role, city, status, created_at
- Admin can **suspend / activate** accounts
- Suspended users receive a friendly message on login attempt.

---

## 3. Technology stack

- PHP 8.x (tested on XAMPP / MariaDB)
- MySQL / MariaDB with `mysqli` + prepared statements
- Bootstrap 5 for layout
- Custom CSS in `assets/css/style.css` (hero cards, gradients, hover effects, badges, auth cards)
- jQuery + custom JS in `assets/js`:
  - `app.js` – global behaviour (tooltips, etc.)
  - `home.js` – homepage stat counters & impact toggle
  - `browse_sessions.js` – search & filters + live session count
  - `register.js` – password strength meter & client-side validation
- Chart.js (CDN) for impact graphs

---

## 4. Folder structure (simplified)

- `config/db.php` – database connection
- `includes/auth.php` – authentication helpers
- `includes/header.php` / `includes/footer.php` – layout shell, navbar, footer
- `public/`
  - `index.php` – landing page with stats and impact intro
  - `login.php`, `register.php`, `logout.php`
  - `sessions_browse.php`, `session_view.php`
  - `session_create.php`, `session_edit.php`, `my_sessions.php`
  - `booking_manage.php`
  - `impact_dashboard.php`
  - `admin_users.php`
- `assets/css/style.css` – custom styling
- `assets/js/` – jQuery logic split by page
- `skillshare_local.sql` – export of the database schema & seed data (for examiner)

---

## 5. Getting started

1. Create a database called `skillshare_local`.
2. Import `skillshare_local.sql` using phpMyAdmin.
3. Place this folder under your web root, e.g.
   `C:\xampp\htdocs\skillshare_local`.
4. Ensure `config/db.php` credentials match your MySQL setup.
5. Visit `http://localhost/skillshare_local/public/index.php`.

---

## 6. Default accounts (example)

- **Admin**
  - Email: `admin@example.com`
  - Password: `Admin123!`
- **Instructor**
  - Email: `inst@example.com`
  - Password: `Inst123!`
- **Learner**
  - Email: `learner@example.com`
  - Password: `Learner123!`

(See the SQL seed or `users` table if these are changed.)

---

## 7. Security & validation

- All data access uses prepared statements via `mysqli->prepare()` to reduce the risk of SQL injection.
- Passwords are never stored in plain text; `password_hash()` and `password_verify()` are used.
- Authentication state is stored in PHP sessions; helper functions guard protected pages.
- Forms are validated both:
  - **Server-side** (PHP) for every critical operation.
  - **Client-side** (jQuery) e.g. password length + confirmation, password strength meter, session filters.

---

## 8. JavaScript behaviour

The project uses jQuery in an unobtrusive way:

- **Homepage:** animated counters and “More about how impact is calculated” toggle.
- **Browse sessions:** dynamic filtering by title, category, city/online and fee, plus a live
  result counter.
- **Registration:** password strength meter and client-side validation for password length and
  confirmation.

All custom scripts live under `assets/js/` and are loaded only on the pages that need them.

---
