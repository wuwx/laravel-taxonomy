<?php

namespace Wuwx\LaravelTaxonomy\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class TermsSynced
{
    use Dispatchable;

    /**
     * @param  array<int, int>  $termIds
     * @param  array<string, array<int, int>>  $changes  Keys: attached, detached, updated
     */
    public function __construct(
        public readonly Model $model,
        public readonly array $termIds,
        public readonly array $changes,
    ) {}
}
