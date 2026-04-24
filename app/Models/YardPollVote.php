<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YardPollVote extends Model
{
    protected $table = 'yard_poll_votes';

    protected $fillable = [
        'poll_id',
        'option_id',
        'user_id',
    ];

    public function poll(): BelongsTo
    {
        return $this->belongsTo(YardPoll::class, 'poll_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(YardPollOption::class, 'option_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
