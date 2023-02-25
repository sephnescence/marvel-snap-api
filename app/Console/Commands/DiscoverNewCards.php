<?php

namespace App\Console\Commands;

use App\Http\Services\DiscoverSnapFanCardsService;
use Illuminate\Console\Command;

class DiscoverNewCards extends Command
{
    /** @var string $signature */
    protected $signature = 'marvel_snap:discover_new_cards';
    /** @var string $description */
    protected $description = 'Discover new cards';

    public function __construct(
        private DiscoverSnapFanCardsService $discoverSnapFanCardsService
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $this->discoverSnapFanCardsService->discoverNewCards();
    }
}
