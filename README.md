# 🔍 Laravel PostgreSQL Search

**Smart PostgreSQL search for Laravel with text normalization and relationship support.**

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/Laravel-10%2B|11%2B-red)](https://laravel.com)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-Compatible-336791)](https://postgresql.org)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)
[![Tests](https://img.shields.io/badge/Tests-Passing-brightgreen)](tests)

## ✨ Why This Package?

- 🎯 **Smart matching**: Find "Jane Doe" even when stored as "Jane-Doe"
- 📱 **Phone numbers**: Search "1234567890" matches "(123) 456-7890"
- 🔗 **Relationships**: Search across related models seamlessly
- ⚡ **PostgreSQL optimized**: Uses ILIKE and REGEXP_REPLACE for performance
- 🛡️ **Safe fallback**: Works on non-PostgreSQL databases (no-op)

## 🚀 Quick Start

### Install
```bash
composer require provydon/laravel-pg-search
```

### Use Immediately
```php
// Search users
User::query()->pgSearch('john doe', ['name', 'email'])->get();

// Search with relationships  
Post::query()->pgSearch('jane', ['title', 'user.name'])->get();

// Phone number search
User::query()->pgSearch('1234567890', ['phone'])->get();
```

That's it! No configuration needed.

## 📖 Usage Examples

### Basic Search
```php
// Single column
User::query()->pgSearch('john', ['name'])->get();

// Multiple columns
User::query()->pgSearch('example', ['name', 'email'])->get();
```

### Relationship Search
```php
// Search posts by author name
Post::query()->pgSearch('jane doe', ['title', 'user.name'])->get();

// Search orders by customer info
Order::query()->pgSearch('smith', ['number', 'customer.name', 'customer.email'])->get();
```

### Advanced Options
```php
// Disable text normalization
User::query()->pgSearch('exact-match', ['name'], ['normalize' => false])->get();

// Chain with other query methods
User::query()
    ->where('active', true)
    ->pgSearch('john', ['name'])
    ->orderBy('created_at')
    ->paginate(15);
```

## 🔧 Configuration (Optional)

Publish config to customize behavior:

```bash
php artisan vendor:publish --tag=pgsearch-config
```

```php
// config/pgsearch.php
return [
    'normalize' => true, // Enable smart text matching
];
```

## 🧠 How It Works

The package performs intelligent PostgreSQL searches:

| Search Type | SQL Example | Matches |
|-------------|-------------|---------|
| **Direct** | `name ILIKE '%john doe%'` | "John Doe", "JOHN DOE" |
| **Normalized** | `REGEXP_REPLACE(phone, '[^a-zA-Z0-9]', '', 'g') ILIKE '%1234567890%'` | "(123) 456-7890", "123-456-7890" |

### Real-World Examples

```php
// These all find the same user:
User::query()->pgSearch('Jane Doe', ['name'])->get();      // Direct match
User::query()->pgSearch('jane doe', ['name'])->get();      // Case insensitive  
User::query()->pgSearch('janedoe', ['name'])->get();       // Normalized match

// Phone number variations:
User::query()->pgSearch('1234567890', ['phone'])->get();   // Finds all these:
// "(123) 456-7890", "123-456-7890", "123.456.7890", "123 456 7890"
```

## 📋 Requirements

- **Laravel**: 10.0+ or 11.0+
- **PHP**: 8.1+
- **Database**: PostgreSQL (graceful fallback for others)

## 🧪 Testing

```bash
# Create test database
createdb pg-search

# Run tests
composer test
```

## 💖 Support

If this package helped you, consider supporting its development:

<a href="https://www.buymeacoffee.com/provydon" target="_blank">
  <img src="https://cdn.buymeacoffee.com/buttons/v2/default-yellow.png" alt="Buy Me A Coffee" height="50">
</a>

## 📝 License

MIT License - see [LICENSE](LICENSE) for details.

---

<p align="center">
<strong>Made with ❤️ for the Laravel community</strong>
</p>