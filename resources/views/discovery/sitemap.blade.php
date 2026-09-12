{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach(['ar','en','fr'] as $locale)<url><loc>{{ route('public.home',['locale'=>$locale]) }}</loc><changefreq>weekly</changefreq></url>@endforeach
@foreach($pages as $page)@if($page->slug!=='home')@foreach(['ar','en','fr'] as $locale)<url><loc>{{ $page->template==='entity' ? route('public.entities.show',['locale'=>$locale,'entity'=>str($page->slug)->after('entity-')]) : route('public.pages.show',['locale'=>$locale,'public_page'=>$page]) }}</loc><lastmod>{{ $page->updated_at?->toAtomString() }}</lastmod></url>@endforeach @endif @endforeach
@foreach(['ar','en','fr'] as $locale)<url><loc>{{ route('public.articles.index',['locale'=>$locale]) }}</loc><changefreq>daily</changefreq></url>@endforeach
@foreach($contentArticles as $article)@foreach(['ar','en','fr'] as $locale)<url><loc>{{ route('public.articles.show',['locale'=>$locale,'article'=>$article]) }}</loc><lastmod>{{ $article->updated_at?->toAtomString() }}</lastmod></url>@endforeach @endforeach
@if($journalArticles->isNotEmpty())@foreach(['ar','en','fr'] as $locale)<url><loc>{{ route('journal.public.index',['locale'=>$locale]) }}</loc><changefreq>weekly</changefreq></url>@endforeach @endif
@foreach($journalArticles as $article)@foreach(['ar','en','fr'] as $locale)<url><loc>{{ route('journal.public.articles.show',['locale'=>$locale,'article'=>$article]) }}</loc><lastmod>{{ $article->published_at?->toAtomString() }}</lastmod></url>@endforeach @endforeach
</urlset>
