<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Group;
use App\Models\Event;
use App\Models\FishingSession;
use App\Models\FishingCatch;
use App\Models\Species;
use App\Models\CatchPhoto;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DemoKuchnaSeeder extends Seeder
{
    public function run(): void
    {
        // --- IMPORTANT: create group FIRST to satisfy any User observer attaching default group ---
        /** @var \App\Models\Group $group */
        $group = Group::firstOrCreate(
            ['name' => 'Kuchna'],
        );

        // --- Create core users WITHOUT firing model events (avoid UserObserver attaching non-existent group) ---
        $owner = null; $u1 = null; $u2 = null;
        User::withoutEvents(function () use (&$owner, &$u1, &$u2) {
            $owner = User::firstOrCreate(
                ['email' => 'dachamf@gmail.com'],
                [
                    'name' => 'dachamf',
                    'password' => Hash::make('Welcome1'),
                    'email_verified_at' => now(),
                ]
            );
            $u1 = User::firstOrCreate(
                ['email' => 'reviewer1@example.com'],
                ['name' => 'Reviewer One', 'password' => Hash::make('password'), 'email_verified_at' => now()]
            );
            $u2 = User::firstOrCreate(
                ['email' => 'reviewer2@example.com'],
                ['name' => 'Reviewer Two', 'password' => Hash::make('password'), 'email_verified_at' => now()]
            );
        });

        // --- Ensure group_user memberships (idempotent) ---
        DB::table('group_user')->updateOrInsert(
            ['group_id' => $group->id, 'user_id' => $owner->id],
            ['role' => 'owner', 'created_at' => now(), 'updated_at' => now()]
        );
        foreach ([$u1, $u2] as $u) {
            DB::table('group_user')->updateOrInsert(
                ['group_id' => $group->id, 'user_id' => $u->id],
                ['role' => 'member', 'created_at' => now(), 'updated_at' => now()]
            );
        }

        DB::table('groups')->where('name', 'Kuchna')->update([
            'season_year' => (int) now()->format('Y'),
        ]);

        // --- Species ---
        $speciesItems = [
            ['slug'=>'som',     'name_sr'=>'Som'],
            ['slug'=>'smudj',   'name_sr'=>'Smuđ'],
            ['slug'=>'stuka',   'name_sr'=>'Štuka'],
            ['slug'=>'saran',   'name_sr'=>'Šaran'],
            ['slug'=>'babuska', 'name_sr'=>'Babuška'],
            ['slug'=>'amur',    'name_sr'=>'Amur'],
            ['slug'=>'bucov',   'name_sr'=>'Bucov'],
            ['slug'=>'mrena',   'name_sr'=>'Mrena'],
            ['slug'=>'deverika','name_sr'=>'Deverika'],
            ['slug'=>'linjak',  'name_sr'=>'Linjak'],
        ];
        foreach ($speciesItems as $it) Species::firstOrCreate(['slug' => $it['slug']], $it);
        $speciesByName = Species::all()->keyBy(fn($s) => mb_strtolower($s->name_sr));

        // --- Events ---
        $now = now();
        $events = [];
        $events[] = Event::firstOrCreate(
            ['title' => 'Prolećni Kup — Dunav', 'group_id' => $group->id],
            [
                'description' => 'Prolećni izlazak na vodu.',
                'start_at' => $now->copy()->subDays(20)->setTime(7, 30),
                'location_name'  => 'Dunav — Novi Sad',
            ]
        );
        $events[] = Event::firstOrCreate(
            ['title' => 'Noćno pecanje — Tisa', 'group_id' => $group->id],
            [
                'description' => 'Test noćne opreme.',
                'start_at' => $now->copy()->addDays(5)->setTime(20, 0),
                'location_name'  => 'Tisa — Titel',
            ]
        );
        $events[] = Event::firstOrCreate(
            ['title' => 'Vikend na Savi', 'group_id' => $group->id],
            [
                'description' => 'Porodični vikend i pecanje.',
                'start_at' => $now->copy()->addDays(14)->setTime(8, 0),
                'location_name'  => 'Sava — Obrenovac',
            ]
        );
        foreach ($events as $ev) {
            foreach ([$owner, $u1, $u2] as $u) {
                DB::table('event_attendees')->updateOrInsert(
                    ['event_id' => $ev->id, 'user_id' => $u->id],
                    ['rsvp' => 'yes', 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }

        // --- Sessions ---
        $sessionApproved = FishingSession::create([
            'group_id'    => $group->id,
            'user_id'     => $owner->id,
            'event_id'    => $events[0]->id,
            'title'       => 'Jutarnje pecanje — Dunav',
            'latitude'    => 45.2510,
            'longitude'   => 19.8369,
            'location_name' => 'Keј, Novi Sad',
            'started_at'  => $now->copy()->subDays(20)->setTime(7, 45),
            'ended_at'    => $now->copy()->subDays(20)->setTime(12, 30),
            'status'      => 'closed',
            'finalized_at'=> $now->copy()->subDays(19)->setTime(9, 0),
            'final_result'=> 'approved',
            'season_year' => (int) $now->format('Y'),
        ]);
        $sessionRejected = FishingSession::create([
            'group_id'    => $group->id,
            'user_id'     => $owner->id,
            'event_id'    => $events[0]->id,
            'title'       => 'Popodnevno pecanje — Dunav',
            'latitude'    => 45.2550,
            'longitude'   => 19.8400,
            'location_name' => 'Žeželjev most',
            'started_at'  => $now->copy()->subDays(18)->setTime(14, 15),
            'ended_at'    => $now->copy()->subDays(18)->setTime(18, 0),
            'status'      => 'closed',
            'finalized_at'=> $now->copy()->subDays(17)->setTime(10, 0),
            'final_result'=> 'rejected',
            'season_year' => (int) $now->format('Y'),
        ]);
        $sessionOpen = FishingSession::create([
            'group_id'    => $group->id,
            'user_id'     => $owner->id,
            'event_id'    => $events[1]->id,
            'title'       => 'Noćni test — Tisa',
            'latitude'    => 45.2050,
            'longitude'   => 20.2970,
            'location_name' => 'Titel',
            'started_at'  => $now->copy()->addDays(5)->setTime(20, 30),
            'ended_at'    => null,
            'status'      => 'open',
            'finalized_at'=> null,
            'final_result'=> null,
            'season_year' => (int) $now->format('Y'),
        ]);

// Disk za fotke (S3/MinIO ako je default s3, inače public)
        $photoDisk = config('filesystems.default') === 's3' ? 's3' : 'public';

        $makeDemoSvg = function (int $w, int $h, string $bgHex, string $label): string {
            $bgHex = ltrim($bgHex, '#');
            [$r,$g,$b] = [hexdec(substr($bgHex,0,2)), hexdec(substr($bgHex,2,2)), hexdec(substr($bgHex,4,2))];
            $luma = 0.2126*$r + 0.7152*$g + 0.0722*$b;
            $fg = $luma > 160 ? '#141414' : '#f5f5f5';

            $label2 = date('Y-m-d H:i') . " · {$w}×{$h}px";

            // ➜ izračunaj pre unosa u heredoc
            $w2 = $w - 8;
            $h2 = $h - 8;

            $svg = <<<SVG
<?xml version="1.0" encoding="UTF-8"?>
<svg width="{$w}" height="{$h}" viewBox="0 0 {$w} {$h}" xmlns="http://www.w3.org/2000/svg">
  <rect width="100%" height="100%" fill="#{$bgHex}"/>
  <rect x="4" y="4" width="{$w2}" height="{$h2}" fill="none" stroke="{$fg}" stroke-width="2" opacity="0.6"/>
  <text x="50%" y="48%" text-anchor="middle" font-family="system-ui, sans-serif" font-size="28" fill="{$fg}">{$label}</text>
  <text x="50%" y="56%" text-anchor="middle" font-family="system-ui, sans-serif" font-size="16" fill="{$fg}" opacity="0.9">{$label2}</text>
</svg>
SVG;

            return $svg;
        };


        $addCatchWithPhotos = function (
            FishingSession $s,
            string $speciesName,
            int $count,
            float $totalKg,
            float $biggestKg,
            ?string $note = null,
            int $photos = 2
        ) use ($group, $owner, $speciesByName, $photoDisk, $makeDemoSvg) {
            $sp = $speciesByName->get(mb_strtolower($speciesName));

            $catch = \App\Models\FishingCatch::create([
                'group_id'          => $group->id,
                'user_id'           => $owner->id,
                'event_id'          => $s->event_id,
                'session_id'        => $s->id,
                'species_id'        => $sp?->id,
                'count'             => $count,
                'total_weight_kg'   => $totalKg,
                'biggest_single_kg' => $biggestKg,
                'note'              => $note,
                'status'            => 'pending',
                'caught_at'         => optional($s->started_at)?->copy()->addMinutes(90),
                'season_year'       => (int) now()->format('Y'),
            ]);

            $colors = ['#1e90ff','#22c55e','#ef4444','#a3a3a3'];
            for ($i = 1; $i <= $photos; $i++) {
                $color = $colors[($i-1) % count($colors)];
                $label = strtoupper($speciesName) . "  |  #{$catch->id}-{$i}";
                $svg   = $makeDemoSvg(800, 600, $color, $label);

                $key = "catch_photos/{$catch->id}_{$i}.svg";

                \Storage::disk($photoDisk)->put($key, $svg, [
                    'visibility'  => 'public',
                    'ContentType' => 'image/svg+xml',
                    // 'CacheControl' => 'public, max-age=604800',
                ]);

                \App\Models\CatchPhoto::create([
                    'catch_id' => $catch->id,
                    'disk'     => $photoDisk,  // ako imaš kolonu 'disk'
                    'path'     => $key,        // čuvamo samo ključ
                    'ord'      => $i,
                    'format'   => 'svg',
                    'width'    => 800,
                    'height'   => 600,
                ]);
            }

            return $catch;
        };

        // Approved session catches
        $c1 = $addCatchWithPhotos($sessionApproved, 'Smuđ', 2, 3.100, 1.900, 'Lepo vreme, slab vetar.', 3);
        $c2 = $addCatchWithPhotos($sessionApproved, 'Som', 1, 4.250, 4.250, 'Duboka voda, soma na kedera.', 2);
        DB::table('catches')->whereIn('id', [$c1->id, $c2->id])->update(['status' => 'approved']);

        // Rejected session catches
        $c3 = $addCatchWithPhotos($sessionRejected, 'Štuka', 1, 1.200, 1.200, 'Premala, vraćena u vodu.', 2);
        DB::table('catches')->where('id', $c3->id)->update(['status' => 'rejected']);

        // Open session catches (pending)
        $c4 = $addCatchWithPhotos($sessionOpen, 'Babuška', 3, 1.850, 0.900, 'Noćno pecanje, slaba aktivnost.', 2);

        // --- Session-level confirmations ---
        DB::table('session_confirmations')->updateOrInsert(
            ['session_id' => $sessionApproved->id, 'nominee_user_id' => $u1->id],
            ['status' => 'approved', 'decided_at' => now()->subDays(19), 'created_at' => now(), 'updated_at' => now()]
        );
        DB::table('session_confirmations')->updateOrInsert(
            ['session_id' => $sessionApproved->id, 'nominee_user_id' => $u2->id],
            ['status' => 'approved', 'decided_at' => now()->subDays(19), 'created_at' => now(), 'updated_at' => now()]
        );

        DB::table('session_confirmations')->updateOrInsert(
            ['session_id' => $sessionRejected->id, 'nominee_user_id' => $u1->id],
            ['status' => 'rejected', 'decided_at' => now()->subDays(17), 'created_at' => now(), 'updated_at' => now()]
        );
        DB::table('session_confirmations')->updateOrInsert(
            ['session_id' => $sessionRejected->id, 'nominee_user_id' => $u2->id],
            ['status' => 'approved', 'decided_at' => now()->subDays(17), 'created_at' => now(), 'updated_at' => now()]
        );

        DB::table('session_confirmations')->updateOrInsert(
            ['session_id' => $sessionOpen->id, 'nominee_user_id' => $u1->id],
            ['status' => 'pending'], // leave pending
        );
    }
}
