<?php
/**
 * Validate Social Media Links
 * 
 * Check each VK/TG/YouTube link with HTTP request
 * Remove fake/mock links that don't exist
 * 
 * Run: php8.5 /tmp/validate_links.php
 */

require '/var/www/relaticle/vendor/autoload.php';
$app = require_once '/var/www/relaticle/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\People;
use Illuminate\Support\Facades\Http;

echo "=== Validating Social Media Links ===\n\n";

function validateUrl($url)
{
    if (empty($url))
        return false;

    // Clean URL
    $url = trim($url);
    if (!str_starts_with($url, 'http')) {
        $url = 'https://' . $url;
    }

    try {
        $response = Http::timeout(10)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept-Language' => 'ru-RU,ru;q=0.9',
            ])
            ->get($url);

        $status = $response->status();
        $body = $response->body();

        // Check if page exists and is not error
        if ($status >= 200 && $status < 400) {
            // VK specific: check if not "Page not found"
            if (str_contains($url, 'vk.com')) {
                if (
                    str_contains($body, 'Страница удалена') ||
                    str_contains($body, 'Страница не найдена') ||
                    str_contains($body, 'Page not found') ||
                    str_contains($body, 'Такой страницы нет')
                ) {
                    return false;
                }
            }

            // Telegram: check if valid
            if (str_contains($url, 't.me')) {
                if (
                    str_contains($body, 'not exist') ||
                    str_contains($body, 'not found') ||
                    $status == 404
                ) {
                    return false;
                }
            }

            return true;
        }

        return false;
    } catch (\Exception $e) {
        return false;
    }
}

// Get contacts with social links
$contacts = People::where(function ($q) {
    $q->whereNotNull('vk_url')
        ->orWhereNotNull('telegram_url')
        ->orWhereNotNull('youtube_url');
})->get();

echo "Contacts with social links: " . count($contacts) . "\n\n";

$vkValid = 0;
$vkInvalid = 0;
$tgValid = 0;
$tgInvalid = 0;
$ytValid = 0;
$ytInvalid = 0;

$processed = 0;

foreach ($contacts as $contact) {
    $processed++;
    $name = $contact->name;

    echo "[$processed] $name: ";

    // Validate VK
    if ($contact->vk_url) {
        $valid = validateUrl($contact->vk_url);
        if ($valid) {
            echo "VK✓ ";
            $vkValid++;
        } else {
            echo "VK✗(removed) ";
            $contact->update(['vk_url' => null]);
            $vkInvalid++;
        }
    }

    // Validate Telegram
    if ($contact->telegram_url) {
        $valid = validateUrl($contact->telegram_url);
        if ($valid) {
            echo "TG✓ ";
            $tgValid++;
        } else {
            echo "TG✗(removed) ";
            $contact->update(['telegram_url' => null]);
            $tgInvalid++;
        }
    }

    // Validate YouTube
    if ($contact->youtube_url) {
        $valid = validateUrl($contact->youtube_url);
        if ($valid) {
            echo "YT✓ ";
            $ytValid++;
        } else {
            echo "YT✗(removed) ";
            $contact->update(['youtube_url' => null]);
            $ytInvalid++;
        }
    }

    echo "\n";

    usleep(200000); // 0.2 sec delay between requests
}

echo "\n=== Validation Results ===\n";
echo "VK:       Valid: $vkValid | Invalid (removed): $vkInvalid\n";
echo "Telegram: Valid: $tgValid | Invalid (removed): $tgInvalid\n";
echo "YouTube:  Valid: $ytValid | Invalid (removed): $ytInvalid\n";

echo "\n=== Current Stats ===\n";
echo "Contacts with valid VK: " . People::whereNotNull('vk_url')->count() . "\n";
echo "Contacts with valid TG: " . People::whereNotNull('telegram_url')->count() . "\n";
echo "Contacts with valid YT: " . People::whereNotNull('youtube_url')->count() . "\n";
