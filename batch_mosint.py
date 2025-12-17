import json
import subprocess
import time
import os
import concurrent.futures
from threading import Lock

CONFIG_FILE = "mosint/config.yaml"
MOSINT_BIN = "./mosint/mosint" 
results = []
lock = Lock()

def run_mosint_scan(person):
    email = person['email']
    print(f"Scanning: {email}...")
    try:
        output_file = f"temp_{email}_{int(time.time())}.json"
        
        # Remove --silent to capture stdout text for parsing
        cmd = [MOSINT_BIN, email, "--config", CONFIG_FILE, "--output", output_file]
        
        process = subprocess.run(
            cmd, 
            capture_output=True, 
            text=True,
            timeout=30 # Reduced timeout
        )
        
        stdout_io = process.stdout
        
        ip_data_from_text = {}
        for line in stdout_io.split('\n'):
            line = line.strip()
            if "Organization:" in line:
                ip_data_from_text['org'] = line.split('Organization:')[1].strip()
            if "City:" in line:
                ip_data_from_text['city'] = line.split('City:')[1].strip()
            if "Country:" in line:
                 ip_data_from_text['country'] = line.split('Country:')[1].strip()

        data = {}
        if os.path.exists(output_file):
            try:
                with open(output_file, 'r') as f:
                    data = json.load(f)
                os.remove(output_file)
            except:
                pass
        
        if not data.get('ip_api'):
            data['ip_api'] = {}
        
        if ip_data_from_text.get('org'):
            data['ip_api']['org'] = ip_data_from_text['org']
        if ip_data_from_text.get('city'):
            data['ip_api']['city'] = ip_data_from_text['city']
        if ip_data_from_text.get('country'):
             data['ip_api']['country'] = ip_data_from_text['country']
        
        enrichment = {
            'person_id': person['id'],
            'email': email,
            'twitter': data.get('twitter_account', {}).get('exists', False),
            'spotify': data.get('spotify_account', {}).get('exists', False),
            'ip_info': data.get('ip_api', {}),
            'dns_records': data.get('dns_records', [])
        }
        
        ip = enrichment['ip_info']
        if ip:
            geo = f"{ip.get('city', '')}, {ip.get('country', '')}".strip(', ')
            org = ip.get('org', '')
            enrichment['summary'] = f"Loc: {geo} | Org: {org}"
        
        print(f"  -> {email}: {enrichment.get('summary', 'data found')}")
        return enrichment

    except Exception as e:
        print(f"Error {email}: {e}")
        return None

def main():
    if not os.path.exists('mosint_candidates.json'):
         print("No input file found.")
         return

    with open('mosint_candidates.json', 'r') as f:
        candidates = json.load(f)

    print(f"Processing {len(candidates)} emails with 10 threads.")
    
    global results
    
    with concurrent.futures.ThreadPoolExecutor(max_workers=10) as executor:
        future_to_email = {executor.submit(run_mosint_scan, person): person for person in candidates}
        for future in concurrent.futures.as_completed(future_to_email):
            res = future.result()
            if res:
                results.append(res)
    
    with open('mosint_rich_data.json', 'w') as f:
        json.dump(results, f, indent=4, ensure_ascii=False)
    
    print(f"Done. Saved {len(results)} records.")

if __name__ == '__main__':
    main()
