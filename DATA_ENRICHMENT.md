# Data Enrichment & Analytics System

This repository contains a suite of tools for enriching CRM contact data with social media insights from **VK** and **LinkedIn**, coupled with an AI-driven Lead Scoring system.

## 1. LinkedIn Enrichment Integration
**Script:** `linkedin_enrich.py`
**Library:** `linkedin-api` (unofficial)

A Python-based tool to find and verify contact details using the LinkedIn Voyager API.

### Features
- **Smart Search:** Searches by `Name + Company`, falls back to `Name` only.
- **Robust Profile Matching:** Uses both `public_id` and `urn_id` to ensure data retrieval even for limited profiles.
- **Enrichments:**
  - LinkedIn URL
  - Current Position (Headline)
  - Current Company
  - Location
- **Cookie Authentication:** Supports passing `li_at` cookie to bypass 2FA/CAPTCHA challenges.

### Usage
```bash
python3 linkedin_enrich.py --user "email" --password "pass" --cookie "li_at_value" --limit 50
```

---

## 2. VK Social Media Analysis & Lead Scoring
**Core Script:** `vk_lead_scoring.php`
**Consolidation:** `final_consolidation.php`

A comprehensive PHP system to analyze active VK communities and score leads based on SMM performance.

### Lead Scoring Formula (0-100)
Leads are scored based on weighted metrics:
- **Engagement Rate (ER):** 20 points
- **Posting Frequency:** 15 points
- **Audience Growth:** 10 points
- **SMM Consistency:** 10 points
- **Content Quality (Promo/Live):** 15 points
- **Business Intent (AI):** 15 points
- **Comment Authenticity (AI):** 15 points

### Categories
- **HOT (80-100):** High engagement, active sales. *Strategy: Direct Sales.*
- **WARM (50-79):** Good presence, irregular activity. *Strategy: Nurture.*
- **COLD-WARM (30-49):** Low activity or just started. *Strategy: Education.*
- **COLD (0-29):** Dead or abandoned group.

### Visual Analysis ("Lisa")
For Top 10 companies, an AI agent ("Lisa") analyzes the visual content (avatars, covers, post styles) and generates specific recommendations for improvement, stored in the CRM.

---

## 3. Database Architecture
New columns added to `people` table:
- `vk_status` (ACTIVE/INACTIVE/DEAD)
- `lead_score` (Decimal 0-100)
- `lead_category` (HOT/WARM/COLD)
- `linkedin_url`
- `linkedin_position`
- `linkedin_company`
- `linkedin_location`
- `visual_analysis` (Text)

## 4. Helper Scripts
- `validate_vk_gpt.php`: Validates VK links using YandexGPT.
- `deep_vk_api.php`: Fetches deep post history for engagement calculation.
- `find_vk_links.php`: Discovers missing VK profiles.
- `migrate_notes_to_columns.php`: One-time script to parse unstructured notes into DB columns.
