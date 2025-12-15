import json
import time
import datetime
from linkedin_api import Linkedin

# Config
INPUT_FILE = 'companies_to_enrich.json'
OUTPUT_SQL = 'linkedin_results.sql'

LINKEDIN_USER = "denis.kubasov17@gmail.com"
LINKEDIN_PASS = "TF255014"

def escape_sql(text):
    if not text:
        return ''
    return text.replace("'", "''")

LINKEDIN_COOKIE = "AQEDAULTu3kC1ZIvAAABmxhzzPgAAAGbPIBQ-E0AYLzziZLGF4vSdlWGfebcM6ehk9aSm7ty4h3wpghwd3t0eK0Y9KKEVGhv2eJSZKgfMvjtOyfb2XLHbww6p-YOH8fm1kgjfSIbQf1aD8BGaEh_TcyP"

def clean_name(name):
    # Split by common separators and take first part
    for sep in ['|', '—', '-', '«']:
        if sep in name:
            name = name.split(sep)[0]
    
    # Remove generic words if left at end
    name = name.strip()
    return name

def main():
    print("=== Local LinkedIn Scraper Started ===")
    
    # 1. Auth
    try:
        import requests
        if LINKEDIN_COOKIE:
             print(" [INFO] Authenticating via Cookie...")
             cookie_dict = {'li_at': LINKEDIN_COOKIE, 'JSESSIONID': 'ajax:1234567890'}
             jar = requests.cookies.RequestsCookieJar()
             for k, v in cookie_dict.items():
                 jar.set(k, v)
             api = Linkedin(LINKEDIN_USER, LINKEDIN_PASS, cookies=jar)
        else:
             api = Linkedin(LINKEDIN_USER, LINKEDIN_PASS)
        print(" [OK] LinkedIn Auth Success")
    except Exception as e:
        print(f" [FAIL] LinkedIn Auth Error: {e}")
        return

    # 2. Read Input
    try:
        with open(INPUT_FILE, 'r') as f:
            companies = json.load(f)
    except Exception as e:
        print(f" [FAIL] Could not read input file: {e}")
        return

    sql_statements = []
    
    print(f"Processing {len(companies)} companies...")

    for comp in companies:
        raw_name = comp['name']
        name = clean_name(raw_name)
        cid = comp['id']
        print(f"\nProcessing: {name} (ID: {cid}) [Raw: {raw_name}]")

        # A. Find Company
        urn_id = None
        try:
            # Search
            res = api.search_companies(keywords=name, limit=1)
            if res:
                c_data = res[0]
                urn = c_data.get('urn_id', '') 
                urn_id = urn.split(':')[-1]
                li_url = f"https://www.linkedin.com/company/{urn_id}"
                
                print(f"   > Found Company: {li_url}")
                
                # SQL: Update Company URL
                sql_statements.append(f"UPDATE companies SET linkedin_url = '{li_url}' WHERE id = {cid};")
            else:
                print("   > Company Not Found")
        except Exception as e:
            print(f"   > Error finding company: {e}")
            continue

        if not urn_id:
            continue

        # B. Find Employees
        # We want Decision Makers: Marketing, Founder, CEO, SMM
        combined_q = "Marketing OR SMM OR CEO OR Founder"
        
        try:
            people = api.search_people(keywords=combined_q, current_company=[urn_id], limit=2)
            
            if not people:
                print("   > No employees found.")
                continue
                
            print(f"   > Found {len(people)} employees.")
            
            for p in people:
                p_urn = p.get('urn_id', '')
                p_id = p_urn.split(':')[-1] # urn:li:member:123
                p_public_id = p.get('public_id', p_id)
                p_name = escape_sql(p.get('name', 'Unknown'))
                p_title = escape_sql(p.get('jobTitle', 'Employee'))
                p_url = f"https://www.linkedin.com/in/{p_public_id}"
                
                now = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
                
                # SQL: Insert Person (sqlite syntax)
                # Check existance via WHERE NOT EXISTS is cleaner but simple INSERT OR IGNORE / INSERT valid too.
                # Let's use standard INSERT but we'll run this on server where ID auto-inc.
                
                sql = f"""
                INSERT INTO people (name, company_id, position, linkedin_url, team_id, created_at, updated_at, creation_source)
                SELECT '{p_name}', {cid}, '{p_title}', '{p_url}', 1, '{now}', '{now}', 'import'
                WHERE NOT EXISTS (SELECT 1 FROM people WHERE linkedin_url = '{p_url}');
                """
                sql_statements.append(sql.strip())
                print(f"     + Added SQL for: {p_name}")

        except Exception as e:
            print(f"   > Error finding people: {e}")
            
        time.sleep(2) # Polite delay

    # 3. Write SQL
    with open(OUTPUT_SQL, 'w') as f:
        f.write("BEGIN TRANSACTION;\n")
        for s in sql_statements:
            f.write(s + "\n")
        f.write("COMMIT;\n")
        
    print(f"\n=== Done. Generated {len(sql_statements)} SQL statements in {OUTPUT_SQL} ===")

if __name__ == "__main__":
    main()
