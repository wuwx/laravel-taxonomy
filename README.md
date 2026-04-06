# Laravel Taxonomy

[![tests](https://github.com/wuwx/laravel-taxonomy/actions/workflows/tests.yml/badge.svg)](https://github.com/wuwx/laravel-taxonomy/actions/workflows/tests.yml)

A Drupal-inspired taxonomy package for Laravel applications. It provides:

- vocabularies (`Taxonomy`)
- hierarchical terms (`Term`)
- polymorphic term assignment for any Eloquent model
- a package skeleton aligned with `spatie/laravel-package-tools`

## Installation

```bash
composer require wuwx/laravel-taxonomy
php artisan laravel-taxonomy:install
```

If you prefer manual setup:

```bash
php artisan vendor:publish --tag=laravel-taxonomy-config
php artisan vendor:publish --tag=laravel-taxonomy-migrations
php artisan migrate
```

## Usage

Attach the `HasTaxonomyTerms` trait to a model:

```php
use Wuwx\LaravelTaxonomy\Traits\HasTaxonomyTerms;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasTaxonomyTerms;
}
```

Create a vocabulary and some terms:

```php
use Wuwx\LaravelTaxonomy\Models\Taxonomy;

$topics = Taxonomy::query()->create([
    'name' => 'Topics',
    'slug' => 'topics',
]);

$php = $topics->createTerm(['name' => 'PHP']);
$laravel = $topics->createTerm(['name' => 'Laravel'], parent: $php);
```

Assign terms to a model:

```php
$post->attachTerm($php);
$post->attachTerm('laravel', taxonomy: 'topics');
```

Query only terms that belong to one vocabulary:

```php
$post->termsIn('topics')->get();
```

Filter models by term:

```php
Post::query()->whereHasTerm('laravel', taxonomy: 'topics')->get();
```
