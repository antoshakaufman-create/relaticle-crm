import sqlite3
import pandas as pd
import os

DB_PATH = "server_database.sqlite"
OUTPUT_FILE = "relaticle_full_db_export.xlsx"

def generate_report():
    print("Generating FULL DB report...")
    conn = sqlite3.connect(DB_PATH)
    
    # Query: Join People with Companies
    # Get all people
    query = """
        SELECT 
            p.id as "Person ID",
            p.name as "Person Name",
            p.position as "Position",
            p.linkedin_url as "LinkedIn (Person)",
            p.linkedin_position as "LinkedIn Position",
            p.email as "Email",
            p.phone as "Phone",
            c.name as "Company Name",
            c.industry as "Industry",
            c.lead_score as "Company Score",
            c.lead_category as "Category",
            c.website as "Website",
            c.vk_url as "VK URL",
            c.creation_source as "Source",
            c.er_score as "ER Score",
            c.posts_per_month as "Posts/Month"
        FROM people p
        LEFT JOIN companies c ON p.company_id = c.id
        ORDER BY c.lead_score DESC, p.created_at DESC
    """
    
    df = pd.read_sql_query(query, conn)
    
    # Also get companies with NO people?
    query_companies = """
        SELECT 
            c.id as "Company ID",
            c.name as "Company Name",
            c.industry as "Industry",
            c.lead_score as "Score",
            c.lead_category as "Category",
            c.website as "Website",
            c.vk_url as "VK URL",
            c.vk_status as "VK Status",
            c.er_score as "ER Score",
            c.posts_per_month as "Posts/Month",
            c.creation_source as "Source",
            (SELECT count(*) FROM people WHERE company_id = c.id) as "Employee Count"
        FROM companies c
        ORDER BY c.name ASC
    """
    df_companies = pd.read_sql_query(query_companies, conn)
    
    conn.close()
    
    print(f"Fetched {len(df)} People records.")
    print(f"Fetched {len(df_companies)} Company records.")
    
    try:
        with pd.ExcelWriter(OUTPUT_FILE) as writer:
            df.to_excel(writer, sheet_name='People (Enriched)', index=False)
            df_companies.to_excel(writer, sheet_name='All Companies', index=False)
            
        print(f"Successfully saved to {OUTPUT_FILE}")
    except Exception as e:
        print(f"Error saving Excel: {e}")

if __name__ == "__main__":
    generate_report()
