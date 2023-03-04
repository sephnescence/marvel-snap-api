<?php

namespace App\Http\Services;

use App\Models\MarvelSnapCard;
use App\Models\MarvelSnapCardCardSeries;
use App\Models\MarvelSnapCardSeries;
use App\Models\MarvelSnapCardVariant;
use Carbon\Carbon;
use Exception;
use Illuminate\Log\Logger;
use Ramsey\Uuid\Uuid;

class DiscoverSnapFanCardsService {
    private ?string $cacheDate = null;
    
    public function __construct(
        private Logger $log,
        private SnapFanService $snapFanService
    ) {
    }

    public function setCacheDate(?string $cacheDate): self
    {
        $this->cacheDate = $cacheDate;

        return $this;
    }

    public function discoverNewCards(): bool
    {
        $snapFanCards = $this
            ->snapFanService
            ->setCacheDate($this->cacheDate)
            ->discoverCards();

        if (empty($snapFanCards)) {
            return false;
        }

        $this->doDiscoverNewCards($snapFanCards);

        return true;
    }

    private function doDiscoverNewCards(array $snapFanCards): bool
    {
        foreach ($snapFanCards as $snapFanCard) {
            ksort($snapFanCard);

            /**
             * There's a bug with discovering season changes. For example, every time I run ./vendor/bin/sail artisan marvel_snap:discover_new_cards
             *  Fix an issue where multiple new season entries get added for AgentCoulson for example. There are a few this applies to - done
             *  It was related to not checking the the lifespan was current
             * 
             * The snapfan watermarks were removed, which should prompt redownloading - BTTODO
             *  - The history index isn't being added correctly. e.g. It's adding a new record to the current date, instead of adding a new date - BTTODO
             * 
             * If the card doesn't exist at all, this is pretty simple
             *  - Find or create the marvel_snap_card_series row that the card belongs to - done
             *  - Create the marvel_snap_cards row - done
             *  - Create the marvel_snap_card_card_series row - done
             * 
             * Over time, cards will change data, like series, cost, or power
             * - We must detect if the series has changed. If so - done
             *  - We need to end the lifespan of the current marvel_snap_card_card_series entry - done
             *  - We need to make a new marvel_snap_card_card_series entry - done
             * 
             * - We must detect if new variants have been added
             * - We can detect that cost or power, etc. has been updated too
             * 
             * Over time, seasons will also become defunct as their season pass cards move to series 5 - BTTODO
             *  - Since there's an issue with the cookies when fetching from the api, I might need to manually create the files
             * 
             * Once variants have been found, we need to queue the background images, foreground images, etc. in
             *  the `internal_data->'downloads'` column - done
             * 
             * Need to make a service that will try to go and download these images - done
             * 
             * It's worth copying the html from the marvel-snap-helper repo and making blade versions as well,
             *  just so they can easily be dumped here to see if they're working with variants, borders, etc. - done
             * 
             * Don't attempt to load images if they're blacklisted in DownloadSnapFanImagesService - done
             * 
             * Try and figure out how to add card border colours - BTTODO
             * 
             * Figure out how to generate links to vite assets instead of relying on hard coded domains - done
             * 
             * Try and figure out how to add reveal effects and finishes. There are more than are listed on snap fan, including - BTTODO
             *  - White tone flare (e.g. my black widow) (adds white dots in the background and a couple of white swirls)
             *  - Green tone flare (e.g. my black widow) (adds green dots in the background and a couple of green swirls)
             *  - Rainbow tone flare (RockSlide Winter Variant)
             *  - Red tone flare (AgathaHarkness, Mystique)
             *  - Gold tone flare (Wasp)
             *  - Black tone flare (AgathaHarkness)
             *  - Blue tone flare (Enchantress, AmericaChavez)
             *  - Prism Finish (A bunch of seemingly random shapes shimmer around)
             *  - Foil Finish (It looks like rainbow tin foil has been crumpled up and applied)
             *  - White Glimmer Flare (AmericaChavez)
             *  - Black Glimmer Flare (ShangChi)
             *  - Green Glimmer Flare (ShangChi)
             *  - Red Glimmer Flare (AmericaChavez)
             *  - Rainbow Glimmer Flare (WhiteTiger Savage Land)
             *  - Gold Glimmer Flare (Zabu, BlueMarvel)
             *  - Misc - Elektra has purple rain in her base variant
             */
            $cardName = $snapFanCard['key'];
            $series = $snapFanCard['sourceLabel'];

            $snapFanCardSeries = $this->findOrCreateMarvelSnapCardSeries($series);
            $marvelSnapCard = $this->findOrCreateMarvelSnapCard($cardName, $snapFanCard);
            $marvelSnapCardSeries = $marvelSnapCard->currentCardCardSeries;

            $this->syncSeries(
                $marvelSnapCard,
                $snapFanCardSeries,
                $marvelSnapCardSeries
            );

            $this->syncCardVariants($marvelSnapCard, $snapFanCard);
        }
        
        return true;
    }

    private function findOrCreateMarvelSnapCardSeries(string $series): MarvelSnapCardSeries
    {
        /** @var MarvelSnapCardSeries $snapSeriesModel */
        $snapSeriesModel = MarvelSnapCardSeries::firstOrCreate(
            [
                'name' => $series,
            ],
            [
                'name' => $series,
                'lifespan_start' => Carbon::now(),
                'lifespan_end' => Carbon::maxValue(),
            ],
        );

        /** @var MarvelSnapCardSeries $snapSeriesModel */
        $snapSeriesModel = MarvelSnapCardSeries::where('name', '=', $series)
            ->first(); // $snapSeriesModel->id will be null if just created otherwise

        return $snapSeriesModel;
    }

    private function findOrCreateMarvelSnapCard(string $cardName, array $snapFanCard): MarvelSnapCard
    {
        $snapFanDataObject = [
            'date' => Carbon::now()->toString(),
            'data' => $snapFanCard,
        ];

        /** @var MarvelSnapCard $cardModel */
        $cardModel = MarvelSnapCard::firstOrCreate(
            [
                'name' => $cardName,
            ],
            [
                'name' => $cardName,
                'lifespan_start' => Carbon::now(),
                'lifespan_end' => Carbon::maxValue(),
                'snapfan_data' => [
                    'current' => $snapFanDataObject,
                    'history' => [
                        $snapFanDataObject,
                    ],
                ],
            ],
        );

        /** @var MarvelSnapCard $cardModel */
        $cardModel = MarvelSnapCard::where('name', '=', $cardName)
            ->first(); // $cardModel->id will be null if just created otherwise

        $cardSnapFanData = $cardModel->snapfan_data;
        $currentSnapFanData = $cardSnapFanData['current']['data'];
        ksort($currentSnapFanData);

        $this->log->info('Card info');
        $this->log->info(json_encode($currentSnapFanData));
        $this->log->info(json_encode($snapFanCard));

        $cardDataHasChanged = strcmp(
            json_encode($currentSnapFanData),
            json_encode($snapFanCard)
        );

        if ($cardDataHasChanged !== 0) {
            $cardSnapFanData['history'][] = $snapFanDataObject;
            $cardSnapFanData['current'] = $snapFanDataObject;
            $cardModel->snapfan_data = $cardSnapFanData;
            $cardModel->save();
            $cardModel->fresh();
        }

        return $cardModel;
    }

    private function syncSeries(
        MarvelSnapCard $marvelSnapCard,
        MarvelSnapCardSeries $snapFanCardSeries,
        ?MarvelSnapCardCardSeries $marvelSnapCardCardSeries
    ): void {
        $createNewCardCardSeriesRecord = $marvelSnapCardCardSeries === null;
        if (
            ($marvelSnapCardCardSeries instanceof MarvelSnapCardCardSeries)
            && $marvelSnapCardCardSeries->marvel_snap_card_series_id !== $snapFanCardSeries->id
        ) {
            $marvelSnapCardCardSeries->lifespan_end = Carbon::now();
            $marvelSnapCardCardSeries->save();

            $createNewCardCardSeriesRecord = true;
        }

        if ($createNewCardCardSeriesRecord) {
            $marvelSnapCardCardSeries = new MarvelSnapCardCardSeries;
            $marvelSnapCardCardSeries->id = Uuid::uuid4()->toString();
            $marvelSnapCardCardSeries->marvel_snap_card_series_id = $snapFanCardSeries->id;
            $marvelSnapCardCardSeries->marvel_snap_card_id = $marvelSnapCard->id;
            $marvelSnapCardCardSeries->lifespan_start = Carbon::now();
            $marvelSnapCardCardSeries->lifespan_end = Carbon::maxValue();
            $marvelSnapCardCardSeries->created_at = Carbon::now();
            $marvelSnapCardCardSeries->updated_at = Carbon::now();
            $marvelSnapCardCardSeries->save();
        }
    }

    private function syncCardVariants(MarvelSnapCard $marvelSnapCard, array $snapFanCard): void
    {
        if (array_key_exists('variants', $snapFanCard) && is_array($snapFanCard['variants'])) {
            foreach ($snapFanCard['variants'] as $variant) {
                ksort($variant);
                
                $snapFanDataObject = [
                    'date' => Carbon::now()->toString(),
                    'data' => $variant,
                ];

                /** @var MarvelSnapCardVariant $variantModel */
                $variantModel = MarvelSnapCardVariant::firstOrCreate(
                    [
                        'name' => $variant['key'],
                        'marvel_snap_card_id' => $marvelSnapCard->id,
                    ],
                    [
                        'name' => $variant['key'],
                        'marvel_snap_card_id' => $marvelSnapCard->id,
                        'lifespan_start' => Carbon::now(),
                        'lifespan_end' => Carbon::maxValue(),
                        'snapfan_data' => [
                            'current' => $snapFanDataObject,
                            'history' => [
                                $snapFanDataObject
                            ],
                        ],
                        'internal_data' => [
                            'downloads' => $this->getVariantInternalData($snapFanDataObject['data']),
                        ],
                    ],
                );

                /** @var MarvelSnapCardVariant $variantModel */
                $variantModel = MarvelSnapCardVariant::where('name', '=', $variant['key'])
                    ->where('marvel_snap_card_id', '=', $marvelSnapCard->id)
                    ->first(); // $variantModel->id will be null if just created otherwise

                $variantSnapFanData = $variantModel->snapfan_data;
                $currentSnapFanData = $variantSnapFanData['current']['data'];
                ksort($currentSnapFanData);

                $this->log->info('Variant info');
                $this->log->info(json_encode($currentSnapFanData));
                $this->log->info(json_encode($variant));

                $variantDataHasChanged = strcmp(
                    json_encode($currentSnapFanData),
                    json_encode($variant)
                );

                if ($variantDataHasChanged !== 0) {
                    $variantSnapFanData['history'][] = $snapFanDataObject;
                    $variantSnapFanData['current'] = $snapFanDataObject;
                    $cardSnapFanData['internal_data']['downloads'] = $this->getVariantInternalData($snapFanDataObject['data']);
                    $marvelSnapCard->snapfan_data = $variantSnapFanData;
                    $marvelSnapCard->save();
                    $marvelSnapCard->fresh();
                }
            }
        }
    }

    private function getVariantInternalData(array $variantData): array
    {
        $internalData = [
            'backgrounds' => [],
            'foregrounds' => [],
        ];

        $variantName = $variantData['key'];

        $variantImageDir = dirname(__FILE__)."/../../../resources/images/variants/{$variantName}";
        if (!is_dir($variantImageDir)) {
            mkdir($variantImageDir);
        }

        $historyDate = $this->cacheDate ?? date('Y-m-d');
        $datedHistoryDir = $variantImageDir . '/' . $historyDate;
        if (!is_dir($datedHistoryDir)) {
            mkdir($datedHistoryDir);
        }

        $this->downloadImage($variantData['imageUrl'], $variantImageDir . '/SnapFanCard.webp');
        $this->downloadImage($variantData['imageUrl'], $datedHistoryDir . '/SnapFanCard.webp');

        $this->downloadImage($variantData['imageComponents']['logoUrl'], $variantImageDir . '/Logo.webp');
        $this->downloadImage($variantData['imageComponents']['logoUrl'], $datedHistoryDir . '/Logo.webp');

        $backgroundNumber = 1;
        foreach ($variantData['imageComponents']['backgroundUrls'] as $index => $backgroundUrl) {
            $downloadName = 'Background' . $backgroundNumber . '.webp';
            $downloadLocation = $variantImageDir . '/' . $downloadName;
            if ($this->didDownloadImage($backgroundUrl, $downloadLocation)) {
                $internalData['backgrounds'][] = $downloadName;
                $this->downloadImage($backgroundUrl, $datedHistoryDir . '/' . $downloadName);
                $backgroundNumber++;
            }
        }

        $foregroundNumber = 1;
        foreach ($variantData['imageComponents']['foregroundUrls'] as $index => $foregroundUrl) {
            $downloadName = 'Foreground' . $foregroundNumber . '.webp';
            $downloadLocation = $variantImageDir . '/' . $downloadName;
            if ($this->didDownloadImage($foregroundUrl, $downloadLocation)) {
                $internalData['foregrounds'][] = $downloadName;
                $this->downloadImage($foregroundUrl, $datedHistoryDir . '/' . $downloadName);
                $foregroundNumber++;
            }
        }

        return $internalData;
    }

    private function downloadImage(string $downloadUrl, string $downloadLocation): bool
    {
        return $this->didDownloadImage($downloadUrl, $downloadLocation);
    }

    private function didDownloadImage(string $downloadUrl, string $downloadLocation): bool
    {
        try {
            $downloadData = file_get_contents($downloadUrl);
        } catch (Exception $e) {
            $this->log->error(
                'Failed to download an image',
                [
                    'downloadUrl' => $downloadUrl,
                    'error' => $e->getMessage(),
                    'file' => __FILE__,
                    'line' => __LINE__,
                ]
            );

            return false;
        }
        

        $variantDownloadHandle = fopen($downloadLocation, 'w');
        fwrite($variantDownloadHandle, $downloadData);
        fclose($variantDownloadHandle);

        return true;
    }
}