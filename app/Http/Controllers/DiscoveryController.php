<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ContentArticle;
use App\Models\Journal;
use App\Models\JournalArticle;
use App\Models\PublicPage;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;

final class DiscoveryController extends Controller
{
    public function sitemap(): Response
    {
        $pages = PublicPage::query()->published()->get();
        $contentArticles = Schema::hasTable('content_articles') ? ContentArticle::query()->published()->get() : collect();
        $journal = Schema::hasTable('journals') ? Journal::query()->where('status', 'active')->first() : null;
        $journalArticles = $journal?->isPubliclyLaunched() ? JournalArticle::query()->published()->get() : collect();

        return response()->view('discovery.sitemap', compact('pages', 'contentArticles', 'journalArticles'), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
