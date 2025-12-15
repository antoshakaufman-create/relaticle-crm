import sqlite3
import requests
import json
import time
import os

# Configuration
DB_PATH = "database/database.sqlite"
YANDEX_API_KEY = "AQVN..." # Will be passed or read from env
YANDEX_FOLDER_ID = "b1g..." # Will be passed or read from env

# Full MOEX List (Common/Index constituents + Tech/Retail)
MOEX_COMPANIES = [
    "Sberbank", "Lukoil", "Gazprom", "Yandex", "Novatek", "Norilsk Nickel", "Rosneft", "Tatneft", 
    "Surgutneftegas", "Polyus", "Severstal", "NLMK", "MTS", "Magnit", "X5 Group", "Inter RAO", 
    "ALROSA", "PhosAgro", "VTB Bank", "MMK", "Aeroflot", "Rostelecom", "RusHydro", "Sistema", 
    "PIK Group", "VK (Mail.ru Group)", "Ozon", "Tinkoff (T-Bank)", "Positive Technologies", 
    "Whoosh", "Henderson", "Samolet", "M.Video", "Lenta", "Detsky Mir", "Fix Price", 
    "Globaltrans", "Transneft", "Unipro", "Mosenergo", "Segezha Group", "Mechel", "Raspadskaya"
]

def get_yandex_analysis(company_name, api_key, folder_id):
    prompt = f"""
    Analyze the company "{company_name}" for B2B/B2C Digital Agency services.
    
    1. Industry: What is their main industry? (e.g. Banking, Oil&Gas, Retail, Tech).
    2. SMM Relevance (0-10): How critical is Social Media Marketing for them?
       - High (8-10): B2C, Retail, Banks, Tech, Services, E-commerce.
       - Medium (4-7): B2B with strong brand, Telecom, Developers (Real Estate).
       - Low (0-3): Raw materials, Heavy Industry, B2B-only logistics.
       
    Return JSON:
    {{
        "industry": "String",
        "smm_relevance": Float (0-10),
        "reason": "Short explanation"
    }}
    """
    
    url = "https://llm.api.cloud.yandex.net/foundationModels/v1/completion"
    headers = {
        "Authorization": f"Api-Key {api_key}",
        "x-folder-id": folder_id,
        "Content-Type": "application/json"
    }
    data = {
        "modelUri": f"gpt://{folder_id}/yandexgpt-lite/latest",
        "completionOptions": {"stream": False, "temperature": 0.1, "maxTokens": 300},
        "messages": [{"role": "user", "text": prompt}]
    }
    
    try:
        resp = requests.post(url, headers=headers, json=data)
        if resp.status_code != 200:
            print(f"Error {resp.status_code}: {resp.text}")
            return None
            
        result_text = resp.json()['result']['alternatives'][0]['message']['text']
        # Clean markdown code blocks if present
        result_text = result_text.replace("```json", "").replace("```", "").strip()
        return json.loads(result_text)
    except Exception as e:
        print(f"Exception for {company_name}: {e}")
        return None

def main():
    # Read env or args
    import argparse
    parser = argparse.ArgumentParser()
    parser.add_argument("--key", required=True, help="Yandex API Key")
    parser.add_argument("--folder", required=True, help="Yandex Folder ID")
    args = parser.parse_args()
    
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    
    print(f"analyzing {len(MOEX_COMPANIES)} companies...")
    
    for company in MOEX_COMPANIES:
        print(f"Processing: {company}...")
        
        # Check if exists
        cursor.execute("SELECT id FROM companies WHERE name LIKE ?", (f"%{company}%",))
        if cursor.fetchone():
            print(f"  - Already exists. Skipping.")
            continue
            
        analysis = get_yandex_analysis(company, args.key, args.folder)
        if not analysis:
            continue
            
        score = analysis.get('smm_relevance', 0)
        industry = analysis.get('industry', 'Unknown')
        reason = analysis.get('reason', '')
        
        print(f"  - Industry: {industry}, Score: {score}/10 ({reason})")
        
        if score >= 7:
            # Insert
            print(f"  -> ADDING to DB")
            cursor.execute("""
                INSERT INTO companies (name, industry, lead_score, lead_category, created_at, updated_at, creation_source, team_id)
                VALUES (?, ?, ?, ?, datetime('now'), datetime('now'), 'MOEX', 1)
            """, (company, industry, 0, 'COLD', )) # lead_score 0 initially, updated by SMM scoring later
            conn.commit()
        else:
            print(f"  - Low relevance. Skip.")
            
        time.sleep(1) # Rate limit
        
    conn.close()
    print("Done.")

if __name__ == "__main__":
    main()
