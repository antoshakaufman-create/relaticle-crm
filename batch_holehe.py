import json
import subprocess
import time
import sys

def run_holehe(email):
    print(f"Scanning {email}...")
    try:
        # Run holehe with --only-used to reduce output noise
        result = subprocess.run(
            ['holehe', email, '--only-used', '--no-color'], 
            capture_output=True, 
            text=True,
            timeout=30
        )
        output = result.stdout
        
        used_sites = []
        for line in output.split('\n'):
            if '[+]' in line:
                # Extract site name
                site = line.split('[+]')[1].strip()
                # Clean up "Email used" text if present
                site = site.replace('Email used', '').strip()
                used_sites.append(site)
                
        return used_sites
    except Exception as e:
        print(f"Error scanning {email}: {e}")
        return []

def main():
    try:
        with open('candidates.json', 'r') as f:
            candidates = json.load(f)
    except FileNotFoundError:
        print("candidates.json not found!")
        sys.exit(1)

    verified = []
    
    for c in candidates:
        email = c['email']
        sites = run_holehe(email)
        
        if sites:
            print(f"✅ FOUND: {email} on {len(sites)} sites: {', '.join(sites)}")
            c['verified_sites'] = sites
            verified.append(c)
        else:
            print(f"❌ NOT FOUND: {email}")
            
        time.sleep(1) # Polite delay

    with open('verified_candidates.json', 'w') as f:
        json.dump(verified, f, indent=2, ensure_ascii=False)
        
    print(f"\nSaved {len(verified)} verified candidates to verified_candidates.json")

if __name__ == '__main__':
    main()
