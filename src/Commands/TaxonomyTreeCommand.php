<?php

namespace Wuwx\LaravelTaxonomy\Commands;

use Illuminate\Console\Command;
use Wuwx\LaravelTaxonomy\Models\Taxonomy;
use Wuwx\LaravelTaxonomy\Models\Term;

class TaxonomyTreeCommand extends Command
{
    protected $signature = 'taxonomy:tree {slug : The taxonomy slug}';

    protected $description = 'Display a tree view of terms for a taxonomy';

    public function handle(): int
    {
        $taxonomy = Taxonomy::query()->where('slug', $this->argument('slug'))->first();

        if ($taxonomy === null) {
            $this->error("Taxonomy [{$this->argument('slug')}] not found.");

            return self::FAILURE;
        }

        $tree = $taxonomy->toFlatTree();

        if ($tree->isEmpty()) {
            $this->info("Taxonomy [{$taxonomy->name}] has no terms.");

            return self::SUCCESS;
        }

        $this->info("Taxonomy: {$taxonomy->name} ({$taxonomy->slug})");
        $this->newLine();

        $tree->each(function (Term $term) {
            $indent = str_repeat('  ', $term->depth ?? 0);
            $this->line("{$indent}├── {$term->name} [{$term->slug}]");
        });

        return self::SUCCESS;
    }
}
