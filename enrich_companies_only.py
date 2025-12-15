import json
import time
import requests
from linkedin_api import Linkedin

INPUT_FILE = 'full_enrichment_list.json'
OUTPUT_SQL = 'full_moex_enrichment.sql'
LINKEDIN_COOKIE = "AQEDAULTu3kC1ZIvAAABmxhzzPgAAAGbPIBQ-E0AYLzziZLGF4vSdlWGfebcM6ehk9aSm7ty4h3wpghwd3t0eK0Y9KKEVGhv2eJSZKgfMvjtOyfb2XLHbww6p-YOH8fm1kgjfSIbQf1aD8BGaEh_TcyP"

# Cache found URNs to avoid re-searching same company
CACHE = {}
# Track which companies we have already generated SQL for to avoid duplicates
PROCESSED_COMPANIES = set()

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

def main():
    print("=== Company Enrichment Started ===")
    
    try:
        if LINKEDIN_COOKIE:
             cookie_dict = {'li_at': LINKEDIN_COOKIE, 'JSESSIONID': 'ajax:1234567890'}
             jar = requests.cookies.RequestsCookieJar()
             for k, v in cookie_dict.items():
                 jar.set(k, v)
             api = Linkedin("", "", cookies=jar)
    except Exception as e:
        print(f"Auth Error: {e}")
        return

    with open(INPUT_FILE, 'r') as f:
        contacts = json.load(f)

    sql_statements = []
    
    # Extract unique companies from contacts to save time
    # (Though input is contacts, we care about unique companies)
    unique_companies = {} # Name -> 1
    for p in contacts:
        unique_companies[p['company']] = True
        
    print(f"Found {len(unique_companies)} unique companies to enrich.")
    
    for com_name in unique_companies.keys():
        if com_name in PROCESSED_COMPANIES: continue
        
        print(f"\nProcessing Company: {com_name}")
        
        urn = None
        # Try Transliteration First (better for Russian companies)
        lat_name = simple_transliterate(com_name)
        
        try:
            # Search Latin
            res = api.search_companies(keywords=lat_name, limit=1)
            if res:
                urn = res[0]['urn_id'].split(':')[-1]
                print(f"   > Found (Latin): {res[0]['name']} -> {urn}")
            else:
                 # Search Cyrillic
                 res = api.search_companies(keywords=com_name, limit=1)
                 if res:
                     urn = res[0]['urn_id'].split(':')[-1]
                     print(f"   > Found (Cyrillic): {res[0]['name']} -> {urn}")
        except Exception as e:
            print(f"   > Error: {e}")
            
        if urn:
            url = f"https://www.linkedin.com/company/{urn}"
            # Use strict name matching in SQL to avoid accidents? 
            # SQLite escape single quotes
            safe_name = com_name.replace("'", "''")
            sql = f"UPDATE companies SET linkedin_url = '{url}' WHERE name = '{safe_name}';"
            sql_statements.append(sql)
            PROCESSED_COMPANIES.add(com_name)
        else:
            print("   > Not Found")
            
        time.sleep(1)

    # Convert to SQL
    with open(OUTPUT_SQL, 'w') as f:
        f.write("BEGIN TRANSACTION;\n")
        for s in sql_statements:
            f.write(s + "\n")
        f.write("COMMIT;\n")
    
    print(f"\nSaved {len(sql_statements)} company updates to {OUTPUT_SQL}")

if __name__ == "__main__":
    main()
