<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\GroupStoreRequest;
use App\Http\Requests\GroupUpdateRequest;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GroupsController extends Controller
{
    /**
     * GET /api/groups  -> grupe čiji je user član
     *
     * @return mixed
     */
    public function index(Request $req)
    {
        $user = $req->user();
        $groups = $user->groups()
            ->withCount(['users as members_count', 'events'])
            ->latest('groups.created_at')
            ->paginate(20);

        return $groups;
    }

    /**
     * POST /api/groups
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(GroupStoreRequest $req)
    {
        $user = $req->user();
        $group = Group::create($req->validated());

        // kreator postaje owner
        $group->users()->attach($user->id, ['role' => 'owner']);

        return response()->json($group->loadCount(['users as members_count', 'events']), 201);
    }

    /**
     * GET /api/groups/{group}
     *
     * @return Group
     */
    public function show(Request $req, Group $group)
    {
        abort_unless($group->isMember($req->user()->id), 403, 'Not a member of this group');

        return $group->loadCount(['users as members_count', 'events'])
            ->load(['users' => function ($q) {
                $q->select('users.id', 'users.name', 'users.email');
            }]);
    }

    // PUT/PATCH /api/groups/{group}
    public function update(GroupUpdateRequest $req, Group $group)
    {
        abort_unless($group->isOwner($req->user()->id), 403, 'Only owner can update the group');

        $group->update($req->validated());

        return $group->fresh()->loadCount(['users as members_count', 'events']);
    }

    /**
     * DELETE /api/groups/{group}
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $req, Group $group)
    {
        abort_unless($group->isOwner($req->user()->id), 403, 'Only owner can delete the group');

        DB::transaction(function () use ($group) {
            $group->users()->detach();
            $group->delete();
        });

        return response()->noContent();
    }

    /**
     *GET /api/groups/{group}/members
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function members(Request $req, Group $group)
    {
        abort_unless($group->isMember($req->user()->id), 403);

        $members = $group->users()
            ->select('users.id', 'users.name', 'users.email')
            ->withPivot('role')
            ->orderByRaw("FIELD(group_user.role, 'owner','moderator','member') asc")
            ->orderBy('users.name')
            ->get();

        return response()->json($members);
    }

    /**
     * POST /api/groups/{group}/invite   body: { user_id: n, role?: 'member'|'moderator' }
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function invite(Request $req, Group $group)
    {
        abort_unless($group->isModeratorOrOwner($req->user()->id), 403, 'Moderator/Owner only');

        $data = $req->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['nullable', 'in:member,moderator'],
        ]);

        // već član?
        $exists = $group->users()->where('users.id', $data['user_id'])->exists();
        if ($exists) {
            return response()->json(['message' => 'User is already a member'], 422);
        }

        $group->users()->attach($data['user_id'], ['role' => $data['role'] ?? 'member']);

        // (opciono) TODO: poslati notifikaciju pozvanom korisniku

        return response()->json(['status' => 'invited'], 201);
    }
}
