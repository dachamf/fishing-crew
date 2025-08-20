<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\DeleteAccountRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AccountController extends Controller
{
    // PATCH /api/v1/profile/password
    public function changePassword(ChangePasswordRequest $req)
    {
        $user = $req->user();
        $user->forceFill([
            'password' => Hash::make($req->validated()['password']),
        ])->save();

        // (opciono) odjavi sve ostale token-e
        $user->tokens()->where('id', '!=', optional($user->currentAccessToken())->id)->delete();

        return response()->noContent(); // 204
    }

    // DELETE /api/v1/account
    public function destroy(DeleteAccountRequest $req)
    {
        $user = $req->user();

        // Biznis pravilo: ako je owner neke grupe, blokiraj i traži transfer vlasništva
        $owns = DB::table('group_user')
            ->where('user_id', $user->id)
            ->where('role', 'owner')
            ->exists();

        if ($owns) {
            return response()->json([
                'ok' => false,
                'message' => 'You are the owner of one or more groups. Transfer ownership before deleting your account.',
            ], 422);
        }

        // Revoke all tokens
        $user->tokens()->delete();

        // Briši nalog (po potrebi SoftDeletes ako želiš)
        $user->delete();

        return response()->noContent(); // 204
    }
}
