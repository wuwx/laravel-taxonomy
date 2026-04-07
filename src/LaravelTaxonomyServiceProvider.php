<?php

namespace Wuwx\LaravelTaxonomy;

use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Wuwx\LaravelTaxonomy\Commands\TaxonomyCreateTermCommand;
use Wuwx\LaravelTaxonomy\Commands\TaxonomyListCommand;
use Wuwx\LaravelTaxonomy\Commands\TaxonomyTreeCommand;

class LaravelTaxonomyServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-taxonomy')
            ->hasConfigFile()
            ->hasMigrations([
                'create_taxonomies_table',
                'create_taxonomy_terms_table',
                'create_termables_table',
            ])
            ->hasCommands([
                TaxonomyListCommand::class,
                TaxonomyTreeCommand::class,
                TaxonomyCreateTermCommand::class,
            ])
            ->hasInstallCommand(function (InstallCommand $command): void {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations();
            });
    }
}
