<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['layout', 'daily', 'score', 'duration', 'hints', 'shuffles', 'undos'])]
class Score extends Model
{
    protected function casts(): array
    {
        return ['daily' => 'date:Y-m-d'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
