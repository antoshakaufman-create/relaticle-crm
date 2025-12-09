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
