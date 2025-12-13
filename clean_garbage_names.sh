#!/bin/bash

cd /var/www/relaticle

echo "=== Find contacts with garbage names ==="
php8.5 artisan tinker --execute="
use App\Models\People;

// Find contacts with very short or garbage names
\$garbage = People::where(function(\$q) {
    \$q->where('name', '!')
      ->orWhere('name', '!!')
      ->orWhere('name', '?')
      ->orWhere('name', '-')
      ->orWhere('name', '')
      ->orWhere('name', ' ')
      ->orWhereRaw('LENGTH(name) <= 2');
})->get();

echo 'Found ' . count(\$garbage) . \" garbage contacts:\\n\";
foreach(\$garbage as \$g) {
    echo 'ID: ' . \$g->id . ' | Name: \"' . \$g->name . '\"' . \"\\n\";
}

// Delete them
if (count(\$garbage) > 0) {
    \$ids = \$garbage->pluck('id')->toArray();
    \$deleted = People::whereIn('id', \$ids)->forceDelete();
    echo \"\\nDeleted {\$deleted} garbage contacts\\n\";
}
"

echo ""
echo "=== Check for other suspicious names ==="
php8.5 artisan tinker --execute="
use App\Models\People;

\$suspicious = People::whereRaw('LENGTH(name) <= 5')
    ->orWhere('name', 'like', '%№%')
    ->get();

echo 'Short/suspicious names (' . count(\$suspicious) . '):\\n';
foreach(\$suspicious->take(20) as \$s) {
    echo 'ID: ' . \$s->id . ' | Name: \"' . \$s->name . '\"' . \"\\n\";
}
"

echo ""
echo "=== Final count ==="
php8.5 artisan tinker --execute="
use App\Models\People;
echo 'Total: ' . People::count();
"
