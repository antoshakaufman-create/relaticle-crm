import json
import subprocess
import time
import os
import re

CONFIG_FILE = "mosint/config.yaml"
# Ensure we point to the binary we built
MOSINT_BIN = "./mosint/mosint" 

def run_mosint_scan(email):
    print(f"Scanning: {email}...")
    try:
        # Run Mosint
        # Output is messy text + colors. We want structured data.
        # Mosint has --output json flag?
        # Usage says: -o, --output string   output file (.json)
        # So we can use that!
        
        output_file = f"temp_{email}.json"
        
        cmd = [MOSINT_BIN, email, "--config", CONFIG_FILE, "--output", output_file, "--silent"]
        
        subprocess.run(
            cmd, 
            capture_output=True, 
            text=True,
            timeout=120 # 2 mins max per email
        )
        
        # Read the output file
        if os.path.exists(output_file):
            with open(output_file, 'r') as f:
                data = json.load(f)
            os.remove(output_file)
            return data
        else:
            print("  [!] No JSON output generated.")
            return None

    except Exception as e:
        print(f"Error: {e}")
        return None

def main():
    if not os.path.exists('mosint_candidates.json'):
         print("No input file found.")
         return

    with open('mosint_candidates.json', 'r') as f:
        candidates = json.load(f)

    print(f"Processing {len(candidates)} emails.")
    results = []

    for person in candidates:
        email = person['email']
        scan_data = run_mosint_scan(email)
        
        if scan_data:
            # Extract key findings
            # Mosint JSON structure typically mirrors the display
            # Let's capture relevant parts
            
            enrichment = {
                'person_id': person['id'],
                'email': email,
                'twitter': scan_data.get('twitter_account', {}).get('exists', False),
                'spotify': scan_data.get('spotify_account', {}).get('exists', False),
                'ip_info': scan_data.get('ip_api', {}),
                'dns_records': scan_data.get('dns_records', [])
            }
            
            # Formatting IP info string
            ip = enrichment['ip_info']
            if ip:
                geo = f"{ip.get('city', '')}, {ip.get('country', '')}".strip(', ')
                org = ip.get('org', '')
                enrichment['summary'] = f"Loc: {geo} | Org: {org}"
                if enrichment['twitter']:
                    enrichment['summary'] += " | Twitter: YES"
            
            print(f"  -> Found: {enrichment.get('summary', 'data')}")
            results.append(enrichment)
        
        time.sleep(1) # Be nice

    with open('mosint_rich_data.json', 'w') as f:
        json.dump(results, f, indent=4, ensure_ascii=False)
    
    print(f"Done. Enriched {len(results)} emails.")

if __name__ == '__main__':
    main()
