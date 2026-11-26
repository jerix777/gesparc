<?php

namespace App\Http\Controllers\Personnel;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;

/**
 * Page publique ouverte par le code QR d'une décision : confirme qu'elle existe et qu'elle est valide,
 * sans afficher d'information au-delà de ce que porte le document lui-même.
 */
class VerificationController extends Controller
{
    public function show(string $code)
    {
        $leave = LeaveRequest::withoutGlobalScopes()->with('staff', 'institution')
            ->where('verification_code', strtoupper($code))->where('statut', 'accordee')->first();

        return response()->view('personnel.verification', ['l' => $leave], $leave ? 200 : 404);
    }
}
