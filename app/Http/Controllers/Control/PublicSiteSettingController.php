<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\SystemSetting;
use App\Services\AuditTrail;
use App\Services\PublicSiteProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PublicSiteSettingController extends Controller
{
    public function edit(string $locale, PublicSiteProfile $profile): View
    {
        return view('control.public_pages.settings', [
            'profile' => $profile->get(),
            'allowedLogos' => $profile->allowedLogos(),
        ]);
    }

    public function update(Request $request, string $locale, PublicSiteProfile $profile): RedirectResponse
    {
        $validated = $request->validate([
            'brand_name' => ['required', 'string', 'max:80'],
            'legal_name' => ['required', 'string', 'max:180'],
            'primary_logo' => ['required', Rule::in($profile->allowedLogos())],
            'contact_email' => ['required', 'email:rfc', 'max:180'],
            'registration_label' => ['required', 'string', 'max:120'],
            'registration_number' => ['required', 'string', 'max:80'],
            'announcement.ar' => ['nullable', 'string', 'max:240'],
            'announcement.en' => ['nullable', 'string', 'max:240'],
            'announcement.fr' => ['nullable', 'string', 'max:240'],
        ]);

        DB::transaction(function () use ($request, $validated, $profile): void {
            $organizationId = Organization::query()->where('is_root', true)->value('id');
            $setting = SystemSetting::query()->firstOrNew([
                'organization_id' => $organizationId,
                'key' => PublicSiteProfile::SETTING_KEY,
            ]);
            $old = $setting->exists ? ($setting->value ?? []) : [];
            $value = array_replace_recursive($profile->defaults(), $validated);

            $setting->fill([
                'group' => 'public-site',
                'value' => $value,
                'is_public' => true,
            ])->save();

            AuditTrail::record('public_site.settings_updated', $setting, $old, $value, [], $request->user()->id);
        });

        return back()->with('success', trans('public_site.control.settings_saved'));
    }
}
