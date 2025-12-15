import json
import time
import requests
from linkedin_api import Linkedin

LINKEDIN_COOKIE = "AQEDAULTu3kC1ZIvAAABmxhzzPgAAAGbPIBQ-E0AYLzziZLGF4vSdlWGfebcM6ehk9aSm7ty4h3wpghwd3t0eK0Y9KKEVGhv2eJSZKgfMvjtOyfb2XLHbww6p-YOH8fm1kgjfSIbQf1aD8BGaEh_TcyP"
OUTPUT_SQL = "employees_enrichment.sql"
EXISTING_URLS_FILE = "existing_company_urls.json"

# Geo URN for Russia
GEO_RUSSIA = "101728296"

def main():
    print("=== Targeted Employee Enrichment Started ===")
    
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

    # Load Companies
    try:
        with open(EXISTING_URLS_FILE, 'r') as f:
            companies = json.load(f) # Name -> URL
    except:
        companies = {}

    print(f"Loaded {len(companies)} companies to scout.")
    
    sql_statements = []
    
    # Keywords
    keywords = "Sales OR Marketing OR Продажи OR Маркетинг"
    
    for name, url in companies.items():
        if not url or 'company/' not in url:
            continue
            
        urn = url.rstrip('/').split('company/')[-1]
        print(f"\nScouting: {name} (URN: {urn})...")
        
        try:
            # Search Employees
            # current_company filters by URN
            results = api.search_people(
                keywords=keywords, 
                regions=[GEO_RUSSIA], 
                current_company=[urn],
                limit=10 # fetch top 10 relevant people per company
            )
            
            print(f"   > Found {len(results)} matches.")
            
            for person in results:
                p_name = person['name']
                urn_id = person['urn_id'].split(':')[-1]
                public_id = person.get('public_id', urn_id)
                headline = person.get('jobtitle') or person.get('jobTitle') or ''
                
                print(f"     + {p_name} | {headline}")
                
                # SQL Generation
                safe_pname = p_name.replace("'", "''")
                safe_job = headline.replace("'", "''")
                safe_cname = name.replace("'", "''")
                p_url = f"https://www.linkedin.com/in/{public_id}"
                
                # Subquery to link to ID
                # Correct column: position, creation_source
                sql = f"INSERT INTO people (name, linkedin_url, position, company_id, created_at, updated_at, team_id, creation_source) VALUES ('{safe_pname}', '{p_url}', '{safe_job}', (SELECT id FROM companies WHERE name = '{safe_cname}' LIMIT 1), datetime('now'), datetime('now'), 1, 'LINKEDIN_DISCOVERY');"
                sql_statements.append(sql)
                
            time.sleep(2)
            
        except Exception as e:
            print(f"   > Error: {e}")
            time.sleep(1)

    # Save SQL
    if sql_statements:
        with open(OUTPUT_SQL, 'w') as f:
            f.write("BEGIN TRANSACTION;\n")
            for s in sql_statements:
                f.write(s + "\n")
            f.write("COMMIT;\n")
        print(f"\nSaved {len(sql_statements)} new employees to {OUTPUT_SQL}")
    else:
        print("\nNo employees found.")

if __name__ == "__main__":
    main()
