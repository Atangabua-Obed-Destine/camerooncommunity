<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class YardPollOption extends Model
{
    protected $table = 'yard_poll_options';

    protected $fillable = [
        'poll_id',
        'text',
        'position',
        'votes_count',
    ];

    public function poll(): BelongsTo
    {
        return $this->belongsTo(YardPoll::class, 'poll_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(YardPollVote::class, 'option_id');
    }
}
