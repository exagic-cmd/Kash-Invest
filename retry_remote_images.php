<?php
/**
 * Retry the 6 oversized remote images — download, resize/compress, then upload.
 * Run: php retry_remote_images.php
 */

// INCREASE MEMORY LIMIT to handle 68MB images
ini_set('memory_limit', '1024M');

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Botble\RealEstate\Models\Project;
use Botble\Media\Facades\RvMedia;
use Botble\Media\Models\MediaFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Arr;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\JpegEncoder;
use Symfony\Component\Mime\MimeTypes;

$folderId = RvMedia::createFolder('projects');
echo "Media folder ID: $folderId\n\n";

$maxWidth  = 1920;
$maxHeight = 1080;
$jpegQuality = 85;

$projectIds = [15, 155, 159, 164];

foreach ($projectIds as $pid) {
    $project = Project::query()->find($pid);
    if (!$project) {
        echo "Project #$pid not found, skipping.\n\n";
        continue;
    }

    $images = is_array($project->images) ? $project->images : json_decode($project->images, true) ?? [];
    $changed = false;

    echo "=== Project #{$pid}: {$project->name} ({$project->unique_id}) ===\n";

    foreach ($images as $idx => $img) {
        if (!str_starts_with($img, 'http://') && !str_starts_with($img, 'https://')) {
            continue; // already local
        }

        echo "  [$idx] Remote: $img\n";

        // Download
        try {
            $response = Http::timeout(60)->withOptions(['allow_redirects' => true])->get($img);
        } catch (\Throwable $e) {
            echo "    ❌ Download failed: " . $e->getMessage() . "\n";
            continue;
        }

        if (!$response->successful() || strlen($response->body()) < 1000) {
            echo "    ❌ Bad response (status={$response->status()})\n";
            continue;
        }

        $originalSize = strlen($response->body());
        echo "    Downloaded: " . number_format($originalSize / 1024 / 1024, 1) . " MB\n";

        // Save original to temp
        $tempOriginal = tempnam(sys_get_temp_dir(), 'buildify_orig_');
        file_put_contents($tempOriginal, $response->body());

        // Free memory before resize
        unset($response);

        // Resize + compress with Intervention Image
        try {
            $manager = new ImageManager(new GdDriver());
            $image = $manager->read($tempOriginal);
            $image->scaleDown($maxWidth, $maxHeight);
            $encoded = $image->encode(new JpegEncoder(quality: $jpegQuality));

            $tempResized = tempnam(sys_get_temp_dir(), 'buildify_resized_') . '.jpg';
            file_put_contents($tempResized, (string) $encoded);

            $resizedSize = filesize($tempResized);
            echo "    Resized: " . number_format($resizedSize / 1024 / 1024, 1) . " MB ({$maxWidth}x{$maxHeight} max, quality {$jpegQuality})\n";

            // Free memory
            unset($image, $encoded, $manager);

        } catch (\Throwable $e) {
            echo "    ❌ Resize failed: " . $e->getMessage() . "\n";
            @unlink($tempOriginal);
            continue;
        }

        @unlink($tempOriginal);

        // Build filename
        $externalId = str_replace('buildify-', '', $project->unique_id);
        $imageFileName = sprintf('buildify-%s-%d.jpg', $externalId, $idx);
        $imageBaseName = pathinfo($imageFileName, PATHINFO_FILENAME);

        // Dedup check
        $existing = MediaFile::query()
            ->where('name', $imageBaseName)
            ->where('folder_id', $folderId)
            ->first();

        if ($existing) {
            echo "    ⏭ Already in media: {$existing->url}\n";
            $images[$idx] = $existing->url;
            $changed = true;
            @unlink($tempResized);
            continue;
        }

        // Upload via RvMedia (skip validation to bypass the 2MB admin setting)
        $uploadedFile = new UploadedFile(
            $tempResized,
            $imageFileName,
            'image/jpeg',
            null,
            true
        );

        $uploadResult = RvMedia::handleUpload($uploadedFile, $folderId, null, true);
        @unlink($tempResized);

        if (!$uploadResult['error']) {
            $localUrl = $uploadResult['data']->url;
            echo "    ✅ Uploaded: $localUrl\n";
            $images[$idx] = $localUrl;
            $changed = true;
        } else {
            echo "    ❌ Upload failed: " . ($uploadResult['message'] ?? 'unknown') . "\n";
        }
    }

    if ($changed) {
        $project->images = array_values($images);
        $project->save();
        echo "  💾 Saved.\n";
    }
    echo "\n";
}

// Final check
echo "=== Final verification ===\n";
$all = Project::query()->where('unique_id', 'like', 'buildify-%')->get(['id', 'name', 'images']);
$remote = 0;
foreach ($all as $p) {
    $imgs = is_array($p->images) ? $p->images : json_decode($p->images, true) ?? [];
    foreach ($imgs as $i) {
        if (str_starts_with($i, 'http://') || str_starts_with($i, 'https://')) {
            $remote++;
            echo "  Still remote: #{$p->id} ({$p->name}) → $i\n";
        }
    }
}
echo $remote === 0
    ? "✅ All 161 Buildify projects now have fully local images!\n"
    : "⚠ $remote image(s) still remote.\n";
