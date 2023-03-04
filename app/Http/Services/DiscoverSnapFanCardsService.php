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
             * Not sure if bug or not, but it seems that snapfan changes the url for imageUrl frequently, but I don't think it has
             *  actually changed. It did legitimately once though, when they randomly removed all their watermarks. I thought the
             *  change that was picked up again was just them adding in the watermark back, but it wasn't the case
             *      This causes the code to download _all_ the files for that variant. It might be worth adding a list
             *          of urls that have already been downloaded, instead, or just don't download imageUrl ever again
             *      I like the concept of not downloading it again
             *      It's possible that cards having their text, season, or power changed will also cause this
             * 
             * Try and figure out how to add card border colours - BTTODO
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
                'lifespan_start' => $this->getLifespanStart(),
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
            'date' => $this->getLifespanStart(),
            'data' => $snapFanCard,
        ];

        /** @var MarvelSnapCard $cardModel */
        $cardModel = MarvelSnapCard::firstOrCreate(
            [
                'name' => $cardName,
            ],
            [
                'name' => $cardName,
                'lifespan_start' => $this->getLifespanStart(),
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
            $marvelSnapCardCardSeries->lifespan_end = $this->getLifespanStart();
            $marvelSnapCardCardSeries->save();

            $createNewCardCardSeriesRecord = true;
        }

        if ($createNewCardCardSeriesRecord) {
            $marvelSnapCardCardSeries = new MarvelSnapCardCardSeries;
            $marvelSnapCardCardSeries->id = Uuid::uuid4()->toString();
            $marvelSnapCardCardSeries->marvel_snap_card_series_id = $snapFanCardSeries->id;
            $marvelSnapCardCardSeries->marvel_snap_card_id = $marvelSnapCard->id;
            $marvelSnapCardCardSeries->lifespan_start = $this->getLifespanStart();
            $marvelSnapCardCardSeries->lifespan_end = Carbon::maxValue();
            $marvelSnapCardCardSeries->created_at = $this->getLifespanStart();
            $marvelSnapCardCardSeries->updated_at = $this->getLifespanStart();
            $marvelSnapCardCardSeries->save();
        }
    }

    private function syncCardVariants(MarvelSnapCard $marvelSnapCard, array $snapFanCard): void
    {
        if (array_key_exists('variants', $snapFanCard) && is_array($snapFanCard['variants'])) {
            foreach ($snapFanCard['variants'] as $variant) {
                ksort($variant);
                
                $snapFanDataObject = [
                    'date' => $this->getLifespanStart(),
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
                        'lifespan_start' => $this->getLifespanStart(),
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

                // dd('bleh', [
                //     'id' => $variantModel->id,
                //     'changed?' => $variantDataHasChanged,
                //     // 'current' => array_keys($currentSnapFanData),
                //     // 'variant' => array_keys($variant),
                //     'artVariantSourceDefKey' => [
                //         $currentSnapFanData['artVariantSourceDefKey'],
                //         $variant['artVariantSourceDefKey'],
                //     ],
                //     'artists' => [
                //         $currentSnapFanData['artists'],
                //         $variant['artists'],
                //     ],
                //     'cardDefKey' => [
                //         $currentSnapFanData['cardDefKey'],
                //         $variant['cardDefKey'],
                //     ],
                //     'cost' => [
                //         $currentSnapFanData['cost'],
                //         $variant['cost'],
                //     ],
                //     'imageComponents' => [
                //         $currentSnapFanData['imageComponents'],
                //         $variant['imageComponents'],
                //     ],
                //     'imageUrl' => [
                //         $currentSnapFanData['imageUrl'],
                //         $variant['imageUrl'],
                //     ],
                //     'isCardDefDisplayVariant' => [
                //         $currentSnapFanData['isCardDefDisplayVariant'],
                //         $variant['isCardDefDisplayVariant'],
                //     ],
                //     'key' => [
                //         $currentSnapFanData['key'],
                //         $variant['key'],
                //     ],
                //     'power' => [
                //         $currentSnapFanData['power'],
                //         $variant['power'],
                //     ],
                //     'sourceLabel' => [
                //         $currentSnapFanData['sourceLabel'],
                //         $variant['sourceLabel'],
                //     ],
                //     'url' => [
                //         $currentSnapFanData['url'],
                //         $variant['url'],
                //     ],
                //     'variantLabel' => [
                //         $currentSnapFanData['variantLabel'],
                //         $variant['variantLabel'],
                //     ],
                // ]);

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

    private function getLifespanStart(): string
    {
        return ($this->cacheDate ?? date('Y-m-d')) . ' 00:00+00';
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

        if (!$this->historicalImageAlreadyExists($datedHistoryDir . '/SnapFanCard.webp')) {
            $this->downloadImage($variantData['imageUrl'], $variantImageDir . '/SnapFanCard.webp');
            $this->downloadImage($variantData['imageUrl'], $datedHistoryDir . '/SnapFanCard.webp');
        }

        if (!$this->historicalImageAlreadyExists($datedHistoryDir . '/Logo.webp')) {
            $this->downloadImage($variantData['imageComponents']['logoUrl'], $variantImageDir . '/Logo.webp');
            $this->downloadImage($variantData['imageComponents']['logoUrl'], $datedHistoryDir . '/Logo.webp');
        }

        $backgroundNumber = 1;
        foreach ($variantData['imageComponents']['backgroundUrls'] as $index => $backgroundUrl) {
            $downloadName = 'Background' . $backgroundNumber . '.webp';
            $downloadLocation = $variantImageDir . '/' . $downloadName;

            if ($this->historicalImageAlreadyExists($datedHistoryDir . '/' . $downloadName)) {
                $internalData['backgrounds'][] = $downloadName;
                $backgroundNumber++;

                continue;
            }

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

            if ($this->historicalImageAlreadyExists($datedHistoryDir . '/' . $downloadName)) {
                $internalData['foregrounds'][] = $downloadName;
                $foregroundNumber++;

                continue;
            }

            if ($this->didDownloadImage($foregroundUrl, $downloadLocation)) {
                $internalData['foregrounds'][] = $downloadName;
                $this->downloadImage($foregroundUrl, $datedHistoryDir . '/' . $downloadName);
                $foregroundNumber++;
            }
        }

        return $internalData;
    }

    private function historicalImageAlreadyExists(string $downloadLocation): bool
    {
        return file_exists(($downloadLocation));
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