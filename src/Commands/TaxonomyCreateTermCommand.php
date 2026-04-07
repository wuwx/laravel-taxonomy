<?php

namespace Wuwx\LaravelTaxonomy\Commands;

use Illuminate\Console\Command;
use Wuwx\LaravelTaxonomy\Models\Taxonomy;

class TaxonomyCreateTermCommand extends Command
{
    protected $signature = 'taxonomy:create-term
                            {taxonomy : The taxonomy slug}
                            {name : The term name}
                            {--parent= : Parent term slug (optional)}';

    protected $description = 'Create a new term in a taxonomy';

    public function handle(): int
    {
        $taxonomy = Taxonomy::query()->where('slug', $this->argument('taxonomy'))->first();

        if ($taxonomy === null) {
            $this->error("Taxonomy [{$this->argument('taxonomy')}] not found.");

            return self::FAILURE;
        }

        $parent = null;

        if ($parentSlug = $this->option('parent')) {
            $parent = $taxonomy->findTermBySlug($parentSlug);

            if ($parent === null) {
                $this->error("Parent term [{$parentSlug}] not found in taxonomy [{$taxonomy->slug}].");

                return self::FAILURE;
            }
        }

        $term = $taxonomy->createTerm(['name' => $this->argument('name')], $parent);

        $this->info("Term [{$term->name}] created with slug [{$term->slug}] in taxonomy [{$taxonomy->slug}].");

        return self::SUCCESS;
    }
}
