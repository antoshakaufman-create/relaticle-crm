<?php

namespace App\Services\LeadGeneration;

use App\Models\People;
use App\Services\LeadValidation\EmailValidationService;
use App\Services\LeadValidation\ValidationResult;

class EmailDiscoveryService
{
    public function __construct(private EmailValidationService $validator)
    {
    }

    public function findCorporateEmail(People $person, ?string $overrideDomain = null): ?string
    {
        // Require name
        if (!$person->name)
            return null;

        $website = $overrideDomain ?? ($person->company ? $person->company->website : null);

        if (!$website) {
            return null;
        }

        $domain = $this->extractDomain($website);
        if (!$domain)
            return null;

        $patterns = $this->generatePatterns($person->name, $domain);

        foreach ($patterns as $email) {
            $result = $this->validator->validate($email);

            // STRICT MODE: Only accept if SMTP explicitly said "Verified".
            // "Valid" status is not enough because it includes Domain+MX checks which are always true for corporate domains.
            if (str_contains(json_encode($result->details), 'SMTP Verified')) {
                return $email;
            }
        }

        return null;
    }

    private function extractDomain(string $url): ?string
    {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? $url;
        return preg_replace('/^www\./', '', $host);
    }

    private function generatePatterns(string $fullName, string $domain): array
    {
        // Transliterate Cyrillic Name -> Latin
        $latinName = $this->transliterate($fullName);
        $parts = explode(' ', strtolower($latinName));

        if (count($parts) < 2)
            return [];

        $first = $parts[0]; // e.g. andrey
        $last = end($parts); // e.g. berezhnoy

        $f = substr($first, 0, 1);
        $l = substr($last, 0, 1);

        return [
            "$first.$last@$domain",
            "$first@$domain",
            "$f$last@$domain",
            "$first$l@$domain",
            "$last@$domain",
            "$last.$first@$domain" // Less common but possible
        ];
    }

    private function transliterate(string $text): string
    {
        $map = [
            'а' => 'a',
            'б' => 'b',
            'в' => 'v',
            'г' => 'g',
            'д' => 'd',
            'е' => 'e',
            'ё' => 'e',
            'ж' => 'zh',
            'з' => 'z',
            'и' => 'i',
            'й' => 'y',
            'к' => 'k',
            'л' => 'l',
            'м' => 'm',
            'н' => 'n',
            'о' => 'o',
            'п' => 'p',
            'р' => 'r',
            'с' => 's',
            'т' => 't',
            'у' => 'u',
            'ф' => 'f',
            'х' => 'kh',
            'ц' => 'ts',
            'ч' => 'ch',
            'ш' => 'sh',
            'щ' => 'shch',
            'ъ' => '',
            'ы' => 'y',
            'ь' => '',
            'э' => 'e',
            'ю' => 'yu',
            'я' => 'ya'
        ];
        return str_replace(array_keys($map), array_values($map), mb_strtolower($text));
    }
}
