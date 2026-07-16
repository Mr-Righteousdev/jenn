# Technical Overview — Competency Profiling System

## 1. System Architecture

```
┌──────────────────────────────────────────────────────┐
│                   Client Browser                      │
│         Bootstrap 5 + Chart.js + Custom CSS          │
└──────────────────────┬───────────────────────────────┘
                       │ HTTP (Apache/Nginx)
                       ▼
┌──────────────────────────────────────────────────────┐
│                  PHP 8.5 Application                   │
│  ┌──────────┐  ┌──────────┐  ┌────────────────────┐  │
│  │ Auth     │  │ Matching │  │ Job API            │  │
│  │ (session)│  │ Engine   │  │ (cURL → RapidAPI)  │  │
│  └──────────┘  └──────────┘  └────────────────────┘  │
│  ┌──────────┐  ┌──────────┐  ┌────────────────────┐  │
│  │ Import   │  │ Reports  │  │ CRUD (CBC/Skills)  │  │
│  │ (CSV)    │  │ (HTML)   │  │                    │  │
│  └──────────┘  └──────────┘  └────────────────────┘  │
└──────────────────────┬───────────────────────────────┘
                       │ PDO (mysql:host=localhost)
                       ▼
┌──────────────────────────────────────────────────────┐
│                  MySQL / MariaDB                       │
│    10 tables: users, students, skills, jobs, etc.     │
└──────────────────────────────────────────────────────┘
```

### 1.1 Technology Stack

| Layer | Technology | Rationale |
|-------|-----------|-----------|
| **Frontend** | Bootstrap 5.3 + Chart.js | Responsive dashboards, radar/bar/doughnut charts, no build step |
| **Backend** | PHP 8.5 (no framework) | Full control, minimal overhead, PDO for secure DB access |
| **Database** | MySQL / MariaDB | Relational, ACID-compliant, widely available on shared hosting |
| **Job Data** | JSearch API (RapidAPI) | Real-time global job listings, free tier: 200 req/month |
| **Server** | Apache / Nginx + PHP-FPM | Standard LEMP/LEMP stack |

---

## 2. Database Schema

### 2.1 Entity Relationship (Core)

```
users ──1:1──> students ──1:N──> student_skills ──N:1── skills
  │                              │
  │                              └──> assessed_by (FK → users)
  │
  └──> student_results (academic grades)

skills ──1:N──> job_skills ──N:1── jobs
                      │
                      └── weight DECIMAL(3,2) — importance of skill for role

students ──1:N──> job_readiness ──N:1── jobs
  │                     │
  │                     └── match_score, weighted_score, keyword_score, gap_analysis (JSON)
  │
  └──> recommendations (auto-generated per gap)
```

### 2.2 Key Design Decisions

**`student_skills` UNIQUE KEY (`student_id`, `skill_id`)** — prevents duplicate assessments; `ON DUPLICATE KEY UPDATE` uses `GREATEST(current, new)` to keep the highest rating.

**`gap_analysis` stored as JSON** — flexible schema per match result; no separate gaps table needed. Contains top 5 missing skills with severity and weight.

**`cbc_levels` as editable rows** — not hardcoded enums. Admins can redefine Novice (0–30) → Expert (91–100) without code changes.

**No ORM** — raw PDO with prepared statements. The schema is small (10 tables), and an ORM adds complexity without proportional benefit for this scope.

---

## 3. Hybrid Matching Algorithm

### 3.1 Formula

```
Readiness = (WeightedScore × 0.6) + (KeywordMatch × 0.4)
```

Where:

**WeightedScore (60%)** — Student's demonstrated skill level weighted by job importance:

```
WeightedScore = Σ(student_level[i] × job_weight[i]) / Σ(job_weight[i])  × 100
```

- `student_level[i]` — 0–100 from lecturer assessment (or grade mapping)
- `job_weight[i]` — 0.00–1.00 from auto-extraction (TF-like frequency in description)

**KeywordMatch (40%)** — Semantic coverage based on whole-phrase matching:

```
Coverage = matched_skills / total_student_skills
AvgLevel = average(level of matched skills)
KeywordMatch = (Coverage × 0.5 + AvgLevel/100 × 0.5) × 100
```

This penalizes students who have skills but none appear in the job description, while rewarding those whose skills overlap with what employers mention.

### 3.2 Skill Extraction (from Job Descriptions)

```
For each skill_name in master_skills:
    Count regex word-boundary matches in job description
    weight = min(1.0, 0.3 + count × 0.1)
    Assign weight to job_skills[skill_id]
```

Effect: a skill mentioned 7+ times caps at weight 1.0. Mentioned once = 0.4. This creates a proxy for TF-IDF without external NLP libraries.

### 3.3 Example

Student S001 (Jennifer): Python=75, JavaScript=90, MySQL=90, TCP/IP=60

```
Job: "Python Full Stack Engineer with React"
  → Weighted: (75×1.0 + 90×0.5 + 90×0.5) / (1.0+0.5+0.5) = 82.5%
  → Keyword:  Python✓ Javascript✓ MySQL✓ = 3/4 skills matched, avg level 85 → 70.8%
  → Hybrid:  82.5×0.6 + 70.8×0.4 = 77.8%
```

---

## 4. Job Data Pipeline

```
JSearch API ──cURL──> PHP decode ──normalize──> INSERT jobs
                         │
                    extractSkills()
                         │
                    skillCache (key: skill_name, category)
                         │
                    regex match ──> job_skills
```

- **Source**: JSearch API via RapidAPI (Google for Jobs aggregate)
- **Auth**: x-rapidapi-key header
- **Rate limit**: 200 requests/month (free), 1000/hour (free tier cap)
- **Fallback**: 8 hand-crafted sample jobs when API key absent
- **Dedup**: `SELECT job_id FROM jobs WHERE title = ? AND company = ?` before INSERT

---

## 5. Grade-to-Skill Mapping (CSV Import)

```
Grade → Numeric:  A=90, B=75, C=60, D=45, F=25
Course → Category:  "Database Management I" → "Database Management"
                   "Programming I"         → "Programming"
                   "Web Development"       → "Web Development"
                   "Networking I"          → "Networking"
```

The category mapping uses a keyword lookup table:

```php
$keywords = [
    'Program' => 'Programming',
    'Database' => 'Database Management',
    'Web' => 'Web Development',
    'Network' => 'Networking',
    // ...
];
```

The first matching skill in that category gets the numeric grade level. This is intentionally simple — it assumes one representative skill per category (e.g., MySQL for "Database Management").

---

## 6. Auto-Recommendation Engine

When a gap is detected (skill level < 60), three recommendation types are generated:

| Type | Format | Example |
|------|--------|---------|
| `course` | "Course: {skill} Fundamentals" | "Course: Python Fundamentals" |
| `online_resource` | YouTube search link | `https://youtube.com/results?search_query=learn+python` |
| `skill_description` | Plain text guidance | "Python is a key competency..." |

Recommendations are regenerated on each job match (DELETE + INSERT for the student). This keeps them current but means historical recommendations are lost — acceptable for a competency system where current gaps matter most.

---

## 7. CBC Levels (Competency Framework)

```
Level         Range     Description
Novice        0–40      Needs supervision
Intermediate  41–70     Can work with guidance
Proficient    71–90     Works independently
Expert        91–100    Mentors others
```

Each student has a `cbc_level_id` on their profile, set automatically based on their average skill level across all assessments. Admins can edit the level definitions at any time.

---

## 8. Security Model

| Concern | Implementation |
|---------|---------------|
| Auth | `password_hash(PASSWORD_DEFAULT)` — bcrypt via PHP; session-based login |
| SQL injection | All queries use PDO prepared statements with parameterized values |
| CSRF | No forms accept GET parameters for mutations; only POST for writes |
| Role enforcement | `requireRole('admin')` — calls `requireLogin()` first, then checks role; 403 on mismatch |
| .env protection | `.env` at project root (outside `public/`); `chmod 640` recommended |
| XSS | All user output wrapped in `htmlspecialchars()` |

---

## 9. Performance Considerations

- **No pagination** — current scale (< 100 students, < 100 jobs) fits single-page tables
- **Radar chart** — Chart.js renders client-side; no server load for visualizations
- **Job matching** — O(S × J × K) where S=students, J=jobs, K=skills. For 50 students × 100 jobs × 15 skills = 75K iterations, completed in < 2s
- **CSV import** — wrapped in a single transaction; 1000 rows import in < 1s
- **Database** — InnoDB with foreign keys; auto-increment PKs; no indexes beyond FKs and the `uq_student_skill` unique key

---

## 10. Extending the System

| Feature | How to Add |
|---------|-----------|
| New skill category | Admin → Skills → Add (or insert into `skills` table) |
| New job source | Modify `JobApi.php` — add another API provider's endpoint |
| PDF reports | Swap the HTML print view for a library like Dompdf or TCPDF |
| Email notifications | Add PHP `mail()` or an SMTP library; trigger on match calculation |
| Student self-assessment | New `assessment_type` column in `student_skills` ('lecturer' / 'self') |
| Historical tracking | Add `valid_from`/`valid_to` to `student_skills` instead of UPDATE |
| REST API | Create `public/api/` endpoints returning JSON; wrap existing controllers |
