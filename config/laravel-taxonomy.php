<?php

declare(strict_types=1);

use Wuwx\LaravelTaxonomy\Models\Taxonomy;
use Wuwx\LaravelTaxonomy\Models\Term;

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
