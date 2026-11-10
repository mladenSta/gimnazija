<?php

namespace App\Console\Commands;

use App\Services\Content\SlugGenerator;
use App\Services\WordPress\AttachmentIndex;
use App\Services\WordPress\ContentConverter;
use App\Services\WordPress\MediaImporter;
use App\Services\WordPress\WordPressImporter;
use App\Support\ContentSanitizer;
use App\Support\SiteMenu;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportWordPressPages extends Command
{
    protected $signature = 'wp:import-pages
        {--menu=main-menu : WordPress menu whose pages are imported and which becomes the main menu}
        {--also=linkovi : Other WordPress menus whose pages are imported too (comma separated)}
        {--no-menu : Import the pages only, leave the main menu as it is}';

    protected $description = 'Import the pages linked from the old WordPress menus (text + photos) and rebuild the main menu';

    public function handle(): int
    {
        $wp = DB::connection('wordpress');

        try {
            $wp->getPdo();
        } catch (\Throwable $e) {
            $this->error('Nema veze sa WordPress bazom: '.$e->getMessage());

            return self::FAILURE;
        }

        $attachments = new AttachmentIndex($wp);
        $media = new MediaImporter($attachments);
        $importer = new WordPressImporter(
            $wp,
            new ContentConverter(app(ContentSanitizer::class), $attachments, (string) config('cms.wordpress.url')),
            $media,
            app(SlugGenerator::class),
        );

        $menu = (string) $this->option('menu');
        $menus = array_values(array_filter([$menu, ...array_map('trim', explode(',', (string) $this->option('also')))]));

        $this->info('Kategorije…');
        $importer->importCategories(); // menu items may point to categories

        $this->info('Stranice…');
        $pages = $importer->importPages($importer->menuPageIds($menus));

        $this->table(['Stranica', 'Link', 'Fotografije', 'Tekst'], array_map(fn ($page) => [
            $page->title,
            "/{$page->slug}/",
            $page->galleryImages()->count(),
            filled(strip_tags((string) $page->content)) ? 'da' : 'NEMA',
        ], $pages));

        if (! $this->option('no-menu')) {
            $count = $importer->importMenu($menu);
            $this->info("Glavni meni zamijenjen ({$count} stavki).");
        }

        SiteMenu::flush();

        $r = $importer->report;
        $this->line("Nove stranice: {$r['pages_created']}, ažurirane: {$r['pages_updated']}, uklonjeni video blokovi: {$r['embeds_removed']}.");

        if ($media->missing) {
            $this->warn('Nedostaju fotografije:');
            foreach (array_unique($media->missing) as $line) {
                $this->line("  {$line}");
            }
        }

        return self::SUCCESS;
    }
}
