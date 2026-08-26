<?php

namespace Tests\Feature\Docs;

use App\Models\Project;
use App\Models\User;
use App\Support\Docs\DocsRegistry;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocsCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.env' => 'local']);
        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoDataSeeder::class);
    }

    public function test_index_renders_categories_and_articles(): void
    {
        $this->get('/docs')
            ->assertOk()
            ->assertSee('Documentation Center')
            ->assertSee('Quick Start')
            ->assertSee('Foundation Control Tower');
    }

    public function test_quick_start_is_public(): void
    {
        $this->get('/docs/quick-start')->assertOk()->assertSee('Mulai dalam 10 Menit');
    }

    public function test_category_and_article_render(): void
    {
        $this->get('/docs/bored-pile')->assertOk()->assertSee('Pile Passport', false);
        $this->get('/docs/bored-pile/pile-passport')
            ->assertOk()
            ->assertSee('Digital Pile Passport')
            ->assertSee('Buka Fitur');
    }

    public function test_invalid_category_and_slug_are_404(): void
    {
        $this->get('/docs/tidak-ada')->assertNotFound();
        $this->get('/docs/bored-pile/tidak-ada')->assertNotFound();
    }

    public function test_asset_serving_and_traversal_protection(): void
    {
        Storage::disk('docs')->put('screenshots/test/ok.webp', 'fake-image-bytes');

        $this->get('/docs/assets/screenshots/test/ok.webp')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/webp');

        $this->get('/docs/assets/../config/app.php')->assertNotFound();
        $this->get('/docs/assets/screenshots/test/ok.php')->assertNotFound();
        $this->get('/docs/assets/screenshots/test/hilang.webp')->assertNotFound();

        Storage::disk('docs')->delete('screenshots/test/ok.webp');
    }

    public function test_missing_screenshot_falls_back_friendly(): void
    {
        $html = view('components.docs.screenshot', ['keyName' => 'key-tidak-ada', 'alt' => 'x'])->render();
        $this->assertStringContainsString('belum tersedia', $html);
    }

    public function test_role_tag_and_visibility_metadata(): void
    {
        $registry = app(DocsRegistry::class);
        $passport = $registry->find('bored-pile', 'pile-passport');
        $this->assertSame('authenticated', $passport['visibility']);
        $this->assertNotEmpty($passport['role_tags']);
        $this->assertNotNull($passport['feature_route']);
    }

    public function test_feature_url_resolves_with_demo_fixture(): void
    {
        $article = app(DocsRegistry::class)->find('bored-pile', 'foundation-control-tower');
        $url = DocsRegistry::resolveFeatureUrl($article);
        $this->assertStringContainsString('/foundation-control', (string) $url);
        $this->assertStringNotContainsString('{project_id}', (string) $url);

        // Authenticated user dengan permission dapat membuka fitur.
        $user = User::where('email', 'admin@grahapondasi.test')->first();
        $projectId = Project::where('code', 'PRJ-2602')->value('id');
        $this->actingAs($user)->withSession(['company_id' => $user->companies()->first()->id])
            ->get("/admin/projects/{$projectId}/foundation-control")->assertOk();
    }

    public function test_search_finds_article(): void
    {
        $this->get('/docs/search?q=ncr')->assertOk()->assertSee('NCR & CAPA');
    }

    public function test_registry_has_articles(): void
    {
        $this->assertGreaterThan(0, app(DocsRegistry::class)->all()->count());
    }

    public function test_bored_pile_category_is_not_empty(): void
    {
        $this->assertTrue(app(DocsRegistry::class)->byCategory('bored-pile')->isNotEmpty());
    }

    public function test_index_lists_quick_start_dashboard_and_project_articles(): void
    {
        $html = $this->get('/docs')->assertOk()->getContent();
        $this->assertStringContainsString('Quick Start', $html);
        $this->assertStringContainsString('Dashboard Eksekutif', $html);
        $this->assertStringContainsString('Project Control Center', $html);
    }

    public function test_no_duplicate_docs_index_route_registration(): void
    {
        $count = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->getName() === 'docs.index')->count();
        $this->assertSame(1, $count, 'Route docs.index harus terdaftar tepat satu kali.');
    }

    public function test_all_markdown_articles_parse_with_title(): void
    {
        foreach (app(DocsRegistry::class)->all() as $article) {
            $this->assertNotEmpty($article['title'], "Artikel {$article['category']}/{$article['slug']} tanpa judul.");
            $this->assertNotSame(str($article['slug'])->replace('-', ' ')->title(), $article['title'], "Front-matter title hilang di {$article['category']}/{$article['slug']}");
        }
    }

    public function test_contextual_help_button_on_workspace_page(): void
    {
        $user = User::where('email', 'admin@grahapondasi.test')->first();
        $project = Project::where('code', 'PRJ-2602')->firstOrFail();
        $this->actingAs($user)->withSession(['company_id' => $this->defaultCompanyId()])
            ->get("/admin/projects/{$project->id}/foundation-control")
            ->assertOk()
            ->assertSee('? Bantuan')
            ->assertSee('/docs/bored-pile/foundation-control-tower');
    }

    private function defaultCompanyId(): int
    {
        return (int) session('company_id') ?: 1;
    }
}
