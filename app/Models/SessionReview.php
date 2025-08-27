<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionReview extends Model
{
    protected $fillable = ['session_id','reviewer_id','status','note'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(FishingSession::class, 'session_id');
    }
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
