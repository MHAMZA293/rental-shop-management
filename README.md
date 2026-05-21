<<<<<<< HEAD
# ShopLedger Pro — Rental Shop Management System

A complete, production-ready web application for managing rental shops in a market.
Built with PHP, MySQL, HTML5, CSS3, and vanilla JavaScript.

---
## 🌐 Live Demo
http://shopledgerpro.infinityfree.me/rental-shop/

## Features

- **Login-based** with bcrypt-encrypted passwords
- **Tenant Management** — full CRUD with contact details
- **Shop Management** — track units, status, and base rent
- **Lease Management** — assign tenants to shops with rent terms
- **Bill Generation** — auto-generate monthly bills with previous dues rollover
- **Payment Recording** — full or partial payments with method tracking
- **Receipt Issuance** — printable receipts with unique receipt numbers
- **Tenant Ledger** — complete history of bills and payments per tenant
- **Reports** — monthly collection summary, outstanding dues list, 12-month trend chart

---

## Setup Instructions

### Requirements
- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.4+
- Web server (Apache/Nginx) or PHP built-in server

### Installation

1. **Copy files** to your web root (e.g., `/var/www/html/rental-shop/`)

2. **Create the database** — import `database.sql`:
   ```bash
   mysql -u root -p < database.sql
   ```

3. **Configure DB credentials** in `includes/config.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'your_db_user');
   define('DB_PASS', 'your_db_password');
   define('DB_NAME', 'rental_shop');
   ```

4. **Run** — visit `http://localhost/rental-shop/`

### Default Login
| Field    | Value                   |
|----------|-------------------------|
| Email    | admin@rentalshop.com    |
| Password | password                |

> **Change the default password after first login!**

---

## File Structure

```
rental-shop/
├── index.php           Login page
├── logout.php          Session destroy
├── dashboard.php       Overview & stats
├── tenants.php         Tenant CRUD
├── shops.php           Shop CRUD
├── leases.php          Lease management
├── bills.php           Bill generation & listing
├── payments.php        Payment recording & receipts
├── ledger.php          Per-tenant full ledger
├── reports.php         Collection & outstanding reports
├── database.sql        DB schema + seed data
├── includes/
│   ├── config.php      DB connection, helpers
│   ├── header.php      Layout header with sidebar
│   └── footer.php      Layout footer
└── assets/
    ├── css/main.css    Stylesheet
    └── js/main.js      JavaScript
```

---

## Workflow

1. Add **Shops** in the market
2. Add **Tenants**
3. Create **Leases** to link tenants to shops
4. Every month, go to **Bills** → Generate Bills (selects month)
5. As payments come in, click the 💰 icon on any bill to record payment
6. System auto-generates a receipt number and updates bill status
7. Check **Ledger** for any tenant's full history
8. Use **Reports** for monthly summaries and overdue analysis

---

## Security Notes

- Passwords hashed with `password_hash()` (bcrypt, cost 12)
- All user inputs sanitized with `htmlspecialchars()`
- PDO prepared statements throughout (SQL injection safe)
- Session-based authentication — all pages check `requireLogin()`
=======
# rental-shop-management
rental shop management website
>>>>>>> 0779f1448144cbe7d78ee8ffa7aa2d41ef12ca90
"# rental-shop-management" 
