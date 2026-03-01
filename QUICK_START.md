# Quick Start Guide - User Model & Controller

## 🚀 Getting Started

### Step 1: Verify Laravel Setup
```bash
# Check Laravel version (requires 11+)
php artisan --version

# Check PHP version (requires 8.2+)
php -v
```

### Step 2: Run Migrations
```bash
# Create necessary tables (if not already present)
php artisan migrate
```

### Step 3: Run Tests
```bash
# Run all tests
php artisan test

# Run with coverage report
php artisan test --coverage

# Run specific test file
php artisan test tests/Unit/UserModelTest.php
php artisan test tests/Feature/UserControllerTest.php
```

### Step 4: Test the API
```bash
# Start the development server
php artisan serve

# Test in another terminal using curl or Postman

# Create a user
curl -X POST http://localhost:8000/api/users \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "number": "1234567890"
  }'

# Get all users
curl http://localhost:8000/api/users

# Get specific user
curl http://localhost:8000/api/users/1
```

---

## 📁 Project Structure

```
app/
├── Http/
│   └── Controllers/
│       └── UserController.php          ← Main controller
├── Models/
│   ├── User.php                        ← User model (updated)
│   ├── Property.php                    ← New
│   ├── Chat.php                        ← New
│   ├── Review.php                      ← New
│   ├── Subscription.php                ← New
│   ├── TenantAnswer.php                ← New
│   ├── TenantScore.php                 ← New
│   ├── ScreeningQuestion.php           ← New
│   └── ScreeningPreference.php         ← New
routes/
└── api.php                              ← API routes (new)
tests/
├── Unit/
│   └── UserModelTest.php               ← Unit tests (28 tests)
└── Feature/
    └── UserControllerTest.php          ← Feature tests (25 tests)
database/
└── factories/
    ├── UserFactory.php                 ← Updated
    ├── PropertyFactory.php             ← New
    ├── ChatFactory.php                 ← New
    ├── ReviewFactory.php               ← New
    ├── SubscriptionFactory.php         ← New
    ├── TenantAnswerFactory.php         ← New
    └── TenantScoreFactory.php          ← New
```

---

## 🔑 Key Models & Relationships

### User Model
```php
// Create a user
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => Hash::make('password123'),
    'number' => '1234567890',
    'is_premium' => false
]);

// Access relationships
$user->properties();        // Properties owned
$user->sentChats();         // Messages sent
$user->receivedChats();     // Messages received
$user->reviews();           // Reviews written
$user->receivedReviews();   // Reviews received
$user->savedProperties();   // Saved properties
$user->subscription();      // Subscriptions
$user->tenantAnswers();     // Screening answers
$user->tenantScores();      // Tenant scores
```

---

## 📚 API Reference

### User Endpoints

#### List Users
```bash
GET /api/users
Response: { success: true, data: [...], message: "..." }
```

#### Create User
```bash
POST /api/users
Body: {
  "name": "string",
  "email": "string",
  "password": "string",
  "password_confirmation": "string",
  "number": "string",
  "telegram_id": "string",
  "is_premium": boolean
}
Response: { success: true, data: {...}, message: "User created successfully" }
```

#### Get User
```bash
GET /api/users/{id}
Response: { success: true, data: {...}, message: "..." }
```

#### Update User
```bash
PUT /api/users/{id}
Body: {
  "name": "string",
  "email": "string",
  "number": "string",
  "is_premium": boolean
}
Response: { success: true, data: {...}, message: "User updated successfully" }
```

#### Delete User
```bash
DELETE /api/users/{id}
Response: { success: true, message: "User deleted successfully" }
```

#### Update Profile
```bash
PUT /api/users/{id}/profile
Body: {
  "name": "string",
  "email": "string",
  "number": "string",
  "old_password": "string",
  "new_password": "string",
  "new_password_confirmation": "string"
}
```

#### Get User Statistics
```bash
GET /api/users/{id}/statistics
Response: {
  "total_properties": number,
  "total_saved_properties": number,
  "total_reviews_written": number,
  "total_reviews_received": number,
  "total_chats_sent": number,
  "total_chats_received": number,
  "average_rating": number,
  "is_premium": boolean
}
```

#### Upgrade to Premium
```bash
POST /api/users/{id}/upgrade-premium
Response: { success: true, data: {...}, message: "User upgraded to premium successfully" }
```

#### Downgrade from Premium
```bash
POST /api/users/{id}/downgrade-premium
Response: { success: true, data: {...}, message: "User downgraded from premium successfully" }
```

#### Get Properties
```bash
GET /api/users/{id}/properties
Response: { success: true, data: {...}, message: "..." }
```

#### Get Saved Properties
```bash
GET /api/users/{id}/saved-properties
```

#### Save Property
```bash
POST /api/users/{id}/save-property
Body: { "property_id": number }
```

#### Unsave Property
```bash
POST /api/users/{id}/unsave-property
Body: { "property_id": number }
```

#### Get Reviews Written
```bash
GET /api/users/{id}/reviews-written
```

#### Get Reviews Received
```bash
GET /api/users/{id}/reviews-received
```

#### Get Chats
```bash
GET /api/users/{id}/chats
```

#### Get Subscription
```bash
GET /api/users/{id}/subscription
```

---

## ✅ Test Summary

### Unit Tests (28)
- User creation and attributes
- Password hashing
- Relationships (HasMany, BelongsToMany)
- Email and phone uniqueness
- User updates and deletion
- Premium status
- Hidden attributes
- Fillable attributes

### Feature Tests (25)
- All CRUD operations
- Validation rules and errors
- Relationship endpoints
- Premium management
- Property management
- Statistics endpoint
- Error handling
- Edge cases

### Running Tests
```bash
# All tests
php artisan test

# With verbose output
php artisan test -v

# With coverage
php artisan test --coverage

# Specific test class
php artisan test tests/Unit/UserModelTest.php
php artisan test tests/Feature/UserControllerTest.php

# Specific test method
php artisan test tests/Unit/UserModelTest.php --filter test_user_can_be_created
```

---

## 🔍 Validation Rules

### User Creation/Update
```php
'name'            => 'required|string|max:255',
'email'           => 'required|email|unique:users,email',
'password'        => 'required|string|min:8|confirmed',
'number'          => 'nullable|string|unique:users,number',
'telegram_id'     => 'nullable|string',
'is_premium'      => 'nullable|boolean'
```

### Property Saving
```php
'property_id'     => 'required|integer|exists:property,id'
```

### Profile Update
```php
'name'            => 'sometimes|string|max:255',
'email'           => 'sometimes|email|unique:users,email',
'number'          => 'sometimes|string|unique:users,number',
'old_password'    => 'sometimes|string',
'new_password'    => 'sometimes|string|min:8|confirmed'
```

---

## 🛠️ Common Tasks

### Create Multiple Test Users
```php
use App\Models\User;

$users = User::factory()->count(5)->create();
```

### Create User with Properties
```php
$user = User::factory()
    ->has(Property::factory()->count(3), 'properties')
    ->create();
```

### Query with Relationships
```php
// Eager load relationships
$user = User::with([
    'properties',
    'reviews',
    'savedProperties'
])->find($id);

// Lazy eager loading
$user = User::find($id);
$user->load('properties', 'reviews');

// Count relationships
$user->loadCount(['properties', 'reviews']);
```

### Update Related Data
```php
$user = User::find(1);

// Save a property
$user->savedProperties()->attach(5);

// Remove a saved property
$user->savedProperties()->detach(5);

// Toggle premium status
$user->update(['is_premium' => !$user->is_premium]);
```

### Delete User with Cascading
```php
$user = User::find(1);

// This will delete:
// - User record
// - All related properties
// - All related chats
// - All related reviews
// - All related subscriptions
// Note: Ensure cascade delete is set up in migrations
$user->delete();
```

---

## 📖 Documentation

For complete documentation, see:
- [USER_MODEL_IMPLEMENTATION.md](smart-rent/USER_MODEL_IMPLEMENTATION.md) - Detailed implementation guide
- [IMPLEMENTATION_SUMMARY.md](IMPLEMENTATION_SUMMARY.md) - Complete summary of changes

---

## ⚠️ Important Notes

1. **Migrations:** Ensure all required tables exist before using models
2. **Relationships:** All foreign keys should be properly defined in migrations
3. **Testing:** Tests use `RefreshDatabase` trait - do not run on production
4. **Password:** Always hash passwords using `Hash::make()` or bcrypt
5. **Validation:** All user input is validated before processing
6. **Timestamps:** Created/updated timestamps are automatic
7. **Hidden Attributes:** Password and sensitive fields are hidden from JSON

---

## 🐛 Troubleshooting

### Models Not Found
```bash
# Clear cached classmap
php artisan clear-cache
composer dumpautoload
```

### Tests Failing
```bash
# Reset test database
php artisan migrate:fresh --env=testing

# Run tests with seed
php artisan test --seed
```

### Routes Not Working
```bash
# Clear route cache
php artisan route:clear

# List all routes
php artisan route:list
```

### Database Errors
```bash
# Check database connection in .env
# Ensure database exists: mysql -u root -p -e "CREATE DATABASE smart_rent;"

# Run migrations
php artisan migrate

# Seed test data
php artisan db:seed
```

---

## 📞 Support

For issues or questions:
1. Check the documentation files
2. Review test examples
3. Check Laravel documentation: https://laravel.com/docs
4. Review test files for usage examples

---

## ✨ Summary

You now have:
- ✅ Complete User Model with all fields
- ✅ 7 Related Models (Property, Chat, Review, Subscription, etc.)
- ✅ UserController with 18 methods
- ✅ 17 API Endpoints
- ✅ 53 Comprehensive Tests
- ✅ Database Factories
- ✅ Complete Documentation

Ready for development! 🚀
