<?php

namespace App\Console\Commands;

use App\Http\Services\DownloadSnapFanImagesService;
use Illuminate\Console\Command;

class DownloadNewImages extends Command
{
    /** @var string $signature */
    protected $signature = 'marvel_snap:download_new_images';
    /** @var string $description */
    protected $description = 'Download new images';

    public function __construct(
        private DownloadSnapFanImagesService $downloadSnapFanImagesService
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        // BTTODO - Really need to be adding tests to this
        $this->downloadSnapFanImagesService->downloadNewImages();
    }
}
