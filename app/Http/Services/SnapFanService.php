<?php

namespace App\Http\Services;

use CurlHandle;
use Exception;
use Illuminate\Log\Logger;

class SnapFanService {
    // https://snap.fan/api/cards/?page=1
    // http://localhost/api/snap_fan_cards?page=1
    private string $baseUrl = 'http://localhost/api/snap_fan_cards';

    private array $snapFanCards = [];

    public function __construct(
        private Logger $log
    ) {
    }

    public function discoverCards(): array
    {
        $this->getSnapFanCardsWithIncrementalBackoff();
        
        return $this->snapFanCards;
    }

    private function getSnapFanCardsWithIncrementalBackoff()
    {
        $gotSnapFanCards = false;

        $attempt = 0;
        while (++$attempt <= 3) {
            try {
                $gotSnapFanCards = $this->getSnapFanCards($attempt);

                if ($gotSnapFanCards) {
                    $this->log->info(
                        'Successfully got Snap Fan cards',
                        [
                            'attempt' => $attempt,
                            'file' => __FILE__,
                            'line' => __LINE__,
                        ]
                    );

                    break;
                }
                
                sleep($attempt * 5);
            } catch (Exception $e) {
                $this->log->error(
                    'Error encountered while discovering cards',
                    [
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                        'file' => __FILE__,
                        'line' => __LINE__,
                    ]
                );

                return [];
            }
        }

        if (!$gotSnapFanCards) {
            $this->log->error(
                'Unable to discover cards after 30 seconds',
                [
                    'attempt' => $attempt,
                    'file' => __FILE__,
                    'line' => __LINE__,
                ]
            );

            return [];
        }
    }

    private function getSnapFanCards(int $attempt): bool
    {
        $page1CurlHandle = $this->getCurlHandle("{$this->baseUrl}?page=1");
        $page1ApiResponse = curl_exec($page1CurlHandle);

        if (curl_error($page1CurlHandle)) {
            $this->log->error(
                'Curl error encountered while figuring out how many pages of cards there are',
                [
                    'attempt' => $attempt,
                    'error' => curl_error($page1CurlHandle),
                    'file' => __FILE__,
                    'line' => __LINE__,
                ]
            );

            return false;
        }

        $page1ApiResponseJson = json_decode($page1ApiResponse, true);
        if (!$page1ApiResponseJson) {
            $this->log->error(
                'Json decode error encountered while figuring out how many pages of cards there are',
                [
                    'apiResponse' => $page1ApiResponse,
                    'attempt' => $attempt,
                    'file' => __FILE__,
                    'line' => __LINE__,
                ]
            );
        }

        $count = $page1ApiResponseJson['count']; // 203 as of 2023-02-24
        $this->log->info(
            'Total number of cards available',
            [
                'attempt' => $attempt,
                'count' => (string) $count,
                'file' => __FILE__,
                'line' => __LINE__,
            ]
        );

        $results = $page1ApiResponseJson['results'];
        $perPage = count($results); // 24 as of 2023-02-24
        $this->log->info(
            'Number of cards per page',
            [
                'attempt' => $attempt,
                'perPage' => (string) $perPage,
                'file' => __FILE__,
                'line' => __LINE__,
            ]
        );

        $totalPages = (int) ceil($count / $perPage);
        $this->log->info(
            'Number of pages expected',
            [
                'attempt' => $attempt,
                'totalPages' => (string) $totalPages,
                'file' => __FILE__,
                'line' => __LINE__,
            ]
        );

        // Despite having already fetched page 1, the point of this is to get a snapshot of all pages at once
        //  I'm not expecting the contents of the pages to change in Snap Fan's case, but I've found it's a
        //  more reliable way to ensure we don't have items appearing on multiple pages, and missing things
        //  that moved to an earlier page for example

        $curlHandles = [];
        for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++) {
            $pageXCurlHandle = $this->getCurlHandle("{$this->baseUrl}?page={$pageNumber}");
            $curlHandles[$pageNumber] = $pageXCurlHandle;
        }

        $multiCurlHandle = curl_multi_init();
        foreach ($curlHandles as $curlHandle) {
            curl_multi_add_handle($multiCurlHandle, $curlHandle);
        }

        $executingMultiCurl = null;
        $status = CURLM_OK;
        do {
            $status = curl_multi_exec($multiCurlHandle, $executingMultiCurl);
        } while ($executingMultiCurl && $status === CURLM_OK);

        if ($status != CURLM_OK) {
            $this->log->error(
                'Error executing multi curl exec',
                [
                    'attempt' => $attempt,
                    'error' => curl_multi_strerror($status),
                    'file' => __FILE__,
                    'line' => __LINE__,
                ]
            );

            return false;
        }

        foreach ($curlHandles as $curlHandle) {
            curl_multi_remove_handle($multiCurlHandle, $curlHandle);
        }
        curl_multi_close($multiCurlHandle);

        $pageResults = [];
        foreach ($curlHandles as $pageNumber => $curlHandle) {
            $apiResponse = curl_multi_getcontent($curlHandle);
            $apiResponseJson = json_decode($apiResponse, true);

            if (!$apiResponseJson) {
                $this->log->error(
                    'Json decode error encountered while fetching a page of cards',
                    [
                        'attempt' => $attempt,
                        'apiResponse' => $apiResponse,
                        'file' => __FILE__,
                        'line' => __LINE__,
                    ]
                );

                return false;
            }

            $pageResults[$pageNumber] = $apiResponseJson;
        }

        foreach ($pageResults as $pageNumber => $results) {
            $this->snapFanCards = array_merge($this->snapFanCards, $results['results'] ?? []);
        }

        return true;
    }

    private function getCurlHandle(string $url): CurlHandle
    {
        $curlHandle = curl_init($url);

        // From previous experience I set this to false because there are times where the third party's certificate lapses
        curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curlHandle, CURLOPT_SSL_VERIFYSTATUS, false);
        curl_setopt($curlHandle, CURLOPT_PROXY_SSL_VERIFYPEER, false);

        // This is required otherwise it goes to standard out
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);

        return $curlHandle;
    }
}