# Project Architecture

## Overview

Smart Rent follows a **three-layer architecture** pattern combined with **modular feature organization**. This document describes the system architecture, design patterns, and code organization.

---

## Architecture Layers

```
┌─────────────────────────────────────────────┐
│      Presentation Layer (UI)                │
│  - HTML/CSS/JavaScript                      │
│  - User forms and pages                     │
│  - Input validation and display             │
└────────────────┬────────────────────────────┘
                 │
┌────────────────▼────────────────────────────┐
│    Business Logic Layer                     │
│  - Controllers/Logic classes                │
│  - API integration                          │
│  - Data processing and validation           │
│  - Business rules enforcement               │
└────────────────┬────────────────────────────┘
                 │
┌────────────────▼────────────────────────────┐
│     Data Access Layer (DAL)                 │
│  - Database queries                         │
│  - CRUD operations                          │
│  - Query preparation                        │
└────────────────┬────────────────────────────┘
                 │
┌────────────────▼────────────────────────────┐
│        Database (MySQL)                     │
└─────────────────────────────────────────────┘
```

---

## Module Structure

Each feature module has a consistent three-layer structure:

```
app/MODULE_NAME/
├── data/                    # Data Access Layer
│   └── FeatureData.php
├── logic/                   # Business Logic Layer
│   └── FeatureLogic.php
└── presentation/            # Presentation Layer
    ├── index.php
    ├── create.php
    └── edit.php
```

### Example: Property Module

```
app/property/
├── data/
│   └── PropertyData.php         # All database queries
├── logic/
│   └── PropertyLogic.php        # Business logic
└── presentation/
    ├── index.php                # List properties
    ├── post_property.php        # Create property
    ├── update_property.php      # Edit property
    ├── view_property.php        # View details
    └── search.php               # Search interface
```

---

## Data Flow

### Request Flow

```
1. User Request (Form/AJAX)
   ↓
2. Presentation Layer
   - Parse $_POST, $_GET, $_FILES
   - Basic sanitization (htmlspecialchars)
   - User authentication check
   ↓
3. Logic Layer
   - Validate input (required fields, data types)
   - Call external APIs (Google, OpenRouter, Fixer)
   - Execute business logic
   - Process data transformations
   ↓
4. Data Access Layer
   - Build SQL queries with prepared statements
   - Execute database operations
   - Return results
   ↓
5. Response
   - JSON (AJAX) or Redirect (Form)
   - Update session with success/error messages
```

## Key Architectural Patterns

### 1. MVC-like Pattern (Modified)

- **Model** = Data Layer (`*Data.php` classes)
- **View** = Presentation Layer (HTML files)
- **Controller** = Logic Layer (`*Logic.php` classes)

### 2. Dependency Injection

Database connection passed to constructors:

```php
class PropertyLogic {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->propertyData = new PropertyData($conn);
    }
}
```

### 3. Separation of Concerns

- **Data Layer**: Only handles database
- **Logic Layer**: Only handles business logic
- **Presentation**: Only handles UI and user interaction

### 4. Prepared Statements

All database queries use PDO prepared statements:

```php
$stmt = $conn->prepare("SELECT * FROM property WHERE id = ? AND user_id = ?");
$stmt->execute([$property_id, $user_id]);
```

---

## Authentication Flow

```
User Access
    ↓
session_start() check
    ↓
$_SESSION['user_id'] exists?
    ├─ YES → Allow access
    └─ NO → Redirect to login
        ↓
    Login Form
        ↓
    Verify credentials (password_verify)
        ↓
    Check OTP (if enabled)
        ↓
    Set $_SESSION['user_id']
        ↓
    Redirect to dashboard
```

---

## Database Architecture

### Connection Strategy

```php
// config/env.php
$conn = new PDO(
    "mysql:host={$db_host};dbname={$db_name}",
    $db_user,
    $db_pass
);
```

### Query Pattern

```php
// Prepared statement with parameter binding
$stmt = $conn->prepare("
    SELECT * FROM property 
    WHERE id = ? AND user_id = ?
");
$stmt->execute([$property_id, $user_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
```

### Error Handling

```php
try {
    $conn->beginTransaction();
    // Multiple operations
    $conn->commit();
} catch (PDOException $e) {
    $conn->rollBack();
    error_log("Database error: " . $e->getMessage());
    // Return error to user
}
```

---

## API Integration Architecture

### External APIs

```
Application
├── Google Cloud APIs
│   ├── Maps JavaScript API (address autocomplete)
│   ├── Geocoding API (location coordinates)
│   └── Translation API (Italian → English)
├── OpenRouter API
│   ├── Chat completions (chatbot)
│   └── Tenant scoring (AI evaluation)
└── Fixer.io API
    └── Currency conversion (EUR/USD/ETB)
```

### API Request Flow

```
Logic Layer
    ↓
Validate API key from environment
    ↓
Prepare request payload
    ↓
cURL request to endpoint
    ↓
Parse JSON response
    ↓
Validate response
    ↓
Return processed data or error
```

---

## Security Architecture

### Input Validation Flow

```
Raw Input
    ↓
1. Type Validation (FILTER_VALIDATE_*)
    ↓
2. Length Check
    ↓
3. Whitelist Check (for select values)
    ↓
4. HTML Escaping (htmlspecialchars)
    ↓
Database Query (Prepared Statements)
```

### Authentication & Authorization

```
Request
    ↓
Session Check
    ├─ User ID exists?
    ├─ Session valid?
    └─ User role correct?
        ↓
        Access Granted / Denied
```

---

## Caching Strategy

### Session Cache

```php
$_SESSION['rates'] = [
    'EUR' => 1,
    'USD' => 1.1,
    'ETB' => 60
];
$_SESSION['rates_time'] = time();

// Check if cache expired (3600 seconds)
if (time() - $_SESSION['rates_time'] > 3600) {
    // Fetch new rates
}
```

### File Cache

```
cache/
├── scam_detection_last_run.txt
└── (other cache files)
```

---

## Error Handling

### Error Flow

```
Exception/Error
    ↓
Try-Catch Block
    ↓
Log to error_log()
    ↓
1. Database Errors → Rollback transaction
2. API Errors → Return graceful message
3. Validation Errors → Show warning_msg
4. User Errors → Display error to user
```
