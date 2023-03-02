<?php

namespace App\Http\Controllers;

use App\Models\MarvelSnapCard;

class CardsController extends Controller
{
    public function all()
    {
        $cards = MarvelSnapCard::all()->sortBy('name');

        return view('cards.variants', [
            'cards' => $cards,
        ]);
    }
}
