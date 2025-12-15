from linkedin_api import Linkedin
import requests

LINKEDIN_USER = "denis.kubasov17@gmail.com"
LINKEDIN_PASS = "TF255014"
LINKEDIN_COOKIE = "AQEDAULTu3kC1ZIvAAABmxhzzPgAAAGbPIBQ-E0AYLzziZLGF4vSdlWGfebcM6ehk9aSm7ty4h3wpghwd3t0eK0Y9KKEVGhv2eJSZKgfMvjtOyfb2XLHbww6p-YOH8fm1kgjfSIbQf1aD8BGaEh_TcyP"

if LINKEDIN_COOKIE:
    print("Using provided cookie for auth...")
    cookie_dict = {'li_at': LINKEDIN_COOKIE, 'JSESSIONID': 'ajax:1234567890'}
    jar = requests.cookies.RequestsCookieJar()
    for k, v in cookie_dict.items():
        jar.set(k, v)
    api = Linkedin(LINKEDIN_USER, LINKEDIN_PASS, cookies=jar)
else:
    api = Linkedin(LINKEDIN_USER, LINKEDIN_PASS)

print("Auth done. Testing search...")

# Test 1: Simple global search people
try:
    res = api.search_people(keywords="Marketing", limit=1)
    if res:
        print(f"Test 1 (Global Marketing) Success: Found {res[0]['name']}")
    else:
        print("Test 1 Failed: No results")
except Exception as e:
    print(f"Test 1 Error: {e}")

# Test 2: Current Company Search (Sberbank URN check)
try:
    comp_res = api.search_companies(keywords="Sberbank", limit=1)
    if comp_res:
        urn = comp_res[0]['urn_id'] # urn:li:company:1330912
        num_id = urn.split(':')[-1]
        print(f"Sberbank URN: {urn}, ID: {num_id}")
        
        # Test 3: Search in Sberbank with simple keyword
        res = api.search_people(keywords="Marketing", current_company=[num_id], limit=3)
        if res:
            print(f"Test 3 (Sberbank Marketing) Found: {[p['name'] for p in res]}")
        else:
            print("Test 3 Failed: No results in Sberbank")
            
        # Test 4: Search in Sberbank with COMPLEX keyword
        complex_kw = "SMM OR Marketing OR Brand OR Digital OR PR OR Communication OR CMO OR \"Head of Marketing\""
        res = api.search_people(keywords=complex_kw, current_company=[num_id], limit=3)
        if res:
            print(f"Test 4 (Complex FW) Found: {[p['name'] for p in res]}")
        else:
            print("Test 4 Failed: No results with Complex KW")
            
        # Test 5: Full URN check
        try:
             res = api.search_people(keywords="Marketing", current_company=[urn], limit=3)
             if res:
                 print(f"Test 5 (Full URN) Found: {[p['name'] for p in res]}")
             else:
                 print("Test 5 Failed (Full URN)")
        except Exception as e:
             print(f"Test 5 Error: {e}")

        # Test 6: Keyword Only strategy "Marketing Sberbank"
        try:
             res = api.search_people(keywords="Marketing Sberbank", limit=3)
             if res:
                 print(f"Test 6 (Keyword 'Marketing Sberbank') Found: {[p['name'] for p in res]}")
             else:
                 print("Test 6 Failed (Keyword search)")
        except Exception as e:
             print(f"Test 6 Error: {e}")

    else:
        print("Sberbank not found")
except Exception as e:
    print(f"Test 2/3/4 Error: {e}")
