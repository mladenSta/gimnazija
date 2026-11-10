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

class ImportWordPress extends Command
{
    protected $signature = 'wp:import
        {--limit= : Import only the first N posts (for a trial run)}';

    protected $description = 'Import categories and posts (with photos) from the old WordPress database';

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

        $this->info('Kategorije…');
        $importer->importCategories();

        $this->info('Objave i fotografije…');
        $bar = $this->output->createProgressBar();
        $importer->importPosts(
            $this->option('limit') !== null ? (int) $this->option('limit') : null,
            function (int $done, int $total) use ($bar) {
                $bar->setMaxSteps($total);
                $bar->setProgress($done);
            },
        );
        $bar->finish();
        $this->newLine(2);

        SiteMenu::flush();

        $r = $importer->report;
        $this->table(['', 'Broj'], [
            ['Kategorije', $r['categories']],
            ['Nove objave', $r['posts_created']],
            ['Ažurirane objave', $r['posts_updated']],
            ['Fotografije u medijima (iz WordPressa)', $r['images']],
            ['Uklonjeni video/embed blokovi', $r['embeds_removed']],
            ['Fotografije kojih nema u uploads folderu', count($media->missing)],
        ]);

        if ($r['slugs_changed']) {
            $this->warn('Promijenjeni linkovi (stari link preusmjerava na novi):');
            foreach ($r['slugs_changed'] as $line) {
                $this->line("  {$line}");
            }
        }

        if ($media->missing) {
            $this->warn('Nedostaju fotografije (prvih 20):');
            foreach (array_slice(array_unique($media->missing), 0, 20) as $line) {
                $this->line("  {$line}");
            }
        }

        return self::SUCCESS;
    }
}
