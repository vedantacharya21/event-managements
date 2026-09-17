# 🎓 EventHub — College Event Management Portal

A full-stack web application for managing college events, built with **PHP**, **MySQL**, and **vanilla JavaScript**. Supports three user roles with distinct capabilities: **Students**, **Organizers**, and **Admins**.

---

## 📋 Table of Contents

- [Features](#-features)
- [Tech Stack](#-tech-stack)
- [Screenshots](#-screenshots)
- [Installation](#-installation)
- [Database Setup](#-database-setup)
- [Configuration](#-configuration)
- [Usage](#-usage)
- [Project Structure](#-project-structure)
- [Database Schema](#-database-schema)
- [Security](#-security)
- [Troubleshooting](#-troubleshooting)
- [Contributing](#-contributing)
- [License](#-license)

---

## ✨ Features

### 👨‍💼 Admin
- Dashboard with live statistics (users, events, registrations, feedback)
- Approve / reject / complete events submitted by organizers
- Promote users from student → organizer
- Generate event-wise reports with registrations and average ratings
- Delete events with cascading cleanup

### 🎤 Organizer
- Create events with poster upload (JPG/PNG/WEBP, max 5 MB)
- Edit event details including capacity, fee, and categories
- View participant list with registration timestamps
- Mark attendance per participant
- Manage prize pool (position, amount, description)
- Delete events (cascades to registrations, attendance, prizes, feedback)

### 🎓 Student
- Browse approved events with search + filters (type, category)
- Register for events (with capacity enforcement + row locking)
- Cancel registration
- View registered events with live status
- Submit, edit, and delete feedback (1–5 star rating + comments)
- View prize pool per event

### 🎨 UI/UX
- Custom design system with CSS variables
- Ticket-stub event cards with notches and perforation
- Status chips with color-coded dots
- Fully responsive (mobile-first media queries)
- Auto-hide success messages
- Confirmation dialogs on destructive actions
- Double-submit protection on forms

---

## 🛠 Tech Stack

| Layer | Technology |
|-------|-----------|
| **Backend** | PHP 7.4+ (procedural, no framework) |
| **Database** | MySQL 5.7+ / MariaDB 10.4+ (InnoDB) |
| **Frontend** | HTML5, CSS3, Vanilla JavaScript (ES6) |
| **Fonts** | Google Fonts (Bricolage Grotesque, Public Sans) |
| **Server** | Apache 2.4+ / Nginx 1.18+ / PHP Built-in |
| **Tools** | phpMyAdmin, VS Code, MySQL Workbench |

---

## 📸 Screenshots

> Add your own screenshots here after running the project.

| Page | Description |
|------|-------------|
| Homepage | Event grid with filters |
| Event Detail | Full event info + prize pool |
| Admin Dashboard | Statistics tiles |
| Organizer Dashboard | Event management table |
| Student Dashboard | Registered events + feedback |

---

## 🚀 Installation

### Prerequisites

- **PHP 7.4+** with extensions: `mysqli`, `mbstring`, `fileinfo`, `openssl`
- **MySQL 5.7+** or **MariaDB 10.4+**
- **Apache/Nginx** (or PHP built-in server)
- **XAMPP / WAMP / MAMP** (recommended for beginners)

### Step 1 — Clone or Download

```bash
git clone https://github.com/vedantacharya21/event-managements.git
cd eventmanagements
```

Or download the ZIP and extract.

### Step 2 — Move to Web Root

**XAMPP (Windows):**
```
C:\xampp\htdocs\eventmanagements\
```

**WAMP:**
```
C:\wamp64\www\eventmanagements\
```

**MAMP (macOS):**
```
/Applications/MAMP/htdocs/eventmanagements/
```

**Linux (Apache):**
```
/var/www/html/eventmanagements/
```

### Step 3 — Start Services

Start **Apache** and **MySQL** from your XAMPP/WAMP/MAMP control panel.

### Step 4 — Set Up the Database

See [Database Setup](#-database-setup) below.

### Step 5 — Set Permissions (Linux/macOS)

```bash
chmod -R 755 uploads/
chmod 644 includes/*.php
```

### Step 6 — Access the App

Open your browser:

```
http://localhost/eventmanagements/
```

---

## 🗄 Database Setup

### Option A — Using phpMyAdmin (Recommended)

1. Open **http://localhost/phpmyadmin**
2. Click **Import** (top menu)
3. Click **Choose File** → select `event_management.sql`
4. Click **Go**
5. Database `event_management` is created ✅

### Option B — Using MySQL CLI

```bash
mysql -u root -p < event_management.sql
```

### Create the Admin Account

The SQL file inserts a placeholder admin. You **must** set a real password:

1. Create `hash.php` in the project root:

```php
<?php
echo password_hash('admin123', PASSWORD_DEFAULT);
```

2. Visit `http://localhost/eventmanagements/hash.php`
3. Copy the hash (starts with `$2y$10$...`)
4. Run this SQL in phpMyAdmin:

```sql
UPDATE users
SET password = 'PASTE_YOUR_HASH_HERE'
WHERE email = 'admin@eventhub.com';
```

5. **Delete `hash.php` immediately.**

### Default Admin Credentials

| Field | Value |
|-------|-------|
| Email | `admin@eventhub.com` |
| Password | `admin123` (after you set it) |

---

## ⚙️ Configuration

Edit `includes/db.php`:

```php
$host = "localhost";
$username = "root";
$password = "";           // Your MySQL password
$database = "event_management";
```

If your MySQL password isn't empty, update the `$password` variable.

### PHP Configuration (recommended)

In your `php.ini`:

```ini
upload_max_filesize = 5M
post_max_size = 8M
max_execution_time = 60
display_errors = On       ; Set to Off in production
error_reporting = E_ALL
```

---

## 🎯 Usage

### First-Time Setup Flow

1. **Login as admin** (`admin@eventhub.com` / `admin123`)
2. **Logout**
3. **Register** a new account — choose **Organizer**
4. **Login as organizer** — create your first event
5. **Login as admin** — approve the event
6. **Register another account** as **Student**
7. **Login as student** — register for the event
8. **Login as organizer** — mark attendance
9. **Login as admin** — mark event as completed
10. **Login as student** — submit feedback

### Common Tasks

**Register as Organizer:**
- Go to `/register.php` → select "Organizer" from dropdown

**Promote a Student to Organizer:**
- Login as admin → **Users** → change dropdown → **Update**

**Reset a Password:**
- Generate hash with `password_hash()` and run:
  ```sql
  UPDATE users SET password = '...' WHERE email = '...';
  ```

---

## 📁 Project Structure

```
eventmanagements/
│
├── index.php                       # Homepage (event grid + filters)
├── login.php                       # Login
├── register.php                    # Register (student/organizer)
├── logout.php                      # Session destroy
├── event.php                       # Event detail page
├── event_management.sql            # Database schema + seed data
├── README.md                       # This file
├── requirements.txt                # Environment requirements
│
├── includes/
│   ├── db.php                      # MySQL connection
│   ├── auth.php                    # Session + role helpers
│   ├── header.php                  # Site header + <main> opener
│   └── footer.php                  # Site footer + </main> closer
│
├── assets/
│   ├── css/
│   │   └── style.css               # Design system + responsive
│   └── js/
│       └── script.js               # Form validation + UX
│
├── uploads/
│   └── posters/                    # Event poster images
│
├── admin/
│   ├── dashboard.php               # Statistics overview
│   ├── events.php                  # Approve/reject/complete/delete
│   ├── users.php                   # Role management
│   └── reports.php                 # Event-wise analytics
│
├── organizer/
│   ├── dashboard.php               # Own events overview
│   ├── create-event.php            # Event creation form
│   ├── edit-event.php              # Edit + replace poster
│   ├── delete-event.php            # Cascading delete
│   ├── participants.php            # Registration list
│   ├── attendance.php              # Mark attendance
│   ├── manage-prizes.php           # Prize pool CRUD
│   └── delete-prizes.php           # Prize deletion
│
└── student/
    ├── dashboard.php               # Student stats
    ├── my-events.php               # Registered events
    ├── register-event.php          # Confirm registration
    ├── cancel-registration.php     # Cancel
    ├── feedback.php                # Submit + list feedback
    ├── edit-feedback.php           # Update feedback
    ├── delete-feedback.php         # Remove feedback
    └── prize-pool.php              # Prize pool view
```

---

## 🗃 Database Schema

### Tables (9)

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `users` | System accounts | user_id, email, role |
| `categories` | Event categories | category_id, name |
| `events` | Event listings | event_id, organizer_id, status |
| `event_categories` | M:N junction | event_id, category_id |
| `registrations` | Student signups | registration_id, user_id, event_id |
| `attendance` | Attendance records | attendance_id, registration_id |
| `prizes` | Prize pool | prize_id, event_id, position |
| `winners` | Award winners | winner_id, prize_id, registration_id |
| `feedback` | Student ratings | feedback_id, user_id, event_id |

### Relationships

```
users ─┬──< events ─┬──< registrations ─┬──< attendance
       │           │                   └──< winners
       │           ├──< prizes ─────────┘
       │           ├──< event_categories >── categories
       │           └──< feedback
       ├──< registrations
       └──< feedback
```

Full schema: see `event_management.sql`.

### ER Diagram

Generate a visual ER diagram at **https://dbdiagram.io** — paste the DBML from the project documentation.

---

## 🔒 Security

### Implemented

- ✅ **Prepared statements** on all user-input queries
- ✅ **Password hashing** with bcrypt (`password_hash`)
- ✅ **Session regeneration** on login (`session_regenerate_id(true)`)
- ✅ **XSS protection** via `htmlspecialchars()` on all output
- ✅ **Role-based access control** via `requireRole()`
- ✅ **File upload validation** (MIME + extension + size)
- ✅ **Transaction + row locking** for registration race conditions
- ✅ **Ownership checks** before edit/delete operations
- ✅ **CSRF-safe** forms (session-gated)

### Recommended for Production

- [ ] Use **HTTPS** (TLS certificate via Let's Encrypt)
- [ ] Set `display_errors = Off` in `php.ini`
- [ ] Rename default admin email
- [ ] Add `.htaccess` in `uploads/` to block PHP execution:
  ```apache
  php_flag engine off
  <FilesMatch "\.(php|phtml|pl|py|jsp|asp|sh|cgi)$">
      Require all denied
  </FilesMatch>
  ```
- [ ] Change MySQL password from default
- [ ] Add rate limiting on login
- [ ] Enable `session.cookie_secure` and `session.cookie_httponly`

---

## 🐛 Troubleshooting

| Problem | Solution |
|---------|----------|
| **"Database connection failed"** | Check `includes/db.php` credentials. Verify MySQL is running. |
| **"Invalid email or password"** | Reset the admin hash (see Database Setup). |
| **Blank white page** | Enable errors in `db.php`: `ini_set('display_errors', 1);` |
| **"404 Not Found"** | Ensure folder is named `eventmanagements` inside `htdocs`/`www`. |
| **Poster upload fails** | Check `uploads/posters/` permissions (chmod 755). |
| **Session lost after login** | Ensure `session_start()` isn't called twice. |
| **"Access denied for user 'root'"** | Set the MySQL password in `db.php`. |
| **Redirect loop on login** | Check the role column in DB matches expected values. |

### Enable Debug Mode

Temporarily add to `includes/db.php`:

```php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
```

**Remember to disable before deploying.**

---

## 🧪 Testing Checklist

Run through this after installation:

- [ ] Homepage loads without errors
- [ ] Can register as student
- [ ] Can register as organizer
- [ ] Login as admin works
- [ ] Admin can approve an event
- [ ] Organizer can create an event
- [ ] Organizer can upload poster
- [ ] Student can register for event
- [ ] Student can cancel registration
- [ ] Organizer can mark attendance
- [ ] Organizer can add prizes
- [ ] Student can view prize pool
- [ ] Student can submit feedback
- [ ] Admin can view reports
- [ ] Admin can promote user role

---

## 🚧 Roadmap

Potential enhancements:

- [ ] QR code check-in for events
- [ ] Email notifications (PHPMailer)
- [ ] Winner assignment UI
- [ ] PDF certificate generation
- [ ] Payment gateway (Razorpay/Stripe)
- [ ] Event calendar view (FullCalendar.js)
- [ ] Chart.js analytics dashboard
- [ ] REST API for mobile app
- [ ] Search autocomplete (AJAX)
- [ ] Export participants to CSV

---

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
   ```bash
   git checkout -b feature/amazing-feature
   ```
3. Commit changes
   ```bash
   git commit -m "Add amazing feature"
   ```
4. Push to branch
   ```bash
   git push origin feature/amazing-feature
   ```
5. Open a Pull Request

### Coding Standards

- **PHP:** PSR-12 style, 4-space indent, no tabs
- **SQL:** Uppercase keywords, one clause per line
- **CSS:** Use design tokens from `:root`
- **JavaScript:** ES6+, no jQuery
- **Naming:** `snake_case` for DB, `camelCase` for JS, `kebab-case` for CSS

---

## 📄 License

This project is licensed under the **MIT License**.

```
MIT License

Copyright (c) 2025 EventHub

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND.
```

---

## 👨‍💻 Author

**Your Name**
- GitHub: [@vedantacharya21](https://github.com/vedantacharya21)
- Email: your.email@example.com

---

## 🙏 Acknowledgements

- **Google Fonts** — Bricolage Grotesque, Public Sans
- **XAMPP** — Local development environment
- **PHP & MySQL** — Backend foundation

---

## 📞 Support

If you encounter issues:

1. Check the [Troubleshooting](#-troubleshooting) section
2. Search [existing issues](https://github.com/vedantacharya21/eventmanagements/issues)
3. Open a new issue with:
   - PHP version
   - MySQL version
   - Error message (from browser or logs)
   - Steps to reproduce

---

## ⭐ Show Your Support

If this project helped you, please give it a ⭐ on GitHub!

---

**Built with ❤️ for college event management.**
