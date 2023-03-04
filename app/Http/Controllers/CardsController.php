<?php

namespace App\Http\Controllers;

use App\Models\MarvelSnapCard;

class CardsController extends Controller
{
    public function all()
    {
        // For some reason, I can't seem to get `with` and `load` calls to work - BTTODO

        // For example
        // $card = MarvelSnapCard::query()
        //     ->with(['variants'])
        //     ->where('name', '=', $cardName)
        //     ->get();

        /** @var MarvelSnapCard[] $cards */
        $cards = MarvelSnapCard::all()->sortBy('name');

        return view('cards.all', [
            'cards' => $cards,
        ]);
    }
}
