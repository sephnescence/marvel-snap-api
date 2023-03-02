<?php

namespace App\Http\Controllers;

use App\Http\Services\DownloadSnapFanImagesService;
use App\Models\MarvelSnapCard;

class CardsController extends Controller
{
    public function all(DownloadSnapFanImagesService $downloadSnapFanImagesService)
    {
        // For some reason, I can't seem to get `with` and `load` calls to work - BTTODO

        // For example
        // $card = MarvelSnapCard::query()
        //     ->with(['variants'])
        //     ->where('name', '=', $cardName)
        //     ->get();

        /** @var MarvelSnapCard[] $cards */
        $cards = MarvelSnapCard::all()->sortBy('name');

        $blacklistedUrls = $downloadSnapFanImagesService->getBlacklistedUrls();

        return view('cards.all', [
            'cards' => $cards,
            'blacklistedUrls' => $blacklistedUrls,
        ]);
    }
}
