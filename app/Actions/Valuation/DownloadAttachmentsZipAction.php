<?php

declare(strict_types=1);

namespace App\Actions\Valuation;

use App\Models\PropertyPicture;
use App\Models\User;
use App\Models\ValuationRequest;
use App\Support\Valuation\PropertyPictureMedia;
use App\Support\Valuation\ValuationActivity;
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

final class DownloadAttachmentsZipAction
{
    public function __construct(
        private readonly PropertyPictureMedia $media,
    ) {}

    public function execute(User $actor, ValuationRequest $request): StreamedResponse
    {
        Gate::forUser($actor)->authorize('downloadAttachments', $request);

        $request->loadMissing('property.pictures');
        $property = $request->property;
        $pictures = $property === null ? collect() : $property->pictures;

        if ($pictures->isEmpty()) {
            throw new RuntimeException(__('No attachments available for download.'));
        }

        $filename = 'valuation-'.$request->id.'-attachments.zip';

        return response()->streamDownload(function () use ($pictures, $actor, $request): void {
            $tmp = tempnam(sys_get_temp_dir(), 'valzip');
            if ($tmp === false) {
                throw new RuntimeException('Unable to create temporary zip file.');
            }

            $zip = new ZipArchive;
            if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
                @unlink($tmp);
                throw new RuntimeException('Unable to open zip archive.');
            }

            $added = 0;
            foreach ($pictures as $picture) {
                /** @var PropertyPicture $picture */
                $path = $this->media->absolutePath($picture);
                if ($path === null || ! is_file($path)) {
                    continue;
                }
                $entry = ($picture->filename ?: ('picture-'.$picture->id)).($picture->id ? '-'.$picture->id : '');
                $zip->addFile($path, $entry);
                $added++;
            }
            $zip->close();

            if ($added === 0) {
                @unlink($tmp);
                throw new RuntimeException(__('No attachment files found on disk.'));
            }

            ValuationActivity::log($actor, $request, 'attachments_zip_downloaded', 'Attachments ZIP downloaded', [
                'count' => $added,
            ]);

            readfile($tmp);
            @unlink($tmp);
        }, $filename, [
            'Content-Type' => 'application/zip',
        ]);
    }
}
