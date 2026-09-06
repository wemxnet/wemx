<?php

namespace Extensions\Modules\Knowledgebase\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Extensions\Modules\Knowledgebase\Models\KnowledgebaseArticle;
use Extensions\Modules\Knowledgebase\Models\KnowledgebaseCategory;

class KnowledgebaseController extends Controller
{
    public function index()
    {
        return admin_view('knowledgebase::knowledgebase.index');
    }

    public function createArticle()
    {
        return admin_view('knowledgebase::knowledgebase.articles.create');
    }

    public function editArticle(KnowledgebaseArticle $article)
    {
        return admin_view('knowledgebase::knowledgebase.articles.edit', [
            'article' => $article,
        ]);
    }

    public function categories()
    {
        return admin_view('knowledgebase::knowledgebase.categories.index');
    }

    public function createCategory()
    {
        return admin_view('knowledgebase::knowledgebase.categories.create');
    }

    public function editCategory(KnowledgebaseCategory $category)
    {
        return admin_view('knowledgebase::knowledgebase.categories.edit', [
            'category' => $category,
        ]);
    }
}
