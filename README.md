# Laravel PostgreSQL Search

A Laravel package that provides PostgreSQL-friendly search functionality for Eloquent models using ILIKE queries with text normalization.

## Features

- Case-insensitive search using PostgreSQL's ILIKE
- Text normalization (removes punctuation for better matching)
- Support for searching across model relationships
- Automatic fallback for non-PostgreSQL databases
- Configurable search options

## Installation

Install the package via Composer:

```bash
composer require provydon/laravel-pg-search
```

The service provider will be automatically registered via Laravel's package discovery.

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=pgsearch-config
```

This will create `config/pgsearch.php` with the following options:

```php
return [
    'normalize' => true, // Enable text normalization
];
```

## Usage

The package adds a `pgSearch` macro to Eloquent's query builder:

```php
use App\Models\User;

// Search users by name
$users = User::query()
    ->pgSearch('john doe', ['name'])
    ->get();

// Search across multiple columns
$users = User::query()
    ->pgSearch('example', ['name', 'email'])
    ->get();

// Search with relationships
$posts = Post::query()
    ->pgSearch('jane', ['title', 'user.name'])
    ->get();

// Disable normalization for specific search
$users = User::query()
    ->pgSearch('exact-match', ['name'], ['normalize' => false])
    ->get();
```

## How It Works

The search performs two types of matching:

1. **Direct ILIKE match**: `CAST(column AS TEXT) ILIKE '%term%'`
2. **Normalized match** (if enabled): Removes punctuation and searches again

This allows matching "Jane Doe" with "Jane-Doe" or phone numbers like "123-456-7890" with "1234567890".

## Database Requirements

- PostgreSQL database
- For non-PostgreSQL databases, the macro returns the query unchanged (no-op)

## Testing

The package includes PostgreSQL-specific tests. Make sure you have a PostgreSQL database set up:

```bash
# Create test database
createdb pg-search

# Run tests
composer test
```

Test configuration uses these environment variables:
- `DB_CONNECTION=pgsql`
- `DB_HOST=127.0.0.1`
- `DB_PORT=5432`
- `DB_DATABASE=pg-search`
- `DB_USERNAME=`
- `DB_PASSWORD=`

## Code Formatting

The project uses Laravel Pint for code formatting:

```bash
# Format code
composer format

# Check formatting (without making changes)
composer format-check
```

## License

MIT License
