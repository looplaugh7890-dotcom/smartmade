# SmartMade Embroidery — Core PHP Website

A complete, production-ready custom embroidery studio website built with **Core PHP**, **MySQL**, and **vanilla JS/CSS**. No MVC framework. No build step required.

## Quick Start

1. **Upload files** to your PHP-enabled web server (Apache or Nginx + PHP-FPM).
2. **Create a MySQL database** and import `schema.sql`.
3. **Edit `includes/config.php`** with your database credentials and site URL.
4. **Set folder permissions** so PHP can write to `/uploads` and its subfolders:
   ```bash
   chmod -R 755 uploads/
   ```
5. **Log in to the admin panel** at `/admin/login.php`:
   - Email: `admin@smartmade.example`
   - Password: `password`
   - **Change this immediately** via Admin → Profile.

## Folder Structure

```
smartmade-php/
├── admin/              # Admin panel pages
├── assets/             # CSS, JS, images
├── includes/           # Shared PHP (db, functions, templates)
├── uploads/            # User uploads (portfolio, quotes, blog, testimonials)
├── .htaccess           # Apache security / rewrite rules
├── schema.sql          # Database setup
└── *.php               # Public pages
```

## Features

- **Public pages:** Home, Portfolio (AJAX filtering + modal), Services, About, Quote form, Contact, FAQ, Testimonials, Blog, Privacy, Terms, Shipping.
- **Admin panel:** Secure login, dashboard stats, portfolio CRUD, quote requests, contact messages, testimonials, blog posts, site settings, profile.
- **Security:** PDO prepared statements, CSRF tokens, password hashing, XSS escaping, secure session config, upload validation.
- **Design:** Black/cream/gold embroidery aesthetic, stitch-draw animations, scroll reveals, mobile-first responsive.

## Notes

- Replace placeholder images in `/assets/images/` with your own photography.
- Update site settings (email, Instagram, address) in Admin → Settings.
- For production, set `display_errors` to `0` in `includes/config.php`.
- If using Nginx, translate the `.htaccess` rules to your Nginx config.

## Customisation

- Colours are defined as CSS custom properties in `assets/css/style.css` (`:root`).
- Copy tone is intentionally warm, witty, and Scottish — swap in your own copy via the admin or by editing page files.
