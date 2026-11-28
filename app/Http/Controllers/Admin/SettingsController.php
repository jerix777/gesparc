<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SettingsController extends Controller
{
    public function edit()
    {
        Gate::authorize('settings.manage');

        return view('admin.settings.edit', ['values' => Setting::values()]);
    }

    public function update(Request $request)
    {
        Gate::authorize('settings.manage');
        $data = $request->validate([
            'sigle' => ['required', 'string', 'max:60'],
            'nom' => ['nullable', 'string', 'max:190'],
        ]);
        Setting::store($data);
        AuditLog::record('parametres.modification', null, "Nom de l'application : {$data['sigle']}");

        return redirect()->route('admin.settings.edit')->with('ok', 'Paramètres enregistrés.');
    }
}
