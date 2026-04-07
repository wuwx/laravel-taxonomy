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
        $this->terms()->syncWithoutDetaching($this->resolveTermIds($terms, $taxonomy));

        return $this;
    }

    public function syncTerms(iterable $terms, string|Taxonomy|null $taxonomy = null, bool $detaching = true): static
    {
        $ids = $this->resolveTermIds($terms, $taxonomy);

        $this->terms()->sync($ids, $detaching);

        return $this;
    }

    public function detachTerm(Term|int|string $term, string|Taxonomy|null $taxonomy = null): static
    {
        $this->terms()->detach($this->resolveTermId($term, $taxonomy));

        return $this;
    }

    public function detachTerms(iterable $terms, string|Taxonomy|null $taxonomy = null): static
    {
        $ids = $this->resolveTermIds($terms, $taxonomy);

        if ($ids !== []) {
            $this->terms()->detach($ids);
        }

        return $this;
    }

    public function detachAllTerms(): static
    {
        $this->terms()->detach();

        return $this;
    }

    public function hasTerm(Term|int|string $term, string|Taxonomy|null $taxonomy = null): bool
    {
        $termId = $this->tryResolveTermId($term, $taxonomy);

        if ($termId === null) {
            return false;
        }

        return $this->terms()
            ->whereKey($termId)
            ->exists();
    }

    public function hasAnyTerms(iterable $terms, string|Taxonomy|null $taxonomy = null): bool
    {
        $ids = $this->resolveResolvableTermIds($terms, $taxonomy);

        if ($ids === []) {
            return false;
        }

        return $this->terms()
            ->whereIn($this->terms()->getRelated()->qualifyColumn($this->terms()->getRelatedKeyName()), $ids)
            ->exists();
    }

    public function hasAllTerms(iterable $terms, string|Taxonomy|null $taxonomy = null): bool
    {
        $ids = $this->resolveResolvableTermIds($terms, $taxonomy);

        if ($ids === []) {
            return false;
        }

        return $this->terms()
            ->whereIn($this->terms()->getRelated()->qualifyColumn($this->terms()->getRelatedKeyName()), $ids)
            ->count() === count($ids);
    }

    public function scopeWhereHasTerm(Builder $query, Term|int|string $term, string|Taxonomy|null $taxonomy = null): Builder
    {
        $termId = $this->resolveTermId($term, $taxonomy);

        return $query->whereHas('terms', fn (Builder $relationQuery): Builder => $relationQuery->whereKey($termId));
    }

    public function scopeWithAnyTerms(Builder $query, iterable $terms, string|Taxonomy|null $taxonomy = null): Builder
    {
        $ids = $this->resolveTermIds($terms, $taxonomy);

        if ($ids === []) {
            return $query;
        }

        return $query->whereHas(
            'terms',
            fn (Builder $relationQuery): Builder => $relationQuery->whereIn(
                $relationQuery->getModel()->qualifyColumn($relationQuery->getModel()->getKeyName()),
                $ids
            )
        );
    }

    public function scopeWithAllTerms(Builder $query, iterable $terms, string|Taxonomy|null $taxonomy = null): Builder
    {
        $ids = $this->resolveTermIds($terms, $taxonomy);

        if ($ids === []) {
            return $query;
        }

        foreach ($ids as $id) {
            $query->whereHas('terms', fn (Builder $relationQuery): Builder => $relationQuery->whereKey($id));
        }

        return $query;
    }

    public function scopeWithoutTerms(Builder $query, iterable $terms, string|Taxonomy|null $taxonomy = null): Builder
    {
        $ids = $this->resolveTermIds($terms, $taxonomy);

        if ($ids === []) {
            return $query;
        }

        return $query->whereDoesntHave(
            'terms',
            fn (Builder $relationQuery): Builder => $relationQuery->whereIn(
                $relationQuery->getModel()->qualifyColumn($relationQuery->getModel()->getKeyName()),
                $ids
            )
        );
    }

    public function scopeWithoutAnyTerms(Builder $query): Builder
    {
        return $query->doesntHave('terms');
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

    protected function tryResolveTermId(Term|int|string $term, string|Taxonomy|null $taxonomy = null): ?int
    {
        try {
            return $this->resolveTermId($term, $taxonomy);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * @return array<int, int>
     */
    protected function resolveTermIds(iterable $terms, string|Taxonomy|null $taxonomy = null): array
    {
        $resolvedTerms = is_array($terms) ? $terms : iterator_to_array($terms, false);

        return array_values(array_unique(array_map(
            fn (Term|int|string $term): int => $this->resolveTermId($term, $taxonomy),
            $resolvedTerms
        )));
    }

    /**
     * @return array<int, int>
     */
    protected function resolveResolvableTermIds(iterable $terms, string|Taxonomy|null $taxonomy = null): array
    {
        $resolvedTerms = is_array($terms) ? $terms : iterator_to_array($terms, false);

        return array_values(array_unique(array_filter(array_map(
            fn (Term|int|string $term): ?int => $this->tryResolveTermId($term, $taxonomy),
            $resolvedTerms
        ), fn (?int $id): bool => $id !== null)));
    }
}
