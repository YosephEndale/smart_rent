# Smart Rent 🏠

A simple PHP-based rental platform for landlords and tenants.

---

## ✨ Features

* Property listing and management
* Tenant applications
* AI chatbot for inquiries
* User authentication (login/register)
* Admin dashboard
* Image uploads

---

## 📦 Requirements

* PHP 8+
* Composer
* MySQL (or SQLite for development)

Required PHP extensions:

* pdo
* mysql
* curl
* openssl
* mbstring

---

## 🚀 Setup

### 1. Clone the project

```bash
git clone git@github.com:YosephEndale/smart_rent.git
cd smart_rent
```

### 2. Install dependencies

```bash
composer install
```

### 3. Configure environment

```bash
cp .env.example .env
```

Edit `.env` with your database settings.

---

## 🗄️ Database

### Option 1: MySQL

```bash
mysql -u root -p
```

```sql
CREATE DATABASE rent_web;
```

```bash
mysql -u root -p rent_web < database/rent_web.sql
```

### Option 2: SQLite (Recommended for development)

```env
DB_DRIVER=sqlite
DB_PATH=database/rent_web.sqlite
```

---

## ▶️ Run the App

```bash
php -S localhost:8000
```

Open in browser:

[http://localhost:8000](http://localhost:8000)

---

## 🛠️ Notes

* SQLite is best for local development
* MySQL is recommended for production
* Make sure required folders are writable:

```bash
chmod -R 777 uploaded_files cache
```

---

## 📁 Structure (Basic)

* `app/` – main features
* `components/` – reusable code
* `config/` – environment setup
* `public/` – assets
* `index.php` – entry point
