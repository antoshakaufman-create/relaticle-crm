import json
import subprocess
import time
import os
import re
import sys

# Simple Transliteration Map
TRANS_MAP = {
    'а': 'a', 'б': 'b', 'в': 'v', 'г': 'g', 'д': 'd', 'е': 'e', 'ё': 'e',
    'ж': 'zh', 'з': 'z', 'и': 'i', 'й': 'y', 'к': 'k', 'л': 'l', 'м': 'm',
    'н': 'n', 'о': 'o', 'п': 'p', 'р': 'r', 'с': 's', 'т': 't', 'у': 'u',
    'ф': 'f', 'х': 'h', 'ц': 'ts', 'ч': 'ch', 'ш': 'sh', 'щ': 'sch',
    'ъ': '', 'ы': 'y', 'ь': '', 'э': 'e', 'ю': 'yu', 'я': 'ya'
}

def slugify(text):
    text = text.lower()
    res = ''
    for char in text:
        if 'a' <= char <= 'z' or '0' <= char <= '9':
            res += char
        elif char in TRANS_MAP:
            res += TRANS_MAP[char]
        elif char == ' ':
            res += '-'
        elif char == '-':
            res += '-'
    # Remove multi-dashes
    return re.sub(r'-+', '-', res).strip('-')

def run_yaseeker(username):
    print(f"Checking: {username}...")
    try:
        # Run via subprocess, capture stdout
        # Assuming run from root, YaSeeker is in YaSeeker/
        # Check if we need to be in YaSeeker dir? The script likely imports local files?
        # ya_seeker.py seems standalone but imports requirements.
        # Best to run inside YaSeeker dir?
        # Let's try running from current dir as `python3 YaSeeker/ya_seeker.py` - if it works.
        # But `ya_seeker.py` might expect relative paths. 
        # Actually in previous step I ran `cd YaSeeker && python3 ya_seeker.py`. I should replicate that context.
        
        cmd = ["python3", "YaSeeker/ya_seeker.py", username]
        
        # We need to run it such that PYTHONPATH finds modules if needed.
        # But let's try calling it.
        result = subprocess.run(
            cmd, 
            capture_output=True, 
            text=True, 
            cwd=os.getcwd(), # Run from current dir (root), script path is relative
            env={**os.environ, "PYTHONPATH": "YaSeeker"} # Maybe needed
        )
        
        output = result.stdout
        
        if "Email:" in output and "Not found" not in output:
             # Extract Email?
             # Yandex Music output: Email: test@yandex.ru
             emails = re.findall(r'Email:\s*([\w\.\-]+@[\w\.\-]+)', output)
             return list(set(emails))
        
        return []

    except Exception as e:
        print(f"Error: {e}")
        return []

def main():
    with open('candidates.json', 'r') as f:
        candidates = json.load(f)

    # Prioritize People (Type 'person')
    # They have higher chance of success due to email usernames
    candidates.sort(key=lambda x: x.get('type') == 'person', reverse=True)
    
    print(f"Processing {len(candidates)} candidates (Sorted: People First).")

    results = []

    for item in candidates:
        name = item.get('name', 'Unknown')
        record_type = item.get('type')
        usernames = []
        
        # 1. Generate Guesses based on Type
        if record_type == 'company':
            s = slugify(name)
            usernames = [s, s.replace('-', ''), f"{s}-official"]
            # If domain present, try domain parts? Maybe later.
            
        elif record_type == 'person':
            # Priority: Email Username
            if item.get('email_username'):
                usernames.append(item['email_username'])
            
            # Guesses: first.last
            parts = name.split()
            if len(parts) >= 2:
                first = slugify(parts[0])
                last = slugify(parts[1])
                usernames.append(f"{first}.{last}")
                usernames.append(f"{first}{last}")
                usernames.append(f"{last}.{first}")
        
        # Deduplicate
        usernames = list(set(usernames))
        
        found_emails = []
        
        for user in usernames:
            if len(user) < 3: continue
            
            emails = run_yaseeker(user)
            if emails:
                print(f"  [+] FOUND for {name} ({user}): {emails}")
                found_emails.extend(emails)
                break 
            
            # Rate limit
            time.sleep(1) 
        
        if found_emails:
            results.append({
                'name': name,
                'id': item.get('id'),
                'type': record_type,
                'found_emails': found_emails,
                'username_used': user
            })

    # Save
    with open('yaseeker_global_results.json', 'w') as f:
        json.dump(results, f, indent=4, ensure_ascii=False)
    
    print(f"Done. Found {len(results)} matches.")

if __name__ == '__main__':
    main()
