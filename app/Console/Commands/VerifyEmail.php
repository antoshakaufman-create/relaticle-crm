<?php

namespace App\Console\Commands;

use App\Services\LeadValidation\EmailValidationService;
use Illuminate\Console\Command;

class VerifyEmail extends Command
{
    protected $signature = 'app:verify-email {email}';
    protected $description = 'Manually verify if an email address is real using SMTP handshake';

    public function handle(EmailValidationService $validator)
    {
        $email = $this->argument('email');
        $this->info("Starting verification for: $email");

        $this->info("[1/4] Checking format...");
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("Invalid format.");
            return;
        }
        $this->info("   OK.");

        $this->info("[2/4] Checking DNS (A)...");
        // Validator does this internally, but let's just run the full suite

        $startTime = microtime(true);
        $result = $validator->validate($email);
        $duration = round(microtime(true) - $startTime, 2);

        $this->newLine();
        $this->comment("--- Results ({$duration}s) ---");

        $this->info("Status: " . strtoupper($result->status));
        $this->info("Score: " . $result->score . "/100");

        $this->newLine();
        $this->comment("--- Detailed Checks ---");
        foreach ($result->details as $key => $msg) {
            $this->line("• $msg");
        }

        if ($result->error) {
            $this->error("Error: " . $result->error);
        }

        if (str_contains(json_encode($result->details), 'SMTP Verified')) {
            $this->info("\n✅ VERDICT: REAL EMAIL (Confirmed by Mail Server)");
        } elseif (str_contains(json_encode($result->details), 'SMTP Rejected')) {
            $this->error("\n❌ VERDICT: DOES NOT EXIST (Rejected by Mail Server)");
        } elseif ($result->status === 'mock') {
            $this->error("\n❌ VERDICT: FAKE/MOCK");
        } else {
            $this->warn("\n⚠️ VERDICT: UNCERTAIN (Server exists, but mailbox check failed/blocked)");
            $this->warn("   Reason: " . ($result->error ?? 'Unknown SMTP response'));
        }
    }
}
