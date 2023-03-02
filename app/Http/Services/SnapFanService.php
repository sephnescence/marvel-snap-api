<?php

namespace App\Http\Services;

use CurlHandle;
use Exception;
use Illuminate\Log\Logger;

class SnapFanService {
    private ?string $cacheDate = null;

    // Switch to true to use ZenRows
    private bool $isProd = true;

    private int $attempt = 0;

    private array $snapFanCards = [];
    private array $pageResponseArrays = [];

    public function __construct(
        private Logger $log
    ) {
    }

    public function setCacheDate(?string $cacheDate): self
    {
        $this->cacheDate = $cacheDate;

        return $this;
    }

    public function discoverCards(): array
    {
        $this->getSnapFanCardsWithIncrementalBackoff();
        
        return $this->snapFanCards;
    }

    private function getSnapFanCardsWithIncrementalBackoff()
    {
        $gotSnapFanCards = false;

        $this->attempt = 0;
        while (++$this->attempt <= 3) {
            try {
                $gotSnapFanCards = $this->getSnapFanCards();

                if ($gotSnapFanCards) {
                    $this->log->info(
                        'Successfully got Snap Fan cards',
                        [
                            'attempt' => $this->attempt,
                            'file' => __FILE__,
                            'line' => __LINE__,
                        ]
                    );

                    break;
                }
                
                sleep($this->attempt * 5);
            } catch (Exception $e) {
                $this->log->error(
                    'Error encountered while discovering cards',
                    [
                        'attempt' => $this->attempt,
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
                    'attempt' => $this->attempt,
                    'file' => __FILE__,
                    'line' => __LINE__,
                ]
            );

            return [];
        }
    }

    private function getSnapFanCards(): bool
    {
        $totalPages = $this->getTotalPages();

        $curlHandles = [];
        // We don't need to start at page 1 because getTotalPages will already fetch it for us
        //  and add it to $this->snapFanCards
        for ($pageNumber = 2; $pageNumber <= $totalPages; $pageNumber++) {
            if ($this->pageIsAlreadyDownloaded($pageNumber)) {
                $this->log->info(
                    'Page was already download. Using a cached value. A new copy will be loaded tomorrow',
                    [
                        'attempt' => $this->attempt,
                        'pageNumber' => $pageNumber,
                        'file' => __FILE__,
                        'line' => __LINE__,
                    ]
                );

                continue;
            }

            $pageXCurlHandle = $this->getCurlHandle($pageNumber);
            $curlHandles[$pageNumber] = $pageXCurlHandle;

            $this->log->info(
                'Fetching a page',
                [
                    'attempt' => $this->attempt,
                    'pageNumber' => $pageNumber,
                    'file' => __FILE__,
                    'line' => __LINE__,
                ]
            );
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
                    'attempt' => $this->attempt,
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
                        'attempt' => $this->attempt,
                        'pageNumber' => $pageNumber,
                        'apiResponse' => $apiResponse,
                        'file' => __FILE__,
                        'line' => __LINE__,
                    ]
                );

                return false;
            }

            if (
                ($apiResponseJson['details'] ?? '') === 'The concurrency limit was reached. Please upgrade to a higher plan or slow down your requests to continue using the service.'
                || ($apiResponseJson['status'] ?? 0) === 429
            ) {
                $this->log->info(
                    'Unable to download a page as there were too many concurrent threads. Just run the artisan command again until you stop seeing this message',
                    [
                        'attempt' => $this->attempt,
                        'pageNumber' => $pageNumber,
                        'apiResponse' => $apiResponse,
                        'file' => __FILE__,
                        'line' => __LINE__,
                    ]
                );

                continue; // We don't want to save this page file
            }

            $pageResults[$pageNumber] = $apiResponseJson;
        }

        foreach ($pageResults as $pageNumber => $responseArray) {
            $this->savePageResponseArray($pageNumber, $responseArray);
            $this->snapFanCards = array_merge($this->snapFanCards, $results['results'] ?? []);
        }

        return true;
    }

    private function getTotalPages()
    {
        try {
            $firstPageResponseJson = $this->getFirstPage();

            $count = $firstPageResponseJson['count']; // 203 as of 2023-02-24
            $results = $firstPageResponseJson['results'];
            $perPage = count($results); // 24 as of 2023-02-24
            $totalPages = (int) ceil($count / $perPage); // 9 as of 2023-02-24
            
            $this->log->info(
                'Stats',
                [
                    'Total number of cards available' => (string) $count,
                    'Number of cards per page' => (string) $perPage,
                    'Number of pages expected' => (string) $totalPages,
                    'attempt' => $this->attempt,
                    'count' => (string) $count,
                    'file' => __FILE__,
                    'line' => __LINE__,
                ]
            );

            return $totalPages;
        } catch (Exception $e) {
            $this->log->error(
                'Failed to determine total number of pages',
                [
                    'attempt' => $this->attempt,
                    'count' => (string) $count,
                    'file' => __FILE__,
                    'line' => __LINE__,
                ]
            );

            return 0;
        }
    }

    private function getFirstPage()
    {
        if ($this->pageIsAlreadyDownloaded('1')) {
            $responseArray = $this->pageResponseArrays['1'];
        } else {
            $this->log->info(
                'Fetching first page',
                [
                    'attempt' => $this->attempt,
                    'pageNumber' => '1',
                    'file' => __FILE__,
                    'line' => __LINE__,
                ]
            );

            $firstPageCurlHandle = $this->getCurlHandle('1');
            $firstPageResponse = curl_exec($firstPageCurlHandle);

            if (curl_error($firstPageCurlHandle)) {
                $this->log->error(
                    'Curl error encountered while fetching the first page',
                    [
                        'attempt' => $this->attempt,
                        'error' => curl_error($firstPageCurlHandle),
                        'file' => __FILE__,
                        'line' => __LINE__,
                    ]
                );

                return false;
            }

            $responseArray = json_decode($firstPageResponse, true);
            $this->savePageResponseArray('1', $responseArray);
        }
        
        if (!$responseArray) {
            $this->log->error(
                'Json decode error encountered while fetching the first page',
                [
                    'apiResponse' => $firstPageResponse,
                    'attempt' => $this->attempt,
                    'file' => __FILE__,
                    'line' => __LINE__,
                ]
            );

            return [];
        }

        return $responseArray;
    }

    private function getCurlHandle(string $pageNumber): CurlHandle
    {
        $url = $this->getBaseUrl() . "?page={$pageNumber}";

        $curlHandle = curl_init($url);

        // From experience I set this to false because there are times where the third party's certificate lapses
        curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curlHandle, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curlHandle, CURLOPT_SSL_VERIFYSTATUS, false);
        curl_setopt($curlHandle, CURLOPT_PROXY_SSL_VERIFYPEER, false);
        curl_setopt($curlHandle, CURLOPT_FOLLOWLOCATION, true);

        // This is required otherwise it goes to standard out
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);

        // Not sure if this will work forever, my free account has 1,000 free requests for use within 14 days

        if ($this->isProd) {
            $zenRowsApiKey = env('SNAP_ZENROWS_API_KEY');
            curl_setopt($curlHandle, CURLOPT_PROXY, 'http://' . $zenRowsApiKey . ':@proxy.zenrows.com:8001');

            // The docs say to use this header, so I'll honour their request
            //  It's actually not necessary, but I seem to have only been able
            //  to do 5 simultaneous requests
            curl_setopt($curlHandle, CURLOPT_CUSTOMREQUEST, 'GET');
        }
        

        return $curlHandle;
    }

    private function getBaseUrl(): string
    {
        return $this->isProd
            ? 'https://snap.fan/api/cards/'
            : 'http://localhost/api/snap_fan_cards';
    }

    private function pageIsAlreadyDownloaded(string $pageNumber): bool
    {
        $date = $this->getDate();

        $pageFolder = dirname(__FILE__) . "/../../../snapfancache/{$date}";
        if (!is_dir($pageFolder)) {
            mkdir($pageFolder);

            return false;
        }

        $pageFile = dirname(__FILE__) . "/../../../snapfancache/{$date}/page{$pageNumber}.json";
        if (file_exists($pageFile)) {
            $content = file_get_contents($pageFile);
            $cards = json_decode($content, true);
            $this->pageResponseArrays[$pageNumber] = $cards;

            $this->snapFanCards = array_merge($this->snapFanCards, $cards['results'] ?? []);

            return true;
        }
        
        return false;
    }

    private function getDate(): string
    {
        if ($this->cacheDate !== null) {
            $this->isProd = false;
            return $this->cacheDate;
        }
        
        return $this->isProd
            ? date('Y-m-d')
            : '2023-02-24';
    }

    private function savePageResponseArray(string $pageNumber, array $results): void
    {
        $date = $this->getDate();

        $pageFile = dirname(__FILE__) . "/../../../snapfancache/{$date}/page{$pageNumber}.json";
        if (file_exists($pageFile)) {
            // Should never hit this
            return;
        }

        $pageFileHandle = fopen($pageFile, 'w');
        fwrite($pageFileHandle, json_encode($results));
        fclose($pageFileHandle);
    }
}