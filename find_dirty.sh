#!/bin/bash

cd /var/www/relaticle

echo "=== Find and show dirty contacts (SQLite compatible) ==="
php8.5 artisan tinker --execute="
use App\Models\People;

// Find contacts with bad data patterns (SQLite compatible)
\$dirty = People::where(function(\$q) {
    \$q->where('name', 'like', '%Компания:%')
      ->orWhere('name', 'like', '%Адрес:%')
      ->orWhere('name', 'like', '%Телефон:%')
      ->orWhere('name', 'like', '%Источник:%')
      ->orWhere('name', 'like', '%Сайт:%')
      ->orWhere('name', 'like', '%Позиция:%')
      ->orWhere('name', 'like', '%руководитель%группы%развития%')
      ->orWhere('name', 'like', '%Административный Менеджер%');
})->get();

echo 'Found ' . count(\$dirty) . \" potentially dirty contacts\\n\\n\";

foreach(\$dirty as \$d) {
    echo 'ID: ' . \$d->id . ' | Name: ' . substr(\$d->name, 0, 60) . \"\\n\";
}
"

echo ""
echo "=== Show sample of contacts with multi-line names ==="
php8.5 artisan tinker --execute="
use App\Models\People;

\$multiline = People::where('name', 'like', '%
%')->get();
echo 'Contacts with newlines in name: ' . count(\$multiline) . \"\\n\";
foreach(\$multiline->take(5) as \$m) {
    echo 'ID: ' . \$m->id . ' | Name: ' . str_replace(\"\\n\", ' | ', substr(\$m->name, 0, 80)) . \"\\n\";
}
"
