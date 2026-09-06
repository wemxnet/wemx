<?php

namespace Extensions\Modules\Downloads\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DownloadLog extends Model
{
    protected $table = 'download_logs';

    protected $fillable = [
        'file_id',
        'user_id',
        'ip_address',
        'user_agent',
    ];

    public function file(): BelongsTo
    {
        return $this->belongsTo(DownloadFile::class, 'file_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
