<?php

namespace Wuwx\LaravelTaxonomy\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class TermDetached
{
    use Dispatchable;

    /**
     * @param  array<int, int>  $termIds
     */
    public function __construct(
        public readonly Model $model,
        public readonly array $termIds,
    ) {}
}
