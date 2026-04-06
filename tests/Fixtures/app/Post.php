<?php

namespace Workbench\App;

use Wuwx\LaravelTaxonomy\Traits\HasTaxonomyTerms;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasTaxonomyTerms;

    protected $guarded = [];
}
