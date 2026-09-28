# 3YOS Catering System

A comprehensive, full-stack web application designed to streamline catering reservations and event management. 

## Overview

The 3YOS Catering System is a specialized platform built to handle the end-to-end workflow of a catering business. It serves two main audiences: 
1. **Clients (Guests):** Who need an intuitive way to explore catering packages, view past event galleries, check date availability, and submit booking requests or inquiries.
2. **Administrators (Staff):** Who require a robust backend to review incoming reservations, manage service contracts, track business analytics, and maintain website content.

By replacing manual booking processes with an automated online reservation system, 3YOS Catering solves issues like double-booking (enforcing a maximum of 3 events per day) and lead-time constraints (enforcing a minimum 2-day advance notice for bookings). 

## Features

### Guest / Public Website
- **Home & About:** Introduction to the catering business and its values.
- **Services:** Dynamic listing of available catering services (e.g., Buffet setup, Event styling).
- **Catering Packages:** Tiered package browsing (Silver, Gold, Platinum, Diamond) with detailed inclusions, menus, and estimated pricing.
- **Gallery:** Visual portfolio of past catered events and setups.
- **Reservation System:** 
  - Real-time date availability checking.
  - Interactive event time picker.
  - Automatic capacity limits (prevents overbooking on a single day).
- **Reservation Status:** A dedicated portal for clients to track their booking status using a unique reservation code.
- **Inquiry Form:** A dedicated contact form for custom event queries and general questions, protected by rate limiting and CAPTCHA.

### Admin Panel
- **Dashboard & Analytics:** High-level overview of pending reservations, recent inquiries, and business metrics.
- **Reservations Management:** Review booking details, update statuses (e.g., pending, approved, cancelled), and upload/manage signed service contracts.
- **Inquiries Management:** Track client questions and reply directly from the admin interface.
- **Catering Packages & Services:** Full CRUD (Create, Read, Update, Delete) management to adjust pricing, descriptions, and toggle service availability.
- **Gallery Management:** Secure upload and deletion of portfolio images.
- **Reports:** Generate and export business data based on daily, weekly, monthly, or yearly periods.
- **User / Account Management (Team Admins):** Manage administrator access with role-based permissions (Full Admin vs. Limited Admin).
- **Activity Logs:** A comprehensive audit trail tracking administrator actions for security and accountability.
- **System Backups:** Integrated tools to create, download, restore, and delete database backups directly from the interface.

## System Architecture

The 3YOS Catering System is built on a modern, monolithic architecture utilizing the TALL-stack ecosystem (minus Livewire) for rapid development and reliability.

Browser (Client)
       ↓
Frontend (Blade + Tailwind CSS + Vite)
       ↓
Backend (Laravel + PHP)
       ↓
Database (SQLite / MySQL)

- **Frontend Technology:** Laravel Blade Templates, Tailwind CSS (v4), Vanilla JavaScript, and Vite for asset bundling.
- **Backend Technology:** Laravel Framework (PHP 8.2+).
- **Database Technology:** Eloquent ORM supporting SQLite (default local configuration) or MySQL.
- **Authentication & Authorization:** Laravel Session-based authentication with custom middleware (`ensure.admin`, `ensure.full-admin`) for role-based access control.
- **Important Integrations:** 
  - Google reCAPTCHA v2 (Spam protection on public forms).
  - SMTP Mailer (For sending inquiry replies and reservation updates).

## Project Structure

```text
3yos-final-ver/
├── app/
│   ├── Http/
│   │   ├── Controllers/    # Application logic (Admin, Public, Auth)
│   │   ├── Middleware/     # Security, Role-checks, Activity Logging
│   │   └── Requests/       # Form validation rules
│   └── Models/             # Database models (Reservation, Package, User, etc.)
├── bootstrap/              # Framework bootstrapping and middleware aliases
├── config/                 # Application, database, and service configurations
├── database/
│   ├── migrations/         # Database schema definitions
│   └── seeders/            # Initial data seeding (Admin user, Default packages)
├── public/
│   ├── build/              # Compiled Vite frontend assets (CSS/JS)
│   └── gallery-images/     # Uploaded public images (via storage symlink)
├── resources/
│   ├── css/                # Tailwind CSS source files
│   ├── js/                 # Frontend JavaScript source
│   └── views/              # Blade templates (admin/, public/, layouts/)
├── routes/
│   ├── web.php             # Public and protected Admin routes
│   └── console.php         # Artisan console commands
├── storage/                # Application logs, file uploads, and system backups
├── .env                    # Environment variables (Secrets, DB config, Mail)
└── README.md               # Project documentation
```
