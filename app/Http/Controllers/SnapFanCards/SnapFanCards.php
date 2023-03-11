<?php

namespace App\Http\Controllers\SnapFanCards;

use App\Http\Controllers\Controller;
use App\Http\Requests\SnapFanCardsRequest;
use App\Models\MarvelSnapCard;
use Exception;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Vite;
use Illuminate\Validation\ValidationException;

class SnapFanCards extends Controller
{
    public function page(
        SnapFanCardsRequest $request,
        Logger $log
    ): array {
        try {
            $validated = $request->safe()->only(['page', 'date']);
            
            $page = $validated['page'];
            $date = $validated['date'] ?? '2023-02-24';
        } catch (ValidationException $e) {
            return [];
        }

        $pageFile = dirname(__FILE__) . "/../../../../snapfancache/{$date}/page{$page}.json";
        if (file_exists($pageFile)) {
            $content = file_get_contents($pageFile);
            try {
                return json_decode($content, true);
            } catch (Exception $e) {
                $log->error(
                    'There was an error decoding the json file',
                    [
                        'exception' => $e->getMessage(),
                        'content' => $content,
                        'pageFile' => $pageFile,
                        'file' => __FILE__,
                        'line' => __LINE__,
                    ]
                );

                return [];
            }
            
        }

        return [];
    }

    public function all(): array
    {
        $return = [];
        $cards = MarvelSnapCard::all()->sortBy('name');
        foreach ($cards as $card) {
            if (!$card->isWithinLifespan) {
                continue;
            }

            $cardAttributes = $card->attributesToArray();
            $cardReturn = [
                'name' => $cardAttributes['name'],
                'logo' => Vite::asset('resources/images/variants/' . $cardAttributes['name'] . '/Logo.webp'),
                'variants' => [],
            ];
            foreach ($card->variants as $variant) {
                $variantAttributes = $variant->attributesToArray();
                $cardReturn['variants'][$variantAttributes['name']] = [
                    'name' => $variantAttributes['name'],
                    'artists' => $variant->artists,
                    'downloads' => $variant->downloads,
                ];
            }
            $return[$cardReturn['name']] = $cardReturn;
        }

        return $return;
    }
}
