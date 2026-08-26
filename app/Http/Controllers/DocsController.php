<?php

namespace App\Http\Controllers;

use App\Support\Docs\DocsMarkdown;
use App\Support\Docs\DocsRegistry;
use Illuminate\Http\Request;

/**
 * User Documentation Center (ADR-085): /docs publik + artikel per fitur
 * dengan sidebar, search, TOC, role filter, dan tombol Buka Fitur.
 */
class DocsController extends Controller
{
    public function __construct(private DocsRegistry $registry) {}

    public function index()
    {
        return view('docs.index', [
            'articles' => $this->registry->all(),
            'categories' => DocsRegistry::CATEGORIES,
        ]);
    }

    public function quickStart()
    {
        $article = $this->registry->all()->firstWhere('slug', 'quick-start');
        abort_if($article === null, 404);

        return view('docs.article', [
            'article' => $article,
            'html' => DocsMarkdown::toHtml($article['body']),
            'toc' => DocsRegistry::toc($article['body']),
            'related' => collect(),
        ]);
    }

    public function category(string $category)
    {
        abort_unless(isset(DocsRegistry::CATEGORIES[$category]), 404);
        $articles = $this->registry->byCategory($category);
        abort_if($articles->isEmpty(), 404);

        return view('docs.category', [
            'category' => $category,
            'categoryLabel' => DocsRegistry::CATEGORIES[$category],
            'articles' => $articles,
            'categories' => DocsRegistry::CATEGORIES,
        ]);
    }

    public function article(string $category, string $slug)
    {
        $article = $this->registry->find($category, $slug);
        abort_if($article === null, 404);
        // Visibility: admin-only tidak dilayani di context publik tanpa auth admin.
        if ($article['visibility'] === 'admin' && ! optional(auth()->user())->hasPermission('organization.manage', (int) session('company_id', 0))) {
            abort(404);
        }

        $related = collect($article['related'])
            ->map(fn ($r) => $this->registry->find(str_before($r, '/'), str_after($r, '/')))
            ->filter();

        return view('docs.article', [
            'article' => $article,
            'html' => DocsMarkdown::toHtml($article['body']),
            'toc' => DocsRegistry::toc($article['body']),
            'related' => $related,
        ]);
    }

    public function search(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $results = $this->registry->search($q)->groupBy('category');

        return view('docs.search', [
            'q' => $q,
            'results' => $results,
            'categories' => DocsRegistry::CATEGORIES,
        ]);
    }
}
