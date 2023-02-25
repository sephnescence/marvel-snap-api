<?php

namespace App\Http\Services;

use App\Models\MarvelSnapCard;
use App\Models\MarvelSnapCardSeries;
use Carbon\Carbon;
use Illuminate\Log\Logger;

class DiscoverSnapFanCardsService {
    public function __construct(
        private Logger $log,
        private SnapFanService $snapFanService//,
    ) {
    }

    public function discoverNewCards(): bool
    {
        $snapFanCards = $this->snapFanService->discoverCards();

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
             * If the card doesn't exist at all, this is pretty simple
             *  - Find or create the marvel_snap_card_series row that the card belongs to - done
             *  - Create the marvel_snap_cards row - done
             *  - Create the marvel_snap_card_card_series row - BTTODO
             * 
             * Over time, cards will change data, like series, cost, or power
             * - We must detect if the series has changed. If so - BTTODO
             *  - We need to end the lifespan of the current marvel_snap_card_card_series entry - BTTODO
             *  - We need to make a new marvel_snap_card_card_series entry - BTTODO
             * 
             * - We must detect if new variants have been added - BTTODO
             * - We can detect that cost or power, etc. has been updated too - BTTODO
             */
            $cardName = $snapFanCard['key'];
            $series = $snapFanCard['sourceLabel'];

            $marvelSnapCardSeries = $this->findOrCreateMarvelSnapCardSeries($series);
            $marvelSnapCard = $this->findOrCreateMarvelSnapCard($cardName, $snapFanCard);

            $cardSnapFanData = $marvelSnapCard->snapfan_data;
            $currentSnapFanData = $cardSnapFanData['current']['data'];
            ksort($currentSnapFanData);

            $this->log->info(json_encode($currentSnapFanData));
            $this->log->info(json_encode($snapFanCard));

            $cardDataHasChanged = strcmp(
                json_encode($currentSnapFanData),
                json_encode($snapFanCard)
            );

            if ($cardDataHasChanged !== 0) {
                $snapFanDataObject = [
                    'date' => Carbon::now()->toString(),
                    'data' => $snapFanCard,
                ];

                $cardSnapFanData['history'][] = $snapFanDataObject;
                $cardSnapFanData['current'] = $snapFanDataObject;
                $marvelSnapCard->snapfan_data = $cardSnapFanData;
                $marvelSnapCard->save();
                $marvelSnapCard->fresh();
            }
        }
        
        return true;
    }

    private function findOrCreateMarvelSnapCardSeries(string $series): MarvelSnapCardSeries
    {
        return MarvelSnapCardSeries::firstOrCreate(
            [
                'name' => $series,
            ],
            [
                'name' => $series,
                'lifespan_start' => Carbon::now(),
                'lifespan_end' => Carbon::maxValue(),
            ],
        );
    }

    private function findOrCreateMarvelSnapCard(string $cardName, array $snapFanCard): MarvelSnapCard
    {
        $snapFanDataObject = [
            'date' => Carbon::now()->toString(),
            'data' => $snapFanCard,
        ];

        return MarvelSnapCard::firstOrCreate(
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
                'internal_data' => [
                    'downloads' => [], // BTTODO - Construct an array of background images, etc. that I'll need to download eventually
                ],
            ],
        );
    }
}