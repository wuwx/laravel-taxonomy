<?php

namespace Workbench\App;

use Illuminate\Database\Eloquent\Model;
use Wuwx\LaravelTaxonomy\Traits\HasTaxonomyTerms;

class Post extends Model
{
    use HasTaxonomyTerms;

    protected $guarded = [];
}
