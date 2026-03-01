# Smart Rent 🏠

A comprehensive PHP-based rental property management and tenant screening platform with AI-powered features for landlords and tenants.

**Version:** 1.0.0  
**Last Updated:** March 1, 2026  

---

## 📋 Table of Contents

- [Features](#features)
- [Project Structure](#project-structure)
- [Prerequisites](#prerequisites)
- [Installation & Setup](#installation--setup)
- [Configuration](#configuration)
- [Database Setup](#database-setup)
- [Running the Application](#running-the-application)
- [API Keys & Environment Variables](#api-keys--environment-variables)
- [Project Architecture](#project-architecture)
- [Key Technologies](#key-technologies)
- [Development Guide](#development-guide)
- [Security](#security)
- [Troubleshooting](#troubleshooting)
- [Documentation](#documentation)
- [Contributing](#contributing)
- [License](#license)

---

## ✨ Features

### For Landlords
- ✅ **Property Management** - Post, update, and manage property listings
- ✅ **Tenant Screening** - AI-powered tenant application scoring
- ✅ **Scam Detection** - Automated detection of suspicious listings using machine learning
- ✅ **Notifications** - Real-time Telegram notifications for important events
- ✅ **Image Management** - Upload and manage up to 5 property images
- ✅ **Currency Support** - Real-time exchange rates (EUR, USD, ETB)

### For Tenants
- ✅ **Property Search** - Search with Google Maps integration and autocomplete
- ✅ **AI Chatbot** - 24/7 property inquiries via OpenRouter AI
- ✅ **Application Forms** - Apply for properties with preference matching
- ✅ **Multi-language Support** - Auto-translation of property descriptions
- ✅ **Subscriptions** - Tiered subscription plans for premium features

### Platform Features
- ✅ **User Authentication** - Secure registration and login with OTP verification
- ✅ **Role-based Access** - Admin, Landlord, and Tenant roles
- ✅ **Chat System** - Encrypted messaging between users
- ✅ **Admin Dashboard** - Complete platform management and monitoring
- ✅ **Responsive Design** - Mobile-friendly interface

---

## 📁 Project Structure

```
smart_rent/
├── app/                          # Application logic and features
│   ├── admin/                    # Admin panel features
│   │   ├── data/                # Data access layer
│   │   ├── logic/               # Business logic
│   │   └── presentation/        # Admin UI
│   ├── auth/                     # Authentication system
│   │   ├── data/                # User/OTP data access
│   │   ├── logic/               # Auth logic and OTP verification
│   │   └── presentation/        # Login/Register pages
│   ├── chat/                     # Direct messaging system
│   ├── notifications/            # Notification service
│   ├── property/                 # Property management
│   │   ├── data/                # Property data access
│   │   ├── logic/               # Property & scam detection logic
│   │   └── presentation/        # Property listing & management UI
│   ├── reviews/                  # Review system
│   ├── scam/                     # Scam detection module
│   ├── screening/                # Tenant screening system
│   ├── subscription/             # Subscription management
│   └── user/                     # User profile management
├── components/                   # Reusable PHP components
│   ├── connect.php              # Database connection
│   ├── currency.php             # Currency conversion
│   ├── chatbot.php              # AI chatbot interface
│   ├── chatbot-handler.php      # Chatbot request processing
│   ├── user_header.php          # User navigation header
│   ├── admin_header.php         # Admin navigation header
│   └── footer.php               # Footer component
├── config/                       # Configuration files
│   └── env.php                  # Environment variables loader
├── public/                       # Frontend assets
│   ├── css/                     # Stylesheets
│   ├── js/                      # JavaScript files
│   └── images/                  # Static images
├── doc/                          # Documentation
├── vendor/                       # Composer dependencies
├── cache/                        # Application cache
├── uploaded_files/               # User-uploaded property images
├── .env                          # Environment variables (gitignored)
├── .env.example                  # Environment template
├── .gitignore                    # Git ignore rules
├── composer.json                 # PHP dependencies
├── index.php                     # Application entry point
└── README.md                     # This file
```

---

## 📦 Prerequisites

Before you begin, ensure you have the following installed:

- **PHP 8.0+** - Server-side language
- **MySQL 5.7+** or **MariaDB 10.3+** - Database
- **Composer** - PHP dependency manager
- **Git** - Version control
- **cURL** - For API requests
- **OpenSSL** - For encryption

### Required PHP Extensions

```bash
php-json
php-pdo
php-mysql
php-openssl
php-curl
php-mbstring
```

Verify extensions:
```bash
php -m | grep -E "json|pdo|mysql|openssl|curl|mbstring"
```

---

## 🚀 Installation & Setup

### Step 1: Clone the Repository

```bash
git clone git@github.com:YosephEndale/smart_rent.git
cd smart_rent
```

### Step 2: Install Dependencies

```bash
composer install
```

This installs:
- `vlucas/phpdotenv` - Environment variable management
- `guzzlehttp/guzzle` - HTTP client for API requests
- Other utility packages

### Step 3: Copy Environment Template

```bash
cp .env.example .env
```

### Step 4: Configure Environment Variables

Edit `.env` with your configuration:

```env
# Database
DB_HOST=localhost
DB_NAME=rent_web
DB_USER=root
DB_PASS=your_password

# External APIs
FIXER_API_KEY=your_fixer_io_key
OPENROUTER_API_KEY=your_openrouter_key
OPENROUTER_API_KEY_SCORING=your_scoring_key
GOOGLE_API_KEY=your_google_api_key

# Encryption
ENCRYPTION_KEY=your_base64_encoded_key
```

See [API Keys & Environment Variables](#api-keys--environment-variables) for detailed setup.

### Step 5: Create Database

```bash
mysql -u root -p
```

```sql
CREATE DATABASE IF NOT EXISTS rent_web;
USE rent_web;
```

### Step 6: Import Database Schema

```bash
mysql -u root -p rent_web < database/schema.sql
```

*(Schema file available in documentation)*

### Step 7: Set Permissions

```bash
chmod -R 755 app/
chmod -R 755 components/
chmod -R 755 public/
chmod -R 777 uploaded_files/
chmod -R 777 cache/
chmod -R 777 secure_keys/
```

### Step 8: Start Your Server

#### Using PHP Built-in Server (Development)

```bash
php -S localhost:8000
```

Access at: `http://localhost:8000`


## 🛠️ Key Technologies

### Backend
- **PHP 8.0+** - Server-side programming
- **MySQL/MariaDB** - Relational database
- **Composer** - Dependency management
- **PDO** - Database abstraction

### Frontend
- **HTML5** - Markup
- **CSS3** - Styling
- **JavaScript** - Client-side interactivity
- **Bootstrap** - Responsive framework (optional)

### External Services
- **OpenRouter** - LLM for chatbot & AI features
- **Google Cloud APIs** - Maps & translation
- **Fixer.io** - Currency exchange rates
- **Telegram** - Notifications

### Security
- **OpenSSL** - Encryption & key generation
- **password_hash()** - Bcrypt password hashing
- **PDO Prepared Statements** - SQL injection prevention

---

## 👨‍💻 Development Guide

### Code Standards

- **Namespaces** - Use PSR-4 autoloading (`App\*`)
- **Naming** - CamelCase for classes, snake_case for functions
- **Comments** - Document complex logic
- **Error Handling** - Try-catch for database operations
- **Validation** - Always validate user input

### Adding a New Feature

1. **Create module directory:**
   ```bash
   mkdir -p app/feature/{data,logic,presentation}
   ```

2. **Create data layer** (`app/feature/data/FeatureData.php`):
   ```php
   <?php
   namespace App\Feature\Data;
   class FeatureData {
       private $conn;
       public function __construct($conn) {
           $this->conn = $conn;
       }
   }
   ```

3. **Create logic layer** (`app/feature/logic/FeatureLogic.php`):
   ```php
   <?php
   namespace App\Feature\Logic;
   class FeatureLogic {
       private $data;
       public function __construct($conn) {
           $this->data = new FeatureData($conn);
       }
   }
   ```

4. **Create presentation** (`app/feature/presentation/index.php`):
   ```php
   <?php
   require_once __DIR__ . '/../../../config/env.php';
   $logic = new FeatureLogic($conn);
   ?>
   ```


## 🎯 Roadmap

### Planned Features (v2.0)
- [ ] Mobile app (React Native)
- [ ] Video tours for properties
- [ ] Advanced analytics dashboard
- [ ] Payment integration (Stripe)
- [ ] Video call support
- [ ] Machine learning improvements
- [ ] Multi-language UI
- [ ] Dark mode

### Current Status
- ✅ Core platform complete
- ✅ AI chatbot integrated
- ✅ Scam detection active
- ✅ Admin dashboard ready
- ⏳ Mobile app in development
