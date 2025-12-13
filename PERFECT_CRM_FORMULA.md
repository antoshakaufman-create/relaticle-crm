# The Perfect CRM Enrichment Formula 🚀

This repository implements a **state-of-the-art "Perfect Formula"** for automated CRM data enrichment, combining multiple AI and API data sources to create a comprehensive profile for every lead.

## The Formula
$$
\text{Perfect Profile} = \text{YandexGPT (Name Normalization)} + \text{LinkedIn (Executive Search)} + \text{VK (Social Activity & Validation)} + \text{Lead Scoring (Sales Analysis)}
$$

---

## 1. Executive Search Workflow (C-Level)
**Tool:** `executive_search.py`
**Goal:** Identifying decision-makers (CEO, CTO, Founder) automatically.

1.  **Normalization:** Takes a raw company name from CRM (e.g., "Р Фарм") and asks **YandexGPT** for the official English and Russian legal names.
2.  **Company Match:** Searches LinkedIn for the exact Company Page using the normalized names.
3.  **Role Targeting:** Scans the company's employees for specific C-level titles ("CEO", "Founder", "Director", "President").
4.  **Result:** Returns verifiable LinkedIn profiles for decision-makers.

---

## 2. Sales Analysis: Lead Scoring (0-100)
**Tool:** `vk_lead_scoring.php`
**Goal:** Prioritize leads based on their digital maturity and activity.

Each lead is assigned a score based on a weighted formula:
*   **Engagement Rate (30%):** How active is their audience?
*   **Posting Frequency (20%):** Are they alive in 2025? *(See Active Status below)*
*   **Growth (15%):** Is the community growing?
*   **Content Quality (15%):** AI analysis of post intents (Sales vs Info).
*   **Authenticity (5%):** AI check for bot comments.
*   **Promo Ratio (10%):** How much do they sell?

### Categories
*   🔥 **HOT (75-100):** High engagement, active sales. *Strategy: Direct Offer.*
*   ☀️ **WARM (50-74):** Good presence. *Strategy: Nurture.*
*   ❄️ **COLD (0-49):** Abandoned or low quality. *Strategy: Education/Ignore.*

### Active vs Inactive (2025 Check)
The system automatically flags communities as **INACTIVE/DEAD** if they haven't posted significantly in the last 30 days.

---

## 3. SMM Analysis & Validation
**Tool:** `validate_vk_gpt.php`
**Goal:** Ensure data quality and relevance.

Using **YandexGPT**, the system validates every social link by reading the page content and comparing it against the company's **Industry** context.
*   *Example Error Caught:* A Pharma company named "Everest" linked to a travel agency "Everest".
*   **Logic:** "Does the content of this VK page match the 'Pharmaceuticals' industry context of the company?" -> If NO, link is removed.

---

## 4. Visual Analysis (Project "Lisa")
**Tool:** `final_consolidation.php`
**Goal:** Aesthetic critique.

An AI agent analyzes visual assets (Avatars, Covers) and provides a text critique:
*   "Logo is pixelated."
*   "Cover does not match brand identity."
*   "Mobile crop is incorrect."

---

## Usage
All tools are integrated into the repository. 
*   **Pipeline Run:** `executive_search.py` -> `validate_vk_gpt.php` -> `vk_lead_scoring.php`.
*   **Stack:** Python 3 (LinkedIn), PHP 8.5 (VK/Scoring), SQLite (Data Layer).
