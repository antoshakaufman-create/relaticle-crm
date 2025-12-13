#!/bin/bash

cd /var/www/relaticle

echo "=== Test various Yandex Search API endpoints ==="

# Try v1 endpoint
echo "--- Trying v1 endpoint ---"
curl -s "https://yandex.ru/search/xml?user=virtucrm&key=ajetvrtcaq19kpik8cf6&query=test" | head -50

echo ""
echo "--- Trying searchapi.api.cloud.yandex.net ---"
curl -s -X POST "https://searchapi.api.cloud.yandex.net/v2/web/search" \
  -H "Authorization: Api-Key ajetvrtcaq19kpik8cf6" \
  -H "Content-Type: application/json" \
  -d '{
    "query": {"searchType": "SEARCH_TYPE_RU", "queryText": "test"},
    "folderId": "b1gn3qao39gb9uecn2c2"
  }' | head -100

echo ""
echo "--- Check if we need to use search.api.cloud.yandex.net ---"
curl -s -X POST "https://search.api.cloud.yandex.net/v2/search" \
  -H "Authorization: Api-Key ajetvrtcaq19kpik8cf6" \
  -H "Content-Type: application/json" \
  -d '{
    "query": "test",
    "folderId": "b1gn3qao39gb9uecn2c2"
  }' | head -100

echo ""
echo "=== Done ==="
