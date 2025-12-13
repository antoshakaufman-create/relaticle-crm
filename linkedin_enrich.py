import os
import sqlite3
import time
import argparse
import pandas as pd
from linkedin_api import Linkedin
from dotenv import load_dotenv

# Load environment variables
load_dotenv()

def get_db_connection():
    # Connect to the DOWNLOADED server database
    conn = sqlite3.connect('server_database.sqlite')
    conn.row_factory = sqlite3.Row
    return conn

import requests

def enrich_people(username, password, limit=10, cookie_value=None):
    print(f"Authenticating with LinkedIn...")
    try:
        if cookie_value:
            # Inject dummy JSESSIONID as it is often required for CSRF checks by the lib
            # Convert to CookieJar to avoid 'dict has no attribute extract_cookies' error
            cookie_dict = {'li_at': cookie_value, 'JSESSIONID': 'ajax:1234567890'}
            jar = requests.cookies.RequestsCookieJar()
            for k, v in cookie_dict.items():
                jar.set(k, v)
                
            api = Linkedin(username or '', password or '', cookies=jar)
        else:
            api = Linkedin(username, password)
        print("Authentication successful!")
    except Exception as e:
        print(f"Error logging in: {e}")
        return

    conn = get_db_connection()
    # Join with companies to get company name
    query = """
        SELECT p.id, p.name, p.position, c.name as company_name 
        FROM people p 
        LEFT JOIN companies c ON p.company_id = c.id
        WHERE p.deleted_at IS NULL
        AND p.name IN ('Алексей Андреев', 'Анастасия Мещерякова', 'Анна Козловская')
    """
    df = pd.read_sql_query(query, conn)
    
    updates = []
    
    count = 0
    for index, row in df.iterrows():
        if count >= limit and limit > 0:
            break
            
        full_name = row['name']
        company_name = row['company_name']
        current_position = row['position']
        
        search_query = full_name
        if company_name:
            search_query += f" {company_name}"
            
        print(f"Searching for: {search_query}")
        
        try:
            results = api.search_people(keywords=search_query, limit=1)
            
            # Fallback: Try searching just by name if no results and we had a company
            if not results and company_name:
                print(f"  No results for '{search_query}'. Retrying with name only: '{full_name}'")
                results = api.search_people(keywords=full_name, limit=1)

            if not results:
                print(f"  No results found for {full_name}")
                continue
                
            profile_summary = results[0]
            urn_id = profile_summary.get('urn_id')
            public_id = profile_summary.get('public_id')
            headline = profile_summary.get('headline', '')
            subline = profile_summary.get('subline', '')
            
            # Extract URN ID (urn:li:fs_miniProfile:ACoAA...)
            # The API get_profile can take urn_id if public_id is missing
            
            print(f"  Found Match. PublicID: {public_id}, URN: {urn_id}")
            
            # Fetch full profile details

            profile = {}
            # Only try fetching full profile if we have a public_id (urn_id fetch seems unstable)
            if public_id:
                try:
                    print(f"  Fetching full profile for {public_id}...")
                    profile = api.get_profile(public_id)
                except Exception as e:
                    print(f"  Warning: Could not fetch profile: {e}")

            # Fallback / Extraction
            linkedin_url = f"https://www.linkedin.com/in/{public_id}" if public_id else f"https://www.linkedin.com/in/{urn_id}"
            
            experience = profile.get('experience', [])
            current_job = experience[0] if experience else {}
            
            if current_job:
                new_position = current_job.get('title', headline)
                new_company = current_job.get('companyName', '')
            else:
                # Use Headline from search result
                new_position = headline
                new_company = "See Headline" 
            
            # Location
            location = profile.get('locationName', '') or profile.get('geoLocationName', '')
            if not location:
                location = subline 

            updates.append({
                'id': row['id'],
                'name': full_name,
                'linkedin_url': linkedin_url,
                'linkedin_position': new_position,
                'linkedin_company': new_company,
                'linkedin_location': location,
                'match_headline': headline
            })
            
            count += 1
            # Sleep to avoid bans
            time.sleep(5) 
            
        except Exception as e:
            print(f"  Error processing {full_name}: {e}")
            time.sleep(5)
            
    conn.close()
    
    # Save results to review
    if updates:
        result_df = pd.DataFrame(updates)
        print("\n--- Enrichment Results ---")
        print(result_df[['name', 'linkedin_company', 'linkedin_position', 'linkedin_url']].to_string())
        result_df.to_csv('linkedin_enrichment_results.csv', index=False)
        print("Saved to linkedin_enrichment_results.csv")
    else:
        print("No matches found or processed.")

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description='Enrich CRM contacts with LinkedIn data')
    parser.add_argument('--user', help='LinkedIn Username/Email')
    parser.add_argument('--password', help='LinkedIn Password')
    parser.add_argument('--cookie', help='LinkedIn li_at cookie value')
    parser.add_argument('--limit', type=int, default=5, help='Limit number of profiles to process (safe mode)')
    
    args = parser.parse_args()
    
    user = args.user or os.getenv('LINKEDIN_USERNAME')
    pwd = args.password or os.getenv('LINKEDIN_PASSWORD')
    cookie = args.cookie or os.getenv('LINKEDIN_COOKIE')
    
    if cookie:
        print("Using provided li_at cookie for authentication...")
        # Start the function with cookie (we need to modify enrich_people signature too or just pass it)
        # Re-calling with modified signature logic below
        enrich_people(user, pwd, args.limit, cookie_value=cookie)
    elif not user or not pwd:
        print("Error: LinkedIn credentials required. Set LINKEDIN_USERNAME/PASSWORD in .env or pass flags.")
        exit(1)
    else:    
        enrich_people(user, pwd, args.limit)
