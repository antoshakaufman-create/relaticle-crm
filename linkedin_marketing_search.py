import sqlite3
import time
import requests
import json
from linkedin_api import Linkedin

# Config
DB_PATH = "server_database.sqlite"
LINKEDIN_USER = "denis.kubasov17@gmail.com"
LINKEDIN_PASS = "TF255014" 
LINKEDIN_COOKIE = "AQEDAULTu3kC1ZIvAAABmxhzzPgAAAGbPIBQ-E0AYLzziZLGF4vSdlWGfebcM6ehk9aSm7ty4h3wpghwd3t0eK0Y9KKEVGhv2eJSZKgfMvjtOyfb2XLHbww6p-YOH8fm1kgjfSIbQf1aD8BGaEh_TcyP"

# Connect DB
conn = sqlite3.connect(DB_PATH)
conn.row_factory = sqlite3.Row
cursor = conn.cursor()

# Get MOEX Companies
cursor.execute("SELECT id, name FROM companies WHERE creation_source = 'MOEX'")
companies = cursor.fetchall()
print(f"Found {len(companies)} MOEX companies for employee search.")

# Auth LinkedIn
if LINKEDIN_COOKIE:
    print("Using provided cookie for auth...")
    cookie_dict = {'li_at': LINKEDIN_COOKIE, 'JSESSIONID': 'ajax:1234567890'}
    jar = requests.cookies.RequestsCookieJar()
    for k, v in cookie_dict.items():
        jar.set(k, v)
    api = Linkedin(LINKEDIN_USER, LINKEDIN_PASS, cookies=jar)
else:
    api = Linkedin(LINKEDIN_USER, LINKEDIN_PASS)

TARGET_COUNT = 50

for row in companies:
    comp_id = row['id']
    comp_name = row['name']
    
    # Clean company name (remove parentheses like "VK (Mail.ru Group)")
    comp_name_clean = comp_name.split('(')[0].strip()
    
    # Check current DB count
    cursor.execute("SELECT count(*) FROM people WHERE creation_source='LINKEDIN'")
    current_count = cursor.fetchone()[0]
    print(f"Current LinkedIn contacts in DB: {current_count}/{TARGET_COUNT}")
    
    if current_count >= TARGET_COUNT:
        print("Target reached!")
        break
        
    print(f"Searching for {comp_name_clean} employees...")
    
    roles = ["Marketing", "SMM", "Brand", "Digital", "PR", "Communications"]
    found_in_company = 0
    
    for role in roles:
        if found_in_company >= 5: # Limit per company to spread out
            break
            
        query_str = f"{comp_name_clean} {role}"
        # print(f"  Searching '{query_str}'...")
        
        try:
            people = api.search_people(keywords=query_str, limit=3)
            
            if not people:
                continue
                
            for p in people:
                full_name = p.get('name')
                headline = p.get('subline') or p.get('headline')
                public_id = p.get('public_id')
                urn_id = p.get('urn_id')
                
                li_url = f"https://www.linkedin.com/in/{public_id}" if public_id else f"https://www.linkedin.com/in/{urn_id}"
                
                # Check DB
                cursor.execute("SELECT id FROM people WHERE name = ?", (full_name,))
                if cursor.fetchone():
                    continue

                print(f"    + Found: {full_name} ({headline}) at {comp_name}")
                
                # Insert
                cursor.execute("""
                    INSERT INTO people (name, company_id, position, linkedin_url, linkedin_position, created_at, updated_at, team_id, creation_source)
                    VALUES (?, ?, ?, ?, ?, datetime('now'), datetime('now'), 1, 'LINKEDIN')
                """, (full_name, comp_id, headline, li_url, headline))
                conn.commit()
                found_in_company += 1
                
        except Exception as e:
            print(f"    Error searching {query_str}: {e}")
            time.sleep(2)
            
    time.sleep(2) # Safety sleep between companies

conn.close()
print("Done.")
