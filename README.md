# Learning Support Portal

A PHP + MySQL prototype for:
- AI-driven dropout risk early warning
- Personalized mobile learning (offline-capable via PWA)
- Community learning hub support workflows
- Role-based access (`admin`, `educator`, `counselor`)
- Built-in AI chatbot for intervention guidance

## Tech Stack
- PHP (XAMPP compatible)
- MySQL / MariaDB
- Vanilla JS + CSS
- Service Worker + Web App Manifest

## Project Structure
- `login.php` role-based login page
- `student-register.php` student registration page
- `student-home.php` student portal after login
- `logout.php` session logout
- `index.php` public sample homepage (limited access)
- `students.php` main authenticated student hub
- `dashboard.php` authenticated analytics dashboard
- `api/students.php` risk model + student analytics endpoint
- `api/alerts.php` actionable alerts endpoint
- `api/chatbot.php` AI assistant endpoint
- `includes/db.php` database helper
- `includes/auth.php` auth/session/role helpers
- `database/schema.sql` full database schema
- `database/seed.sql` demo data + demo users
- `config.php` local DB credentials

## Setup (XAMPP)
1. Put this project in `C:\xampp\htdocs\EarEyes-Ideathon`.
2. Start Apache and MySQL in XAMPP Control Panel.
3. Open phpMyAdmin and run:
   - `database/schema.sql`
   - `database/seed.sql`
4. Check `config.php` credentials:
   - host: `127.0.0.1`
   - user: `root`
   - pass: `` (empty by default)
   - db: `learning_portal`
5. Open:
   - `http://localhost/EarEyes-Ideathon/`

## Default Login Credentials
- Admin:
  - Email: `admin@platform.local`
  - Password: `admin123`
- Educator:
  - Email: `educator@platform.local`
  - Password: `educator123`
- Counselor:
  - Email: `counselor@platform.local`
  - Password: `counselor123`
- Student (seeded):
  - Email: `student1@platform.local`
  - Password: `student123`

## Notes
- The risk engine in `api/students.php` uses weighted rules from attendance, scores, and behavior incidents.
- The chatbot in `api/chatbot.php` generates contextual intervention guidance from real dashboard metrics.
- PWA files (`manifest.json`, `service-worker.js`) provide install/offline behavior for mobile-style usage.
- Replace seeded data and demo credentials before production.


