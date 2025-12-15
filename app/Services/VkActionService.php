<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\Http;

class VkActionService
{
    protected string $vkToken = 'vk1.a.33bsbI5XV3fhrWMN1Ut2VYDbXCNTafhZwcBigSq5XKBlckhhuDnbDnf5Q-TQ7e5Fe8iLkCWRQLdlsAJaC7kbjiK4bAEbTOxSd7qnHMuEUsDF-gKW46vOHlWTPEmP6X5qT6tMZffX9tXIt8vz-FBDuL1Yn5G18TYOnqcH3rxMhmHSNdKy0utYvOTHIXy8dDh8tEdhX1ise6KVvLXURkk0gA';

    /**
     * Find a validated VK group for a company name, prioritizing Website Scraping > Domain Match > GPT > Search.
     */
    public function findGroup(string $query, ?string $domain = null): ?string
    {
        // 1. Scraping Strategy (Gold Standard)
        if ($domain) {
            $url = str_starts_with($domain, 'http') ? $domain : "https://$domain";
            $scrapedUrl = $this->scanWebsiteForVk($url);
            if ($scrapedUrl && $this->validateVkUrl($scrapedUrl))
                return $scrapedUrl;
        }

        try {
            $itemsDomain = [];
            $itemsName = [];

            // 2. Domain Match Search
            if ($domain) {
                $response = Http::get('https://api.vk.com/method/groups.search', [
                    'q' => $domain,
                    'count' => 20,
                    'access_token' => $this->vkToken,
                    'v' => '5.131',
                    'fields' => 'members_count,verified,site'
                ]);
                $itemsDomain = $response['response']['items'] ?? [];

                // Validation (Implicitly valid as they came from search api, but good to be consistent)
                $d = strtolower(trim($domain));
                foreach ($itemsDomain as $item) {
                    $site = strtolower($item['site'] ?? '');
                    if ($site && str_contains($site, $d)) {
                        return "https://vk.com/" . $item['screen_name'];
                    }
                }
            }

            // 3. Name Search (with Domain Validation check)
            $searchRes = $this->searchVkByName($query);

            // IF LATIN and NO RESULTS -> TRY CLEANED + CYRILLIC TRANSLITERATION
            if (empty($searchRes) && preg_match('/^[a-zA-Z0-9\s]+$/', $query)) {
                $cleanedQuery = $this->cleanCompanyName($query);

                // Try searching cleaned latin (e.g. "PIK Group" -> "PIK")
                if ($cleanedQuery !== $query) {
                    $searchRes = $this->searchVkByName($cleanedQuery);
                }

                // If still empty, try transliterated cleaned name (e.g. "PIK" -> "ПИК")
                if (empty($searchRes)) {
                    $cyrillic = $this->transliterate($cleanedQuery);
                    $searchRes = $this->searchVkByName($cyrillic);
                }
            }

            $itemsName = $searchRes; // Assign to itemsName for fallback logic

            // Loop through results immediately to see if we found a valid one
            // If valid found, return it. If NOT found, and name is SHORT, try Contextual Search
            foreach ($searchRes as $item) {
                // Check validity
                $cUrl = "https://vk.com/" . $item['screen_name'];
                if ($this->validateGroupRelevance($cUrl, $query, $domain)) {
                    return $cUrl;
                }
            }

            // If we are here, direct search failed to yield a valid result.
            // If Short Name, try Contextual Search (e.g. "PIK Moscow", "PIK Real Estate")
            $cleanedQuery = $this->cleanCompanyName($query);
            $cyrillic = $this->transliterate($cleanedQuery);
            if (mb_strlen($cleanedQuery) < 5 || mb_strlen($cyrillic) < 5) {
                $suffixes = ['Москва', 'Россия', 'Недвижимость', 'Застройщик', 'Official', 'Официальный'];
                foreach ($suffixes as $suffix) {
                    $contextQuery = $cyrillic . ' ' . $suffix;
                    $contextRes = $this->searchVkByName($contextQuery);
                    foreach ($contextRes as $item) {
                        $cUrl = "https://vk.com/" . $item['screen_name'];
                        // Validate against ORIGINAL query (so name checks strictness apply, but keywords help)
                        if ($this->validateGroupRelevance($cUrl, $query, $domain)) {
                            return $cUrl;
                        }
                    }
                }
            }

            // 4. Fallback to GPT
            if ($domain) {
                $gptUrl = $this->findVkViaGpt($query, $domain);
                if ($gptUrl) {
                    $valid = $this->validateVkUrl($gptUrl);
                    if ($valid) {
                        // Additional Content Relevance Check
                        if ($this->validateGroupRelevance($gptUrl, $query, $domain)) {
                            return $gptUrl;
                        }
                    }
                }
            }

            // 5. Fallback to Heuristic Handle Search (Last Resort for acronyms like CFT)
            // Try: team{name}, {name}team, {name}ru, {name}official
            $heuristicUrl = $this->checkCommonHandles($query, $domain);
            if ($heuristicUrl)
                return $heuristicUrl;

            // 6. Fallback to Best Verified/Size Match from Name Search
            // (Name search is safer default than Domain search if domain is ambiguous acronym)
            $fallbackItems = !empty($itemsName) ? $itemsName : $itemsDomain;

            if (!empty($fallbackItems)) {
                // Sort by Verified then Members
                usort($fallbackItems, function ($a, $b) {
                    $aVer = $a['verified'] ?? 0;
                    $bVer = $b['verified'] ?? 0;
                    if ($aVer !== $bVer)
                        return $bVer <=> $aVer;
                    return ($b['members_count'] ?? 0) <=> ($a['members_count'] ?? 0);
                });

                // Iterate and checks relevance
                foreach ($fallbackItems as $item) {
                    $cUrl = "https://vk.com/" . $item['screen_name'];
                    if ($this->validateGroupRelevance($cUrl, $query, $domain)) {
                        return $cUrl;
                    }
                }
            }

        } catch (\Exception $e) {
            // Log error
        }

        return null;
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
            // Must be a group, page, or event.
            // 'user' is also valid technically but we want companies mostly. Allowing 'user' might be risky for corporate but some small businesses use profiles.
            // Let's allow group, page, event.
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
            $tName = mb_strtolower($companyName); // Target Name

            // Normalize Target Name for stricter checks (remove legal entities)
            $tNameClean = trim(str_replace(['ooo', 'зао', 'ao', 'llc', 'inc', 'company', 'group', 'holdings', 'corporation', '"', "'"], '', $tName));

            // 1. Negative Keywords
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
                'знакомства'
            ];
            foreach ($blacklist as $bad) {
                if (str_contains($gName, $bad))
                    return false;
            }

            // 2. Domain Match (Strongest)
            if ($domain) {
                $baseDom = str_ireplace(['www.', 'http://', 'https://'], '', strtolower($domain));
                if (str_contains($gSite, $baseDom) || str_contains($gDesc, $baseDom)) {
                    return true;
                }
            }

            // 3. Content Validation (Check this BEFORE Verified status for generic names)
            // If the name is generic, we require some evidence it's a business
            $corporateKeywords = ['official', 'официальн', 'group', 'группа', 'company', 'компания', 'ооо', 'зао', 'ao', 'llc', 'shop', 'магазин', 'store', 'business', 'недвижимость', 'developer', 'застройщик', 'brand', 'бренд'];

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

            // Loose match (Normal)
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
            'Ph' => 'Ф'
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

        // 2. Get Posts
        try {
            $r = Http::get("https://api.vk.com/method/wall.get", [
                'owner_id' => "-$groupId",
                'count' => 30, // Analyze last 30 posts
                'access_token' => $this->vkToken,
                'v' => '5.131'
            ]);
            $posts = $r['response']['items'] ?? [];
            $membersCount = 5000; // Simplified mock without extra API call
        } catch (\Exception $e) {
            return ['error' => 'API Error (Wall): ' . $e->getMessage()];
        }

        // 3. Calculate Scores
        $erData = $this->calculateErScore($posts);
        $erScore = $erData['score'];
        $erRaw = $erData['raw'];

        $postingData = $this->calculatePostingScore($posts);
        $postingScore = $postingData['score'];
        $postsPerMonth = $postingData['raw'];

        // Simplified growth (constant for now as we lack history)
        $growthScore = 7;

        // Simplified promo (keyword check)
        $promoScore = $this->calculatePromoScore($posts);

        // Quality check
        $commentQuality = 5; // Simplified to avoid slow extra API calls for comments in UI action

        // Final Score (No GPT)
        $finalScore = $erScore + $postingScore + $growthScore + $promoScore + $commentQuality;
        $finalScore = min(100, round($finalScore, 1));

        // Classify
        $cat = $this->classifyLead($finalScore, $postingScore);
        $status = $cat['status'];
        $catLabel = $cat['cat'];

        // Generate Formula-Based Summary
        $summary = "### SMM Analysis (Formula Based)\n\n" .
            "**Status:** {$status}\n" .
            "**Score:** {$finalScore} ({$catLabel})\n\n" .
            "**Metrics:**\n" .
            "- **ER Score:** " . round($erScore, 1) . " (Raw: " . round($erRaw, 2) . "%)\n" .
            "- **Posting:** " . round($postingScore, 1) . " pts ($postsPerMonth posts/mo)\n" .
            "- **Promo Content:** " . round($promoScore, 1) . " pts\n";

        return [
            'lead_score' => $finalScore,
            'lead_category' => $catLabel,
            'vk_status' => trim(str_replace(' (2025)', '', $status)),
            'er_score' => $erRaw,
            'posts_per_month' => $postsPerMonth,
            'smm_analysis' => $summary
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
            $views = $post['views']['count'] ?? ($likes * 10);
            if ($views == 0)
                $views = 100;

            $totalEngagement += ($likes + $comments + $reposts);
            $totalReach += $views;
        }

        if ($totalReach == 0)
            return ['score' => 0, 'raw' => 0];

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
        // 1. Resolve & Fetch Posts (Reuse logic or call internal helper)
        $path = parse_url($vkUrl, PHP_URL_PATH);
        $screenName = trim(str_replace('/', '', $path));

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
            }
        } catch (\Exception $e) {
            return ['error' => 'API Error: ' . $e->getMessage()];
        }

        if (!$groupId)
            return ['error' => 'Could not resolve Group ID'];

        try {
            $r = Http::get("https://api.vk.com/method/wall.get", [
                'owner_id' => "-$groupId",
                'count' => 30,
                'access_token' => $this->vkToken,
                'v' => '5.131'
            ]);
            $posts = $r['response']['items'] ?? [];
        } catch (\Exception $e) {
            return ['error' => 'API Error: ' . $e->getMessage()];
        }

        // 2. Prepare Data for GPT
        $textSample = "";
        foreach (array_slice($posts, 0, 10) as $p) {
            $textSample .= mb_substr($p['text'] ?? '', 0, 200) . " | ";
        }

        // 3. Call YandexGPT
        $apiKey = config('ai.yandex.api_key');
        $folderId = config('ai.yandex.folder_id');

        $prompt = "Проанализируй эти посты VK группы компании и напиши отчет в формате Markdown.
        Текст постов: $textSample
        
        Структура отчета:
        **1. Контент-стратегия (о чём пишут):**
        * [Список тем и подходов]
        
        **2. Вовлечённость (оценка):**
        * [Как аудитория реагирует, на что направлена стратегия]
        
        **3. Что улучшить:**
        * [5 конкретных советов для SMM]";

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

            return ['smm_analysis' => $aiText];

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
}
