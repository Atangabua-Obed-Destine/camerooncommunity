<?php

namespace App\Console\Commands;

use App\Enums\RoomType;
use App\Models\YardRoom;
use App\Services\RoomNamingService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Renames every system room (National / Regional / City) to the new
 * "{ShortLabel} - Kamer" format and refreshes their slugs.
 *
 *   php artisan rooms:rename-system          (preview)
 *   php artisan rooms:rename-system --apply  (commit)
 */
class RenameSystemRoomsCommand extends Command
{
    protected $signature = 'rooms:rename-system {--apply : Persist changes (otherwise dry-run)}';

    protected $description = 'Rename National/Regional/City system rooms to the "{Short} - Kamer" format';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $rooms = YardRoom::withoutGlobalScopes()
            ->where('is_system_room', true)
            ->whereIn('room_type', [RoomType::National, RoomType::Regional, RoomType::City])
            ->get();

        $changed = 0;
        $skipped = 0;

        foreach ($rooms as $room) {
            [$newName, $newSlug] = $this->build($room);

            if ($newName === null) {
                $skipped++;
                continue;
            }

            if ($room->name === $newName && $room->slug === $newSlug) {
                $skipped++;
                continue;
            }

            $this->line(sprintf(
                '  [%s] %s  →  %s',
                $room->room_type->value,
                $room->name,
                $newName
            ));

            if ($apply) {
                // Avoid slug collisions — append room id when needed.
                $finalSlug = $newSlug;
                $exists = YardRoom::withoutGlobalScopes()
                    ->where('slug', $finalSlug)
                    ->where('id', '!=', $room->id)
                    ->exists();
                if ($exists) {
                    $finalSlug = $newSlug . '-' . $room->id;
                }

                $room->forceFill([
                    'name' => $newName,
                    'slug' => $finalSlug,
                ])->saveQuietly();
            }

            $changed++;
        }

        $this->newLine();
        $this->info(sprintf(
            '%s %d room(s); %d unchanged.',
            $apply ? 'Renamed' : 'Would rename',
            $changed,
            $skipped
        ));

        if (! $apply && $changed > 0) {
            $this->comment('Run again with --apply to persist.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: ?string, 1: ?string}  [name, slug] — name=null means skip.
     */
    protected function build(YardRoom $room): array
    {
        $country = (string) ($room->country ?? '');

        return match ($room->room_type) {
            RoomType::National => [
                RoomNamingService::national($country),
                Str::slug(RoomNamingService::shortCountry($country) . '-kamer'),
            ],
            RoomType::Regional => $room->region
                ? [
                    RoomNamingService::regional($room->region),
                    Str::slug($room->region . '-kamer-' . RoomNamingService::shortCountry($country)),
                ]
                : [null, null],
            RoomType::City => $room->city
                ? [
                    RoomNamingService::city($room->city),
                    Str::slug($room->city . '-kamer'),
                ]
                : [null, null],
            default => [null, null],
        };
    }
}
