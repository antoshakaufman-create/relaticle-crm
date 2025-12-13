#!/usr/bin/env python3
"""
Parse DOCX file and create JSON for import to CRM - improved version
"""

from zipfile import ZipFile
from xml.etree import ElementTree
import json
import re

# Extract text from DOCX
with ZipFile('data.docx', 'r') as z:
    xml_content = z.read('word/document.xml')
    tree = ElementTree.fromstring(xml_content)
    
    ns = {'w': 'http://schemas.openxmlformats.org/wordprocessingml/2006/main'}
    paragraphs = tree.findall('.//w:p', ns)
    
    lines = []
    for p in paragraphs:
        texts = p.findall('.//w:t', ns)
        line = ''.join(t.text or '' for t in texts).strip()
        if line:
            lines.append(line)

# Parse line by line
companies = {}
contacts = []
current_company = None
current_contact = None
current_type = None

def extract_value(line):
    """Extract value after **: """
    if '**: ' in line:
        return line.split('**: ', 1)[1].strip()
    return ''

for line in lines:
    if '### Company' in line:
        if current_contact:
            contacts.append(current_contact)
        current_type = 'company'
        current_company = {}
        current_contact = None
    elif '### Contact' in line:
        if current_contact:
            contacts.append(current_contact)
        current_type = 'contact'
        current_contact = {}
    elif current_type == 'company' and '**company_name**' in line:
        current_company['name'] = extract_value(line)
    elif current_type == 'company' and '**industry**' in line:
        current_company['industry'] = extract_value(line)
    elif current_type == 'company' and '**website**' in line:
        current_company['website'] = extract_value(line)
    elif current_type == 'company' and '**services_offered**' in line:
        current_company['services'] = extract_value(line)
    elif current_type == 'company' and '**company_comment**' in line:
        current_company['comment'] = extract_value(line)
    elif current_type == 'company' and '**import_id**' in line:
        import_id = extract_value(line)
        current_company['import_id'] = import_id
        if import_id:
            companies[import_id] = current_company
    elif current_type == 'contact' and '**full_name**' in line:
        current_contact['full_name'] = extract_value(line)
    elif current_type == 'contact' and '**job_title**' in line:
        current_contact['job_title'] = extract_value(line)
    elif current_type == 'contact' and '**email**' in line:
        current_contact['email'] = extract_value(line)
    elif current_type == 'contact' and '**phone**' in line:
        current_contact['phone'] = extract_value(line)
    elif current_type == 'contact' and '**contact_comment**' in line:
        current_contact['comment'] = extract_value(line)
    elif current_type == 'contact' and '**company_import_id**' in line:
        current_contact['company_import_id'] = extract_value(line)

# Add last contact
if current_contact:
    contacts.append(current_contact)

# Filter valid contacts
valid_contacts = [c for c in contacts if c.get('full_name') and c.get('full_name') != 'Не указано']

print(f"Found {len(companies)} companies")
print(f"Found {len(valid_contacts)} valid contacts")

# Save to JSON
data = {
    'companies': list(companies.values()),
    'contacts': valid_contacts
}

with open('import_data.json', 'w', encoding='utf-8') as f:
    json.dump(data, f, ensure_ascii=False, indent=2)

print("Saved to import_data.json")

# Show sample
print("\n=== Sample Companies ===")
for c in list(companies.values())[:3]:
    print(f"  - {c.get('name', 'N/A')} ({c.get('industry', 'N/A')})")

print("\n=== Sample Contacts ===")
for c in valid_contacts[:5]:
    print(f"  - {c.get('full_name', 'N/A')} | {c.get('job_title', 'N/A')} | {c.get('email', 'N/A')}")
