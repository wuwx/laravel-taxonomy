<?php

namespace Wuwx\LaravelTaxonomy\Tests\Feature;

use InvalidArgumentException;
use Wuwx\LaravelTaxonomy\Models\Taxonomy;
use Wuwx\LaravelTaxonomy\Models\Term;
use Wuwx\LaravelTaxonomy\Tests\TestCase;
use Workbench\App\Post;

class TaxonomyTest extends TestCase
{
    public function test_it_runs_the_package_migrations_and_generates_slugs(): void
    {
        $taxonomy = Taxonomy::query()->create([
            'name' => 'Developer Topics',
        ]);

        $term = $taxonomy->createTerm([
            'name' => 'PHP',
        ]);

        $this->assertSame('developer-topics', $taxonomy->slug);
        $this->assertSame('php', $term->slug);
        $this->assertSame($taxonomy->id, Taxonomy::query()->slug('developer-topics')->value('id'));
        $this->assertSame($term->id, Term::query()->slug('php')->value('id'));
    }

    public function test_it_finds_root_terms_and_descendants(): void
    {
        $taxonomy = Taxonomy::query()->create([
            'name' => 'Topics',
            'slug' => 'topics',
        ]);

        $php = $taxonomy->createTerm([
            'name' => 'PHP',
            'weight' => 10,
        ]);
        $laravel = $taxonomy->createTerm(['name' => 'Laravel'], $php);
        $eloquent = $taxonomy->createTerm(['name' => 'Eloquent'], $laravel);
        $symfony = $taxonomy->createTerm(['name' => 'Symfony']);

        $this->assertTrue($php->taxonomy->is($taxonomy));
        $this->assertTrue($laravel->parent->is($php));
        $this->assertSame($php->id, $taxonomy->findTermBySlug('php')?->id);
        $this->assertSame(['symfony', 'php'], $taxonomy->rootTerms()->pluck('slug')->all());
        $this->assertSame(['laravel', 'eloquent'], $php->descendants()->pluck('slug')->all());
        $this->assertSame(['eloquent', 'laravel', 'php', 'symfony'], Term::query()->forTaxonomy('topics')->orderBy('slug')->pluck('slug')->all());
        $this->assertSame(['eloquent', 'laravel', 'php', 'symfony'], Term::query()->forTaxonomy($taxonomy)->orderBy('slug')->pluck('slug')->all());
    }

    public function test_it_filters_terms_and_models_through_the_public_api(): void
    {
        $topics = Taxonomy::query()->create([
            'name' => 'Topics',
            'slug' => 'topics',
        ]);
        $skills = Taxonomy::query()->create([
            'name' => 'Skills',
            'slug' => 'skills',
        ]);

        $php = $topics->createTerm(['name' => 'PHP']);
        $laravel = $topics->createTerm(['name' => 'Laravel'], $php);
        $docker = $skills->createTerm(['name' => 'Docker']);

        $post = Post::query()->create(['title' => 'Typed properties']);
        $post->attachTerm($php->id);
        $post->attachTerms([$laravel], taxonomy: $topics);
        $post->attachTerm('docker', taxonomy: 'skills');

        $this->assertSame(['laravel', 'php'], $post->termsIn($topics)->orderBy('slug')->pluck('slug')->all());
        $this->assertSame(['docker'], $post->termsIn('skills')->pluck('slug')->all());
        $this->assertSame([$post->id], $php->models(Post::class)->pluck('id')->all());
        $this->assertSame([$post->id], Post::query()->whereHasTerm($php)->pluck('id')->all());
        $this->assertSame([$post->id], Post::query()->whereHasTerm('laravel', taxonomy: 'topics')->pluck('id')->all());
    }

    public function test_it_can_detach_and_sync_terms(): void
    {
        $taxonomy = Taxonomy::query()->create([
            'name' => 'Topics',
            'slug' => 'topics',
        ]);

        $php = $taxonomy->createTerm(['name' => 'PHP']);
        $laravel = $taxonomy->createTerm(['name' => 'Laravel']);
        $symfony = $taxonomy->createTerm(['name' => 'Symfony']);

        $post = Post::query()->create(['title' => 'Typed properties']);
        $post->attachTerms([$php, $laravel], taxonomy: $taxonomy);
        $post->detachTerm('php', taxonomy: $taxonomy);

        $this->assertSame(['laravel'], $post->terms()->pluck('slug')->all());

        $post->syncTerms([$php], taxonomy: $taxonomy, detaching: false);
        $this->assertSame(['laravel', 'php'], $post->terms()->orderBy('slug')->pluck('slug')->all());

        $post->syncTerms([$symfony], taxonomy: $taxonomy);
        $this->assertSame(['symfony'], $post->terms()->pluck('slug')->all());
    }

    public function test_it_rejects_child_terms_for_non_hierarchical_taxonomy(): void
    {
        $taxonomy = Taxonomy::query()->create([
            'name' => 'Tags',
            'is_hierarchical' => false,
        ]);
        $parent = $taxonomy->createTerm(['name' => 'PHP']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('This taxonomy does not allow hierarchical terms.');

        $taxonomy->createTerm(['name' => 'Laravel'], $parent);
    }

    public function test_it_requires_a_taxonomy_when_resolving_a_term_by_string(): void
    {
        $taxonomy = Taxonomy::query()->create([
            'name' => 'Topics',
            'slug' => 'topics',
        ]);
        $taxonomy->createTerm(['name' => 'PHP']);
        $post = Post::query()->create(['title' => 'Typed properties']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A taxonomy slug, name, or instance is required when resolving a term by string.');

        $post->attachTerm('php');
    }

    public function test_it_rejects_unknown_taxonomies_in_term_queries(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown taxonomy [missing].');

        Term::query()->forTaxonomy('missing')->get();
    }

    public function test_it_rejects_unknown_taxonomies_in_model_queries(): void
    {
        $post = Post::query()->create(['title' => 'Typed properties']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown taxonomy [missing].');

        $post->termsIn('missing')->get();
    }

    public function test_it_rejects_unknown_terms_within_a_taxonomy(): void
    {
        Taxonomy::query()->create([
            'name' => 'Topics',
            'slug' => 'topics',
        ]);
        $post = Post::query()->create(['title' => 'Typed properties']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown term [missing] in the given taxonomy.');

        $post->attachTerm('missing', taxonomy: 'topics');
    }
}
