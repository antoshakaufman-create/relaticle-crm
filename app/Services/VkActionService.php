<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\Http;

class VkActionService
{
    protected string $vkToken = 'vk1.a.33bsbI5XV3fhrWMN1Ut2VYDbXCNTafhZwcBigSq5XKBlckhhuDnbDnf5Q-TQ7e5Fe8iLkCWRQLdlsAJaC7kbjiK4bAEbTOxSd7qnHMuEUsDF-gKW46vOHlWTPEmP6X5qT6tMZffX9tXIt8vz-FBDuL1Yn5G18TYOnqcH3rxMhmHSNdKy0utYvOTHIXy8dDh8tEdhX1ise6KVvLXURkk0gA';

    /**
     * Find a validated VK group for a company name, using Context (City) and Legal Name.
     */
    public function findGroup(string $query, ?string $domain = null, ?string $legalName = null, ?string $address = null): ?string
    {
        \Illuminate\Support\Facades\Log::info("Entering findGroup: $query ($domain)");
        $candidates = [];
        // Extract City from Address
        $city = null;
        if ($address) {
            if (preg_match('/(?:г\.|город)\s*([а-яА-ЯёЁ-]+)/iu', $address, $matches)) {
                $city = $matches[1];
            } elseif (preg_match('/(?:Moscow|Saint Petersburg|Москва|Санкт-Петербург)/iu', $address, $matches)) {
                $city = $matches[0];
            }
        }
        // 1. Scraping Strategy (Gold Standard)
        if ($domain) {
            $url = str_starts_with($domain, 'http') ? $domain : "https://$domain";
            $scrapedUrl = $this->scanWebsiteForVk($url);
            if ($scrapedUrl && $this->validateVkUrl($scrapedUrl))
                return $scrapedUrl;
        }

        // 2. Exa.ai Search Strategy (Merge into candidates instead of early return)
        $exaUrls = $this->findVkViaExa($query, $domain);
        foreach ($exaUrls as $exaUrl) {
            // We need to fetch group details to treat it as a candidate
            $path = parse_url($exaUrl, PHP_URL_PATH);
            $slug = trim(str_replace('/', '', $path));
            if ($slug) {
                $exInfo = $this->getGroupInfoBySlug($slug);
                if ($exInfo) {
                    $exInfo['is_exa'] = true; // Flag for bonus
                    $candidates[] = $exInfo;
                }
            }
        }

        try {
            $itemsDomain = [];

            // ... (keep existing Domain Search logic) ... check for $itemsDomain usage or remove if redundant, but let's keep it and merge results.

            // 2. Domain Match Search (VK API)
            if ($domain) {
                // ... existing request ...
                $response = Http::get('https://api.vk.com/method/groups.search', [
                    'q' => $domain,
                    'count' => 20,
                    'access_token' => $this->vkToken,
                    'v' => '5.131',
                    'fields' => 'members_count,verified,site'
                ]);
                $itemsDomain = $response['response']['items'] ?? [];
                $candidates = array_merge($candidates, $itemsDomain);
            }

            // 3. Name Search
            $searchRes = $this->searchVkByName($query);
            $candidates = array_merge($candidates, $searchRes);

            // ... (keep latin/cyrillic fallback searches if you wish, adding to candidates) ...

            // Deduplicate by ID
            \Illuminate\Support\Facades\Log::info("Candidates found: " . count($candidates));
            $uniqueCandidates = [];
            foreach ($candidates as $c) {
                if (isset($c['id'])) {
                    $uniqueCandidates[$c['id']] = $c;
                }
            }

            $bestCandidate = null;
            $bestScore = -1000;

            foreach ($uniqueCandidates as $item) {
                $cUrl = "https://vk.com/" . $item['screen_name'];

                // Basic Relevance Check First
                if (
                    !$this->validateGroupRelevance($cUrl, $query, $domain) &&
                    ($legalName && !$this->validateGroupRelevance($cUrl, $legalName, $domain))
                ) {
                    \Illuminate\Support\Facades\Log::info("Rejected candidate: $cUrl", ['query' => $query, 'domain' => $domain]);
                    continue;
                }

                // Calculate Score
                $score = 0;



                $screen = strtolower($item['screen_name']);
                $qLower = strtolower($query);
                $nameLower = mb_strtolower($item['name']);
                $siteLower = isset($item['site']) ? strtolower($item['site']) : '';

                // 0. Exa Boost
                if (($item['is_exa'] ?? false)) {
                    $score += 50;
                }

                // 1. DOMAIN MATCH (CRITICAL - New Logic)
                if ($domain && $siteLower) {
                    $baseDom = str_ireplace(['www.', 'http://', 'https://', '/'], '', strtolower($domain));
                    $siteClean = str_ireplace(['www.', 'http://', 'https://', '/'], '', $siteLower);

                    if ($baseDom === $siteClean || str_contains($siteClean, $baseDom)) {
                        $score += 300; // Massive boost for website match
                    } elseif (str_contains($baseDom, $siteClean) && strlen($siteClean) > 4) {
                        $score += 200;
                    }
                }

                // 2. Slug Match
                if ($screen === $qLower)
                    $score += 100;
                elseif (str_starts_with($screen, $qLower))
                    $score += 80;
                elseif (str_contains($screen, $qLower))
                    $score += 60;

                // 3. Exact Title Match
                if ($nameLower === $qLower)
                    $score += 50;
                elseif (str_contains($nameLower, $qLower))
                    $score += 20;

                // 4. Verification
                if (($item['verified'] ?? 0) == 1)
                    $score += 50; // Increased from 30

                // 5. Member Count
                $members = $item['members_count'] ?? 0;
                if ($members > 0)
                    $score += log($members) * 5; // Increased weight

                // 6. Penalties
                if (str_contains($nameLower, 'аренда') && !str_contains($qLower, 'аренда'))
                    $score -= 50;
                if (str_contains($nameLower, 'работа') && !str_contains($qLower, 'работа'))
                    $score -= 50;
                if (str_contains($nameLower, 'подслушано'))
                    $score -= 100;
                if (str_contains($nameLower, 'типичный'))
                    $score -= 100;

                \Illuminate\Support\Facades\Log::info("Scored candidate: $cUrl = $score");

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestCandidate = $cUrl;
                }
            }

            return $bestCandidate;

        } catch (\Exception $e) {
            // ... 
        }

        return null;
    }

    // Helper to get info for Exa results
    private function getGroupInfoBySlug($slug)
    {
        try {
            $response = Http::get('https://api.vk.com/method/groups.getById', [
                'group_id' => $slug,
                'fields' => 'members_count,verified,site',
                'access_token' => $this->vkToken,
                'v' => '5.131'
            ]);
            return $response['response'][0] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function verifyLinkRelevance(string $url, string $name, ?string $domain): bool
    {
        // Public wrapper for internal validation
        if (!$this->validateVkUrl($url))
            return false;
        return $this->validateGroupRelevance($url, $name, $domain);
    }

    private function validateVkUrl(string $url): bool
    {
        try {
            $path = parse_url($url, PHP_URL_PATH);
            $screenName = trim(str_replace('/', '', $path));

            if (!$screenName)
                return false;

            $response = Http::get('https://api.vk.com/method/utils.resolveScreenName', [
                'screen_name' => $screenName,
                'access_token' => $this->vkToken,
                'v' => '5.131'
            ]);

            $type = $response['response']['type'] ?? null;
            return in_array($type, ['group', 'page', 'event']);

        } catch (\Exception $e) {
            return false;
        }
    }

    private function validateGroupRelevance(string $url, string $companyName, ?string $domain): bool
    {
        try {
            $path = parse_url($url, PHP_URL_PATH);
            $screenName = trim(str_replace('/', '', $path));

            // Fetch group info
            $response = Http::get('https://api.vk.com/method/groups.getById', [
                'group_id' => $screenName,
                'fields' => 'site,description,status,verified',
                'access_token' => $this->vkToken,
                'v' => '5.131'
            ]);

            $group = $response['response'][0] ?? null;
            if (!$group)
                return false;

            $gName = mb_strtolower($group['name'] ?? '');
            $gDesc = mb_strtolower($group['description'] ?? '');
            $gSite = mb_strtolower($group['site'] ?? '');
            $tName = mb_strtolower($companyName);

            $tNameClean = trim(str_replace(['ooo', 'зао', 'ao', 'llc', 'inc', 'company', 'group', 'holdings', 'corporation', '"', "'"], '', $tName));

            // 1. Negative Keywords (Updated)
            $blacklist = [
                'clan',
                'guild',
                'gaming',
                'minecraft',
                'cs:go',
                'fan page',
                'fan club',
                'girl',
                'boy',
                'model',
                'escort',
                'dating',
                'private',
                'xxx',
                'personal blog',
                'девушка',
                'парень',
                'блог',
                'дневник',
                'игра',
                'клан',
                'фан',
                'знакомства',
                'handmade',
                'ручная работа',
                'мастерская',
                'поделки',
                'nails',
                'маникюр',
                'resell',
                'барахолка',
                'promokod',
                'промокод',
                'coupon',
                'купон',
                'skidk',
                'скидк',
                'besplatn',
                'бесплатн',
                'free',
                'ad',
                'obyavlen',
                'объявлен',
                'rabota',
                'работа',
                'vakans',
                'ваканс' // Often separate groups, maybe keep if official? But usually "Company Job" is safer to exclude if we want main brand.
            ];
            foreach ($blacklist as $bad) {
                if (str_contains($gName, $bad))
                    return false;
            }

            // 2. Domain Match (Strongest)
            if ($domain) {
                $baseDom = str_ireplace(['www.', 'http://', 'https://', '/'], '', strtolower($domain));
                // Remove path for base comparison
                $baseDom = explode('/', $baseDom)[0];

                if (str_contains($gSite, $baseDom) || str_contains($gDesc, $baseDom)) {
                    return true;
                }


            }

            // 3. Content Validation (Check this BEFORE Verified status for generic names)
            // If the name is generic, we require some evidence it's a business
            $corporateKeywords = ['official', 'официальн', 'group', 'группа', 'company', 'компания', 'ооо', 'зао', 'ao', 'llc', 'inc', 'shop', 'магазин', 'store', 'business', 'недвижимость', 'developer', 'застройщик', 'brand', 'бренд', 'bank', 'банк', 'finance', 'финансы', 'cinema', 'кино', 'фильм', 'movie', 'tv', 'service', 'сервис', 'платформа', 'platform', 'app', 'приложение', 'digital', 'technology', 'технолог'];

            // Combine name and desc for keyword check
            $content = $gName . ' ' . $gDesc;
            $hasShift = false;
            foreach ($corporateKeywords as $kw) {
                if (str_contains($content, $kw)) {
                    $hasShift = true;
                    break;
                }
            }

            // If name is short/generic and NO corporate keywords found -> Reject
            if (mb_strlen($tNameClean) < 10 && !$hasShift) {
                return false;
            }

            // 4. Verified Check - MOVED to scoring/sorting only. 
            // We DO NOT trust verified status blindly anymore, because "Verified" groups can be irrelevant (e.g. Yuri Podolyaka vs M.Video).
            // We must proceed to Name/Content Matching.
            // if (($group['verified'] ?? 0) === 1) { return true; } 

            // 5. Name Match
            // Strict match check for generic names
            if (mb_strlen($tNameClean) < 5) {
                if ($gName === $tNameClean)
                    return true;
                return false;
            }

            // Medium names: Use Word Boundaries Regex to avoid partially matching inside other words
            // e.g. "Estel" shouldn't match "Ani Estel Handmade" unless we are loose, but we want strictness now.
            // Actually "Estel" IS a distinct word in "Ani Estel Handmade".
            // But we already filtered "Handmade" above.
            // Let's protect against "Art" matching "Party".
            if (mb_strlen($tNameClean) < 8) {
                if (preg_match('/(^|\s|[^a-zA-Zа-яА-Я0-9])' . preg_quote($tNameClean, '/') . '($|\s|[^a-zA-Zа-яА-Я0-9])/iu', $gName)) {
                    return true;
                }
                // Try translit with boundaries
                $tCyrillic = $this->transliterate($tNameClean);
                if (preg_match('/(^|\s|[^a-zA-Zа-яА-Я0-9])' . preg_quote($tCyrillic, '/') . '($|\s|[^a-zA-Zа-яА-Я0-9])/iu', $gName)) {
                    return true;
                }
                return false;
            }

            // Loose match (Normal) for long names
            if (str_contains($gName, $tNameClean) || str_contains($tName, $gName)) {
                return true;
            }

            // Check Transliterated Name (Important for "M.Video" -> "М.Видео")
            $tCyrillic = $this->transliterate($tNameClean);
            if (str_contains($gName, $tCyrillic) || str_contains($tCyrillic, $gName)) {
                return true;
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function searchVkByName(string $query): array
    {
        try {
            $response = Http::get('https://api.vk.com/method/groups.search', [
                'q' => $query,
                'count' => 20,
                'access_token' => $this->vkToken,
                'v' => '5.131',
                'fields' => 'members_count,verified,site'
            ]);
            return $response['response']['items'] ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function transliterate(string $text): string
    {
        $map = [
            'a' => 'а',
            'b' => 'б',
            'v' => 'в',
            'g' => 'г',
            'd' => 'д',
            'e' => 'е',
            'yo' => 'ё',
            'zh' => 'ж',
            'z' => 'з',
            'i' => 'и',
            'j' => 'й',
            'k' => 'к',
            'l' => 'л',
            'm' => 'м',
            'n' => 'н',
            'o' => 'о',
            'p' => 'п',
            'r' => 'р',
            's' => 'с',
            't' => 'т',
            'u' => 'у',
            'f' => 'ф',
            'h' => 'х',
            'ts' => 'ц',
            'ch' => 'ч',
            'sh' => 'ш',
            'shch' => 'щ',
            'y' => 'ы',
            'ji' => 'ы',
            'yu' => 'ю',
            'ya' => 'я',
            'A' => 'А',
            'B' => 'Б',
            'V' => 'В',
            'G' => 'Г',
            'D' => 'Д',
            'E' => 'Е',
            'YO' => 'Ё',
            'ZH' => 'Ж',
            'Z' => 'З',
            'I' => 'И',
            'J' => 'Й',
            'K' => 'К',
            'L' => 'Л',
            'M' => 'М',
            'N' => 'Н',
            'O' => 'О',
            'P' => 'П',
            'R' => 'Р',
            'S' => 'С',
            'T' => 'Т',
            'U' => 'У',
            'F' => 'Ф',
            'H' => 'Х',
            'TS' => 'Ц',
            'CH' => 'Ч',
            'SH' => 'Ш',
            'SHCH' => 'Щ',
            'Y' => 'Ы',
            'JI' => 'Ы',
            'YU' => 'Ю',
            'YA' => 'Я',
            'w' => 'в',
            'W' => 'В',
            'ph' => 'ф',
            'Ph' => 'Ф',
            'th' => 'т',
            'Th' => 'Т',
            'x' => 'кс',
            'X' => 'КС',
            'q' => 'к',
            'Q' => 'К'
        ];
        // Only run if mostly latin
        return str_ireplace(array_keys($map), array_values($map), $text);
    }

    private function cleanCompanyName(string $name): string
    {
        $name = mb_strtolower($name);
        return trim(str_replace(['ooo', 'зао', 'ao', 'llc', 'inc', 'company', 'group', 'holdings', 'corporation', '"', "'"], '', $name));
    }

    private function checkCommonHandles(string $query, ?string $domain): ?string
    {
        $candidates = [];

        // 1. From Domain (e.g. cft.ru -> cft)
        if ($domain) {
            $base = str_ireplace(['.ru', '.com', '.net'], '', $domain);
            $candidates[] = "team$base";
            $candidates[] = "{$base}team";
            $candidates[] = "{$base}ru";
            $candidates[] = "{$base}official";
            $candidates[] = "{$base}_group";
            $candidates[] = "{$base}group";
        }

        // 2. From Name (if latinate)
        if (preg_match('/^[a-zA-Z0-9]+$/', $query)) {
            $n = strtolower($query);
            $candidates[] = "team$n";
            $candidates[] = "{$n}team";
        }

        foreach (array_unique($candidates) as $handle) {
            $url = "https://vk.com/$handle";
            if ($this->validateVkUrl($url)) {
                // CRITICAL: Validate Content Relevance even if it exists
                if ($this->validateGroupRelevance($url, $query, $domain)) {
                    return $url;
                }
            }
        }

        return null;
    }

    private function scanWebsiteForVk(string $url): ?string
    {
        try {
            $html = Http::withHeaders(['User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'])->timeout(5)->connectTimeout(3)->get($url)->body();
            preg_match_all('/href=["\'](?:https?:\/\/)?(?:www\.)?vk\.com\/([a-zA-Z0-9_.-]+)["\']/i', $html, $matches);

            foreach ($matches[1] as $slug) {
                if (in_array($slug, ['share.php', 'feed', 'wall', 'login', 'search', 'app', 'write']))
                    continue;
                if (str_contains($slug, 'share'))
                    continue;
                // Exclude obviously bad slugs or numerical User IDs if we prefer groups
                return "https://vk.com/$slug";
            }
        } catch (\Exception $e) {
        }
        return null;
    }

    /**
     * Find VK links using Exa.ai search
     * 
     * @param string $companyName Company name to search for
     * @param string|null $domain Company domain for better context
     * @return array Array of VK URLs found
     */
    private function findVkViaExa(string $companyName, ?string $domain): array
    {
        try {
            $exaService = app(\App\Services\AI\ExaService::class);

            if (!$exaService->isEnabled()) {
                return [];
            }

            // Build search query targeting VK
            $query = "{$companyName} site:vk.com";
            if ($domain) {
                $query .= " OR {$domain} вконтакте OR {$domain} vk";
            }

            // Search with Exa
            $results = $exaService->searchCompany($query, null, 10);

            if (!$results || empty($results['results'])) {
                return [];
            }

            $vkUrls = [];

            foreach ($results['results'] as $result) {
                $url = $result['url'] ?? '';
                $text = $result['text'] ?? '';

                // Extract VK URL from result URL
                if (str_contains($url, 'vk.com')) {
                    // Clean up URL to get base group URL
                    if (preg_match('/vk\.com\/([a-zA-Z0-9_.-]+)/', $url, $matches)) {
                        $cleanUrl = "https://vk.com/{$matches[1]}";
                        // Remove query parameters and fragments
                        $cleanUrl = strtok($cleanUrl, '?');
                        $cleanUrl = strtok($cleanUrl, '#');
                        $vkUrls[] = $cleanUrl;
                    }
                }

                // Also extract VK links from text content
                if (preg_match_all('/(?:https?:\/\/)?(?:www\.)?vk\.com\/([a-zA-Z0-9_.-]+)/', $text, $matches)) {
                    foreach ($matches[1] as $slug) {
                        // Skip common non-group URLs
                        if (in_array($slug, ['share.php', 'feed', 'wall', 'login', 'search', 'app', 'write'])) {
                            continue;
                        }
                        if (str_contains($slug, 'share') || str_contains($slug, 'wall')) {
                            continue;
                        }
                        $vkUrls[] = "https://vk.com/{$slug}";
                    }
                }
            }

            // Remove duplicates and return
            $uniqueUrls = array_unique($vkUrls);

            \Illuminate\Support\Facades\Log::info('Exa VK search completed', [
                'company' => $companyName,
                'found_count' => count($uniqueUrls),
                'urls' => $uniqueUrls
            ]);

            return array_values($uniqueUrls);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Exa VK search failed', [
                'company' => $companyName,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    private function findVkViaGpt(string $name, string $domain): ?string
    {
        $prompt = "Найди официальную ссылку на сообщество ВКонтакте для компании \"$name\" (сайт: $domain). Верни ТОЛЬКО ссылку (например https://vk.com/club123) или 'null' если не найдено.";

        $apiKey = config('ai.yandex.api_key');
        $folderId = config('ai.yandex.folder_id');

        if (!$apiKey || !$folderId)
            return null;

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Api-Key ' . $apiKey,
                'x-folder-id' => $folderId,
            ])->post('https://llm.api.cloud.yandex.net/foundationModels/v1/completion', [
                        'modelUri' => "gpt://$folderId/yandexgpt",
                        'completionOptions' => ['stream' => false, 'temperature' => 0.1, 'maxTokens' => 50],
                        'messages' => [
                            ['role' => 'system', 'text' => 'Ты помощник по поиску соцсетей.'],
                            ['role' => 'user', 'text' => $prompt]
                        ],
                    ]);

            $text = $response['result']['alternatives'][0]['message']['text'] ?? '';
            if (str_contains($text, 'vk.com')) {
                preg_match('/https?:\/\/(?:www\.)?vk\.com\/[a-zA-Z0-9_.-]+/', $text, $matches);
                return $matches[0] ?? null;
            }
        } catch (\Exception $e) {
        }
        return null;
    }

    /**
     * Analyze a company's VK group and return enriched data array.
     */
    public function analyzeGroup(string $vkUrl): array
    {
        $path = parse_url($vkUrl, PHP_URL_PATH);
        $screenName = trim(str_replace('/', '', $path));

        $groupId = null;

        // 1. Resolve Screen Name
        try {
            $r = Http::get("https://api.vk.com/method/utils.resolveScreenName", [
                'screen_name' => $screenName,
                'access_token' => $this->vkToken,
                'v' => '5.131'
            ]);
            $obj = $r['response'] ?? [];
            if (($obj['type'] ?? '') == 'group' || ($obj['type'] ?? '') == 'page') {
                $groupId = $obj['object_id'];
            }
        } catch (\Exception $e) {
            return ['error' => 'API Error (Resolve): ' . $e->getMessage()];
        }

        if (!$groupId) {
            return ['error' => 'Could not resolve Group ID'];
        }

        // 2. Fetch Group Info (Real Members Count)
        $membersCount = 0;
        try {
            $g = Http::get("https://api.vk.com/method/groups.getById", [
                'group_id' => $groupId,
                'fields' => 'members_count',
                'access_token' => $this->vkToken,
                'v' => '5.131'
            ]);
            $groupInfo = $g['response'][0] ?? [];
            $membersCount = $groupInfo['members_count'] ?? 0;

            // If group is hidden or closed and we can't see members, we might still proceed with public posts,
            // but accurate analysis might be compromised.
        } catch (\Exception $e) {
            return ['error' => 'Расчет невозможен из-за технической причины (API Error Group Info)'];
        }

        // 3. Get Posts
        try {
            $r = Http::get("https://api.vk.com/method/wall.get", [
                'owner_id' => "-$groupId",
                'count' => 30,
                'access_token' => $this->vkToken,
                'v' => '5.131'
            ]);
            $posts = $r['response']['items'] ?? [];
        } catch (\Exception $e) {
            return ['error' => 'Расчет невозможен из-за технической причины (API Error Wall)'];
        }

        if (empty($posts)) {
            return ['lead_score' => 0, 'smm_analysis' => ['summary' => "Нет постов для анализа."], 'detailed_report' => "Нет постов."];
        }

        // 4. Calculate Scores (REAL DATA ONLY)
        $erData = $this->calculateErScore($posts);

        // Check for technical impossibility (e.g. Views missing)
        if (isset($erData['error'])) {
            return ['error' => $erData['error']];
        }

        $erScore = $erData['score'];
        $erRaw = $erData['raw'];

        $postingData = $this->calculatePostingScore($posts);
        $postingScore = $postingData['score'];
        $postsPerMonth = $postingData['raw'];

        // Comment Analysis
        $commentAnalysis = $this->analyzeComments($posts, $groupId);
        $commentScore = $commentAnalysis['score'];
        $commentSummary = $commentAnalysis['summary'] ?? 'No Analysis';
        $commentDetails = $commentAnalysis['details'] ?? '';

        // Growth Score - REMOVED (No History)
        // $growthScore = 7; // Mock removed

        // Simplified promo (keyword check)
        $promoScore = $this->calculatePromoScore($posts);

        // Final Score (Redistributed Weights: Growth 5% -> Spread to others)
        // ER: 40 -> 40
        // Posting: 20 -> 25
        // Comment: 30 -> 30
        // Promo: 5 -> 5
        // Total: 100
        $finalScore = ($erScore * 0.40) + ($postingScore * 0.25) + ($commentScore * 0.30) + ($promoScore * 0.05);
        $finalScore = min(100, max(0, $finalScore));
        $finalScore = min(100, max(0, $finalScore));

        // Categorize
        if ($finalScore >= 80)
            $catLabel = 'HOT';
        elseif ($finalScore >= 50)
            $catLabel = 'WARM';
        else
            $catLabel = 'COLD-WARM';

        // Prepare Detailed Report for Note
        // Determine first post date for status
        $firstPost = null;
        if (!empty($posts)) {
            usort($posts, function ($a, $b) {
                return $a['date'] <=> $b['date'];
            });
            $firstPost = $posts[0];
        }

        $status = $firstPost ? date("Y-m-d", $firstPost['date']) : 'INACTIVE';
        if ($firstPost && (time() - $firstPost['date'] < 30 * 24 * 3600)) {
            $status = "ACTIVE (" . date("Y") . ")";
        }

        $detailedReport = "### SMM Analysis Report\n\n";
        $detailedReport .= "**Status:** $status\n";
        $detailedReport .= "**Score:** " . round($finalScore, 1) . " ($catLabel)\n\n";
        $detailedReport .= "**Metrics:**\n";
        $detailedReport .= "- **ER Score:** " . number_format($erScore, 1) . " (Raw: " . number_format($erRaw, 2) . "%)\n";
        $detailedReport .= "- **Posting:** " . number_format($postingScore, 1) . " pts ($postsPerMonth posts/mo)\n";
        $detailedReport .= "- **Comments:** " . number_format($commentScore, 1) . " pts\n\n";
        $detailedReport .= "**AI Summary:**\n$commentSummary\n\n";
        $detailedReport .= "**Detailed Insights:**\n$commentDetails";

        // 4. Get Managers & Contacts
        $contactsSummary = "";
        $contactsList = [];
        $managersMap = [];

        try {
            // A. Fetch Managers via groups.getMembers (The comprehensive list)
            $mRes = Http::get("https://api.vk.com/method/groups.getMembers", [
                'group_id' => $groupId,
                'filter' => 'managers',
                'fields' => 'first_name,last_name,role',
                'access_token' => $this->vkToken,
                'v' => '5.131'
            ]);
            $managers = $mRes['response']['items'] ?? [];

            foreach ($managers as $m) {
                // Role translation
                $role = match ($m['role'] ?? '') {
                    'creator' => 'Creator',
                    'administrator' => 'Administrator',
                    'editor' => 'Editor',
                    'moderator' => 'Moderator',
                    default => ucfirst($m['role'] ?? 'Manager')
                };

                $managersMap[$m['id']] = [
                    'id' => $m['id'],
                    'first_name' => $m['first_name'],
                    'last_name' => $m['last_name'],
                    'role' => $role,
                    'desc' => null, // Will be filled from public contacts if available
                    'email' => null,
                    'phone' => null,
                    'source' => 'members_api'
                ];
            }

            // B. Fetch Public Contacts via groups.getById (For Email/Phone/Custom Desc)
            $r = Http::get("https://api.vk.com/method/groups.getById", [
                'group_id' => $groupId,
                'fields' => 'contacts',
                'access_token' => $this->vkToken,
                'v' => '5.131'
            ]);

            $groupData = $r['response'][0] ?? [];
            $publicContacts = $groupData['contacts'] ?? [];

            // Merge Public Info into Managers Map
            foreach ($publicContacts as $contact) {
                if (isset($contact['user_id'])) {
                    $uid = $contact['user_id'];

                    if (isset($managersMap[$uid])) {
                        // Enrich existing manager
                        if (!empty($contact['desc']))
                            $managersMap[$uid]['desc'] = $contact['desc'];
                        if (!empty($contact['email']))
                            $managersMap[$uid]['email'] = $contact['email'];
                        if (!empty($contact['phone']))
                            $managersMap[$uid]['phone'] = $contact['phone'];
                    } else {
                        // User listed in contacts but NOT returned as manager (rare, but add them)
                        // We need to resolve their name if we want to display them nicely, 
                        // BUT for efficiency, let's skip name resolution for now OR do a quick fetch.
                        // Since this is less common, let's add them with a marker to fetch details if needed.
                        // Actually, let's just add them.
                        $managersMap[$uid] = [
                            'id' => $uid,
                            'first_name' => 'ID' . $uid, // Placeholder if not resolved
                            'last_name' => '',
                            'role' => 'Public Contact',
                            'desc' => $contact['desc'] ?? null,
                            'email' => $contact['email'] ?? null,
                            'phone' => $contact['phone'] ?? null,
                            'source' => 'contacts_widget'
                        ];
                    }
                }
            }

            // Resolve names for 'contacts_widget' only users if any exist (Optimization)
            $unresolvedIds = [];
            foreach ($managersMap as $uid => $data) {
                if ($data['first_name'] === 'ID' . $uid) {
                    $unresolvedIds[] = $uid;
                }
            }

            if (!empty($unresolvedIds)) {
                $uRes = Http::get("https://api.vk.com/method/users.get", [
                    'user_ids' => implode(',', $unresolvedIds),
                    'access_token' => $this->vkToken,
                    'v' => '5.131'
                ]);
                foreach ($uRes['response'] ?? [] as $u) {
                    if (isset($managersMap[$u['id']])) {
                        $managersMap[$u['id']]['first_name'] = $u['first_name'];
                        $managersMap[$u['id']]['last_name'] = $u['last_name'];
                    }
                }
            }

            // Generate Output
            if (!empty($managersMap)) {
                $contactsSummary = "\n**Managers & Contacts:**\n";

                // Prioritize Creators/Admins
                usort($managersMap, function ($a, $b) {
                    $roles = ['Creator' => 4, 'Administrator' => 3, 'Editor' => 2, 'Moderator' => 1, 'Manager' => 0, 'Public Contact' => 0];
                    return ($roles[$b['role']] ?? 0) <=> ($roles[$a['role']] ?? 0);
                });

                foreach ($managersMap as $m) {
                    $fullName = trim($m['first_name'] . ' ' . $m['last_name']);
                    $roleDisplay = $m['role'];
                    if (!empty($m['desc']) && $m['desc'] !== $m['role']) {
                        $roleDisplay .= " ({$m['desc']})";
                    }

                    $contactsSummary .= "- [{$fullName}](https://vk.com/id{$m['id']}) - *{$roleDisplay}*";

                    if (!empty($m['email']))
                        $contactsSummary .= " 📧 {$m['email']}";
                    if (!empty($m['phone']))
                        $contactsSummary .= " 📞 {$m['phone']}";

                    $contactsSummary .= "\n";

                    $contactsList[] = [
                        'name' => $fullName,
                        'title' => $roleDisplay,
                        'vk_id' => $m['id'],
                        'link' => "https://vk.com/id{$m['id']}",
                        'email' => $m['email'],
                        'phone' => $m['phone']
                    ];
                }
            } else {
                $contactsSummary = "\n**Managers:** No public managers found.\n";
            }

        } catch (\Exception $e) {
            $contactsSummary = "\n**Managers:** Error fetching data ({$e->getMessage()}).\n";
        }

        $commentSummary .= $contactsSummary;

        return [
            'lead_score' => $finalScore,
            'lead_category' => $catLabel,
            'vk_status' => trim(str_replace(' (2025)', '', $status)),
            'er_score' => $erRaw,
            'posts_per_month' => $postsPerMonth,
            'smm_analysis' => ['summary' => $commentSummary], // Return array for Model cast
            'contacts_data' => $contactsList
        ];
    }

    private function calculateErScore($posts)
    {
        if (empty($posts))
            return ['score' => 0, 'raw' => 0];

        $totalReach = 0;
        $totalEngagement = 0;

        foreach ($posts as $post) {
            $likes = $post['likes']['count'] ?? 0;
            $comments = $post['comments']['count'] ?? 0;
            $reposts = $post['reposts']['count'] ?? 0;

            // STRICT MODE: No Fallbacks.
            $views = $post['views']['count'] ?? 0;

            if ($views == 0)
                continue;

            $totalEngagement += ($likes + $comments + $reposts);
            $totalReach += $views;
        }

        if ($totalReach == 0) {
            // If we have posts but 0 reach, it means Views are hidden/unavailable.
            // User requested explicit error in this case.
            return ['error' => 'Расчет невозможен из-за технической причины (Нет данных о просмотрах)'];
        }

        $er = ($totalEngagement / $totalReach) * 100;
        // Normalize: 3% ER = 30 points (Max 30)
        return ['score' => min(($er / 3) * 30, 30), 'raw' => $er];
    }

    private function calculatePostingScore($posts)
    {
        if (empty($posts))
            return ['score' => 0, 'raw' => 0];

        $monthAgo = time() - (30 * 24 * 60 * 60);
        $postingDays = [];
        $rawCount = 0;

        foreach ($posts as $post) {
            if ($post['date'] > $monthAgo) {
                $day = date('Y-m-d', $post['date']);
                $postingDays[$day] = true;
                $rawCount++;
            }
        }

        $daysCount = count($postingDays);
        // Normalize: 30 days = 20 points (Max 20)
        return ['score' => ($daysCount / 30) * 20, 'raw' => $rawCount];
    }

    private function calculatePromoScore($posts)
    {
        if (empty($posts))
            return 0;
        $promoCount = 0;
        $count = 0;

        foreach ($posts as $post) {
            $text = mb_strtolower($post['text'] ?? '');
            $isAd = ($post['marked_as_ads'] ?? 0) == 1;
            if ($isAd || preg_match('/(купить|акция|скидка|заказать|цена|магазин)/u', $text)) {
                $promoCount++;
            }
            $count++;
        }
        if ($count == 0)
            return 0;
        return ($promoCount / $count) * 10;
    }

    /**
     * Perform Deep AI Analysis using YandexGPT.
     */
    public function performDeepAnalysis(string $vkUrl): array
    {
        // 1. Get Base SMM Data (Metrics, Comments, Activity)
        $smmData = $this->analyzeGroup($vkUrl);

        if (isset($smmData['error'])) {
            return $smmData;
        }

        // Extract Signals
        $er = $smmData['er_score'] ?? 0;
        $postsMo = $smmData['posts_per_month'] ?? 0;
        $smmReport = $smmData['detailed_report'] ?? '';

        // Find Brand Reply signal in the report
        // We look for keyword 'игнорирует' or 'Brand Answers: No' depending on what analyzeComments returns
        $brandIgnores = str_contains(mb_strtolower($smmReport), 'игнорирует') || str_contains($smmReport, 'Бренд игнорирует');

        // 2. Resolve Group ID again for text sampling (or refactor to pass posts, but simple is robust)
        // Actually, analyzeGroup doesn't return the raw posts text.
        // We need sample text for "Tone of Voice" analysis. 
        // Let's re-fetch briefly or just trust the SMM report? 
        // User wants "Find connections between numbers", so Metrics are key.
        // Let's fetch 5 posts just for context.
        $path = parse_url($vkUrl, PHP_URL_PATH);
        $screenName = trim(str_replace('/', '', $path));

        $textSample = "";
        // ... (fetch logic omitted for brevity, let's use the params we have or quickly re-fetch)
        // To be safe and fast, let's re-fetch.
        $groupId = null;
        try {
            $r = Http::get("https://api.vk.com/method/utils.resolveScreenName", [
                'screen_name' => $screenName,
                'access_token' => $this->vkToken,
                'v' => '5.131'
            ]);
            $obj = $r['response'] ?? [];
            if (($obj['type'] ?? '') == 'group' || ($obj['type'] ?? '') == 'page') {
                $groupId = $obj['object_id'];
                $r2 = Http::get("https://api.vk.com/method/wall.get", [
                    'owner_id' => "-$groupId",
                    'count' => 10,
                    'access_token' => $this->vkToken,
                    'v' => '5.131'
                ]);
                foreach (($r2['response']['items'] ?? []) as $p) {
                    $textSample .= mb_substr($p['text'] ?? '', 0, 150) . " | ";
                }
            }
        } catch (\Exception $e) {
        }


        // 3. Call YandexGPT with ENHANCED Prompt
        $apiKey = config('ai.yandex.api_key');
        $folderId = config('ai.yandex.folder_id');

        $prompt = "Ты — ведущий digital-стратег агентства Virtu Digital (virtudigital.agency).
        Мы эксперты в: Разработке, Брендинге, SMM, Мониторинге и Performance.
        
        Твоя задача: Подготовить жесткий, фактурный разбор (Pitch) для клиента на основе ДАННЫХ.
        
        ВХОДНЫЕ ДАННЫЕ:
        1. ER (Вовлеченность): {$er}% (Норма ~1.5-2%)
        2. Частота постинга: {$postsMo} постов/мес
        3. Отчет по SMM: 
           $smmReport
        4. Контент (примеры): 
           $textSample
        
        ПРАВИЛА АНАЛИЗА (Deep Analysis Rules):
        - Если 'Бренд игнорирует' комментарии -> ОБЯЗАТЕЛЬНО предложи услугу 'Мониторинг и управление репутацией (ORM)'. Объясни, что игнор убивает LTV.
        - Если ER низкий (<1%) но постов много (>20) -> Проблема в контенте/качестве (Content Quality Issue). Предложи SMM-стратегию.
        - Если ER высокий (>3%) -> Аудитория лояльна, предложи масштабирование (Performance/Ads).
        
        СТРУКТУРА ОТВЕТА (Markdown):
        
        **1. Диагноз (Data-Driven):**
        * Сделай вывод, связав цифры и факты. (Например: 'Вы постите много (30/мес), но ER 0.2% — вы выжигаете базу скучным контентом' или 'Аудитория задает вопросы, но вы молчите. Это потеря денег.').
        
        **2. Решение от Virtu Digital:**
        * Четкий оффер. Если найден игнор — 'Внедрение ORM-системы'. Если проблема с ER — 'Перезапуск контент-стратегии'.
        
        **3. Точка роста (Business Value):**
        * Что изменится в деньгах/лидах?";

        try {
            $response = Http::timeout(60)->withHeaders([
                'Authorization' => 'Api-Key ' . $apiKey,
                'x-folder-id' => $folderId
            ])->post('https://llm.api.cloud.yandex.net/foundationModels/v1/completion', [
                        'modelUri' => 'gpt://' . $folderId . '/yandexgpt-lite/latest',
                        'completionOptions' => ['stream' => false, 'temperature' => 0.3, 'maxTokens' => 1500],
                        'messages' => [['role' => 'user', 'text' => $prompt]]
                    ]);

            $aiText = $response['result']['alternatives'][0]['message']['text'] ?? 'AI Error';

            // Format result
            return [
                'smm_analysis' => $aiText, // This goes into the field currently used for Deep Analysis
                'status' => 'success'
            ];

        } catch (\Exception $e) {
            return ['error' => 'AI Service Error: ' . $e->getMessage()];
        }
    }

    private function classifyLead($score, $postingScore)
    {
        $status = ($postingScore > 1) ? "ACTIVE (2025)" : "INACTIVE/DEAD";
        if ($score >= 75)
            return ['cat' => 'HOT', 'status' => $status];
        if ($score >= 50)
            return ['cat' => 'WARM', 'status' => $status];
        if ($score >= 25)
            return ['cat' => 'COLD-WARM', 'status' => $status];
        return ['cat' => 'COLD', 'status' => $status];
    }

    /**
     * Analyze comments for toxicity and community management.
     */
    /**
     * Analyze comments for toxicity and community management using YandexGPT.
     */
    private function analyzeComments(array $posts, string $groupId): array
    {
        if (empty($posts)) {
            return ['score' => 0, 'summary' => "No posts to analyze."];
        }

        $analyzedPostsCount = 0;
        $totalCommentsFetched = 0;
        $brandReplies = 0;
        $monthAgo = time() - (30 * 24 * 60 * 60);
        $hasActivity = false;

        // Corpus for AI
        $commentCorpus = "";

        foreach ($posts as $post) {
            // Only analyze recent posts
            if ($post['date'] < $monthAgo) {
                continue;
            }

            $commentsCount = $post['comments']['count'] ?? 0;

            // Criteria: Active but manageable threads
            if ($commentsCount > 0 && $commentsCount < 20) {
                $hasActivity = true;
                $analyzedPostsCount++;

                try {
                    $r = Http::get("https://api.vk.com/method/wall.getComments", [
                        'owner_id' => "-$groupId",
                        'post_id' => $post['id'],
                        'count' => 20,
                        'sort' => 'desc',
                        'access_token' => $this->vkToken,
                        'v' => '5.131'
                    ]);

                    $comments = $r['response']['items'] ?? [];
                    $totalCommentsFetched += count($comments);

                    if (!empty($comments)) {
                        $commentCorpus .= "\n[Post ID {$post['id']} Comments]:\n";
                    }

                    foreach ($comments as $c) {
                        $text = trim($c['text'] ?? '');
                        if (empty($text))
                            continue;

                        $fromId = $c['from_id'] ?? 0;

                        // Check Brand Reply (Keep Logic check as backup/validation)
                        if ($fromId == "-$groupId") {
                            $brandReplies++;
                            $commentCorpus .= "Brand Answer: $text\n";
                        } else {
                            $commentCorpus .= "- User: $text\n";
                        }
                    }

                } catch (\Exception $e) {
                    // Silently fail specific comment fetch
                }
            }
        }

        // Default Score logic (Fallback)
        $score = $hasActivity ? 7 : 3;
        $s = "**Community Analysis:**\n";

        // --- AI BLOCK ---
        if (strlen($commentCorpus) > 50) { // Only call AI if we have actual text
            $apiKey = config('ai.yandex.api_key');
            $folderId = config('ai.yandex.folder_id');

            if ($apiKey && $folderId) {
                $prompt = "Проанализируй эти комментарии из соцсетей бренда.
                 Текст комментариев:
                 $commentCorpus
                 
                 Твоя задача:
                 1. Сформируй КРАТКИЙ вывод (summary) - максимум 2 предложения. Укажи общую тональность и ОТВЕЧАЕТ ЛИ БРЕНД (Да/Нет/Редко). Без списка постов!
                 2. Сформируй ДЕТАЛЬНЫЙ отчет (details) - список постов с проблемами, если есть.
                 
                 Верни ответ В ФОРМАТЕ JSON:
                 {\"summary\": \"Текст краткого вывода...\", \"details\": \"Текст детального отчета...\"}
                 ВСЕГДА НА РУССКОМ ЯЗЫКЕ.";

                try {
                    $aiRes = Http::withHeaders([
                        'Authorization' => 'Api-Key ' . $apiKey,
                        'x-folder-id' => $folderId,
                    ])->post('https://llm.api.cloud.yandex.net/foundationModels/v1/completion', [
                                'modelUri' => "gpt://$folderId/yandexgpt", // Standard
                                'completionOptions' => ['stream' => false, 'temperature' => 0.3, 'maxTokens' => 1000],
                                'messages' => [
                                    ['role' => 'system', 'text' => 'Ты эксперт SMM. Отвечай только валидным JSON.'],
                                    ['role' => 'user', 'text' => $prompt]
                                ],
                            ]);

                    $aiText = $aiRes['result']['alternatives'][0]['message']['text'] ?? '';

                    // Clean Code Blocks
                    $aiText = str_replace(['```json', '```'], '', $aiText);
                    $aiData = json_decode($aiText, true);

                    if (json_last_error() === JSON_ERROR_NONE && isset($aiData['summary'])) {
                        $s = $aiData['summary'];
                        $details = $aiData['details'] ?? '';
                    } else {
                        // Fallback if AI fails JSON
                        $s = "AI Analysis (Raw): " . substr($aiText, 0, 100) . "...";
                        $details = $aiText;
                    }

                    // Score adjustment... (Logic remains roughly same)
                    if (str_contains(mb_strtolower($s), 'негатив') || str_contains(mb_strtolower($s), 'toxic')) {
                        $score -= 3;
                    }
                    if (str_contains(mb_strtolower($s), 'игнорирует')) {
                        $score -= 2;
                    }

                } catch (\Exception $e) {
                    $s = "AI Analysis Unavailable.";
                    $details = $e->getMessage();
                }
            } else {
                $s = "AI Config Missing.";
                $details = "";
            }
        } // Close outer if (strlen > 50)

        return [
            'score' => $score,
            'summary' => $s,
            'details' => $details ?? ''
        ];
    }
}
