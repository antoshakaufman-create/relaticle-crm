import os
import sqlite3
import time
import argparse
import requests
import json
import pandas as pd
from linkedin_api import Linkedin
from dotenv import load_dotenv

# Load environment variables
load_dotenv()

def get_db_connection():
    conn = sqlite3.connect('server_database.sqlite')
    conn.row_factory = sqlite3.Row
    return conn

def yandex_expand_company(api_key, folder_id, company_name):
    """
    Ask YandexGPT for full official name in RU and EN.
    """
    prompt = f"""
    Задача: У тебя есть название компании "{company_name}" (оно может быть неполным или на русском/английском).
    Твоя цель: Найти или предположить полное юридическое или международное название этой компании на Русском и Английском языках.
    
    Верни ответ СТРОГО в формате JSON:
    {{
        "ru": "Полное Название на Русском",
        "en": "Full Company Name in English"
    }}
    
    Пример: для "Яндекс" -> {{ "ru": "ООО Яндекс", "en": "Yandex LLC" }}
    Если не знаешь компанию, просто транслитерируй.
    """
    
    url = "https://llm.api.cloud.yandex.net/foundationModels/v1/completion"
    headers = {
        "Authorization": f"Api-Key {api_key}",
        "x-folder-id": folder_id,
        "Content-Type": "application/json"
    }
    
    data = {
        "modelUri": f"gpt://{folder_id}/yandexgpt-lite/latest",
        "completionOptions": {
            "stream": False,
            "temperature": 0.3,
            "maxTokens": 200
        },
        "messages": [
            {"role": "user", "text": prompt}
        ]
    }
    
    try:
        response = requests.post(url, headers=headers, json=data, timeout=10)
        if response.status_code == 200:
            result_text = response.json()['result']['alternatives'][0]['message']['text']
            # Clean markup if any
            result_text = result_text.replace('```json', '').replace('```', '').strip()
            return json.loads(result_text)
        else:
            print(f"  YandexGPT Error {response.status_code}: {response.text}")
    except Exception as e:
        print(f"  YandexGPT Request Failed: {e}")
        
    return {"ru": company_name, "en": company_name}

def search_executives(user, pwd, li_cookie, yandex_key, yandex_folder, db_limit=5):
    # Auth LinkedIn
    print("Authenticating LinkedIn...")
    api = None
    try:
        if li_cookie:
             jar = requests.cookies.RequestsCookieJar()
             jar.set('li_at', li_cookie)
             jar.set('JSESSIONID', 'ajax:1234567890')
             api = Linkedin(user or '', pwd or '', cookies=jar)
        else:
             api = Linkedin(user, pwd)
    except Exception as e:
        print(f"LinkedIn Auth Error: {e}")
        return

    conn = get_db_connection()
    # Get unique companies from people or companies table
    # Using companies table is better
    query = "SELECT id, name FROM companies LIMIT ?"
    companies = pd.read_sql_query(query, conn, params=(db_limit,))
    
    results = []
    
    for idx, row in companies.iterrows():
        original_name = row['name']
        print(f"\n[{idx+1}] Processing: {original_name}")
        
        # 1. Expand Name
        names = yandex_expand_company(yandex_key, yandex_folder, original_name)
        print(f"  Start Names: {names}")
        
        name_en = names.get('en', original_name)
        name_ru = names.get('ru', original_name)
        
        # 2. Find Company URN
        company_urn = None
        # Try finding by English name first
        try:
            search_res = api.search_companies(keywords=name_en, limit=1)
            if not search_res:
                search_res = api.search_companies(keywords=name_ru, limit=1)
                
            if search_res:
                company_urn = search_res[0].get('urn_id') # urn:li:company:12345
                print(f"  Found Company: {search_res[0].get('name')} (URN: {company_urn})")
            else:
                print("  Company not found.")
                
        except Exception as e:
            print(f"  Company Search Error: {e}")
            continue
            
        if not company_urn:
            continue
            
        # 3. Search Executives
        # API doesn't have direct 'search_people' filter for current_company URN in public interface easily
        # But we can try keywords + facet logic if supported, or just keyword "Company Name CEO"
        
        # Best approach with this lib: search_people(keywords="CEO OR CTO OR Founder", current_company=[urn])
        # Note: current_company param expects list of URN IDs (numeric strings usually, or full URNs)
        # The library's search_people signature: params include 'current_company' list
        
        titles = "CEO OR Founder OR CTO OR Director OR President"
        print(f"  Searching employees: {titles} @ {company_urn}")
        
        try:
            # Extract numeric ID from URN if needed? Usually the lib handles full URN or numeric ID
            # urn:li:company:12345 -> 12345
            company_id_numeric = company_urn.split(':')[-1]
            
            employees = api.search_people(
                keywords=titles,
                current_company=[company_id_numeric],
                limit=3
            )
            
            for emp in employees:
                print(f"    Found: {emp.get('name', 'Unknown')} - {emp.get('subline', '')}")
                results.append({
                    'original_company': original_name,
                    'found_company': name_en,
                    'company_urn': company_urn,
                    'emp_name': emp.get('name'),
                    'emp_headline': emp.get('subline'),
                    'emp_id': emp.get('public_id') or emp.get('urn_id'),
                    'emp_url': f"https://www.linkedin.com/in/{emp.get('public_id')}" if emp.get('public_id') else ''
                })
                
        except Exception as e:
            print(f"  Employee Search Error: {e}")
            
        time.sleep(5)
        
    conn.close()
    
    if results:
        df = pd.DataFrame(results)
        df.to_csv('c_level_candidates.csv', index=False)
        print("\nSaved candidates to c_level_candidates.csv")
        print(df)

if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument('--user')
    parser.add_argument('--password')
    parser.add_argument('--cookie')
    parser.add_argument('--yandex_key')
    parser.add_argument('--folder_id')
    parser.add_argument('--limit', type=int, default=3)
    
    args = parser.parse_args()
    
    # Defaults from ENV
    user = args.user or os.getenv('LINKEDIN_USERNAME')
    pwd = args.password or os.getenv('LINKEDIN_PASSWORD')
    cookie = args.cookie or os.getenv('LINKEDIN_COOKIE')
    
    ykey = args.yandex_key or os.getenv('YANDEX_API_KEY')
    yfolder = args.folder_id or os.getenv('YANDEX_FOLDER_ID')
    
    if not ykey or not yfolder:
        print("Error: Yandex API Key and Folder ID required.")
        exit(1)
        
    search_executives(user, pwd, cookie, ykey, yfolder, args.limit)
