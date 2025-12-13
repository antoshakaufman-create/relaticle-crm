#!/bin/bash

cd /var/www/relaticle

echo "=== Update social media links for key companies ==="

php8.5 artisan tinker --execute="
use App\Models\People;

\$updates = [
    // Р-Фарм
    ['search' => 'Р Фарм', 'field' => 'notes', 'data' => [
        'vk_url' => 'https://vk.com/r_pharm',
        'youtube_url' => 'https://youtube.com/@GKRPharm',
    ]],
    
    // Валента Фарм
    ['search' => 'Валента Фарм', 'field' => 'notes', 'data' => [
        'vk_url' => 'https://vk.com/valentapharm',
    ]],
    
    // НоваМедика
    ['search' => 'НоваМедика', 'field' => 'notes', 'data' => [
        'vk_url' => 'https://vk.com/novamedica',
    ]],
    
    // Эвалар
    ['search' => 'Эвалар', 'field' => 'notes', 'data' => [
        'vk_url' => 'https://vk.com/evalar_ru',
        'telegram_url' => 'https://t.me/evalar_official',
    ]],
    
    // Веро Фарма
    ['search' => 'Веро Фарма', 'field' => 'notes', 'data' => [
        'vk_url' => 'https://vk.com/veropharm',
    ]],
    
    // Stada
    ['search' => 'Stada', 'field' => 'notes', 'data' => [
        'vk_url' => 'https://vk.com/stadaru',
    ]],
    
    // KION
    ['search' => 'Kion', 'field' => 'notes', 'data' => [
        'vk_url' => 'https://vk.com/kionru',
        'telegram_url' => 'https://t.me/kionru',
    ]],
    
    // IVI
    ['search' => 'IVI', 'field' => 'notes', 'data' => [
        'vk_url' => 'https://vk.com/ivi',
        'telegram_url' => 'https://t.me/ivi_rus',
    ]],
    
    // 2GIS
    ['search' => '2Gis', 'field' => 'notes', 'data' => [
        'vk_url' => 'https://vk.com/2gis',
    ]],
    
    // Сбер
    ['search' => 'Сбер', 'field' => 'notes', 'data' => [
        'vk_url' => 'https://vk.com/sberbank',
        'telegram_url' => 'https://t.me/slozhnyeprotsenty',
        'youtube_url' => 'https://youtube.com/@slozhnyeprotsenty',
    ]],
    
    // Тинькофф
    ['search' => 'Тинькофф', 'field' => 'notes', 'data' => [
        'vk_url' => 'https://vk.com/tinkoff',
        'telegram_url' => 'https://t.me/tinkoffbank',
        'youtube_url' => 'https://youtube.com/@Tinkoff',
    ]],
    
    // Билайн
    ['search' => 'Билайн', 'field' => 'notes', 'data' => [
        'vk_url' => 'https://vk.com/beeline',
        'telegram_url' => 'https://t.me/beeline',
    ]],
    
    // Газпромнефть
    ['search' => 'Газпром', 'field' => 'notes', 'data' => [
        'vk_url' => 'https://vk.com/gazpromneft',
        'telegram_url' => 'https://t.me/gpnbonus',
    ]],
    
    // МТС
    ['search' => 'МТС', 'field' => 'notes', 'data' => [
        'vk_url' => 'https://vk.com/mts',
        'telegram_url' => 'https://t.me/mts_media',
    ]],
    
    // Яндекс
    ['search' => 'Яндекс', 'field' => 'notes', 'data' => [
        'vk_url' => 'https://vk.com/yandex',
        'telegram_url' => 'https://t.me/yandex',
        'youtube_url' => 'https://youtube.com/@yandex',
    ]],
    
    // Касперский
    ['search' => 'Kaspersky', 'field' => 'notes', 'data' => [
        'vk_url' => 'https://vk.com/kaspersky',
        'telegram_url' => 'https://t.me/kasperskylab_ru',
        'youtube_url' => 'https://youtube.com/@Kaspersky',
    ]],
];

\$updated = 0;
foreach(\$updates as \$item) {
    \$contacts = People::where('notes', 'like', '%' . \$item['search'] . '%')
        ->orWhere('name', 'like', '%' . \$item['search'] . '%')
        ->get();
    
    foreach(\$contacts as \$contact) {
        \$contact->update(\$item['data']);
        echo 'Updated: ' . \$contact->name . \" (\" . implode(', ', array_keys(\$item['data'])) . \")\\n\";
        \$updated++;
    }
}

echo \"\\n=== Total updated: \" . \$updated . \" ===\";
"
