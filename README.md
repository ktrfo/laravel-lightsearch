# Laravel LightSearch

A Laravel Scout driver using a shared DB table for inverted index. It utilizes a unigram model for tokenization, breaking text into individual words to build an efficient and searchable index. Works with your existing database (MySQL, Postgres, SQLite).

## Installation

```bash
composer require ktr/laravel-lightsearch-driver
php artisan vendor:publish --provider="Ktr\LightSearch\LightSearchServiceProvider" --tag="migrations"
php artisan migrate
```

## Usage

Add `Searchable` to models, import via:

```bash
php artisan scout:import "App\Models\Model"
```

Search:

```php
$results = Model::search('keyword')->get();
```



#### Available Options

- Customize the importance ('weight') of specific fields for each model.
- The key is your model class (e.g., ). `\App\Models\Model::class`
- The value is an array of field names with their search relevance weight (1–5).
- Default weight is **1** if not specified.

**model_field_weights**

This allows you to make some model fields more influential in search results. For example, giving the `city` field a weight of `2` makes matches on the city field twice as significant as those with the default weight.

Example Configuration in `config/scout.php`

```php
'lightsearch' => [
    'model_field_weights' => [
        \App\Models\Address::class => [
            'city' => 2,
        ],
    ],
],
```

### Enabling the Driver
Set the driver in your file: `.env`
```bash
SCOUT_DRIVER=lightsearch
```
