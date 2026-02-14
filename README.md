# EarEyes Ideathon Prototype

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
- `logout.php` session logout
- `index.php` protected dashboard
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
   - db: `ear_eyes`
5. Open:
   - `http://localhost/EarEyes-Ideathon/login.php`

## Default Login Credentials
- Admin:
  - Email: `admin@eareyes.local`
  - Password: `admin123`
- Educator:
  - Email: `educator@eareyes.local`
  - Password: `educator123`
- Counselor:
  - Email: `counselor@eareyes.local`
  - Password: `counselor123`

## Notes
- The risk engine in `api/students.php` uses weighted rules from attendance, scores, and behavior incidents.
- The chatbot in `api/chatbot.php` generates contextual intervention guidance from real dashboard metrics.
- PWA files (`manifest.json`, `service-worker.js`) provide install/offline behavior for mobile-style usage.
- Replace seeded data and demo credentials before production.
