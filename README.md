# Laravel Taxonomy

[![tests](https://github.com/wuwx/laravel-taxonomy/actions/workflows/tests.yml/badge.svg)](https://github.com/wuwx/laravel-taxonomy/actions/workflows/tests.yml)

A Drupal-inspired taxonomy package for Laravel applications. It provides:

- **Vocabularies** via `Taxonomy` — group terms into categories, tags, locations, etc.
- **Hierarchical terms** via `Term` — powered by `kalnoy/nestedset` for efficient tree queries
- **Polymorphic assignment** — attach terms to any Eloquent model
- **Rich query scopes** — `withAnyTerms`, `withAllTerms`, `withoutTerms`, `byTaxonomies`
- **Translations** — `name` and `description` are translatable via `spatie/laravel-translatable`
- **Slug auto-generation** — powered by `spatie/laravel-sluggable` with scoped uniqueness
- **Events** — `TermAttached`, `TermDetached`, `TermsSynced` dispatched automatically
- **Pivot data** — `order` and `metadata` on the pivot table
- **Artisan commands** — `taxonomy:list`, `taxonomy:tree`, `taxonomy:create-term`

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

$topics = Taxonomy::query()->create(['name' => 'Topics']);

$php = $topics->createTerm(['name' => 'PHP']);
$laravel = $topics->createTerm(['name' => 'Laravel'], parent: $php);
```

Assign and query:

```php
$post->attachTerm($php);
$post->attachTerms(['php', 'laravel'], taxonomy: 'topics');

Post::withAnyTerms(['php', 'laravel'], taxonomy: 'topics')->get();
```

## Taxonomies And Terms

Create a taxonomy (slug is auto-generated if omitted):

```php
$topics = Taxonomy::query()->create([
    'name' => 'Topics',
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

Slugs are auto-generated and unique — within the same taxonomy, duplicate names produce `php`, `php-1`, `php-2`, etc. Taxonomy slugs are globally unique.

If a taxonomy is not hierarchical, creating child terms will throw an `InvalidArgumentException`.

## Assigning Terms To Models

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

String-based term resolution requires a taxonomy:

```php
$post->attachTerm('laravel', taxonomy: 'topics');
$post->attachTerms(['php', 'laravel'], taxonomy: $topics);
```

### Pivot Data

Attach terms with extra pivot data (`order` and `metadata` columns):

```php
$post->attachTerm($php, pivot: ['order' => 1, 'metadata' => json_encode(['primary' => true])]);
$post->attachTerms([$php, $laravel], pivot: ['order' => 5]);

$post->terms->first()->pivot->order;    // 1
$post->terms->first()->pivot->metadata; // '{"primary":true}'
```

## Checking Attached Terms

```php
$post->hasTerm($php);
$post->hasTerm('laravel', taxonomy: 'topics');

$post->hasAnyTerms(['php', 'go'], taxonomy: 'topics');
$post->hasAllTerms(['php', 'laravel'], taxonomy: 'topics');
```

Unknown terms resolve to `false`.

## Querying Models By Terms

```php
Post::whereHasTerm('laravel', taxonomy: 'topics')->get();

Post::withAnyTerms(['php', 'laravel'], taxonomy: 'topics')->get();
Post::withAllTerms(['php', 'laravel'], taxonomy: 'topics')->get();

Post::withoutTerms(['deprecated'], taxonomy: 'statuses')->get();
Post::withoutAnyTerms()->get();
```

### Multi-Taxonomy Filtering

Filter by multiple vocabularies at once — AND between vocabularies, OR within:

```php
Post::byTaxonomies([
    'topics' => ['php', 'laravel'],   // has php OR laravel
    'cities' => ['shanghai'],          // AND has shanghai
])->get();
```

## Translations

`name` and `description` are translatable via `spatie/laravel-translatable`:

```php
$topics = Taxonomy::query()->create([
    'name' => ['en' => 'Topics', 'zh' => '主题'],
    'description' => ['en' => 'Blog topics', 'zh' => '博客主题'],
]);

$php = $topics->createTerm([
    'name' => ['en' => 'PHP', 'zh' => 'PHP 编程'],
]);

app()->setLocale('zh');
$topics->name; // '主题'
$php->name;    // 'PHP 编程'
```

Single-language usage works as before — just pass a plain string:

```php
$topics = Taxonomy::query()->create(['name' => 'Topics']);
```

## Working With Trees

`Term` uses `kalnoy/nestedset` internally, so all tree operations are single-query, not recursive.

```php
$laravel->parent;
$php->children()->get();
$backend->descendants()->get();

$laravel->ancestors()->get();
$laravel->siblings()->get();

$backend->isRoot();
$laravel->isLeaf();

$backend->isAncestorOf($laravel);
$laravel->isDescendantOf($backend);

$backend->ancestors()->count();  // 0
$php->ancestors()->count();      // 1
$laravel->ancestors()->count();  // 2
```

Build tree structures for menus, navigation, or selects:

```php
$tree = $topics->toTree();         // nested with children relations
$flatTree = $topics->toFlatTree(); // flat list with computed depth attribute
```

## Events

All attach/detach/sync operations dispatch events:

| Operation | Event |
|-----------|-------|
| `attachTerm` / `attachTerms` | `TermAttached` |
| `detachTerm` / `detachTerms` / `detachAllTerms` | `TermDetached` |
| `syncTerms` | `TermsSynced` |

```php
use Wuwx\LaravelTaxonomy\Events\TermAttached;

Event::listen(TermAttached::class, function (TermAttached $event) {
    // $event->model   — the Eloquent model
    // $event->termIds — array of attached term IDs
});
```

`TermsSynced` also includes `$event->changes` with `attached`, `detached`, and `updated` arrays.

## Artisan Commands

```bash
php artisan taxonomy:list                              # list all taxonomies with term counts
php artisan taxonomy:tree topics                       # tree view of a taxonomy's terms
php artisan taxonomy:create-term topics "PHP"           # create a term
php artisan taxonomy:create-term topics "Laravel" --parent=php  # create a child term
```

## Configuration

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

Both `Taxonomy` and `Term` support Route Model Binding via `slug` by default.

## Testing

```bash
vendor/bin/pint --test
vendor/bin/phpunit
```
