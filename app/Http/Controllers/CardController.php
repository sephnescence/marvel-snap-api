<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CardController extends Controller
{
    public function show(
        Request $request
    )
    {
        dd('show', $request->all());
    }
}
