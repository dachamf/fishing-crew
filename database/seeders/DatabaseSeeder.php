<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Kreiraj korisnike
        $users = User::factory()->count(20)->create();

        // Kreiraj grupe
        $groups = Group::factory()->count(6)->create();

        foreach ($groups as $group) {
            // Dodeli vlasnika (owner)
            $owner = $users->random();
            $group->users()->attach($owner->id, ['role' => 'owner']);

            // Dodeli 1-2 moderatora
            $availableForMods = $users->where('id', '!=', $owner->id);
            $modsCount = min($availableForMods->count(), random_int(1, 2));
            $modIds = $modsCount > 0
                ? $availableForMods->pluck('id')->random($modsCount)->all()
                : [];
            foreach ($modIds as $modId) {
                $group->users()->syncWithoutDetaching([$modId => ['role' => 'moderator']]);
            }

            // Dodeli 5-10 članova (member)
            $occupiedIds = array_merge([$owner->id], $modIds);
            $availableMembers = $users->whereNotIn('id', $occupiedIds);
            $memberCount = min($availableMembers->count(), random_int(5, 10));
            if ($memberCount > 0) {
                $memberIds = $availableMembers->pluck('id')->random($memberCount)->all();
                foreach ($memberIds as $memberId) {
                    $group->users()->syncWithoutDetaching([$memberId => ['role' => 'member']]);
                }
            }

            // Kreiraj 3-7 događaja za grupu
            $events = Event::factory()->count(random_int(3, 7))->for($group)->create();

            // Dodaj prisutne (attendees) na događaje iz članova grupe
            $groupUserIds = $group->users()->pluck('users.id');
            foreach ($events as $event) {
                if ($groupUserIds->isEmpty()) {
                    continue;
                }
                $attendeeCount = min($groupUserIds->count(), random_int(3, 12));
                $attendees = $groupUserIds->random($attendeeCount)->all();

                $pivotData = [];
                foreach ($attendees as $userId) {
                    $rsvp = collect(['yes','no','undecided'])->random();
                    $checkedInAt = $rsvp === 'yes' ? now()->subMinutes(random_int(5, 120)) : null;
                    $rating = $checkedInAt ? random_int(1, 5) : null;

                    $pivotData[$userId] = [
                        'rsvp' => $rsvp,
                        'reason' => $rsvp === 'no' ? fake()->optional()->sentence() : null,
                        'checked_in_at' => $checkedInAt,
                        'rating' => $rating,
                    ];
                }

                $event->users()->syncWithoutDetaching($pivotData);
            }
        }
    }
}
