<?php

namespace App\Http\Services;

use GuzzleHttp\Client;

class SnapFanService {
    // https://snap.fan/api/cards/?page=1
    // http://localhost/api/snap_fan_cards?page=1
    private string $baseUrl = 'http://localhost/api/snap_fan_cards';

    public function test()
    {
        $chPage1 = curl_init("{$this->baseUrl}?page=1");

        // From previous experience I set this to false because there are times where the third party's certificate lapses
        curl_setopt($chPage1, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($chPage1, CURLOPT_SSL_VERIFYSTATUS, false);
        curl_setopt($chPage1, CURLOPT_PROXY_SSL_VERIFYPEER, false);

        // This is required otherwise it goes to standard out
        curl_setopt($chPage1, CURLOPT_RETURNTRANSFER, 1);

        $page1 = curl_exec($chPage1);

        if (curl_error($chPage1)) {
            dd('error');
            dd(curl_error($chPage1));
        }

        $page1Json = json_decode($page1, true);
        if (!$page1Json) {
            dd('Failed to json decode');
        }
        $count = $page1Json['count']; // 203 as of 20230224
        $results = $page1Json['results'];
        $perPage = count($results); // 24 as of 20230224

        $pages = (int) ceil($count / $perPage);

        // BTTODO - Up to here
    }

}