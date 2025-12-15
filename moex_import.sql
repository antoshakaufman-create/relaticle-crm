BEGIN TRANSACTION;
INSERT INTO companies (name, industry, lead_score, lead_category, creation_source, team_id, created_at, updated_at) VALUES ('UniPro', 'Digital Agency', 8, 'COLD', 'MOEX', 1, datetime('now'), datetime('now'));
COMMIT;
