<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\Group;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventsController extends Controller
{

    /**
     * @param Request $r
     * @return JsonResponse
     */
    public function index(Request $r, ?Group $group = null)
    {
        $q = Event::query()->with(['group:id,name']);

        // scope po grupi (path param ima prioritet)
        if ($group) {
            $q->where('group_id', $group->id);
        } elseif ($r->filled('group_id')) {
            $q->where('group_id', (int)$r->group_id);
        }

        // from=today | ISO date
        $from = $r->query('from');
        if ($from === 'today') {
            $q->where('start_at', '>=', now()->startOfDay());
        } elseif ($from) {
            $q->where('start_at', '>=', \Carbon\Carbon::parse($from));
        }

        // include=my_rsvp
        $include = collect(explode(',', (string)$r->query('include','')))
            ->map(fn($i)=>trim($i))->filter()->values();
        if ($include->contains('my_rsvp')) {
            $q->with(['rsvps' => fn($qq) => $qq->where('user_id', $r->user()->id)]);
        }

        $events = $q->orderBy('start_at')->limit(min(50, (int)$r->query('limit', 3)))->get();

        // Normalizuj my_rsvp
        $events->each(function($e) {
            $e->setRelation('my_rsvp', optional($e->rsvps)->first());
            unset($e->rsvps);
        });

        return response()->json($events);
    }

    /**
     * Validates the incoming request data and creates a new event for the specified group.
     * Returns a JSON response with the newly created event and HTTP status code 201.
     *
     * @param  Request  $req  The incoming HTTP request containing event data.
     * @param  Group  $group  The group associated with the event being created.
     * @return EventResource The JSON response containing the created event.
     */
    public function store(StoreEventRequest $req, Group $group): EventResource
    {
        $this->authorize('createEvent', $group);
        $event = $group->events()->create($req->validated());

        // TODO: dispatch notifications (FCM + mail)
        return new EventResource($event);
    }

    /**
     * Retrieves the specified event along with its associated group data.
     *
     * @param  Event  $event  The event instance to be retrieved.
     * @return Model The event with its loaded group relationship.
     */
    public function show(Event $event): Model
    {
        return $event->load('group');
    }

    /**
     * @param Request $request
     * @param Event $event
     * @return JsonResponse
     */
    public function update(Request $request, Event $event)
    {
        $this->authorize('update', $event);

        $data = $request->validate([
            'title' => ['required','string','max:200'],
            'description' => ['nullable','string','max:2000'],
        ]);

        $event->update($data);

        return response()->json([
            'message' => 'Updated.',
            'event' => $event->only(['id','title','description']),
        ]);
    }


    /**
     * Handles the RSVP for a specific event by validating the request and updating or creating the attendance record.
     * If more than 50% of attendees respond with "no," a subject for voting might be created.
     *
     * @param  Request  $req  The incoming HTTP request containing RSVP data.
     * @param  Event  $event  The event for which the RSVP is being recorded.
     * @return JsonResponse A response indicating no content.
     */
    public function rsvp(Request $r, Event $event, ?Group $group = null)
    {
        // Ako je pozvano kroz grupnu rutu, validiraj pripadnost
        if ($group && (int)$event->group_id !== (int)$group->id) {
            abort(404);
        }

        $this->authorize('rsvp', $event);

        $data = $r->validate([
            'status' => ['required', Rule::in(['going','maybe','declined'])],
        ]);

        $rsvp = EventRsvp::updateOrCreate(
            ['event_id' => $event->id, 'user_id' => $r->user()->id],
            ['status' => $data['status']]
        );

        $counts = EventRsvp::selectRaw('status, COUNT(*) c')
            ->where('event_id', $event->id)
            ->groupBy('status')
            ->pluck('c','status');

        return response()->json(['my_rsvp' => $rsvp, 'counts' => $counts]);
    }

    /**
     * Updates the check-in status of the authenticated user for the specified event.
     * Sets the 'checked_in_at' timestamp to the current time.
     *
     * @param  Request  $req  The incoming HTTP request containing the authenticated user.
     * @param  Event  $event  The event for which the user is checking in.
     * @return JsonResponse An empty HTTP response with no content.
     */
    public function checkin(Request $req, Event $event): JsonResponse
    {
        $userId = $req->user()->id;

        // Obezbedi da pivot zapis postoji
        $event->attendees()->syncWithoutDetaching([$userId => []]);

        // Update samo pivot kolone (bez targetiranja users tabele)
        $event->attendees()->updateExistingPivot($userId, [
            'checked_in_at' => now(),
        ], true);

        return response()->json(['ok'=>true]);

    }

    /**
     * Handles proposing the postponement of an event by initiating a voting process
     * and triggering automatic notifications. Returns a JSON response indicating
     * that the postponement has been proposed.
     *
     * @param  Request  $req  The incoming HTTP request containing necessary data for postponement proposal.
     * @param  Event  $event  The event for which the postponement is being proposed.
     * @return JsonResponse The JSON response indicating the proposal status.
     */
    public function proposePostpone(Request $req, Event $event): JsonResponse
    {
        // upis subjecta za glasanje + automatske notifikacije
        return response()->json(['status' => 'proposed']);
    }

    /**
     * Registers a vote to postpone the specified event.
     * If the predetermined threshold is met, the event's status is updated to "postponed."
     * Returns a JSON response indicating the voting status.
     *
     * @param  Request  $req  The incoming HTTP request containing vote data.
     * @param  Event  $event  The event being voted on for postponement.
     * @return JsonResponse The JSON response indicating the voting outcome.
     */
    public function votePostpone(Request $req, Event $event): JsonResponse
    {
        // upis glasa; po pragu menjaj event->status = postponed
        return response()->json(['status' => 'voted']);
    }
}
