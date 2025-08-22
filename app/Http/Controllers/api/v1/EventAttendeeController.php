<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttendeeResource;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventAttendeeController extends Controller
{
    // GET /api/events/{event}/attendees?rsvp=yes|undecided|no
    public function index(Request $request, Event $event) {
        $status = $request->query('rsvp');
        $query = $event->attendees()->with('profile');
        if ($status) {
            $query->wherePivot('rsvp', $status);
        }

        return response()->json([
            'data' => AttendeeResource::collection($query->orderBy('users.name')->get()),
            'counts' => [
                'yes'   => $event->attendees()->wherePivot('rsvp','yes')->count(),
                'undecided'   => $event->attendees()->wherePivot('rsvp','undecided')->count(),
                'no'=> $event->attendees()->wherePivot('rsvp','no')->count(),
                'total'   => $event->attendees()->count(),
            ],
        ]);
    }

    // POST /api/events/{event}/attendees  body: { rsvp?: ?rsvp=yes|undecided|no }
    public function store(Request $request, Event $event) {
        $this->authorize('rsvp', $event); // po želji policy

        $validated = $request->validate([
            'rsvp' => ['nullable', Rule::in(['yes','undecided','no'])],
        ]);
        $status = $validated['rsvp'] ?? 'yes';

        $event->attendees()->syncWithoutDetaching([
            $request->user()->id => ['rsvp' => $status],
        ]);

        return response()->json([
            'message' => 'RSVP saved.',
        ], 201);
    }

    // PATCH /api/events/{event}/attendees  body: { status: going|maybe|declined }
    public function update(Request $request, Event $event) {
        $this->authorize('rsvp', $event);

        $validated = $request->validate([
            'rsvp' => ['required', Rule::in(['yes','undecided','no'])],
        ]);

        $event->attendees()->updateExistingPivot($request->user()->id, [
            'rsvp' => $validated['rsvp'],
        ]);

        return response()->json(['message' => 'RSVP updated.']);
    }

    // DELETE /api/events/{event}/attendees (odjava)
    public function destroy(Request $request, Event $event) {
        $this->authorize('rsvp', $event);

        $event->attendees()->detach($request->user()->id);
        return response()->json(['message' => 'RSVP removed.']);
    }
}
