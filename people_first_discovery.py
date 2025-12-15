import json
import time
import requests
from linkedin_api import Linkedin

LINKEDIN_COOKIE = "AQEDAULTu3kC1ZIvAAABmxhzzPgAAAGbPIBQ-E0AYLzziZLGF4vSdlWGfebcM6ehk9aSm7ty4h3wpghwd3t0eK0Y9KKEVGhv2eJSZKgfMvjtOyfb2XLHbww6p-YOH8fm1kgjfSIbQf1aD8BGaEh_TcyP"
OUTPUT_SQL = "people_discovery.sql"
EXISTING_URLS_FILE = "existing_company_urls.json"

# Geo URN for Russia
GEO_RUSSIA = "101728296"

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
    print("=== People-First Discovery Started ===")
    
    # Auth
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

    # Load Existing Companies (Name -> URL)
    try:
        with open(EXISTING_URLS_FILE, 'r') as f:
            existing_companies = json.load(f)
    except:
        existing_companies = {}

    existing_urns = set()
    for name, url in existing_companies.items():
        if 'company/' in url:
            urn = url.rstrip('/').split('company/')[-1]
            existing_urns.add(urn)

    print(f"Loaded {len(existing_urns)} existing company URNs.")
    
    sql_statements = []
    
    # Keywords
    # Specific titles to find employed people
    keywords = '"Sales Manager" OR "Marketing Director" OR "Head of Sales" OR "CMO" OR "Директор по маркетингу" OR "Руководитель отдела продаж"'
    
    print(f"Searching for: '{keywords}' in Russia...")
    
    try:
        # Search People
        # Increase limit to check more
        results = api.search_people(keywords=keywords, regions=[GEO_RUSSIA], limit=50)
        
        print(f"Found {len(results)} potential profiles.")
        
        for person in results:
            name = person['name']
            urn_id = person['urn_id'].split(':')[-1]
            public_id = person.get('public_id', urn_id)
            # Fix casing: API returns 'jobtitle' (lowercase)
            headline = person.get('jobtitle') or person.get('jobTitle') or ''
            location = person.get('locationName', '')
            
            print(f"\nAnalyzing: {name} | {headline} ({location})")
            
            # Fetch full profile to get current company URN?
            # search_people results often contain 'jobTitle' but maybe not structured company URN directly in 'current_company'
            # We usually need to fetch profile or guess.
            
            print(f"\nAnalyzing: {name} | {headline} ({location})")
            
            company_name = None
            company_urn = None
            
            # Lightweight: Extract Company from Headline directly
            # "Head of Sales at R-Pharm" -> "R-Pharm"
            separators = [" at ", " @ ", " в "]
            for sep in separators:
                if sep in headline:
                    parts = headline.split(sep)
                    company_name = parts[-1].strip()
                    break
            
            # Fallback: if no separator, assume invalid?
            if not company_name:
                print("   > Coudn't extract company from headline. Searching by Name?")
                # Too risky. Skip.
                continue

            print(f"   > Extracted Company: {company_name}")
            
            # Check if Large (Find URN and Employee Count)
            try:
                # Search Company to get URN and Size
                res = api.search_companies(keywords=company_name, limit=1)
                if not res:
                    print("     [SKIP] Company not found on LinkedIn.")
                    continue
                
                comp = res[0]
                company_urn = comp.get('urn_id', '').split(':')[-1]
                
                # Get Details
                comp_details = api.get_company(company_urn)
                staff_count = comp_details.get('staffCount', 0)
                
                print(f"   > Verified: {comp['name']} (URN: {company_urn}) | Staff: {staff_count}")
                
                if staff_count > 100: # Slightly lower threshold to catch mid-large
                     print("     [ACCEPTED] Large Firm.")
                     p_url = f"https://www.linkedin.com/in/{public_id}"
                     c_url = f"https://www.linkedin.com/company/{company_urn}"
                     
                     safe_cname = comp['name'].replace("'", "''")
                     
                     # 1. Insert Company
                     if company_urn not in existing_urns:
                         sql = f"INSERT OR IGNORE INTO companies (name, linkedin_url, industry, lead_score, lead_category, created_at, updated_at, team_id, creation_source) VALUES ('{safe_cname}', '{c_url}', 'Unknown', 0, 'COLD', datetime('now'), datetime('now'), 1, 'LINKEDIN_DISCOVERY');"
                         sql_statements.append(sql)
                         existing_urns.add(company_urn)
                     
                     # 2. Insert Person
                     safe_pname = name.replace("'", "''")
                     safe_job = headline.replace("'", "''")

                     # We link by Name since we might just have inserted it.
                     # Using subquery OK.
                     sql_person = f"INSERT INTO people (name, linkedin_url, title, company_id, created_at, updated_at, team_id) VALUES ('{safe_pname}', '{p_url}', '{safe_job}', (SELECT id FROM companies WHERE name = '{safe_cname}' LIMIT 1), datetime('now'), datetime('now'), 1);"
                     sql_statements.append(sql_person)
                     
                else:
                    print("     [SKIP] Too small.")
                    
            except Exception as e:
                print(f"     [Error] Company validation failed: {str(e)}")
                time.sleep(1)

            time.sleep(2) # Rate limit


            time.sleep(2) # Rate limit

    except Exception as e:
        print(f"Search Error: {e}")

    # Save SQL
    if sql_statements:
        with open(OUTPUT_SQL, 'w') as f:
            f.write("BEGIN TRANSACTION;\n")
            for s in sql_statements:
                f.write(s + "\n")
            f.write("COMMIT;\n")
        print(f"\nSaved {len(sql_statements)} updates to {OUTPUT_SQL}")
    else:
        print("\nNo findings.")

if __name__ == "__main__":
    main()
