<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Score extends Model
{
    protected $fillable = [
        'group_id','user_id','season_year',
        'activity_points','weight_points','total_points','biggest_single_kg',
    ];

    protected $casts = [
        'activity_points' => 'int',
        'weight_points'   => 'int',
        'total_points'    => 'int',
        'biggest_single_kg' => 'decimal:3',
    ];

    public function group(): BelongsTo { return $this->belongsTo(Group::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

}
