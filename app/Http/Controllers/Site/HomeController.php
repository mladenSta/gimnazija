<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\AlumniTestimonial;
use App\Models\Event;
use App\Models\Gallery;
use App\Models\GalleryItem;
use App\Models\Post;
use App\Support\Seo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request, SearchController $search): Response
    {
        // WordPress-style search: /?s=term
        if ($request->has('s')) {
            return $search($request);
        }

        $slides = $this->items(Gallery::HOME_SLIDER);
        $top = $this->items(Gallery::HOME_TOP);
        $about = $this->items(Gallery::HOME_ABOUT);
        $bottom = $this->items(Gallery::HOME_BOTTOM, withTags: true);

        $filterTags = $bottom->flatMap(fn (GalleryItem $item) => $item->tags)
            ->unique('id')
            ->sortBy('name')
            ->values();

        $base = rtrim((string) config('app.url'), '/');

        return response()->view('site.home', [
            'slides' => $slides,
            'ribbon' => $top,
            'about' => $about->take(3),
            'alumniPhoto' => $top->get(3) ?? $top->first(),
            'posts' => Post::query()->published()->with(['featuredImage', 'categories'])->latest('published_at')->limit(5)->get(),
            'events' => Event::query()->upcoming()->limit(6)->get(),
            'testimonials' => AlumniTestimonial::query()->published()->with('photo')->limit(30)->get(),
            'gallery' => $bottom,
            'filterTags' => $filterTags,
            'seo' => Seo::make(
                image: $slides->first()?->media->url('large'),
                jsonLd: [
                    '@context' => 'https://schema.org',
                    '@type' => 'HighSchool',
                    'name' => 'JU Gimnazija „Jovan Dučić" Doboj',
                    'url' => $base.'/',
                    'telephone' => '+387 53 200 970',
                    'email' => 'ss38@skolers.org',
                    'address' => [
                        '@type' => 'PostalAddress',
                        'streetAddress' => 'Hilandarska 2',
                        'postalCode' => '74000',
                        'addressLocality' => 'Doboj',
                        'addressCountry' => 'BA',
                    ],
                    'sameAs' => ['https://www.facebook.com/profile.php?id=61573103333031'],
                ],
            ),
        ]);
    }

    /**
     * @return Collection<int, GalleryItem>
     */
    private function items(string $slug, bool $withTags = false): Collection
    {
        $gallery = Gallery::findBySlug($slug);

        if ($gallery === null) {
            return new Collection;
        }

        return $gallery->items()->with($withTags ? ['media', 'tags'] : ['media'])->get();
    }
}
