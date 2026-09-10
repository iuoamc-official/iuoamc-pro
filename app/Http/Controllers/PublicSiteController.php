<?php

namespace App\Http\Controllers;

use App\Models\PublicPage;
use App\Services\PublicSiteProfile;
use Illuminate\View\View;

class PublicSiteController extends Controller
{
    public function home(string $locale, PublicSiteProfile $profile): View
    {
        $page = PublicPage::query()->published()->where('slug', 'home')->firstOrFail();

        return $this->render($page, $profile);
    }

    public function show(string $locale, PublicPage $public_page, PublicSiteProfile $profile): View
    {
        $publicPage = $public_page;
        abort_unless($publicPage->status === 'published' && $publicPage->published_at !== null, 404);
        abort_if($publicPage->slug === 'home', 404);

        return $this->render($publicPage, $profile);
    }

    public function entity(string $locale, string $entity, PublicSiteProfile $profile): View
    {
        $siteProfile = $profile->get();
        $entityProfile = collect($siteProfile['entities'])->firstWhere('slug', $entity);
        abort_if($entityProfile === null, 404);

        $page = PublicPage::query()
            ->published()
            ->where('slug', 'entity-'.$entity)
            ->firstOrFail();

        return $this->render($page, $profile, ['entity' => $entityProfile]);
    }

    /** @param array<string, mixed> $extra */
    private function render(PublicPage $page, PublicSiteProfile $profile, array $extra = []): View
    {
        $navigation = PublicPage::query()
            ->published()
            ->where('show_in_navigation', true)
            ->orderBy('navigation_order')
            ->get();

        $view = match ($page->template) {
            'entity' => 'public.entities.show',
            'leadership' => 'public.leadership.show',
            default => 'public.pages.show',
        };

        return view($view, array_merge([
            'page' => $page,
            'navigation' => $navigation,
            'siteProfile' => $profile->get(),
        ], $extra));
    }
}
