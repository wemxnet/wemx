<?php

namespace Extensions\Modules\Knowledgebase\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgebaseArticleVote extends Model
{
    protected $table = 'knowledgebase_article_votes';

    protected $fillable = [
        'article_id',
        'user_id',
        'visitor_hash',
        'is_helpful',
    ];

    protected function casts(): array
    {
        return [
            'is_helpful' => 'boolean',
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
