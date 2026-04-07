<?php

namespace Wuwx\LaravelTaxonomy\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;
use Kalnoy\Nestedset\NodeTrait;

class Term extends Model
{
    use NodeTrait;

    protected $fillable = [
        'taxonomy_id',
        'parent_id',
        'name',
        'slug',
        'description',
        'weight',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function getTable(): string
    {
        return config('laravel-taxonomy.table_names.terms', 'taxonomy_terms');
    }

    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo($this->taxonomyModel(), 'taxonomy_id');
    }

    public function scopeSlug(Builder $query, string $slug): Builder
    {
        return $query->where('slug', $slug);
    }

    public function scopeForTaxonomy(Builder $query, string|Taxonomy $taxonomy): Builder
    {
        $taxonomyId = $taxonomy instanceof Taxonomy
            ? $taxonomy->getKey()
            : $this->resolveTaxonomy($taxonomy)->getKey();

        return $query->where('taxonomy_id', $taxonomyId);
    }

    public function models(string $modelClass): MorphToMany
    {
        return $this->morphedByMany($modelClass, 'model', $this->pivotTable(), 'term_id', 'model_id');
    }

    protected static function booted(): void
    {
        static::saving(function (self $term): void {
            $term->slug = $term->slug ?: Str::slug($term->name);
        });
    }

    protected function taxonomyModel(): string
    {
        return config('laravel-taxonomy.models.taxonomy', Taxonomy::class);
    }

    protected function pivotTable(): string
    {
        return config('laravel-taxonomy.table_names.morph_pivot', 'termables');
    }

    protected function getScopeAttributes(): array
    {
        return ['taxonomy_id'];
    }

    protected function resolveTaxonomy(string $taxonomy): Taxonomy
    {
        /** @var Taxonomy|null $resolved */
        $resolved = $this->taxonomyModel()::query()
            ->where('slug', $taxonomy)
            ->orWhere('name', $taxonomy)
            ->first();

        if ($resolved === null) {
            throw new \InvalidArgumentException("Unknown taxonomy [{$taxonomy}].");
        }

        return $resolved;
    }
}
