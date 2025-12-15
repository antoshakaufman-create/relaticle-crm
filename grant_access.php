<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$teamName = 'virtudigital'; // Check loosely
$emails = [
    'm.stromov@virtudigital.agency',
    'info@virtudigital.agency'
];

// 1. Find Team
$team = Team::where('name', 'LIKE', "%$teamName%")->first();

if (!$team) {
    echo "ERROR: Team '$teamName' not found. Creating it for context...\n";
    // Usually attached to a user, finding ANY admin to own it if needed, or creating standalone if possible (Jetstream usually requires owner)
    $owner = User::first();
    if ($owner) {
        $team = Team::create([
            'user_id' => $owner->id,
            'name' => 'Virtu Digital',
            'personal_team' => false,
        ]);
        echo "Created team 'Virtu Digital' (ID: {$team->id}) owned by {$owner->email}\n";
    } else {
        die("No users found to own the team.\n");
    }
} else {
    echo "Found Team: '{$team->name}' (ID: {$team->id})\n";
}

// 2. Process Users
foreach ($emails as $email) {
    $user = User::where('email', $email)->first();
    $password = 'Virtu2025!';

    if (!$user) {
        echo "Creating new user: $email\n";
        $user = User::create([
            'name' => explode('@', $email)[0],
            'email' => $email,
            'password' => Hash::make($password),
            'current_team_id' => $team->id,
        ]);
        echo "User created. Password set to: '$password'\n";
    } else {
        echo "User exists: $email. Updating pass to '$password' for access.\n";
        $user->password = Hash::make($password);
        $user->current_team_id = $team->id;
        $user->save();
    }

    // 3. Add to Team
    if (!$team->hasUser($user)) {
        $team->users()->attach($user, ['role' => 'editor']); // Giving editor role
        echo "Added $email to team '{$team->name}'\n";
    } else {
        echo "$email is already in team '{$team->name}'\n";
    }

    // Switch context
    $user->switchTeam($team);
    echo "Switched $email current_team to '{$team->name}'\n";
}

echo "Done.\n";
