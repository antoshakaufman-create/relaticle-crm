BEGIN TRANSACTION;
UPDATE companies SET linkedin_url = 'https://www.linkedin.com/company/55834016' WHERE id = 391;
UPDATE companies SET linkedin_url = 'https://www.linkedin.com/company/10414639' WHERE id = 392;
INSERT INTO people (name, company_id, position, linkedin_url, team_id, created_at, updated_at, creation_source)
                SELECT 'Артем Глебов', 392, 'Employee', 'https://www.linkedin.com/in/ACoAABH9di0BbbNM3wvDyqWHKf_kOHTUWWObLYA', 1, '2025-12-15 14:56:23', '2025-12-15 14:56:23', 'import'
                WHERE NOT EXISTS (SELECT 1 FROM people WHERE linkedin_url = 'https://www.linkedin.com/in/ACoAABH9di0BbbNM3wvDyqWHKf_kOHTUWWObLYA');
UPDATE companies SET linkedin_url = 'https://www.linkedin.com/company/37842981' WHERE id = 394;
UPDATE companies SET linkedin_url = 'https://www.linkedin.com/company/28856055' WHERE id = 397;
UPDATE companies SET linkedin_url = 'https://www.linkedin.com/company/101630304' WHERE id = 399;
UPDATE companies SET linkedin_url = 'https://www.linkedin.com/company/1032742' WHERE id = 401;
UPDATE companies SET linkedin_url = 'https://www.linkedin.com/company/7304286' WHERE id = 402;
COMMIT;
