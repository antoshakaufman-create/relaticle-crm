#!/bin/bash

cd /var/www/relaticle

echo "=== Update more pharma social media ==="

php8.5 artisan tinker --execute="
use App\Models\People;

\$updates = [
    // Фармстандарт
    ['search' => 'Фармстандарт', 'data' => [
        'vk_url' => 'https://vk.com/pharmstandard',
        'telegram_url' => 'https://t.me/ph_standart_info',
    ]],
    
    // Биокад
    ['search' => 'Биокад', 'data' => [
        'vk_url' => 'https://vk.com/biocad',
        'youtube_url' => 'https://youtube.com/@biocad',
    ]],
    
    // Герофарм
    ['search' => 'Герофарм', 'data' => [
        'vk_url' => 'https://vk.com/geropharm_official',
        'telegram_url' => 'https://t.me/geropharm_career',
    ]],
    
    // Фарм Синтез
    ['search' => 'Фарм Синтез', 'data' => [
        'vk_url' => 'https://vk.com/pharmsintez',
    ]],
    
    // АстраЗенека  
    ['search' => 'АстраЗенека', 'data' => [
        'vk_url' => 'https://vk.com/aaborting',
        'telegram_url' => 'https://t.me/aaborting_career',
    ]],
    
    // Chiesi
    ['search' => 'Чиези', 'data' => [
        'vk_url' => 'https://vk.com/chiesi_ru',
    ]],
    
    // Gedeon Richter
    ['search' => 'Рихтер', 'data' => [
        'vk_url' => 'https://vk.com/gedeon_richter_rus',
    ]],
    
    // Abbott
    ['search' => 'Abbott', 'data' => [
        'vk_url' => 'https://vk.com/abbott_russia',
    ]],
    
    // МТС
    ['search' => 'МТС', 'data' => [
        'vk_url' => 'https://vk.com/mts',
        'telegram_url' => 'https://t.me/mts_media',
    ]],
    
    // Okko
    ['search' => 'Okko', 'data' => [
        'vk_url' => 'https://vk.com/okko',
        'telegram_url' => 'https://t.me/okko_ru',
    ]],
    
    // VK
    ['search' => 'VK', 'data' => [
        'vk_url' => 'https://vk.com/vk',
        'telegram_url' => 'https://t.me/vk',
    ]],
];

\$updated = 0;
foreach(\$updates as \$item) {
    \$contacts = People::where('notes', 'like', '%' . \$item['search'] . '%')
        ->orWhere('name', 'like', '%' . \$item['search'] . '%')
        ->whereNull('vk_url')
        ->get();
    
    foreach(\$contacts as \$contact) {
        \$contact->update(\$item['data']);
        echo 'Updated: ' . \$contact->name . \"\\n\";
        \$updated++;
    }
}

echo \"\\n=== Total updated: \" . \$updated . \" ===\";
"
