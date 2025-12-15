import json
import time
import requests
import datetime
import os

# Config
# YandexGPT API (Sanitized)
API_KEY = os.getenv("YANDEX_API_KEY", "your-api-key")
YANDEX_FOLDER_ID = "b1gn3qao39gb9uecn2c2"

INPUT_EXISTING = "existing_company_names.json"
OUTPUT_SQL = "moex_import.sql"
OUTPUT_JSON = "moex_for_enrichment.json"

# Full MOEX List (Expanded)
MOEX_COMPANIES = [
    "Sberbank", "Lukoil", "Gazprom", "Yandex", "Novatek", "Norilsk Nickel", "Rosneft", "Tatneft", 
    "Surgutneftegas", "Polyus", "Severstal", "NLMK", "MTS", "Magnit", "X5 Group", "Inter RAO", 
    "ALROSA", "PhosAgro", "VTB Bank", "MMK", "Aeroflot", "Rostelecom", "RusHydro", "Sistema", 
    "PIK Group", "VK (Mail.ru Group)", "Ozon", "Tinkoff (T-Bank)", "Positive Technologies", 
    "Whoosh", "Henderson", "Samolet", "M.Video", "Lenta", "Detsky Mir", "Fix Price", 
    "Globaltrans", "Transneft", "Unipro", "Mosenergo", "Segezha Group", "Mechel", "Raspadskaya",
    "Sovcomflot", "En+ Group", "UniPro", "FESCO", "KamAZ", "VSMPO-AVISMA", "Acron"
]

def get_yandex_analysis(company_name):
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
        "Authorization": f"Api-Key {YANDEX_API_KEY}",
        "x-folder-id": YANDEX_FOLDER_ID,
        "Content-Type": "application/json"
    }
    data = {
        "modelUri": f"gpt://{YANDEX_FOLDER_ID}/yandexgpt-lite/latest",
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
    print("=== MOEX Import Started ===")
    
    # Load Existing
    try:
        with open(INPUT_EXISTING, 'r') as f:
            existing = set(json.load(f))
            print(f"Loaded {len(existing)} existing companies.")
    except:
        existing = set()
        print("No existing companies loaded. Assuming all new.")

    sql_statements = []
    enrichment_list = []
    
    user_id = 1
    
    for company in MOEX_COMPANIES:
        # Fuzzy check? Or simple substring?
        # User DB might have "Sberbank PJSC".
        # Let's check for containment.
        
        is_exist = False
        for ex in existing:
            if company.lower() in ex.lower() or ex.lower() in company.lower():
                is_exist = True
                break
        
        if is_exist:
            print(f"Skipping {company} (Exists)")
            continue
            
        print(f"Analyzing {company}...")
        res = get_yandex_analysis(company)
        if not res:
            continue
            
        score = res.get('smm_relevance', 0)
        industry = res.get('industry', 'Unknown')
        print(f" > Score: {score}/10 ({industry})")
        
        if score >= 6: # Lowered threshold slightly to include robust B2B
            print(" -> Adding to Import List")
            
            enrichment_list.append({
                'company': company, # For enrichment script
                'id': -1 # Unknown yet
            })
            
            # Simple SQL Insert
            # Escape quotes
            safe_name = company.replace("'", "''")
            safe_ind = industry.replace("'", "''")
            
            sql = f"INSERT INTO companies (name, industry, lead_score, lead_category, creation_source, team_id, created_at, updated_at) VALUES ('{safe_name}', '{safe_ind}', {score}, 'COLD', 'MOEX', {user_id}, datetime('now'), datetime('now'));"
            sql_statements.append(sql)
            
        time.sleep(1)

    # Save SQL
    if sql_statements:
        with open(OUTPUT_SQL, 'w') as f:
            f.write("BEGIN TRANSACTION;\n")
            for s in sql_statements:
                f.write(s + "\n")
            f.write("COMMIT;\n")
        print(f"Saved {len(sql_statements)} inserts to {OUTPUT_SQL}")
        
    # Save Enrichment JSON
    if enrichment_list:
        with open(OUTPUT_JSON, 'w') as f:
             json.dump(enrichment_list, f, indent=2)
        print(f"Saved {len(enrichment_list)} to {OUTPUT_JSON}")

if __name__ == "__main__":
    main()
