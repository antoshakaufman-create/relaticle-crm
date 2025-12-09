<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

$email = 'anton.kaufmann95@gmail.com';
$password = 'Starten01!';
$name = 'Антон';

echo "Finding user with email: $email\n";

$user = User::where('email', $email)->first();

if (!$user) {
    echo "User not found. Creating new user...\n";
    $user = new User();
    $user->email = $email;
    $user->name = $name;
    $user->password = Hash::make($password);
    $user->email_verified_at = Carbon::now();
    $user->save();
    echo "User created successfully with ID: " . $user->id . "\n";
} else {
    echo "User found (ID: " . $user->id . "). Updating...\n";
    $user->name = $name;
    $user->password = Hash::make($password);
    if (!$user->email_verified_at) {
        $user->email_verified_at = Carbon::now();
        echo "Marked email as verified.\n";
    } else {
        echo "Email was already verified.\n";
    }
    $user->save();
    echo "User updated successfully.\n";
}

// Ensure Team exists and is assigned
echo "Checking Team configuration...\n";
$teamName = 'VirtuDigital';
$team = \App\Models\Team::where('name', $teamName)->first();

if (!$team) {
    echo "Team '$teamName' not found. Creating...\n";
    $team = new \App\Models\Team();
    $team->user_id = $user->id;
    $team->name = $teamName;
    $team->personal_team = true;
    $team->save();
    echo "Team created with ID: " . $team->id . "\n";
} else {
    echo "Team '$teamName' found (ID: " . $team->id . ").\n";
}

// Ensure user owns or belongs to team
if (!$user->belongsToTeam($team)) {
    echo "Attaching user to team...\n";
    // Jetstream/Filament method to attach might be via relationship or pivot
    // For personal team, usually user_id is enough if it's the owner
    if ($team->user_id !== $user->id) {
        // If not owner, attach member
        $team->users()->attach($user, ['role' => 'admin']);
    }
}

// Set current team
if ($user->current_team_id !== $team->id) {
    echo "Setting current_team_id to " . $team->id . "\n";
    $user->current_team_id = $team->id;
    $user->save();
}

echo "Team configuration complete. User Current Team ID: " . $user->current_team_id . "\n";

// Debug Policy Check for Opportunity
$canView = $user->can('viewAny', \App\Models\Opportunity::class);
echo "Policy Check - Can View Opportunities: " . ($canView ? 'YES' : 'NO') . "\n";

if (!$canView) {
    echo "[DEBUG] Policy failure details:\n";
    echo " - Verified Email: " . ($user->hasVerifiedEmail() ? 'YES' : 'NO') . "\n";
    echo " - Current Team Set: " . ($user->currentTeam ? 'YES' : 'NO') . "\n";
}
