import json
import time
import re
import requests
from duckduckgo_search import DDGS

def main():
    if not os.path.exists('mosint_rich_data.json'):
         print("No input found.")
         return

    with open('mosint_rich_data.json', 'r') as f:
        candidates = json.load(f)

    # 1. Deduplicate by Company to save requests
    # We need company names. We have them in 'summary' field: "Org: xxx"
    # Or we load from candidates.json
    
    company_map = {} # "Org Name" -> [person_ids]
    
    # Load names/companies from candidates.json
    name_map = {}
    if os.path.exists('mosint_candidates.json'):
        with open('mosint_candidates.json', 'r') as f:
            raw = json.load(f)
            for r in raw:
                name_map[r['id']] = r
    
    # Group by company
    for p in candidates:
        pid = p['person_id']
        meta = name_map.get(pid)
        if not meta: continue
        
        comp = meta['company'] 
        if comp not in company_map:
            company_map[comp] = []
        company_map[comp].append(pid)

    print(f"Grouped into {len(company_map)} unique companies.")
    
    results = []
    ddgs = DDGS()
    
    count = 0
    for comp_name, pids in company_map.items():
        if count >= 10: break # Demo limit for safety
        
        query = f'"{comp_name}" contacts phone'
        print(f"Searching: {query}...")
        
        phones_found = []
        
        try:
            # DDG search
            ddg_results = ddgs.text(query, max_results=3)
            
            for res in ddg_results:
                url = res['href']
                print(f"  ? {url}")
                
                try:
                    headers = {'User-Agent': 'Mozilla/5.0'}
                    resp = requests.get(url, headers=headers, timeout=5)
                    text = resp.text
                    # Regex for +7 (xxx) ... or +7-xxx...
                    matches = re.findall(r'(\+7|8)[\s\-(]?(\d{3})[\s\)-]?(\d{3})[\s\-]?(\d{2})[\s\-]?(\d{2})', text)
                    for m in matches:
                         # Reconstruct
                         formatted = f"{m[0]} ({m[1]}) {m[2]}-{m[3]}-{m[4]}"
                         if formatted not in phones_found:
                             phones_found.append(formatted)
                             print(f"    -> HIT! {formatted}")
                except:
                    pass
                
                if phones_found: break # Found one for this company
                
        except Exception as e:
            print(f"DDG Error: {e}")
            time.sleep(5)
        
        if phones_found:
            # Assign to ALL people in this company
            for pid in pids:
                results.append({
                    'person_id': pid,
                    'company': comp_name,
                    'phones': phones_found
                })
        
        count += 1
        time.sleep(2)

    with open('phone_results.json', 'w') as f:
        json.dump(results, f, indent=4, ensure_ascii=False)
    
    print(f"Done. Found phones for {len(results)} people.")

import os
if __name__ == '__main__':
    main()
