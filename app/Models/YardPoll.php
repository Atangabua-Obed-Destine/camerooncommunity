<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class YardPoll extends Model
{
    use BelongsToTenant;

    protected $table = 'yard_polls';

    protected $fillable = [
        'message_id',
        'room_id',
        'user_id',
        'question',
        'allow_multiple',
        'is_closed',
    ];

    protected function casts(): array
    {
        return [
            'allow_multiple' => 'boolean',
            'is_closed'      => 'boolean',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(YardMessage::class, 'message_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(YardRoom::class, 'room_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(YardPollOption::class, 'poll_id')->orderBy('position');
    }

    public function votes(): HasManyThrough
    {
        return $this->hasManyThrough(
            YardPollVote::class,
            YardPollOption::class,
            'poll_id',
            'option_id',
            'id',
            'id'
        );
    }

    public function totalVotes(): int
    {
        return (int) $this->options->sum('votes_count');
    }

    public function totalVoters(): int
    {
        return (int) $this->votes()->distinct('user_id')->count('user_id');
    }
}
