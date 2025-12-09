<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

final class YandexSearchService
{
    private ?string $apiKey;
    private ?string $folderId;
    private string $searchUrl;

    public function __construct()
    {
        $this->apiKey = config('ai.yandex.api_key');
        $this->folderId = config('ai.yandex.folder_id');
        $this->searchUrl = config('ai.yandex.search_url', 'https://search.api.cloud.yandex.net/search/v2/search');
    }

    /**
     * Perform a search query and return a list of snippets.
     *
     * @param string $query
     * @return array<int, array{title: string, url: string, description: string}>
     */
    public function search(string $query): array
    {
        if (!$this->apiKey || (!$this->folderId && str_contains($this->searchUrl, 'cloud.yandex'))) {
            Log::warning('Yandex Search API configuration missing.');
            return [];
        }

        try {
            // Yandex Cloud Search V2 XML format
            // We Post XML to the endpoint signed with API Key
            $xmlQuery = $this->buildXmlQuery($query);

            $response = Http::withHeaders([
                'Authorization' => 'Api-Key ' . $this->apiKey,
                'Content-Type' => 'application/xml',
            ])->post($this->searchUrl, $xmlQuery);

            if ($response->successful()) {
                return $this->parseXmlResponse($response->body());
            }

            Log::error('Yandex Search API Error: ' . $response->status() . ' - ' . $response->body());
            return [];
        } catch (\Exception $e) {
            Log::error('Yandex Search Exception: ' . $e->getMessage());
            return [];
        }
    }

    private function buildXmlQuery(string $query): string
    {
        // Simple XML structure for Cloud Search
        // Note: The specific format depends on Cloud vs XML setup, but Cloud Search typically accepts a specific structure.
        // Assuming standard Yandex XML Search structure wrapped for Cloud.
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<request>
    <query>$query</query>
    <sortby>rlv</sortby>
    <maxpassages>2</maxpassages>
    <page>0</page>
    <grouping>
        <mode>flat</mode>
        <groups-on-page>10</groups-on-page>
    </grouping>
    <folderid>{$this->folderId}</folderid>
</request>
XML;
    }

    private function parseXmlResponse(string $xmlBody): array
    {
        $results = [];
        try {
            $xml = new SimpleXMLElement($xmlBody);

            // Navigate the XML structure: response -> results -> grouping -> group -> doc
            if (isset($xml->response->results->grouping->group)) {
                foreach ($xml->response->results->grouping->group as $group) {
                    $doc = $group->doc;

                    // Extract title (highlighting tags might be present)
                    $title = (string) $doc->title;
                    $title = strip_tags($title); // Clean highlighting

                    $url = (string) $doc->url;

                    // Extract description/passages
                    $description = '';
                    if (isset($doc->passages->passage)) {
                        foreach ($doc->passages->passage as $passage) {
                            $description .= (string) $passage . ' ';
                        }
                    } elseif (isset($doc->headline)) {
                        $description = (string) $doc->headline;
                    }
                    $description = strip_tags($description);

                    $results[] = [
                        'title' => trim($title),
                        'url' => trim($url),
                        'description' => trim($description),
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::error('Error parsing Yandex XML response: ' . $e->getMessage());
        }

        return $results;
    }
}
