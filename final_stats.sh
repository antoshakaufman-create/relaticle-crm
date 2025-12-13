#!/bin/bash

cd /var/www/relaticle

php8.5 artisan tinker --execute="
use App\Models\People;

echo '=== FINAL STATS ===' . \"\\n\";
echo 'Total contacts: ' . People::count() . \"\\n\";
echo 'With VK: ' . People::whereNotNull('vk_url')->count() . \"\\n\";
echo 'With Telegram: ' . People::whereNotNull('telegram_url')->count() . \"\\n\";
echo 'With YouTube: ' . People::whereNotNull('youtube_url')->count() . \"\\n\";
echo 'With Instagram: ' . People::whereNotNull('instagram_url')->count() . \"\\n\";
echo 'With email: ' . People::whereNotNull('email')->where('email', '!=', '')->count() . \"\\n\";
echo 'With phone: ' . People::whereNotNull('phone')->count() . \"\\n\";

echo \"\\n=== Sample with social media ===\\n\";
\$samples = People::whereNotNull('vk_url')->take(5)->get();
foreach(\$samples as \$s) {
    echo '---' . \"\\n\";
    echo 'Name: ' . \$s->name . \"\\n\";
    echo 'VK: ' . \$s->vk_url . \"\\n\";
    echo 'TG: ' . (\$s->telegram_url ?: 'N/A') . \"\\n\";
    echo 'YT: ' . (\$s->youtube_url ?: 'N/A') . \"\\n\";
}
"
