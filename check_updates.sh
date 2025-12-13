#!/bin/bash

cd /var/www/relaticle

echo "=== Check updated contacts ==="

php8.5 artisan tinker --execute="
use App\Models\People;

\$withSmm = People::whereNotNull('smm_analysis')->count();
\$withVk = People::whereNotNull('vk_url')->count();
\$withTg = People::whereNotNull('telegram_url')->count();
\$total = People::count();

echo \"Total contacts: {\$total}\\n\";
echo \"With SMM Analysis: {\$withSmm}\\n\";
echo \"With VK URL: {\$withVk}\\n\";
echo \"With Telegram URL: {\$withTg}\\n\";

echo \"\\n=== Sample contact with SMM ===\\n\";
\$sample = People::whereNotNull('smm_analysis')->first();
if(\$sample) {
    echo \"Name: \" . \$sample->name . \"\\n\";
    echo \"VK: \" . (\$sample->vk_url ?: 'N/A') . \"\\n\";
    echo \"TG: \" . (\$sample->telegram_url ?: 'N/A') . \"\\n\";
    echo \"SMM Analysis:\\n\" . \$sample->smm_analysis . \"\\n\";
}
"
