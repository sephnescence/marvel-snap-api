<?php

namespace App\Http\Services;

use App\Models\MarvelSnapCardVariant;
use Exception;
use Illuminate\Log\Logger;

class DownloadSnapFanImagesService {
    private array $blacklistedUrls = [
        'https://game-assets.snap.fan/processed_source_images/Materials/Cards%2FAbsorbingMan_02%2FNoise01_Transparent_01.webp?v11', // AbsorbingMan 02
        'https://game-assets.snap.fan/processed_source_images/Materials/Cards%2FCyclops_07%2FCyclops+-+%28Cyclops+%231+Skottie+Young+Variant+Cover%29_Background_01.webp?v11', // Cyclops 07
        'https://game-assets.snap.fan/processed_source_images/Materials/Cards%2FDeadpool_02%2FDeadpool_02_Background01.webp?v11', // Deadpool 02
        'https://game-assets.snap.fan/processed_source_images/Materials/Cards%2FDebrii%2FGrey1.webp?v11', // Debrii base card
        'https://game-assets.snap.fan/processed_source_images/Materials/Cards%2FElektra_03%2FElektra_03_Background01.webp?v11', // Elektra 03
        'https://game-assets.snap.fan/processed_source_images/Materials/Cards%2FGhostRider_07%2FGrey1.webp?v11', // GhostRider 07
        'https://game-assets.snap.fan/processed_source_images/Materials/Cards%2FNickFury_09%2FNickFury_Background01.webp?v11', // NickFury 09
        'https://game-assets.snap.fan/processed_source_images/Materials/Cards%2FNickFury_09%2FNickFury_Background02.webp?v11', // NickFury 09
        'https://game-assets.snap.fan/processed_source_images/Materials/Cards%2FNightcrawler_03%2FGrey1.webp?v11', // Nightcrawler 03
        'https://game-assets.snap.fan/processed_source_images/Materials/Cards%2FPsylocke%2FNoise00_Transparent.webp?v11', // Psylocke base card
        'https://game-assets.snap.fan/processed_source_images/Baked/Cards/c9d2a4caaf1cba640a87131602f0234b?v11', // ShangChi 05
        'https://game-assets.snap.fan/processed_source_images/Materials/Cards%2FSunspot_02%2FGrey1.webp?v11', // Sunspot 02
        'https://game-assets.snap.fan/processed_source_images/Materials/Cards%2FThanos_06%2FClearSprite.webp?v11', // Thanos 06
        'https://game-assets.snap.fan/processed_source_images/Materials/Cards%2FTheThing_08%2FGrey1.webp?v11', // TheThing 08
        'https://game-assets.snap.fan/processed_source_images/Materials/Cards%2FVenom_03%2FVenom+-+%28Venom+%23150+%28Skottie+Young+Variant+Cover%29%29_Background_01.webp?v11', // Venom 03
    ];

    public function __construct(
        private Logger $log
    ) {
    }

    /**
     * There are 1359 variants, but I've noted that some are broken if I care to fix em upp
     * - Antman 8 
     * - Bishop 3
     * - Cyclops 7? Has no background at all. Probably not the end of the world, but weird. Snap fan has references to them but they don't work
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
                if (in_array($backgroundUrl, $this->blacklistedUrls)) {
                    continue; // These files don't even exist on the real site... 15 out of thousands. Not bad, really
                }
                $downloadName = 'Background' . ($index+1) . '.webp'; // Hopefully that doesn't change
                $downloads[$downloadName] = $backgroundUrl;
                $variantInternalData['downloads']['backgrounds'][] = $downloadName;
            }

            foreach ($variantData['imageComponents']['foregroundUrls'] as $index => $foregroundUrl) {
                if (in_array($foregroundUrl, $this->blacklistedUrls)) {
                    continue; // These files don't even exist on the real site... 15 out of thousands. Not bad, really
                }
                $downloadName = 'Foreground' . ($index+1) . '.webp'; // Hopefully that doesn't change
                $downloads[$downloadName] = $foregroundUrl;
                $variantInternalData['downloads']['foregrounds'][] = $downloadName;
            }

            foreach ($downloads as $downloadName => $downloadUrl) {
                if (in_array($downloadUrl, $this->blacklistedUrls)) {
                    continue; // These files don't even exist on the real site... 15 out of thousands. Not bad, really
                }
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