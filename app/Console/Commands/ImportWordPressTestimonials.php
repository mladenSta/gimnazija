<?php

namespace App\Console\Commands;

use App\Services\Content\SlugGenerator;
use App\Services\WordPress\AttachmentIndex;
use App\Services\WordPress\ContentConverter;
use App\Services\WordPress\MediaImporter;
use App\Services\WordPress\WordPressImporter;
use App\Support\ContentSanitizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportWordPressTestimonials extends Command
{
    protected $signature = 'wp:import-testimonials';

    protected $description = 'Import alumni testimonials (name, title, text, photo) from the old WordPress site';

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

        $testimonials = $importer->importTestimonials();

        $this->table(['Redoslijed', 'Ime', 'Zanimanje', 'Fotografija'], array_map(fn ($t) => [
            $t->sort_order,
            $t->name,
            Str::limit((string) $t->profession, 50),
            $t->photo_media_id ? 'da' : 'NEMA',
        ], $testimonials));

        $this->info('Uvezeno poruka: '.count($testimonials).'.');

        if ($media->missing) {
            $this->warn('Nedostaju fotografije:');
            foreach (array_unique($media->missing) as $line) {
                $this->line("  {$line}");
            }
        }

        return self::SUCCESS;
    }
}
