<?php

namespace Wuwx\LaravelTaxonomy\Tests\Feature;

use InvalidArgumentException;
use Workbench\App\Post;
use Wuwx\LaravelTaxonomy\Models\Taxonomy;
use Wuwx\LaravelTaxonomy\Models\Term;
use Wuwx\LaravelTaxonomy\Tests\TestCase;

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
        $this->assertSame(['laravel', 'eloquent'], $php->descendants()->defaultOrder()->pluck('slug')->all());
        $this->assertSame(['eloquent', 'laravel', 'php', 'symfony'], Term::query()->forTaxonomy('topics')->orderBy('slug')->pluck('slug')->all());
        $this->assertSame(['eloquent', 'laravel', 'php', 'symfony'], Term::query()->forTaxonomy($taxonomy)->orderBy('slug')->pluck('slug')->all());
    }

    public function test_it_provides_tree_navigation_helpers(): void
    {
        $taxonomy = Taxonomy::query()->create([
            'name' => 'Topics',
            'slug' => 'topics',
        ]);

        $backend = $taxonomy->createTerm(['name' => 'Backend']);
        $frontend = $taxonomy->createTerm(['name' => 'Frontend']);
        $php = $taxonomy->createTerm(['name' => 'PHP'], $backend);
        $laravel = $taxonomy->createTerm(['name' => 'Laravel'], $php);
        $symfony = $taxonomy->createTerm(['name' => 'Symfony'], $php);
        $react = $taxonomy->createTerm(['name' => 'React'], $frontend);

        $this->assertSame(['backend', 'php'], $laravel->ancestors()->defaultOrder()->pluck('slug')->all());
        $this->assertSame(['symfony'], $laravel->siblings()->defaultOrder()->pluck('slug')->all());
        $this->assertSame(0, $backend->refresh()->ancestors()->count());
        $this->assertSame(1, $php->refresh()->ancestors()->count());
        $this->assertSame(2, $laravel->refresh()->ancestors()->count());
        $this->assertTrue($backend->isRoot());
        $this->assertFalse($php->isRoot());
        $this->assertFalse($php->isLeaf());
        $this->assertTrue($laravel->isLeaf());
        $this->assertTrue($backend->isAncestorOf($laravel));
        $this->assertTrue($laravel->isDescendantOf($backend));
        $this->assertFalse($frontend->isAncestorOf($laravel));
        $this->assertFalse($react->isDescendantOf($backend));

        $tree = $taxonomy->toTree();
        $this->assertSame(['backend', 'frontend'], $tree->pluck('slug')->all());
        $this->assertSame(['php'], $tree->firstWhere('slug', 'backend')?->children->pluck('slug')->all());
        $this->assertSame(['laravel', 'symfony'], $tree->firstWhere('slug', 'backend')?->children->first()?->children->pluck('slug')->all());

        $flatTree = $taxonomy->toFlatTree();
        $this->assertSame(
            ['backend', 'php', 'laravel', 'symfony', 'frontend', 'react'],
            $flatTree->pluck('slug')->all()
        );
        $this->assertSame([0, 1, 2, 2, 0, 1], $flatTree->pluck('depth')->all());
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

    public function test_it_supports_term_presence_checks(): void
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
        $laravel = $topics->createTerm(['name' => 'Laravel']);
        $go = $topics->createTerm(['name' => 'Go']);
        $docker = $skills->createTerm(['name' => 'Docker']);

        $post = Post::query()->create(['title' => 'Typed properties']);
        $post->attachTerms([$php, $laravel], taxonomy: $topics);
        $post->attachTerm('docker', taxonomy: 'skills');

        $this->assertTrue($post->hasTerm($php));
        $this->assertTrue($post->hasTerm('laravel', taxonomy: 'topics'));
        $this->assertFalse($post->hasTerm($go));
        $this->assertTrue($post->hasAnyTerms(['php', 'go'], taxonomy: 'topics'));
        $this->assertFalse($post->hasAnyTerms(['go', 'rust'], taxonomy: 'topics'));
        $this->assertTrue($post->hasAllTerms(['php', 'laravel'], taxonomy: 'topics'));
        $this->assertFalse($post->hasAllTerms(['php', 'go'], taxonomy: 'topics'));
        $this->assertTrue($post->hasAnyTerms([$docker]));
    }

    public function test_it_supports_multi_term_scopes(): void
    {
        $topics = Taxonomy::query()->create([
            'name' => 'Topics',
            'slug' => 'topics',
        ]);
        $statuses = Taxonomy::query()->create([
            'name' => 'Statuses',
            'slug' => 'statuses',
        ]);

        $php = $topics->createTerm(['name' => 'PHP']);
        $laravel = $topics->createTerm(['name' => 'Laravel']);
        $symfony = $topics->createTerm(['name' => 'Symfony']);
        $deprecated = $statuses->createTerm(['name' => 'Deprecated']);

        $first = Post::query()->create(['title' => 'Laravel tips']);
        $first->attachTerms([$php, $laravel], taxonomy: $topics);

        $second = Post::query()->create(['title' => 'Symfony tips']);
        $second->attachTerms([$php, $symfony], taxonomy: $topics);

        $third = Post::query()->create(['title' => 'Legacy note']);
        $third->attachTerm($deprecated);

        $fourth = Post::query()->create(['title' => 'Untitled']);

        $this->assertSame(
            [$first->id, $second->id],
            Post::query()->withAnyTerms(['php', 'laravel'], taxonomy: 'topics')->orderBy('id')->pluck('id')->all()
        );
        $this->assertSame(
            [$first->id],
            Post::query()->withAllTerms(['php', 'laravel'], taxonomy: $topics)->pluck('id')->all()
        );
        $this->assertSame(
            [$first->id, $second->id, $fourth->id],
            Post::query()->withoutTerms([$deprecated])->orderBy('id')->pluck('id')->all()
        );
        $this->assertSame(
            [$fourth->id],
            Post::query()->withoutAnyTerms()->pluck('id')->all()
        );
    }

    public function test_it_can_detach_multiple_or_all_terms(): void
    {
        $taxonomy = Taxonomy::query()->create([
            'name' => 'Topics',
            'slug' => 'topics',
        ]);

        $php = $taxonomy->createTerm(['name' => 'PHP']);
        $laravel = $taxonomy->createTerm(['name' => 'Laravel']);
        $symfony = $taxonomy->createTerm(['name' => 'Symfony']);

        $post = Post::query()->create(['title' => 'Typed properties']);
        $post->attachTerms([$php, $laravel, $symfony], taxonomy: $taxonomy);

        $post->detachTerms(['php', 'laravel'], taxonomy: $taxonomy);
        $this->assertSame(['symfony'], $post->terms()->pluck('slug')->all());

        $post->detachAllTerms();
        $this->assertSame([], $post->terms()->pluck('slug')->all());
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

    public function test_it_rejects_parents_from_other_taxonomies(): void
    {
        $topics = Taxonomy::query()->create([
            'name' => 'Topics',
            'slug' => 'topics',
        ]);
        $skills = Taxonomy::query()->create([
            'name' => 'Skills',
            'slug' => 'skills',
        ]);

        $parent = $topics->createTerm(['name' => 'PHP']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The parent term does not belong to this taxonomy.');

        $skills->createTerm(['name' => 'Docker'], $parent);
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

    public function test_it_filters_models_by_multiple_taxonomies(): void
    {
        $topics = Taxonomy::query()->create(['name' => 'Topics']);
        $cities = Taxonomy::query()->create(['name' => 'Cities']);

        $php = $topics->createTerm(['name' => 'PHP']);
        $laravel = $topics->createTerm(['name' => 'Laravel']);
        $shanghai = $cities->createTerm(['name' => 'Shanghai']);
        $beijing = $cities->createTerm(['name' => 'Beijing']);

        $post1 = Post::query()->create(['title' => 'Post 1']);
        $post2 = Post::query()->create(['title' => 'Post 2']);
        $post3 = Post::query()->create(['title' => 'Post 3']);

        $post1->attachTerms([$php, $laravel])->attachTerm($shanghai);
        $post2->attachTerms([$php])->attachTerm($beijing);
        $post3->attachTerm($laravel)->attachTerm($shanghai);

        // AND between taxonomies, OR within a taxonomy's terms
        $results = Post::byTaxonomies([
            'topics' => ['php', 'laravel'],
            'cities' => ['shanghai'],
        ])->pluck('title')->all();

        $this->assertContains('Post 1', $results);
        $this->assertContains('Post 3', $results);
        $this->assertNotContains('Post 2', $results); // has php but not in shanghai

        // Single taxonomy works too
        $results = Post::byTaxonomies([
            'topics' => ['php'],
        ])->pluck('title')->all();

        $this->assertContains('Post 1', $results);
        $this->assertContains('Post 2', $results);
        $this->assertNotContains('Post 3', $results);
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
