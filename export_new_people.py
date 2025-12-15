import sqlite3

SOURCE_DB = "server_database.sqlite"
OUTPUT_SQL = "insert_new_people.sql"

conn = sqlite3.connect(SOURCE_DB)
conn.row_factory = sqlite3.Row
cursor = conn.cursor()

# Helper to escape strings
def escape(s):
    if s is None:
        return "NULL"
    return "'" + s.replace("'", "''") + "'"

print("Exporting new people...")

# Select only people created by our script (source=LINKEDIN) and presumably recent
cursor.execute("SELECT * FROM people WHERE creation_source = 'LINKEDIN'")
rows = cursor.fetchall()

print(f"Found {len(rows)} records.")

with open(OUTPUT_SQL, "w") as f:
    f.write("-- Batch insert new people from LinkedIn search\n")
    for row in rows:
        # Columns: name, company_id, position, linkedin_url, linkedin_position, created_at, updated_at, team_id, creation_source
        # We assume IDs are auto-increment, so we omit ID to let server assign new ones (safe) 
        # OR we force IDs if we want consistency? Use server IDs.
        
        cols = ["name", "company_id", "position", "linkedin_url", "linkedin_position", "created_at", "updated_at", "team_id", "creation_source"]
        
        vals = []
        for col in cols:
            val = row[col]
            if isinstance(val, int):
                vals.append(str(val))
            else:
                vals.append(escape(val))
                
        sql = f"INSERT INTO people ({', '.join(cols)}) VALUES ({', '.join(vals)});\n"
        f.write(sql)

print(f"Saved to {OUTPUT_SQL}")
conn.close()
