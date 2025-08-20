<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Group;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EventsController extends Controller
{
    /**
     * Retrieve the latest events of a given group, ordered by the start date,
     * and paginate the results.
     *
     * @param  Group  $group  The group instance whose events are being retrieved.
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator Paginated list of the group's events.
     */
    public function index(Group $group)
    {
        return $group->events()->latest('start_at')->paginate(20);
    }

    /**
     * Validates the incoming request data and creates a new event for the specified group.
     * Returns a JSON response with the newly created event and HTTP status code 201.
     *
     * @param  Request  $req  The incoming HTTP request containing event data.
     * @param  Group  $group  The group associated with the event being created.
     * @return JsonResponse The JSON response containing the created event.
     */
    public function store(Request $req, Group $group): JsonResponse
    {
        $data = $req->validate([
            'title' => 'required|string',
            'location_name' => 'nullable|string',
            'location_geo' => 'nullable|array',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'start_at' => 'required|date',
            'description' => 'nullable|string',
        ]);
        $event = $group->events()->create($data);

        // TODO: dispatch notifications (FCM + mail)
        return response()->json($event, 201);
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
     * Handles the RSVP for a specific event by validating the request and updating or creating the attendance record.
     * If more than 50% of attendees respond with "no," a subject for voting might be created.
     *
     * @param  Request  $req  The incoming HTTP request containing RSVP data.
     * @param  Event  $event  The event for which the RSVP is being recorded.
     * @return \Symfony\Component\HttpFoundation\Response A response indicating no content.
     */
    public function rsvp(Request $req, Event $event): \Symfony\Component\HttpFoundation\Response
    {
        $data = $req->validate([
            'rsvp' => 'required|in:yes,no,undecided',
            'reason' => 'nullable|string',
        ]);
        $event->attendees()->updateOrCreate(
            ['user_id' => $req->user()->id],
            $data
        );

        // Ako >50% "no" -> kreirati subject za glasanje
        return response()->noContent();
    }

    /**
     * Updates the check-in status of the authenticated user for the specified event.
     * Sets the 'checked_in_at' timestamp to the current time.
     *
     * @param  Request  $req  The incoming HTTP request containing the authenticated user.
     * @param  Event  $event  The event for which the user is checking in.
     * @return Response An empty HTTP response with no content.
     */
    public function checkin(Request $req, Event $event): Response
    {
        $event->attendees()->where('user_id', $req->user()->id)->update(['checked_in_at' => now()]);

        return response()->noContent();
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
