<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\FishingCatch;
use App\Models\FishingSession;
use App\Models\Group;
use App\Models\Species;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed species first (needed for foreign key species_id in catches)
        $speciesItems = [
            ['slug'=>'som',     'name_sr'=>'Som',     'name_latin'=>'Silurus glanis'],
            ['slug'=>'smudj',   'name_sr'=>'Smuđ',    'name_latin'=>'Sander lucioperca'],
            ['slug'=>'stuka',   'name_sr'=>'Štuka',   'name_latin'=>'Esox lucius'],
            ['slug'=>'saran',   'name_sr'=>'Šaran',   'name_latin'=>'Cyprinus carpio'],
            ['slug'=>'babuskа', 'name_sr'=>'Babuška', 'name_latin'=>'Carassius gibelio'],
            ['slug'=>'amur',    'name_sr'=>'Amur',    'name_latin'=>'Ctenopharyngodon idella'],
            ['slug'=>'bucov',   'name_sr'=>'Bucov',   'name_latin'=>'Aspius aspius'],
            ['slug'=>'mrena',   'name_sr'=>'Mrena',   'name_latin'=>'Barbus barbus'],
            ['slug'=>'deverika','name_sr'=>'Deverika','name_latin'=>'Abramis brama'],
            ['slug'=>'linjak',  'name_sr'=>'Linjak',  'name_latin'=>'Tinca tinca'],
        ];
        foreach ($speciesItems as $it) Species::firstOrCreate(['slug'=>$it['slug']], $it);
        // Build a lookup from lowercase name_sr to id for convenience
        $speciesNameToId = Species::all()->mapWithKeys(function($s){
            return [mb_strtolower($s->name_sr) => $s->id];
        });

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
                    $rsvp = collect(['yes', 'no', 'undecided'])->random();
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

            foreach ($groups as $group) {
                // ... postojeći kod za users/events/attendees ...

                // Kreiraj 2-5 sesija za random članove grupe
                $groupUserIds = $group->users()->pluck('users.id');
                if ($groupUserIds->isEmpty()) continue;

                $sessions = collect(range(1, random_int(2,5)))->map(function() use ($group, $groupUserIds) {
                    $userId = $groupUserIds->random();
                    return FishingSession::create([
                        'group_id'    => $group->id,
                        'user_id'     => $userId,
                        'title'       => fake()->randomElement(['Jutarnje', 'Večernje', 'Noćno']) . ' pecanje',
                        'latitude'    => fake()->randomFloat(6, 42.0, 46.0),
                        'longitude'   => fake()->randomFloat(6, 18.0, 23.0),
                        'started_at'  => now()->subDays(random_int(1, 30))->setTime(random_int(5,20), random_int(0,59)),
                        'ended_at'    => now()->subDays(random_int(0, 30))->setTime(random_int(6,23), random_int(0,59)),
                        'status'      => fake()->boolean(70) ? 'closed' : 'open',
                        'season_year' => (int) now()->format('Y'),
                    ]);
                });

                // Za svaku sesiju “stackuj” 2-4 vrste
                foreach ($sessions as $s) {
                    $species = collect(['štuka','smuđ','som','beli amur','klen','šaran'])->shuffle()->take(random_int(2,4));
                    foreach ($species as $sp) {
                        $count = random_int(1, 5);
                        $weights = collect(range(1,$count))->map(fn() => fake()->randomFloat(3, 0.3, 6.5));
                        FishingCatch::create([
                            'group_id'          => $s->group_id,
                            'user_id'           => $s->user_id,
                            'event_id'          => $s->event_id,
                            'session_id'        => $s->id,
                            'species_id'        => $speciesNameToId->get(mb_strtolower($sp)),
                            'count'             => $count,
                            'total_weight_kg'   => round($weights->sum(), 3),
                            'biggest_single_kg' => round($weights->max(), 3),
                            'note'              => fake()->optional()->sentence(),
                            'status'            => fake()->randomElement(['pending','approved']),
                            'caught_at'         => $s->started_at->copy()->addMinutes(random_int(10,180)),
                            'season_year'       => (int)$s->started_at->format('Y'),
                        ]);
                    }
                }
            }

        }
        // --- SEED: Catches za ovu grupu (mešovito: sa i bez eventa, razni statusi) ---
        $groupUserIds = $group->users()->pluck('users.id')->all();
        // Use species ids for FK
        $speciesIds   = Species::pluck('id')->all();

// Ako u šemi postoji kolona "caught_at", popunićemo je; ako ne postoji – preskačemo
        $hasCaughtAt = Schema::hasColumn('catches', 'caught_at');

// napravi 12–30 ulova po grupi
        $perGroupCount = random_int(12, 30);

        for ($i = 0; $i < $perGroupCount; $i++) {
            if (empty($groupUserIds)) break;

            $userId         = Arr::random($groupUserIds);
            $attachToEvent  = $events->isNotEmpty() && random_int(0, 100) < 70; // ~70% sa eventom
            $eventId        = $attachToEvent ? $events->random()->id : null;

            $count   = random_int(1, 4);
            $biggest = round(mt_rand(250, 6500) / 1000, 3); // 0.25–6.5kg
            // total >= biggest (ako je count=1 može biti ~= biggest)
            $total   = $count === 1
                ? max($biggest, round($biggest + mt_rand(-200, 500) / 1000, 3))   // malo variranje oko biggest
                : round($biggest + mt_rand(300, 8000) / 1000, 3);                 // veća suma

            $status  = Arr::random(['pending','approved','rejected']);

            $row = [
                'group_id'          => $group->id,
                'user_id'           => $userId,
                'event_id'          => $eventId,
                'species_id'        => !empty($speciesIds) ? Arr::random($speciesIds) : null,
                'count'             => $count,
                'total_weight_kg'   => max($total, $biggest),
                'biggest_single_kg' => $biggest,
                'note'              => fake()->optional()->sentence(),
                'status'            => $status,
                'created_at'        => fake()->dateTimeBetween('-90 days', 'now'),
                'updated_at'        => now(),
            ];

            if ($hasCaughtAt) {
                // ako postoji kolona caught_at – stavi realan datum lova (može oko datuma eventa ili random)
                $row['caught_at'] = $eventId
                    ? fake()->dateTimeBetween('-2 hours', '+6 hours')
                    : fake()->dateTimeBetween('-120 days', 'now');
            }

            /** @var \App\Models\FishingCatch $catch */
            $catch = FishingCatch::create($row);

            // --- Potvrde (catch_confirmations) u zavisnosti od statusa ---
            // biramo potvrđivače iz grupe (bez autora)
            $eligibleConfirmers = array_values(array_diff($groupUserIds, [$userId]));
            if (!empty($eligibleConfirmers)) {
                $howMany = match ($status) {
                    'approved'  => min(count($eligibleConfirmers), random_int(1, 2)), // 1–2 potvrde
                    'rejected'  => min(count($eligibleConfirmers), random_int(1, 2)), // 1–2 odbijanja
                    default     => random_int(0, 1),                                  // ponekad 1 pending potvrda
                };

                if ($howMany > 0) {
                    $confirmers = (array) Arr::random($eligibleConfirmers, $howMany);
                    foreach ($confirmers as $confUserId) {
                        DB::table('catch_confirmations')->insert([
                            'catch_id'     => $catch->id,
                            'confirmed_by' => $confUserId,
                            'status'       => $status === 'rejected'
                                ? 'rejected'
                                : ($status === 'approved' ? 'approved' : Arr::random(['approved','rejected'])),
                            'note'         => fake()->optional()->sentence(),
                            'created_at'   => now(),
                            'updated_at'   => now(),
                        ]);
                    }
                }
            }
        }
    }
}
