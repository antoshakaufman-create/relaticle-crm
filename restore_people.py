import pandas as pd
import sqlite3

# Config
ARTIFACT_EXCEL = "/Users/antonkaufmann/.gemini/antigravity/brain/d3551012-5cc5-40e3-919a-fcb2ccab3a4c/relaticle_full_db_export.xlsx"
CURRENT_DB = "server_database.sqlite"
RESTORE_SQL = "restore_people.sql"

def restore():
    print("Loading backup Excel...")
    try:
        df_backup = pd.read_excel(ARTIFACT_EXCEL, sheet_name='People (Enriched)')
    except Exception as e:
        print(f"Error loading backup: {e}")
        # Try local file if artifact path fails or is not accessible directly (it should be)
        df_backup = pd.read_excel("relaticle_full_db_export.xlsx", sheet_name='People (Enriched)')

    print(f"Backup contains {len(df_backup)} people.")
    
    conn = sqlite3.connect(CURRENT_DB)
    cursor = conn.cursor()
    
    # Get current people names/companies to avoid dupes
    cursor.execute("SELECT name FROM people")
    existing_names = set(row[0] for row in cursor.fetchall())
    
    print(f"Current DB contains {len(existing_names)} unique names.")
    
    restored_count = 0
    sql_statements = []
    
    # Map Company Names to IDs in current DB (Server IDs might differ? No, likely same if synced)
    # BUT, the 18 new MOEX companies exist in both.
    cursor.execute("SELECT id, name FROM companies")
    comp_map = {row[1]: row[0] for row in cursor.fetchall()}
    
    for _, row in df_backup.iterrows():
        name = row['Person Name']
        if name in existing_names:
            continue
            
        # Restore this person
        comp_name = row['Company Name']
        comp_id = comp_map.get(comp_name)
        
        if not comp_id:
            print(f"Warning: Company {comp_name} not found in current DB for {name}. Skipping.")
            continue
            
        position = row.get('Position')
        li_url = row.get('LinkedIn (Person)')
        li_pos = row.get('LinkedIn Position')
        
        # Insert locally
        cursor.execute("""
            INSERT INTO people (name, company_id, position, linkedin_url, linkedin_position, created_at, updated_at, team_id, creation_source)
            VALUES (?, ?, ?, ?, ?, datetime('now'), datetime('now'), 1, 'LINKEDIN')
        """, (name, comp_id, position, li_url, li_pos))
        
        # SQL for server
        # Escaping
        val_name = name.replace("'", "''")
        val_pos = str(position).replace("'", "''") if position else ""
        val_li = str(li_url).replace("'", "''") if li_url else ""
        
        sql = f"INSERT INTO people (name, company_id, position, linkedin_url, linkedin_position, created_at, updated_at, team_id, creation_source) VALUES ('{val_name}', {comp_id}, '{val_pos}', '{val_li}', '{val_pos}', NOW(), NOW(), 1, 'LINKEDIN');"
        # SQLite uses datetime('now'), MySQL uses NOW() or datetime. Laravel uses MySQL usually? Or SQLite? 
        # The server is using sqlite (/var/www/relaticle/database/database.sqlite). So `datetime('now')`.
        sql = f"INSERT INTO people (name, company_id, position, linkedin_url, linkedin_position, created_at, updated_at, team_id, creation_source) VALUES ('{val_name}', {comp_id}, '{val_pos}', '{val_li}', '{val_pos}', datetime('now'), datetime('now'), 1, 'LINKEDIN');"
        
        sql_statements.append(sql)
        restored_count += 1
        
    conn.commit()
    conn.close()
    
    print(f"Restored {restored_count} people locally.")
    
    with open(RESTORE_SQL, "w") as f:
        f.write("-- Restore missing people\n")
        for s in sql_statements:
            f.write(s + "\n")
            
    print(f"Generated {RESTORE_SQL}")

if __name__ == "__main__":
    restore()
