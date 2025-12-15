from linkedin_api import Linkedin
import requests

LINKEDIN_COOKIE = "AQEDAULTu3kC1ZIvAAABmxhzzPgAAAGbPIBQ-E0AYLzziZLGF4vSdlWGfebcM6ehk9aSm7ty4h3wpghwd3t0eK0Y9KKEVGhv2eJSZKgfMvjtOyfb2XLHbww6p-YOH8fm1kgjfSIbQf1aD8BGaEh_TcyP"

COMPANY_MAP = {
    'Р Фарм': 'R-Pharm',
    'Валента Фарм': 'Valenta Pharm',
    'Фарм Синтез': 'Pharmasyntez',
    'Веро Фарма': 'Veropharm',
    'НоваМедика': 'NovaMedica',
    'Вертекс': 'Vertex',     # Too generic?
    'Синтез': 'Sintez',      # Too generic?
    'ПАО "Отисифарм"': 'OTCPharm'
}

def main():
    print("=== POC: Company URN Discovery ===")
    
    # Auth
    try:
        import requests
        if LINKEDIN_COOKIE:
             cookie_dict = {'li_at': LINKEDIN_COOKIE, 'JSESSIONID': 'ajax:1234567890'}
             jar = requests.cookies.RequestsCookieJar()
             for k, v in cookie_dict.items():
                 jar.set(k, v)
             api = Linkedin("", "", cookies=jar)
    except Exception as e:
        print(f"Auth Error: {e}")
        return

    for cyr_name, lat_name in COMPANY_MAP.items():
        print(f"\nSearching for: {lat_name} (was {cyr_name})")
        try:
            res = api.search_companies(keywords=lat_name, limit=1)
            if res:
                c = res[0]
                urn = c.get('urn_id', '')
                print(f" > Found: {c['name']} (URN: {urn})")
                print(f" > URL: https://www.linkedin.com/company/{urn.split(':')[-1]}")
            else:
                print(" > Not Found")
        except Exception as e:
            print(f" > Error: {e}")

if __name__ == "__main__":
    main()
