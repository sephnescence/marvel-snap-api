<?php

namespace App\Http\Controllers\SnapFanCards;

use App\Http\Controllers\Controller;
use App\Http\Requests\SnapFanCardsRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SnapFanCards extends Controller
{
    public function page(
        SnapFanCardsRequest $request
    ) {
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
            return json_decode($content, true);
        }

        return [];
    }
}
