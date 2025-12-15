from linkedin_api import Linkedin
import requests

LINKEDIN_COOKIE = "AQEDAULTu3kC1ZIvAAABmxhzzPgAAAGbPIBQ-E0AYLzziZLGF4vSdlWGfebcM6ehk9aSm7ty4h3wpghwd3t0eK0Y9KKEVGhv2eJSZKgfMvjtOyfb2XLHbww6p-YOH8fm1kgjfSIbQf1aD8BGaEh_TcyP"

def main():
    print("=== Testing LinkedIn Email Search ===")
    
    try:
        import requests
        if LINKEDIN_COOKIE:
             cookie_dict = {'li_at': LINKEDIN_COOKIE, 'JSESSIONID': 'ajax:1234567890'}
             jar = requests.cookies.RequestsCookieJar()
             for k, v in cookie_dict.items():
                 jar.set(k, v)
             api = Linkedin("", "", cookies=jar)
        else:
             print("No cookie")
             return
    except Exception as e:
        print(f"Auth Error: {e}")
        return

    # Test Emails (known valid ones ideally, or generic corporate ones)
    test_emails = [
        "denis.kubasov17@gmail.com", # The account self
        "recruit@google.com",
        "contact@tesla.com"
    ]

    for email in test_emails:
        print(f"\nSearching for: {email}")
        try:
            # Method 1: Keyword Search
            res = api.search_people(keywords=email, limit=1)
            if res:
                print(f" > Keyword Result: {res[0]['name']} ({res[0]['public_id']})")
            else:
                print(" > Keyword Result: None")
            
            # Method 2: Get Profile directly (unlikely to work with email as ID)
            # api.get_profile(email) -> Will likely error or 404
            
        except Exception as e:
            print(f" > Error: {e}")

if __name__ == "__main__":
    main()
