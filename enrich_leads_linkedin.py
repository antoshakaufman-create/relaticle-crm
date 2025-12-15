import json
import sqlite3
import datetime
from linkedin_api import Linkedin
import requests

# Config
DB_PATH = '/var/www/relaticle/database/database.sqlite' # Server path
INPUT_FILE = 'companies_to_enrich.json'

LINKEDIN_USER = "denis.kubasov17@gmail.com"
LINKEDIN_PASS = "TF255014"
# Trying to reuse cookie if possible, but logging in freshly to be safe or use what works
# Assuming credentials work as per debug_linkedin.py

def connect_db():
    return sqlite3.connect(DB_PATH)

def main():
    print("=== LinkedIn Enrichment Started ===")
    
    # 1. Auth
    try:
        api = Linkedin(LINKEDIN_USER, LINKEDIN_PASS)
        print("LinkedIn Auth Success")
    except Exception as e:
        print(f"LinkedIn Auth Failed: {e}")
        return

    # 2. Read Input
    try:
        with open(INPUT_FILE, 'r') as f:
            companies = json.load(f)
    except Exception as e:
        print(f"Could not read {INPUT_FILE}: {e}")
        return

    conn = connect_db()
    cursor = conn.cursor()

    for comp in companies:
        name = comp['name']
        cid = comp['id']
        print(f"\nProcessing: {name} (ID: {cid})")

        # A. Find Company on LinkedIn
        urn_id = None
        try:
            res = api.search_companies(keywords=name, limit=1)
            if res:
                c_data = res[0]
                urn = c_data.get('urn_id', '') # urn:li:company:123
                urn_id = urn.split(':')[-1]
                li_url = f"https://www.linkedin.com/company/{urn_id}"
                
                print(f" - Found LinkedIn: {li_url}")
                
                # Update Company DB
                cursor.execute("UPDATE companies SET linkedin_url = ? WHERE id = ?", (li_url, cid))
                conn.commit()
            else:
                print(" - LinkedIn Company Not Found")
        except Exception as e:
            print(f" - Error finding company: {e}")
            continue

        if not urn_id:
            continue

        # B. Find Employees (Marketing, Founder, CEO)
        queries = ["Marketing", "CEO", "Founder", "SMM"]
        # Just use one combined query
        combined_q = "Marketing OR SMM OR CEO OR Founder"
        
        try:
            people = api.search_people(keywords=combined_q, current_company=[urn_id], limit=3)
            if not people:
                print(" - No employees found.")
                continue
                
            print(f" - Found {len(people)} employees. Saving...")
            
            for p in people:
                p_urn = p.get('urn_id', '') # urn:li:member:123
                p_id = p_urn.split(':')[-1]
                p_name = p.get('name', 'Unknown')
                p_title = p.get('jobTitle', 'Employee')
                p_url = f"https://www.linkedin.com/in/{p.get('public_id', p_id)}"
                
                # Insert into People
                # Schema: name, company_id, position, linkedin_url, team_id, created_at...
                now = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
                
                # Check duplicate by linkedin_url (if possible) or just name+company
                cursor.execute("SELECT id FROM people WHERE linkedin_url = ? OR (name = ? AND company_id = ?)", (p_url, p_name, cid))
                if cursor.fetchone():
                    print(f"   * Skip existing: {p_name}")
                    continue

                cursor.execute("""
                    INSERT INTO people (
                        name, company_id, position, linkedin_url, team_id, 
                        created_at, updated_at, creation_source
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                """, (p_name, cid, p_title, p_url, 1, now, now, 'import'))
                
                print(f"   + Added: {p_name} ({p_title})")
                
            conn.commit()

        except Exception as e:
            print(f" - Error finding people: {e}")

    conn.close()
    print("\n=== Enrichment Complete ===")

if __name__ == "__main__":
    main()
