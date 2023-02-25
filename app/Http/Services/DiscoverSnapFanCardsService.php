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
             * 
             * Over time, season will also become defunct as their season pass cards move to series 5 - BTTODO
             * 
             * Once variants have been found, we need to queue the background images, foreground images, etc. in
             *  the `internal_data->'downloads'` column - BTTODO
             * 
             * Need to make a scheduled task that will try to go and download these images - BTTODO
             * 
             * It's worth copying the html from the marvel-snap-helper repo and making blade versions as well,
             *  just so they can easily be dumped here to see if they're working with variants, borders, etc. - BTTODO
             * 
             * Try and figure out how to add card border colours - BTTODO
             * 
             * Try and figure out how to add card effects. There are more than are listed on snap fan, including - BTTODO
             *  - White tone flare (e.g. my black widow) (adds white dots in the background and a couple of white swirls)
             *  - Green tone flare (e.g. my black widow) (adds green dots in the background and a couple of green swirls)
             *  - Red tone flare
             *  - Prism Finish
             *  - Foil Finish (It looks like rainbow tin foil has been crumpled up and applied)
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