# Laravel Taxonomy

[![tests](https://github.com/wuwx/laravel-taxonomy/actions/workflows/tests.yml/badge.svg)](https://github.com/wuwx/laravel-taxonomy/actions/workflows/tests.yml)

A Drupal-inspired taxonomy package for Laravel applications. It provides:

- vocabularies via `Taxonomy`
- hierarchical terms via `Term`
- polymorphic term assignment for any Eloquent model
- tree operations powered by `kalnoy/nestedset`

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

## Quick Start

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
$post->attachTerms(['php', 'laravel'], taxonomy: 'topics');
```

Query only terms that belong to one vocabulary:

```php
$post->termsIn('topics')->orderBy('slug')->get();
```

Filter models by term:

```php
Post::query()->whereHasTerm('laravel', taxonomy: 'topics')->get();
```

## Taxonomies And Terms

Create a taxonomy:

```php
use Wuwx\LaravelTaxonomy\Models\Taxonomy;

$topics = Taxonomy::query()->create([
    'name' => 'Topics',
    'slug' => 'topics',
    'description' => 'Development topics',
    'is_hierarchical' => true,
]);
```

Create root and child terms:

```php
$backend = $topics->createTerm(['name' => 'Backend']);
$php = $topics->createTerm(['name' => 'PHP'], parent: $backend);
$laravel = $topics->createTerm(['name' => 'Laravel'], parent: $php);
```

Look up terms:

```php
$topics->findTermBySlug('php');
$topics->rootTerms();
```

If a taxonomy is not hierarchical, creating child terms will throw an `InvalidArgumentException`.

## Assigning Terms To Models

The `HasTaxonomyTerms` trait exposes these helpers:

```php
$post->attachTerm($php);
$post->attachTerm('laravel', taxonomy: 'topics');
$post->attachTerms([$php, $laravel]);

$post->syncTerms(['php', 'laravel'], taxonomy: 'topics');
$post->syncTerms(['php'], taxonomy: 'topics', detaching: false);

$post->detachTerm('laravel', taxonomy: 'topics');
$post->detachTerms(['php', 'laravel'], taxonomy: 'topics');
$post->detachAllTerms();
```

String-based term resolution requires a taxonomy slug, name, or `Taxonomy` instance:

```php
$post->attachTerm('laravel', taxonomy: 'topics');
$post->attachTerms(['php', 'laravel'], taxonomy: $topics);
```

## Checking Attached Terms

```php
$post->hasTerm($php);
$post->hasTerm('laravel', taxonomy: 'topics');

$post->hasAnyTerms(['php', 'go'], taxonomy: 'topics');
$post->hasAllTerms(['php', 'laravel'], taxonomy: 'topics');
```

For these boolean checks, unknown terms resolve to `false`.

## Querying Models By Terms

```php
Post::query()->whereHasTerm('laravel', taxonomy: 'topics')->get();

Post::query()->withAnyTerms(['php', 'laravel'], taxonomy: 'topics')->get();
Post::query()->withAllTerms(['php', 'laravel'], taxonomy: 'topics')->get();

Post::query()->withoutTerms(['deprecated'], taxonomy: 'statuses')->get();
Post::query()->withoutAnyTerms()->get();
```

## Working With Trees

`Term` uses a nested set internally, so descendant and ancestor operations are query-based rather than recursive PHP traversal.

Tree navigation helpers on `Term`:

```php
$laravel->parent;
$php->children()->get();
$backend->descendants();

$laravel->ancestors();
$laravel->siblings();

$backend->isRoot();
$laravel->isLeaf();

$backend->isAncestorOf($laravel);
$laravel->isDescendantOf($backend);

$backend->ancestors()->count();  // 0
$php->ancestors()->count();      // 1
$laravel->ancestors()->count();  // 2
```

Build structures for menus, navigation, or selects:

```php
$tree = $topics->toTree();
$flatTree = $topics->toFlatTree();
```

`toTree()` returns nested terms with populated `children` relations. `toFlatTree()` returns a preordered list with a computed `depth` attribute on each term.

## Available Models

The package ships with:

- `Wuwx\LaravelTaxonomy\Models\Taxonomy`
- `Wuwx\LaravelTaxonomy\Models\Term`

The default config file is `config/laravel-taxonomy.php`:

```php
return [
    'table_names' => [
        'taxonomies' => 'taxonomies',
        'terms' => 'taxonomy_terms',
        'morph_pivot' => 'termables',
    ],

    'models' => [
        'taxonomy' => Taxonomy::class,
        'term' => Term::class,
    ],
];
```

## Testing

```bash
vendor/bin/pint --test
vendor/bin/phpunit
```
