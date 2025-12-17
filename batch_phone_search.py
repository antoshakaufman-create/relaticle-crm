import json
import re
import time
import time
from googlesearch import search
import requests

# Load verified candidates (using mosint_rich_data as it has names/orgs)
def main():
    if not os.path.exists('mosint_rich_data.json'):
         print("No input file found.")
         return

    with open('mosint_rich_data.json', 'r') as f:
        candidates = json.load(f)

    # Load ID -> Name mapping if missing in rich data
    # Actually rich data only has ID/Email. We need Names.
    # We should re-export names from DB or fetch from `candidates.json` if available?
    # Or just use email domain
    
    # Load original candidates to get names
    names = {}
    if os.path.exists('mosint_candidates.json'):
        with open('mosint_candidates.json', 'r') as f:
            raw = json.load(f)
            for r in raw:
                names[r['id']] = {'name': r['name'], 'company': r['company']}
    
    results = []
    
    print(f"Starting Phone Discovery for {len(candidates)} contacts...")
    
    # Limit to first 20 for testing the user "find the way" request
    count = 0 
    
    for person in candidates:
        pid = person['person_id']
        meta = names.get(pid, {})
        name = meta.get('name')
        company = meta.get('company')
        
        if not name: continue
        
        query = f'"{name}" "{company}" phone OR телефон OR mobile'
        print(f"Searching: {query}...")
        
        found_phones = []
        try:
            # googlesearch-python uses 'num_results' or 'advanced' depending on version
            # The error says 'stop' is unexpected.
            # Standard usage: search("query", num_results=10)
            
            for url in search(query, num_results=5, sleep_interval=2):
                print(f"  ? {url}")
                
                # Check for PDF/Doc
                # Use regex on the page content? Too slow/risky for blocking.
                # Let's just capture the URLs where the phone might be.
                # Better: Use a simple regex on the *first* result's page content if it's promising.
                
                # Filter for promising URLs (not generic aggregators if possible)
                print(f"  ? {url}")
                
                # Check for PDF/Doc
                if url.endswith('.pdf') or url.endswith('.xls'):
                    found_phones.append(f"Found Doc: {url}")
                    continue

                # Try to fetch page content (Timeout 5s)
                try:
                   headers = {'User-Agent': 'Mozilla/5.0'}
                   resp = requests.get(url, headers=headers, timeout=5)
                   text = resp.text
                   
                   # Regex for Russian phones: +7 (xxx) xxx-xx-xx or 8 xxx
                   phones = re.findall(r'(\+7|8)[\s\-]?\(?[0-9]{3}\)?[\s\-]?[0-9]{3}[\s\-]?[0-9]{2}[\s\-]?[0-9]{2}', text)
                   
                   if phones:
                       unique = list(set(phones))
                       print(f"    -> HIT! {unique[:2]}")
                       found_phones.extend(unique[:2])
                       break # Found a phone for this person, move to next
                except:
                    pass
            
        except Exception as e:
            print(f"Search error: {e}")
        
        if found_phones:
            res = {
                'person_id': pid,
                'name': name,
                'phones': found_phones
            }
            results.append(res)
        
        count += 1
        if count >= 10: break # Demo mode
        
        time.sleep(2)

    with open('phone_discovery_results.json', 'w') as f:
        json.dump(results, f, indent=4, ensure_ascii=False)
        
    print(f"Done. Found potential phones for {len(results)} people.")

import os
if __name__ == '__main__':
    main()
