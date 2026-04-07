<?php

namespace Wuwx\LaravelTaxonomy\Commands;

use Illuminate\Console\Command;
use Wuwx\LaravelTaxonomy\Models\Taxonomy;

class TaxonomyListCommand extends Command
{
    protected $signature = 'taxonomy:list';

    protected $description = 'List all taxonomies with their term counts';

    public function handle(): int
    {
        $taxonomies = Taxonomy::query()->withCount('terms')->get();

        if ($taxonomies->isEmpty()) {
            $this->info('No taxonomies found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Slug', 'Hierarchical', 'Terms'],
            $taxonomies->map(fn (Taxonomy $t) => [
                $t->getKey(),
                $t->name,
                $t->slug,
                $t->is_hierarchical ? 'Yes' : 'No',
                $t->terms_count,
            ])
        );

        return self::SUCCESS;
    }
}
