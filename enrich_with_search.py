import sqlite3
import re
import time
import requests
from linkedin_api import Linkedin

# Config
DB_PATH = "server_database.sqlite"
LINKEDIN_USER = "denis.kubasov17@gmail.com"
LINKEDIN_PASS = "TF255014" 
LINKEDIN_COOKIE = "AQEDAULTu3kC1ZIvAAABmxhzzPgAAAGbPIBQ-E0AYLzziZLGF4vSdlWGfebcM6ehk9aSm7ty4h3wpghwd3t0eK0Y9KKEVGhv2eJSZKgfMvjtOyfb2XLHbww6p-YOH8fm1kgjfSIbQf1aD8BGaEh_TcyP"

# Regex
EMAIL_REGEX = r'[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}'
# Phone regex is risky, let's stick to obvious international formats or skipping for now to avoid garbage
# PHONE_REGEX = r'\+?[0-9]{1,4}[ .-]?[0-9]{3}[ .-]?[0-9]{3}[ .-]?[0-9]{2,4}' 

def enrich():
    print("starting enrichment...")
    
    # Auth
    if LINKEDIN_COOKIE:
        print("Auth with Cookie...")
        cookie_dict = {'li_at': LINKEDIN_COOKIE, 'JSESSIONID': 'ajax:1234567890'}
        jar = requests.cookies.RequestsCookieJar()
        for k, v in cookie_dict.items():
            jar.set(k, v)
        api = Linkedin(LINKEDIN_USER, LINKEDIN_PASS, cookies=jar)
    else:
        api = Linkedin(LINKEDIN_USER, LINKEDIN_PASS)
        
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    cursor = conn.cursor()
    
    # Get LinkeIn people without email
    cursor.execute("SELECT id, name, linkedin_url FROM people WHERE creation_source='LINKEDIN' AND (email IS NULL OR email = '')")
    people = cursor.fetchall()
    
    print(f"Found {len(people)} people to enrich.")
    
    cnt = 0
    for person in people:
        pid = person['id']
        name = person['name']
        url = person['linkedin_url']
        
        # Extract ID from URL
        # Format: .../in/public_id or .../in/ACo...
        try:
            public_id = url.split('/in/')[-1].strip('/')
        except:
            print(f"Skipping malformed URL: {url}")
            continue
            
        print(f"[{cnt+1}/{len(people)}] Scanning {name} ({public_id})...")
        
        email_found = None
        phone_found = None
        
        try:
            # 1. Fetch Full Profile
            # Check if public_id looks like a URN (starts with ACo or is numeric)
            profile = None
            if public_id.startswith('ACo') or public_id.startswith('AU'):
                 # Resolve to numeric URN via Search
                 res = api.search_people(keywords=name, limit=1)
                 if res:
                     # e.g. urn:li:member:12345
                     full_urn = res[0].get('urn_id', '')
                     if 'member' in full_urn:
                         numeric_id = full_urn.split(':')[-1]
                         profile = api.get_profile(urn_id=numeric_id)
            
            if not profile:
                 profile = api.get_profile(public_id=public_id)
            
            # 2. Scan Summary / About / Headline
            text_blob = str(profile.get('summary', '')) + " " + str(profile.get('headline', ''))
            
            emails = re.findall(EMAIL_REGEX, text_blob)
            if emails:
                email_found = emails[0]
                print(f"  -> Found Email in Text: {email_found}")
                
            # 3. Check official fields if available (rare)
            # if not email_found:
            #     contact = api.get_profile_contact_info(public_id)
            #     if contact.get('email_address'):
            #         email_found = contact.get('email_address')
            #         print(f"  -> Found Email in Contact Info: {email_found}")
            
            if email_found:
                cursor.execute("UPDATE people SET email = ?, updated_at = datetime('now') WHERE id = ?", (email_found, pid))
                conn.commit()
                
        except Exception as e:
            print(f"  Error: {e}")
            
        time.sleep(2) # Rate limit
        cnt += 1
        
    conn.close()
    print("Done.")

if __name__ == "__main__":
    enrich()
