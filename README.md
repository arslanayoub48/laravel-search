# SearchableScope Package

A reusable search scope trait for Laravel models with nested relation and priority support.

## 📦 Package Information

- **Package Name:** `arslanayoub/searchable-scope`
- **Description:** Reusable search scope trait for Laravel models with nested relation and priority support
- **License:** MIT
- **Author:** Arslan Ayoub

## 🚀 Installation

### 1. Local Package Setup

If you're using this as a local package in your Laravel project:

#### Step 1: Add Repository to Main composer.json

Add the following to your main project's `composer.json`:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "packages/ArslanAyoub/SearchableScope"
    }
  ],
  "require": {
    "arslanayoub/searchable-scope": "dev-main"
  }
}
```

#### Step 2: Install Package

```bash
composer update arslanayoub/searchable-scope
```

#### Step 3: Publish Configuration

```bash
php artisan vendor:publish --tag=config
```

This will create a `config/searchable-scope.php` file in your Laravel project.

### 2. Package Structure

```
packages/ArslanAyoub/SearchableScope/
├── composer.json
├── README.md
├── config/
│   └── searchable-scope.php
└── src/
    ├── SearchableScopeServiceProvider.php
    └── Traits/
        └── Searchable.php
```

## ⚙️ Configuration

The package comes with a configuration file at `config/searchable-scope.php`:

```php
<?php

return [
  'default_operator' => 'LIKE',        // Default search operator
  'case_sensitive' => false,           // Case sensitivity
  'min_term_length' => 2,             // Minimum search term length
  'default_columns' => [],            // Global default columns
  'default_relations' => [],          // Global default relations
];
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `default_operator` | string | `'LIKE'` | SQL operator used for searching |
| `case_sensitive` | boolean | `false` | Whether search should be case sensitive |
| `min_term_length` | integer | `2` | Minimum length required for search terms |
| `default_columns` | array | `[]` | Default columns to search when none specified |
| `default_relations` | array | `[]` | Default relations to search when none specified |

## 📖 Usage

### 1. Basic Model Setup

Add the `Searchable` trait to your model:

```php
<?php

namespace App\Models;

use ArslanAyoub\SearchableScope\Traits\Searchable;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
  use Searchable;

  // Define searchable columns and relations (optional)
  protected $searchable = [
    'columns' => ['name', 'slug'],
    'relations' => []
  ];
}
```

### 2. Simple Array Configuration

```php
protected $searchable = [
  'columns' => ['name', 'slug', 'description'],
  'relations' => [
    'category' => ['name'],
    'tags' => ['name']
  ]
];
```

### 3. Priority-Based Configuration

```php
protected $searchable = [
  'columns' => [
    'name' => 1,        // Highest priority
    'slug' => 2,        // Medium priority  
    'description' => 3  // Lower priority
  ],
  'relations' => [
    'category' => ['name' => 1],
    'tags' => ['name' => 2]
  ]
];
```

## 🔍 Search Methods

### Basic Search

```php
// Search in model-defined columns
$results = Product::search('laptop')->get();

// Search in specific columns
$results = Product::search('laptop', ['name', 'description'])->get();

// Search with relations
$results = Product::search('electronics', ['name'], [
  'category' => ['name'],
  'brand' => ['name']
])->get();
```

### Priority-Based Search

The package supports three priority modes:

#### 1. Parameter Priority (`params` - default)
Uses columns and relations passed as parameters:

```php
Product::search('laptop', ['name', 'sku'], [], 'params')->get();
```

#### 2. Model Priority (`model`)
Uses columns and relations defined in the model's `$searchable` property:

```php
Product::search('laptop', [], [], 'model')->get();
```

#### 3. Config Priority (`config`)
Uses columns and relations defined in the global configuration:

```php
Product::search('laptop', [], [], 'config')->get();
```

## 🎯 Controller Implementation

### Basic Controller Example

```php
<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
  public function index(Request $request)
  {
    $query = Category::with('subCategories');

    // Add search functionality
    if ($request->has('search') && !empty($request->search)) {
      $query->search($request->search, ['name', 'slug']);
    }

    $categories = $query->get();

    return response()->json($categories);
  }
}
```

### Advanced Controller Example

```php
public function index(Request $request)
{
  $query = Product::with(['category', 'variants']);

  // Search with multiple parameters
  if ($request->filled('search')) {
    $columns = ['name', 'description', 'sku'];
    $relations = [
      'category' => ['name'],
      'variants' => ['name', 'sku']
    ];
    
    $query->search($request->search, $columns, $relations);
  }

  // Add pagination
  $products = $query->paginate(15);

  return ProductResource::collection($products);
}
```

## 🌐 API Usage

### REST API Examples

```bash
# Basic search
GET /api/categories?search=electronics

# Search with pagination  
GET /api/products?search=laptop&page=1&per_page=10

# Multiple filters with search
GET /api/products?search=mobile&category=electronics&status=active
```

### Response Example

```json
{
  "data": [
    {
      "id": "uuid-here",
      "name": "Electronics Category",
      "slug": "electronics",
      "is_active": 1,
      "created_at": "2025-01-01T00:00:00.000000Z"
    }
  ]
}
```

## 🔧 Advanced Features

### 1. Relationship Search

```php
// Search in nested relationships
Product::search('samsung', [], [
  'category.parent' => ['name'],
  'variants.specifications' => ['value']
])->get();
```

### 2. Custom Search Logic

```php
// Override search behavior in your model
public function scopeCustomSearch($query, $term)
{
  return $this->scopeSearch($query, $term, ['name'], [], 'model')
    ->where('is_active', true)
    ->orderBy('name');
}
```

### 3. Search with Additional Constraints

```php
Category::search('electronics')
  ->where('is_active', true)
  ->orderBy('name')
  ->limit(10)
  ->get();
```

## 📝 Real-World Examples

### E-commerce Product Search

```php
class Product extends Model
{
  use Searchable;

  protected $searchable = [
    'columns' => [
      'name' => 1,
      'sku' => 2, 
      'description' => 3
    ],
    'relations' => [
      'category' => ['name' => 1],
      'brand' => ['name' => 2],
      'variants' => ['name' => 3, 'sku' => 4]
    ]
  ];
}

// Usage
$products = Product::search($request->q)
  ->where('is_active', true)
  ->with(['category', 'brand', 'variants'])
  ->paginate(12);
```

### Category Management

```php
class Category extends Model  
{
  use Searchable;

  protected $searchable = [
    'columns' => ['name', 'slug'],
    'relations' => [
      'subCategories' => ['name', 'slug']
    ]
  ];
}

// Controller
public function getCategories(Request $request)
{
  $query = Category::with('subCategories');

  if ($request->filled('search')) {
    $query->search($request->search, ['name', 'slug']);
  }

  return CategoryResource::collection($query->get());
}
```

## 🐛 Troubleshooting

### Common Issues

1. **Search not working**
   - Ensure the `Searchable` trait is imported and used
   - Check that columns exist in the database
   - Verify minimum term length in config

2. **Relationship search failing**
   - Ensure relationships are properly defined in the model
   - Check relationship names match exactly
   - Verify related table columns exist

3. **Performance issues**
   - Add database indexes on searchable columns
   - Limit search results with pagination
   - Consider using full-text search for large datasets

### Debug Mode

Enable query logging to debug search queries:

```php
\DB::enableQueryLog();
Product::search('test')->get();
$queries = \DB::getQueryLog();
dd($queries);
```

## 📄 License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 📞 Support

If you discover any security-related issues, please email the author instead of using the issue tracker.

---

**Happy Searching! 🔍** 