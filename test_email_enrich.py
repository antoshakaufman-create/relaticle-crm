import requests
import re
from linkedin_api import Linkedin

LINKEDIN_USER = "denis.kubasov17@gmail.com"
LINKEDIN_PASS = "TF255014" 
LINKEDIN_COOKIE = "AQEDAULTu3kC1ZIvAAABmxhzzPgAAAGbPIBQ-E0AYLzziZLGF4vSdlWGfebcM6ehk9aSm7ty4h3wpghwd3t0eK0Y9KKEVGhv2eJSZKgfMvjtOyfb2XLHbww6p-YOH8fm1kgjfSIbQf1aD8BGaEh_TcyP"
TEST_URL = "https://www.linkedin.com/in/ACoAAD3DzJUBgDk4BXVMBKinuM8MFqqWTdE14vI"
TEST_URN = "ACoAAD3DzJUBgDk4BXVMBKinuM8MFqqWTdE14vI" 

def test_scraper_logic():
    print("--- Testing Scraper Logic (Request + Regex) ---")
    s = requests.Session()
    # Scraper Headers
    s.headers = {
        "user-agent": "Mozilla/5.0 (Windows NT 6.3; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36 OPR/67.0.3575.97"
    }
    # Inject Cookie
    cookie_dict = {'li_at': LINKEDIN_COOKIE, 'JSESSIONID': 'ajax:1234567890'}
    for k, v in cookie_dict.items():
        s.cookies.set(k, v)
    
    target = TEST_URL + "/detail/contact-info/"
    print(f"Fetching {target}...")
    try:
        resp = s.get(target)
        if resp.status_code != 200:
            print(f"Failed: {resp.status_code}")
        else:
            sc = resp.text
            emails_found = re.findall(r'[a-zA-Z0-9\.\-\_i]+@[\w.]+', sc)
            print(f"Emails found (Regex): {emails_found}")
            # print(f"Sample content: {sc[:500]}") # Debug
    except Exception as e:
        print(f"Error: {e}")

def test_api_logic():
    print("\n--- Testing Linkedin-API Logic ---")
    try:
        if LINKEDIN_COOKIE:
            cookie_dict = {'li_at': LINKEDIN_COOKIE, 'JSESSIONID': 'ajax:1234567890'}
            jar = requests.cookies.RequestsCookieJar()
            for k, v in cookie_dict.items():
                jar.set(k, v)
            api = Linkedin(LINKEDIN_USER, LINKEDIN_PASS, cookies=jar)
        else:
            api = Linkedin(LINKEDIN_USER, LINKEDIN_PASS)
            
        # method: get_profile_contact_info(public_id=None, urn_id=None)
        # Note: public_id is the handle (e.g. "williamhgates"), urn_id is the "ACo..." or numeric ID.
        # Our DB usually stores the full URL. If URL has "ACo...", that's a partial URN or public ID?
        # Actually URNs are usually numeric or ACo... hash.
        # linkedin-api usually expects public_id (vanity name).
        # We need to extract public_id from URL or use URN.
        
        # In our case, URL is `.../in/ACo...` so `ACo...` IS the ID used in the URL.
        # Let's try passing it as public_id (since it acts as one in the URL) AND as urn_id.
        
        contact_info = api.get_profile_contact_info(public_id=TEST_URN)
        print(f"Contact Info (Public ID): {contact_info}")
        
    except Exception as e:
        print(f"API Error: {e}")

if __name__ == "__main__":
    test_scraper_logic()
    test_api_logic()
