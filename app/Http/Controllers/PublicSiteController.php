<?php

namespace App\Http\Controllers;

use App\Models\PublicPage;
use App\Services\PublicSiteProfile;
use Illuminate\View\View;

class PublicSiteController extends Controller
{
    public function home(PublicSiteProfile $profile): View
    {
        $page = PublicPage::query()->published()->where('slug', 'home')->firstOrFail();

        return $this->render($page, $profile);
    }

    public function show(PublicPage $public_page, PublicSiteProfile $profile): View
    {
        $publicPage = $public_page;
        abort_unless($publicPage->status === 'published' && $publicPage->published_at !== null, 404);
        abort_if($publicPage->slug === 'home', 404);

        return $this->render($publicPage, $profile);
    }

    private function render(PublicPage $page, PublicSiteProfile $profile): View
    {
        $navigation = PublicPage::query()
            ->published()
            ->where('show_in_navigation', true)
            ->orderBy('navigation_order')
            ->get();

        return view('public.pages.show', [
            'page' => $page,
            'navigation' => $navigation,
            'siteProfile' => $profile->get(),
        ]);
    }
}
