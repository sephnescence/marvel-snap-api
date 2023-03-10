<?php

namespace App\Http\Controllers;

use App\Models\MarvelSnapCard;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CardController extends Controller
{
    public function show(string $cardName)
    {
        // For some reason, I can't seem to get `with` and `load` calls to work - BTTODO

        // For example
        // $card = MarvelSnapCard::query()
        //     ->with(['variants'])
        //     ->where('name', '=', $cardName)
        //     ->get();

        /** @var MarvelSnapCard $card */
        $card = MarvelSnapCard::query()->where('name', '=', $cardName)->first();

        if ($card === null) {
            throw new ModelNotFoundException();
        }

        // Another example
        // $card->load(['variants']);

        return view('cards.single', [
            'card' => $card,
        ]);
    }
}
