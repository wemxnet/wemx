<?php

namespace Extensions\Modules\Knowledgebase\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Extensions\Modules\Knowledgebase\Models\KnowledgebaseArticle;
use Extensions\Modules\Knowledgebase\Models\KnowledgebaseCategory;
use Illuminate\Http\Request;

class KnowledgebaseController extends Controller
{
    public function index()
    {
        return client_view('knowledgebase::knowledgebase.index');
    }

    public function search(Request $request)
    {
        return client_view('knowledgebase::knowledgebase.search', [
            'q' => trim((string) $request->query('q', '')),
        ]);
    }

    public function category(KnowledgebaseCategory $category)
    {
        abort_unless($category->isVisibleTo(auth()->user()), 404);

        return client_view('knowledgebase::knowledgebase.category', [
            'category' => $category,
        ]);
    }

    public function article(KnowledgebaseCategory $category, KnowledgebaseArticle $article)
    {
        abort_unless($article->category_id === $category->id, 404);
        abort_unless($category->isVisibleTo(auth()->user()), 404);
        abort_unless($article->isVisibleTo(auth()->user()), 404);

        return client_view('knowledgebase::knowledgebase.article', [
            'category' => $category,
            'article' => $article,
        ]);
    }
}
