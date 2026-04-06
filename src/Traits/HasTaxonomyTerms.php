<?php

namespace Wuwx\LaravelTaxonomy\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use InvalidArgumentException;
use Wuwx\LaravelTaxonomy\Models\Taxonomy;
use Wuwx\LaravelTaxonomy\Models\Term;

trait HasTaxonomyTerms
{
    public function terms(): MorphToMany
    {
        return $this->morphToMany(
            $this->termModel(),
            'model',
            $this->pivotTable(),
            'model_id',
            'term_id'
        )->withTimestamps();
    }

    public function termsIn(string|Taxonomy $taxonomy): MorphToMany
    {
        return $this->terms()->where('taxonomy_id', $this->resolveTaxonomy($taxonomy)->getKey());
    }

    public function attachTerm(Term|int|string $term, string|Taxonomy|null $taxonomy = null): static
    {
        $this->terms()->syncWithoutDetaching([$this->resolveTermId($term, $taxonomy)]);

        return $this;
    }

    public function attachTerms(iterable $terms, string|Taxonomy|null $taxonomy = null): static
    {
        $ids = array_map(
            fn (Term|int|string $term): int => $this->resolveTermId($term, $taxonomy),
            is_array($terms) ? $terms : iterator_to_array($terms)
        );

        $this->terms()->syncWithoutDetaching($ids);

        return $this;
    }

    public function syncTerms(iterable $terms, string|Taxonomy|null $taxonomy = null, bool $detaching = true): static
    {
        $ids = array_map(
            fn (Term|int|string $term): int => $this->resolveTermId($term, $taxonomy),
            is_array($terms) ? $terms : iterator_to_array($terms)
        );

        $this->terms()->sync($ids, $detaching);

        return $this;
    }

    public function detachTerm(Term|int|string $term, string|Taxonomy|null $taxonomy = null): static
    {
        $this->terms()->detach($this->resolveTermId($term, $taxonomy));

        return $this;
    }

    public function scopeWhereHasTerm(Builder $query, Term|int|string $term, string|Taxonomy|null $taxonomy = null): Builder
    {
        $termId = $this->resolveTermId($term, $taxonomy);

        return $query->whereHas('terms', fn (Builder $relationQuery): Builder => $relationQuery->whereKey($termId));
    }

    protected function termModel(): string
    {
        return config('laravel-taxonomy.models.term', Term::class);
    }

    protected function taxonomyModel(): string
    {
        return config('laravel-taxonomy.models.taxonomy', Taxonomy::class);
    }

    protected function pivotTable(): string
    {
        return config('laravel-taxonomy.table_names.morph_pivot', 'termables');
    }

    protected function resolveTaxonomy(string|Taxonomy $taxonomy): Taxonomy
    {
        if ($taxonomy instanceof Taxonomy) {
            return $taxonomy;
        }

        /** @var Taxonomy|null $resolved */
        $resolved = $this->taxonomyModel()::query()
            ->where('slug', $taxonomy)
            ->orWhere('name', $taxonomy)
            ->first();

        if ($resolved === null) {
            throw new InvalidArgumentException("Unknown taxonomy [{$taxonomy}].");
        }

        return $resolved;
    }

    protected function resolveTermId(Term|int|string $term, string|Taxonomy|null $taxonomy = null): int
    {
        if ($term instanceof Term) {
            return (int) $term->getKey();
        }

        if (is_int($term)) {
            return $term;
        }

        if ($taxonomy === null) {
            throw new InvalidArgumentException('A taxonomy slug, name, or instance is required when resolving a term by string.');
        }

        /** @var Term|null $resolved */
        $resolved = $this->termModel()::query()
            ->where('slug', $term)
            ->where('taxonomy_id', $this->resolveTaxonomy($taxonomy)->getKey())
            ->first();

        if ($resolved === null) {
            throw new InvalidArgumentException("Unknown term [{$term}] in the given taxonomy.");
        }

        return (int) $resolved->getKey();
    }
}
