<?php

namespace Wuwx\LaravelTaxonomy\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Taxonomy extends Model
{
    use HasSlug;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_hierarchical',
    ];

    protected $casts = [
        'is_hierarchical' => 'bool',
    ];

    public function getTable(): string
    {
        return config('laravel-taxonomy.table_names.taxonomies', 'taxonomies');
    }

    public function terms(): HasMany
    {
        return $this->hasMany($this->termModel(), 'taxonomy_id');
    }

    public function scopeSlug(Builder $query, string $slug): Builder
    {
        return $query->where('slug', $slug);
    }

    public function createTerm(array $attributes, ?Term $parent = null): Term
    {
        if ($parent !== null && $this->is_hierarchical === false) {
            throw new \InvalidArgumentException('This taxonomy does not allow hierarchical terms.');
        }

        if ($parent !== null && (int) $parent->taxonomy_id !== (int) $this->getKey()) {
            throw new \InvalidArgumentException('The parent term does not belong to this taxonomy.');
        }

        /** @var Term $term */
        $term = $this->terms()->make($attributes);

        if ($parent !== null) {
            $term->appendToNode($parent);
        }

        $term->save();

        return $term;
    }

    public function findTermBySlug(string $slug): ?Term
    {
        /** @var Term|null $term */
        $term = $this->terms()->where('slug', $slug)->first();

        return $term;
    }

    /**
     * @return Collection<int, Term>
     */
    public function rootTerms(): Collection
    {
        /** @var Collection<int, Term> $terms */
        $terms = $this->terms()
            ->whereNull('parent_id')
            ->orderBy('weight')
            ->orderBy('name')
            ->get();

        return $terms;
    }

    /**
     * @return Collection<int, Term>
     */
    public function toTree(): Collection
    {
        /** @var Collection<int, Term> $terms */
        $terms = $this->terms()
            ->defaultOrder()
            ->get()
            ->toTree();

        return $terms;
    }

    /**
     * @return Collection<int, Term>
     */
    public function toFlatTree(): Collection
    {
        /** @var Collection<int, Term> $terms */
        $terms = $this->terms()
            ->defaultOrder()
            ->get()
            ->toFlatTree();

        $depths = [];

        $terms->each(function (Term $term) use (&$depths): void {
            $depth = $term->parent_id === null
                ? 0
                : ($depths[$term->parent_id] ?? -1) + 1;

            $term->setAttribute('depth', $depth);
            $depths[$term->getKey()] = $depth;
        });

        return $terms;
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    protected function termModel(): string
    {
        return config('laravel-taxonomy.models.term', Term::class);
    }
}
