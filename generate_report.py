import sqlite3
import pandas as pd
import os

DB_PATH = "server_database.sqlite"
OUTPUT_FILE = "moex_enrichment_results.xlsx"

def generate_report():
    print("Generating report...")
    conn = sqlite3.connect(DB_PATH)
    
    # Query to fetch Companies and their People
    # We focus on companies tracked from MOEX
    query = """
        SELECT 
            c.name as "Company",
            c.industry as "Industry",
            c.lead_score as "SMM AI Score",
            c.smm_analysis as "AI Analysis",
            p.name as "Employee Name",
            p.position as "Position",
            p.linkedin_url as "LinkedIn URL"
        FROM companies c
        LEFT JOIN people p ON c.id = p.company_id
        WHERE c.creation_source = 'MOEX'
        ORDER BY c.lead_score DESC, c.name ASC
    """
    
    df = pd.read_sql_query(query, conn)
    conn.close()
    
    print(f"Fetched {len(df)} records.")
    
    if df.empty:
        print("No data found.")
        return

    # Basic styling or ensuring excel creation
    # requires openpyxl
    try:
        df.to_excel(OUTPUT_FILE, index=False)
        print(f"Successfully saved to {OUTPUT_FILE}")
        print(f"Absolute path: {os.path.abspath(OUTPUT_FILE)}")
    except Exception as e:
        print(f"Error saving Excel: {e}")

if __name__ == "__main__":
    generate_report()
