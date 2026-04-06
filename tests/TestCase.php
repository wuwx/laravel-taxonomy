<?php

namespace Wuwx\LaravelTaxonomy\Tests;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Wuwx\LaravelTaxonomy\LaravelTaxonomyServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelTaxonomyServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->runPackageMigration('create_taxonomies_table');
        $this->runPackageMigration('create_taxonomy_terms_table');
        $this->runPackageMigration('create_termables_table');

        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });
    }

    protected function runPackageMigration(string $migration): void
    {
        /** @var Migration $instance */
        $instance = require dirname(__DIR__) . "/database/migrations/{$migration}.php.stub";

        $instance->up();
    }
}
