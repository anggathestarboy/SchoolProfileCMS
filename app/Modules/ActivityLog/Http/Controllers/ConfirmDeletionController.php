<?php

namespace App\Modules\ActivityLog\Http\Controllers;

use App\Modules\ActivityLog\Models\ActivityLogDeletionRequest;
use Illuminate\Contracts\View\View;

/**
 * Where the emailed confirmation link in ConfirmActivityLogDeletion lands.
 * Sits behind auth+verified (see ActivityLogServiceProvider) so only a
 * logged-in superadmin can confirm — the token alone proves the request is
 * genuine, the session proves the clicker is actually a superadmin.
 * Confirming only flips the request's status; the real hard delete happens
 * later, in HardDeleteActivityLogYear, via the nightly
 * activity-log:process-deletions command.
 */
class ConfirmDeletionController
{
    public function __invoke(string $token): View
    {
        $hash = hash('sha256', $token);
        $request = ActivityLogDeletionRequest::where('token_hash', $hash)->first();

        $status = match (true) {
            ! auth()->user()->isSuper() => 'forbidden',
            ! $request => 'not_found',
            $request->status === 'executed' => 'already_executed',
            $request->status === 'confirmed' => 'already_confirmed',
            $request->isExpired() => 'expired',
            default => 'confirmed',
        };

        if ($status === 'confirmed') {
            $request->update(['status' => 'confirmed', 'confirmed_at' => now()]);
        }

        return view('activity-log::confirm-delete', [
            'status' => $status,
            'year' => $request->year ?? null,
        ]);
    }
}
