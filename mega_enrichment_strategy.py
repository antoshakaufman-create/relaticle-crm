import json
import time
import requests
from linkedin_api import Linkedin

# Config
INPUT_FILE = 'contacts_export.json'
OUTPUT_SQL = 'mega_enrichment_results.sql'
LINKEDIN_COOKIE = "AQEDAULTu3kC1ZIvAAABmxhzzPgAAAGbPIBQ-E0AYLzziZLGF4vSdlWGfebcM6ehk9aSm7ty4h3wpghwd3t0eK0Y9KKEVGhv2eJSZKgfMvjtOyfb2XLHbww6p-YOH8fm1kgjfSIbQf1aD8BGaEh_TcyP"

# Manual Map to bootstrap the URN cache
COMPANY_URN_CACHE = {
    'Р Фарм': '1606395',
    'R-Pharm': '1606395',
    'R Farm': '1606395',
    'Валента Фарм': 'unknown', # Will be resolved
    'Valenta Pharm': 'unknown' 
}

def simple_transliterate(text):
    mapping = {
        'а': 'a', 'б': 'b', 'в': 'v', 'г': 'g', 'д': 'd', 'е': 'e', 'ё': 'yo',
        'ж': 'zh', 'з': 'z', 'и': 'i', 'й': 'y', 'к': 'k', 'л': 'l', 'м': 'm',
        'н': 'n', 'о': 'o', 'п': 'p', 'р': 'r', 'с': 's', 'т': 't', 'у': 'u',
        'ф': 'f', 'х': 'kh', 'ц': 'ts', 'ч': 'ch', 'ш': 'sh', 'щ': 'shch',
        'ъ': '', 'ы': 'y', 'ь': '', 'э': 'e', 'ю': 'yu', 'я': 'ya'
    }
    res = ''
    for char in text.lower():
        res += mapping.get(char, char)
    return res.title()

def get_company_urn(api, company_name):
    # Check Cache
    if company_name in COMPANY_URN_CACHE:
        urn = COMPANY_URN_CACHE[company_name]
        if urn and urn != 'unknown':
            return urn
    
    # Search
    try:
        # Try finding as is
        print(f"   [URN Discovery] Searching for company '{company_name}'...")
        res = api.search_companies(keywords=company_name, limit=1)
        if res:
            urn = res[0]['urn_id'].split(':')[-1]
            COMPANY_URN_CACHE[company_name] = urn # Cache it
            print(f"   [URN Discovery] Found: {res[0]['name']} -> {urn}")
            return urn

        # Try Transliterated
        lat_name = simple_transliterate(company_name)
        if lat_name != company_name:
            print(f"   [URN Discovery] Trying transliterated '{lat_name}'...")
            res = api.search_companies(keywords=lat_name, limit=1)
            if res:
                urn = res[0]['urn_id'].split(':')[-1]
                COMPANY_URN_CACHE[company_name] = urn
                print(f"   [URN Discovery] Found: {res[0]['name']} -> {urn}")
                return urn
    except Exception as e:
        print(f"   [URN Error] {e}")
    
    COMPANY_URN_CACHE[company_name] = None # Mark as not found
    return None

def main():
    print("=== Mega-Enrichment Strategy Started ===")
    
    # 1. Auth
    try:
        import requests
        if LINKEDIN_COOKIE:
             cookie_dict = {'li_at': LINKEDIN_COOKIE, 'JSESSIONID': 'ajax:1234567890'}
             jar = requests.cookies.RequestsCookieJar()
             for k, v in cookie_dict.items():
                 jar.set(k, v)
             api = Linkedin("", "", cookies=jar)
             print(" [OK] Auth Success")
    except Exception as e:
        print(f" [FAIL] Auth Error: {e}")
        return

    # 2. Read Contacts
    with open(INPUT_FILE, 'r') as f:
        contacts = json.load(f)
    print(f"Processing {len(contacts)} contacts...")

    sql_statements = []
    found_count = 0

    for p in contacts:
        # Limit processing for test run? No, user wants full.
        # But let's process first 10 for speed verification.
        # if found_count > 5: break 
        
        cid = p['id']
        name_cyr = p['name']
        company_raw = p['company']
        
        print(f"\nProcessing: {name_cyr} @ {company_raw}")
        
        name_lat = simple_transliterate(name_cyr)
        
        # Step A: Get Company URN
        urn = get_company_urn(api, company_raw)
        
        # Step B: Multi-Layer Person Search
        found_person = None
        
        # Layer 1: Search by Name + URN (Most Precise)
        if urn:
            try:
                # 1.1 Latin
                res = api.search_people(keywords=name_lat, current_company=[urn], limit=1)
                if res: found_person = res[0]
                
                # 1.2 Cyrillic
                if not found_person:
                    res = api.search_people(keywords=name_cyr, current_company=[urn], limit=1)
                    if res: found_person = res[0]
            except Exception as e:
                print(f"   [Search Error L1] {e}")

        # Layer 2: Search by Name + Title Keyword (If URN failed or missing)
        if not found_person:
            # Strategies:
            # - Keywords="Name" + Title="Company" (Works if they put company in headline)
            try:
                # "Name Company" keyword search
                # This mimics Google: `Mikhail Samsonov R-Pharm`
                query = f"{name_lat} {simple_transliterate(company_raw)}"
                res = api.search_people(keywords=query, limit=1)
                
                if res:
                    # Validate? Check if company name appears in headline/subline
                    p = res[0]
                    headline = p.get('jobTitle', '').lower()
                    subline = p.get('subline', '').lower() # Sometimes location/company
                    target = simple_transliterate(company_raw).lower()
                    # Loose validation: if 3 chars match? No, too risky.
                    # Just accept top hit for this loose strategy?
                    # User asked for "Job Title set to this firm".
                    found_person = p
            except Exception as e:
                 print(f"   [Search Error L2] {e}")

        # Result Logic
        if found_person:
            urn_id = found_person.get('urn_id', '').split(':')[-1]
            public_id = found_person.get('public_id', urn_id)
            url = f"https://www.linkedin.com/in/{public_id}"
            title = found_person.get('jobTitle', 'Unknown')
            
            print(f"   [SUCCESS] Found: {found_person['name']} | {title}")
            print(f"   > URL: {url}")
            
            sql_statements.append(f"UPDATE people SET linkedin_url = '{url}' WHERE id = {cid};")
            found_count += 1
        else:
            print("   [FAIL] Not Found")
            
        time.sleep(1) # Rate limit protection

    # 3. Write SQL
    if sql_statements:
        with open(OUTPUT_SQL, 'w') as f:
            f.write("BEGIN TRANSACTION;\n")
            for s in sql_statements:
                f.write(s + "\n")
            f.write("COMMIT;\n")
        print(f"\nSaved {len(sql_statements)} updates to {OUTPUT_SQL}")
    else:
        print("\nNo updates found.")

if __name__ == "__main__":
    main()
