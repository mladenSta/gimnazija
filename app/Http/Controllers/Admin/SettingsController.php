<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SocialSettingsRequest;
use App\Services\Social\SocialAccounts;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Podešavanja (admin only): credentials for publishing posts on the school's
 * Facebook, Instagram and LinkedIn.
 */
class SettingsController extends Controller
{
    public function edit(SocialAccounts $accounts): Response
    {
        return Inertia::render('admin/settings/Index', [
            'networks' => $accounts->forForm(),
            'siteUrl' => rtrim((string) config('app.url'), '/'),
        ]);
    }

    public function update(SocialSettingsRequest $request, SocialAccounts $accounts): RedirectResponse
    {
        /** @var array<string, array{account_id?: string|null, token?: string|null, clear?: bool}> $networks */
        $networks = $request->validated('networks');
        $accounts->save($networks);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Podešavanja su sačuvana.']);

        return to_route('admin.settings.edit');
    }
}
