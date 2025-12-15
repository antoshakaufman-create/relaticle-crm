import sqlite3
import pandas as pd
import os

# Configuration
DB_PATH = 'server_database.sqlite'
OUTPUT_FILE = 'crm_attributes.xlsx'

def export_schema_to_excel(db_path, output_file):
    if not os.path.exists(db_path):
        print(f"Error: Database file '{db_path}' not found.")
        return

    try:
        conn = sqlite3.connect(db_path)
        cursor = conn.cursor()

        # Get all table names
        cursor.execute("SELECT name FROM sqlite_master WHERE type='table';")
        tables = cursor.fetchall()

        all_data = []

        print(f"Found {len(tables)} tables. Extracting schema...")

        for table in tables:
            table_name = table[0]
            
            # Skip internal sqlite tables and migrations/jobs if desired, 
            # but user asked for "all available attributes", so we keep most.
            # We can filter 'sqlite_sequence' etc.
            if table_name.startswith('sqlite_'):
                continue

            # Get column info
            # PRAGMA table_info returns: (cid, name, type, notnull, dflt_value, pk)
            cursor.execute(f"PRAGMA table_info({table_name})")
            columns = cursor.fetchall()

            for col in columns:
                col_id, col_name, col_type, notnull, dflt_value, pk = col
                all_data.append({
                    'Table': table_name,
                    'Attribute (Column)': col_name,
                    'Type': col_type,
                    'Nullable': 'No' if notnull else 'Yes',
                    'Default': dflt_value,
                    'Primary Key': 'Yes' if pk else 'No'
                })

        conn.close()

        if not all_data:
            print("No data found to export.")
            return

        # Create DataFrame
        df = pd.DataFrame(all_data)

        # Write to Excel
        print(f"Writing data to {output_file}...")
        df.to_excel(output_file, index=False)
        print("Export complete.")

    except Exception as e:
        print(f"An error occurred: {e}")

if __name__ == "__main__":
    export_schema_to_excel(DB_PATH, OUTPUT_FILE)
