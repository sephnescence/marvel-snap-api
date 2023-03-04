<?php

namespace App\Http\Services;

use App\Models\MarvelSnapCardVariant;
use Exception;
use Illuminate\Log\Logger;

class DownloadSnapFanImagesService {
    public function __construct(
        private Logger $log
    ) {
    }

    /**
     * There are 1359 variants, but I've noted that some are broken if I care to fix em upp
     * - Antman 8 
     * - Bishop 3
     * - Cyclops 7? Has no background at all. Probably not the end of the world, but weird. Snap fan has references to them but they don't work
     * - ShangChi variant 5 doesn't have a proper card art image
     * - There's a Polaris with an arm floating off to the side
     * - Baby She-Hulk is busted
     */
    public function downloadNewImages(): void
    {
        $variants = MarvelSnapCardVariant::all();

        foreach ($variants as $variant) {
            $variantData = $variant->snapfan_data['current']['data'];
            $variantInternalData = $variant->internal_data;
            $variantInternalData['downloads'] = [
                'SnapFanCard.webp' => $variantData['imageUrl'], // Variant base image
                'Logo.webp' => $variantData['imageComponents']['logoUrl'], // Variant base image
                'backgrounds' => [],
                'foregrounds' => [],
            ];

            $variantName = $variantData['key'];

            $variantImageDir = dirname(__FILE__)."/../../../resources/images/variants/{$variantName}";
            if (!is_dir($variantImageDir)) {
                mkdir($variantImageDir);
            }

            $downloads = [
                'SnapFanCard.webp' => $variantData['imageUrl'], // Variant base image
                'Logo.webp' => $variantData['imageComponents']['logoUrl'], // Variant base image
            ];

            foreach ($variantData['imageComponents']['backgroundUrls'] as $index => $backgroundUrl) {
                $downloadName = 'Background' . ($index+1) . '.webp'; // Hopefully that doesn't change
                $downloads[$downloadName] = $backgroundUrl;
                $variantInternalData['downloads']['backgrounds'][] = $downloadName;
            }

            foreach ($variantData['imageComponents']['foregroundUrls'] as $index => $foregroundUrl) {
                $downloadName = 'Foreground' . ($index+1) . '.webp'; // Hopefully that doesn't change
                $downloads[$downloadName] = $foregroundUrl;
                $variantInternalData['downloads']['foregrounds'][] = $downloadName;
            }

            foreach ($downloads as $downloadName => $downloadUrl) {
                $downloadLocation = $variantImageDir . '/' . $downloadName;
                if (file_exists($downloadLocation)) {
                    continue;
                }

                try {
                    $downloadData = file_get_contents($downloadUrl);

                    $variantDownloadHandle = fopen($downloadLocation, 'w');
                    fwrite($variantDownloadHandle, $downloadData);
                    fclose($variantDownloadHandle);
                } catch (Exception $e) {
                    dd($e->getMessage(), [
                        'tried' => $downloadUrl,
                        'variant' => $variantName,
                    ]);
                    break 2; // Likely just hit a 403 because the server has detected I've pulled too many files
                }

                usleep(200); // Try and avoid getting locked out of downloading from the server
            }

            $variant->internal_data = $variantInternalData;
            $variant->save();
        }
    }
}