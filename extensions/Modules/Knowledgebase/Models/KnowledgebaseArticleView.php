<?php

namespace Extensions\Modules\Knowledgebase\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgebaseArticleView extends Model
{
    public $timestamps = false;

    protected $table = 'knowledgebase_article_views';

    protected $fillable = [
        'article_id',
        'user_id',
        'visitor_hash',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(KnowledgebaseArticle::class, 'article_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
