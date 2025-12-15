from linkedin_api import Linkedin
import requests

LINKEDIN_COOKIE = "AQEDAULTu3kC1ZIvAAABmxhzzPgAAAGbPIBQ-E0AYLzziZLGF4vSdlWGfebcM6ehk9aSm7ty4h3wpghwd3t0eK0Y9KKEVGhv2eJSZKgfMvjtOyfb2XLHbww6p-YOH8fm1kgjfSIbQf1aD8BGaEh_TcyP"

# Test Case: Mikhail Samsonov at R-Pharm
# Previous result: Not Found (searched for "R Farm")
# Correct Data: R-Pharm URN is 1606395

TEST_CASES = [
    {
        "name_lat": "Mikhail Samsonov",
        "name_cyr": "Самсонов Михаил", 
        "company_urn": "1606395",
        "company_name": "R-Pharm"
    },
    {
        "name_lat": "Alexey Andreev", # Or Aleksei
        "name_cyr": "Алексей Андреев",
        "company_urn": "unknown", # Will try to find Valenta Pharm URN dynamically
        "company_text": "Valenta Pharm" 
    }
]

def main():
    print("=== End-to-End Test: URN Strategy ===")
    
    # Auth
    try:
        if LINKEDIN_COOKIE:
             cookie_dict = {'li_at': LINKEDIN_COOKIE, 'JSESSIONID': 'ajax:1234567890'}
             jar = requests.cookies.RequestsCookieJar()
             for k, v in cookie_dict.items():
                 jar.set(k, v)
             api = Linkedin("", "", cookies=jar)
    except Exception as e:
        print(f"Auth Error: {e}")
        return

    for case in TEST_CASES:
        name = case['name_lat']
        print(f"\n--- Testing: {name} at {case.get('company_name', case.get('company_text'))} ---")
        
        urn = case.get('company_urn')
        
        # Dynamic URN fetch if unknown
        if urn == 'unknown':
            print(f" [1] Fetching URN for {case['company_text']}...")
            res = api.search_companies(keywords=case['company_text'], limit=1)
            if res:
                urn = res[0]['urn_id'].split(':')[-1]
                print(f"     > Found URN: {urn}")
            else:
                print("     > Company Not Found. Skipping.")
                continue

        # Search Person
        print(f" [2] Searching Person with URN context [{urn}]...")
        
        # Try Latin Name
        res = api.search_people(keywords=name, current_company=[urn], limit=1)
        
        if not res:
            # Try Cyrillic Name
            print("     > Latin name not found. Trying Cyrillic...")
            res = api.search_people(keywords=case['name_cyr'], current_company=[urn], limit=1)
            
        if res:
            p = res[0]
            print(f" [SUCCESS] Found: {p['name']} - {p.get('jobTitle', 'No Title')}")
            print(f"           URL: https://www.linkedin.com/in/{p.get('public_id')}")
        else:
            print(" [FAIL] Not Found even with URN.")

if __name__ == "__main__":
    main()
