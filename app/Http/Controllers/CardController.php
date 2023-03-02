<?php

namespace App\Http\Controllers;

use App\Models\MarvelSnapCard;

class CardController extends Controller
{
    public function show(string $cardName)
    {
        // For some reason, I can't seem to get `with` and `load` calls to work

        // For example
        // $card = MarvelSnapCard::query()
        //     ->with(['variants'])
        //     ->where('name', '=', $cardName)
        //     ->get();

        $card = MarvelSnapCard::query()->where('name', '=', $cardName)->first();
        
        // Another example
        // $card->load(['variants']);

        return view('card.variants', [
            'card' => $card,
            'variants' => $card->variants,
        ]);
    }
}
