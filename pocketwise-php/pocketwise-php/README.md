# PocketWise — PHP + MySQL Edition

A full-stack AI-powered expense tracker rebuilt in **PHP + MySQL** with no npm/composer required.
The frontend is a single HTML file using React + Recharts from CDN.

---

## Tech Stack

| Layer      | Technology                          |
|------------|-------------------------------------|
| Frontend   | React 18 (CDN), Recharts, vanilla CSS |
| Backend    | PHP 8.1+ (no frameworks, no Composer) |
| Database   | MySQL 5.7+ / MariaDB 10+            |
| Auth       | JWT (HS256, pure PHP implementation) |
| AI Chat    | Anthropic Claude API via cURL        |

---

## Project Structure

```
pocketwise-php/
├── schema.sql                  ← Run once to create DB + seed categories
├── README.md
├── api/
│   ├── index.php               ← Router / bootstrap (all API requests here)
│   ├── config.php              ← DB credentials, JWT secret, API key
│   ├── jwt.php                 ← Pure PHP JWT library (no dependencies)
│   ├── .htaccess               ← Apache URL rewriting
│   └── handlers/
│       ├── auth.php            ← POST /api/auth/signup, /api/auth/signin
│       ├── categories.php      ← GET  /api/categories
│       ├── transactions.php    ← GET/POST/PUT/DELETE /api/transactions
│       ├── budgets.php         ← GET/POST/DELETE /api/budgets
│       ├── profiles.php        ← GET/PUT /api/profiles
│       └── ai_chat.php         ← POST /api/ai-chat, GET /api/chat-history
└── public/
    ├── index.html              ← Complete React SPA (one file, no build step)
    └── .htaccess               ← Serve index.html for all routes
```

---

## Quick Start (PHP built-in server — easiest)

### Step 1: Create the database

```bash
mysql -u root -p < schema.sql
```

### Step 2: Configure database credentials

Edit `api/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'pocketwise');
define('DB_USER', 'root');       // ← your MySQL username
define('DB_PASS', '');           // ← your MySQL password
```

### Step 3: Start the API server (Terminal 1)

```bash
cd pocketwise-php/api
php -S localhost:8000
```

### Step 4: Start the frontend server (Terminal 2)

```bash
cd pocketwise-php/public
php -S localhost:5500
```

### Step 5: Open the app

Open **http://localhost:5500** in your browser.

---

## Apache / XAMPP / WAMP Setup

1. Copy the entire `pocketwise-php/` folder into your `htdocs/` (XAMPP) or `www/` (WAMP) directory.
2. Edit `api/config.php` with your DB credentials.
3. Edit `public/index.html` — find the line:
   ```js
   const API_BASE = 'http://localhost:8000/api';
   ```
   Change it to:
   ```js
   const API_BASE = 'http://localhost/pocketwise-php/api';
   ```
4. Run `schema.sql` in phpMyAdmin or MySQL CLI.
5. Open **http://localhost/pocketwise-php/public/**

Make sure `mod_rewrite` is enabled in Apache (`a2enmod rewrite`).

---

## API Endpoints

| Method | Path                          | Auth | Description              |
|--------|-------------------------------|------|--------------------------|
| POST   | /api/auth/signup              | No   | Register new user        |
| POST   | /api/auth/signin              | No   | Login, returns JWT       |
| GET    | /api/categories               | Yes  | All spending categories  |
| GET    | /api/transactions?userId=     | Yes  | User's transactions      |
| POST   | /api/transactions             | Yes  | Create transaction       |
| PUT    | /api/transactions/:id         | Yes  | Update transaction       |
| DELETE | /api/transactions/:id         | Yes  | Delete transaction       |
| GET    | /api/budgets?userId=          | Yes  | User's budgets           |
| POST   | /api/budgets                  | Yes  | Create/update budget     |
| DELETE | /api/budgets/:id              | Yes  | Delete budget            |
| GET    | /api/profiles?userId=         | Yes  | User profile             |
| PUT    | /api/profiles/:userId         | Yes  | Update profile           |
| POST   | /api/ai-chat                  | Yes  | AI finance assistant     |
| GET    | /api/chat-history             | Yes  | Past AI conversations    |

---

## Features

- ✅ Sign up / Sign in with JWT authentication
- ✅ Dashboard with spending stats (today / week / month)
- ✅ Daily spending area chart (14-day trend)
- ✅ Category breakdown pie chart
- ✅ Budget vs Actual bar chart
- ✅ Add / Edit / Delete transactions
- ✅ Filter & sort transactions (search, category, date, amount)
- ✅ Set daily, monthly, and category budgets with progress bars
- ✅ Analytics: by category, merchant, day-of-week, month comparison
- ✅ ML Insights: spending prediction, unusual transactions, saving suggestions
- ✅ AI Chatbot powered by Claude (Anthropic API)
- ✅ Persistent chat history
- ✅ Profile settings (name, currency)
- ✅ Fully mobile responsive

---

## Default Demo Account

The database seeds the following demo account (from the original project):

| Field    | Value           |
|----------|-----------------|
| Email    | Sneh@gmail.com  |
| Password | Sneh@123        |

---

## Requirements

- PHP 8.0+ with extensions: `pdo`, `pdo_mysql`, `curl`, `json`
- MySQL 5.7+ or MariaDB 10.3+
- A modern browser (Chrome, Firefox, Safari, Edge)

To check PHP extensions:
```bash
php -m | grep -E "pdo|curl|json"
```
