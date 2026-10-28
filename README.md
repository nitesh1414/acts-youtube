# PayAccess - Core PHP + MySQL PWA Payment Gateway

A complete, production-ready web application built with **Core PHP and MySQL** featuring:

- Landing page with user information collection (Name, Email, Mobile)
- Razorpay Payment Gateway integration (admin-configurable amount)
- Post-payment exclusive YouTube video playback
- Automatic channel subscribe prompt
- PWA (Progressive Web App) support - installable on phones/desktops
- Full Admin Panel with:
  - Zebra-striped datatable for payments
  - Ascending/Descending sorting on all columns
  - Detailed view for every user/payment
  - Site settings panel (logo, logo text, Razorpay keys, YouTube link, amount)

---

## Installation Instructions (Option A Design Template)

This application uses the Option A clean professional Bootstrap 5 design template (blue/purple gradient theme, modern sidebar admin panel).

### Requirements
- PHP 7.4+ (with PDO MySQL extension)
- MySQL 5.7+ / MariaDB
- Apache / Nginx / XAMPP / WAMP / LAMP

### Step 1: Upload Files
Upload all files to your web server's document root (e.g., `htdocs/` for XAMPP, `public_html/` for cPanel hosting).

### Step 2: Configure Database
Edit `config.php` to match your database credentials:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'pay_access_db');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('SITE_URL', 'https://yourdomain.com/');
```

### Step 3: Run Installer
Visit `http://yourdomain.com/install.php` in your browser. This will automatically:
1. Create the database
2. Create the required tables (`settings`, `payments`)
3. Insert default configuration

**After installation, DELETE `install.php` from your server for security.**

### Step 4: Configure Razorpay & Site Settings
1. Go to the Admin Panel by clicking the "🔐 Admin Login" button on the home page
2. Default credentials:
   - Username: `admin`
   - Password: `admin123` *(change this in config.php)*
3. Go to "Site Settings" and enter:
   - Your logo URL / Site name
   - Razorpay Key ID and Secret (from https://dashboard.razorpay.com/)
   - Unlisted YouTube video link (use an "Unlisted" video so only paying customers can view it)
   - The amount you want to charge users
4. Click "Save All Settings".

---

## Key Features
1. **User Flow:**
   - User visits homepage → enters name/email/mobile
   - Clicks Pay → Razorpay checkout opens
   - Successful payment → redirected to video page
   - Exclusive YouTube video plays automatically
   - Subscribe button encourages channel subscription
2. **Admin Panel:**
   - Sidebar navigation (Option A design standard)
   - Sortable zebra-datatable (click headers to sort asc/desc)
   - Detailed payment view on click
   - Full settings management
3. **PWA:**
   - Installable as a mobile/desktop app
   - Service worker for offline caching of static assets
4. **Security:**
   - PDO prepared statements to prevent SQL injection
   - Razorpay signature verification on payment
   - Password-protected admin area
   - Session-based authentication

---

## YouTube Note
YouTube does not allow automatic forced subscriptions (that would violate their API terms of service). This application uses an "Unlisted" YouTube video (which does not appear in search or on your channel page, accessible only via direct link) and displays a prominent "Subscribe Now" button that opens your channel after payment is confirmed. This gives paying users exclusive access while growing your channel.

---

## File Structure
```
├── config.php               # DB & app configuration
├── index.php                # Landing page with user form
├── success.php              # Post-payment video page
├── install.php              # Database installer
├── manifest.json            # PWA manifest
├── sw.js                    # PWA service worker
├── .htaccess                # Apache security rules
├── api/
│   ├── create_order.php     # Creates Razorpay order
│   └── verify_payment.php   # Verifies payment signature
└── admin/
    ├── index.php            # Payments dashboard (zebra datatable)
    ├── settings.php         # Site settings
    ├── view_payment.php     # Single payment details
    └── logout.php           # Logout handler
```
