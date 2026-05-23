# Resume AI — ATS Platform (React + Laravel + Python Semantic Matching)

**Resume AI** is an AI-powered **Applicant Tracking System (ATS)** that automatically parses uploaded resumes and ranks candidates against job descriptions using semantic similarity + skill overlap.

---

## Demo / Screenshots

> Add screenshots to make the README instantly credible.

- **Dashboard**: ![picture](https://github.com/itsareebaah/AI_JobFinding/blob/965c9f721f05244dd51d5127048518c9cb703e5a/Screenshot%202026-05-23%20165750.png)
- **Jobs**: ![jobs ss](https://github.com/itsareebaah/AI_JobFinding/blob/965c9f721f05244dd51d5127048518c9cb703e5a/Screenshot%202026-05-23%20165800.png)
- **Candidates**: ![candidate ss](https://github.com/itsareebaah/AI_JobFinding/blob/965c9f721f05244dd51d5127048518c9cb703e5a/Screenshot%202026-05-23%20165816.png)
- **Match Results**: ![AI_match](https://github.com/itsareebaah/AI_JobFinding/blob/965c9f721f05244dd51d5127048518c9cb703e5a/Screenshot%202026-05-23%20165826.png)
---

## Why this project

Recruiting teams need fast, consistent screening. This project helps you:

- Upload resumes (PDF/DOC/DOCX)
- Create and manage job openings
- Get AI-generated match scores and shortlist guidance
- Review candidate ranks, extracted skills, and interview questions

---

## Key Features

- **Authentication (Laravel Sanctum)**
- **Job management**: create/update/delete jobs
- **Resume upload & parsing** (PDF/DOC/DOCX)
- **Semantic matching** using a sentence-transformers model (`all-MiniLM-L6-v2`)
- **Skill overlap comparison** + extracted contact/skills/education/experience
- **Ranked match results** per job
- **Interview question generation** based on extracted skills
- **Fallback matching** when the Python service is unavailable
- **Analytics dashboard** (stats + charts)

---

## Architecture

The system is split into three cooperating services:

```text
React Frontend (3000)
        |
        |  REST API (JWT-like via Sanctum)
        v
Laravel Backend (8000)
        |
        |  parse-and-match
        v
Python Microservice (5001)
  sentence-transformers + cosine similarity
```

---

## Tech Stack

- **Frontend**: React, Tailwind CSS
- **Backend**: Laravel (Sanctum, MySQL)
- **AI Microservice**: Flask, sentence-transformers, scikit-learn
- **Storage**: Laravel local disk (resume files)

---

## Project Structure

```text
Resume_Analyzer/
├─ frontend/            # React SPA
├─ backend/             # Laravel API
└─ ai_services/        # Python Flask semantic matching service
```

---

## Quick Start

### 1) Prerequisites

- Node.js + npm
- PHP 8+ + Composer
- MySQL
- Python 3.9+ (recommended)

---

### 2) Backend (Laravel)

1. Go to the backend folder:

```bash
cd backend
```

2. Configure environment:

```bash
cp .env.example .env
php artisan key:generate
```

3. Ensure these are set in `backend/.env`:

- `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `AI_SERVICE_URL` (example): `http://127.0.0.1:5001`
- `CORS_ALLOWED_ORIGINS` and Sanctum domains for your frontend URL

4. Run migrations + seed:

```bash
php artisan migrate:fresh --seed
php artisan storage:link
```

5. Start Laravel:

```bash
php artisan serve
```

Laravel API base (default):

- `http://127.0.0.1:8000/api`

---

### 3) Python AI Service (Flask)

1. Go to the AI service folder:

```bash
cd ai_services
```

2. Create and activate a virtual environment:

**Windows (PowerShell / cmd):**

```powershell
python -m venv venv
venv\Scripts\activate
```

3. Install dependencies:

```bash
pip install -r requirements.txt
```

4. Start the service:

```bash
python app.py
```

Default ports:

- Health: `http://127.0.0.1:5001/health`
- Parse & match: `http://127.0.0.1:5001/parse-and-match`

> First run may download `all-MiniLM-L6-v2` (~90MB).

---

### 4) Frontend (React)

1. Go to the frontend folder:

```bash
cd frontend
```

2. Install and run:

```bash
npm install
npm start
```

Frontend default:

- `http://localhost:3000`

---

## API Endpoints

All endpoints are grouped under `/api`.

### Auth (Public)

| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/register` | Register HR user |
| POST | `/api/login` | Login + token |

### Auth (Protected)

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/me` | Current user |
| POST | `/api/logout` | Logout |

### Jobs

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/jobs` | List jobs |
| POST | `/api/jobs` | Create job |
| PUT | `/api/jobs/{job}` | Update job |
| DELETE | `/api/jobs/{job}` | Delete job |

### Resume Upload & Matching

| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/upload-resume` | Upload resume (PDF/DOC/DOCX) |
| GET | `/api/resumes/{candidate}/status` | Trigger/return parsing + matching |
| GET | `/api/matches?job_id=` | Ranked matches (optionally filtered by job) |

### Candidates

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/candidates` | List candidates |
| GET | `/api/candidates/{candidate}` | Candidate profile |
| PATCH | `/api/candidates/{candidate}/notes` | Update candidate notes |

---

## Environment Variables

### Backend (`backend/.env`)

- `AI_SERVICE_URL=http://127.0.0.1:5001`
- `CORS_ALLOWED_ORIGINS=http://localhost:3000`
- `SANCTUM_STATEFUL_DOMAINS=localhost:3000`

### Frontend (`frontend/.env`)

- `REACT_APP_API_URL=http://127.0.0.1:8000/api`

---

## How Matching Works

1. HR uploads a resume from the React UI.
2. Laravel stores the file and creates a **candidate** with `status=parsing`.
3. Laravel calls the Python service (`/parse-and-match`) with:
   - job title + description
   - required skills (JSON)
   - resume bytes + extracted resume text
4. Python returns:
   - semantic match score
   - skill overlap
   - extracted contact/skills/education/experience
   - interview questions
5. Laravel saves results to `matches` and updates candidate status.

---

## Database (Overview)

Core tables:

- `users` — HR accounts
- `jobs` — job postings
- `candidates` — uploaded resumes + extracted info
- `matches` — AI scores per candidate/job
- `skills` — skill taxonomy
- `applications` — tracking shortlisted/applied status

---

## Troubleshooting

### Python service not reachable

- Symptom: candidate stays `parsing` or parsing fails.
- Fix: ensure AI service is running and `AI_SERVICE_URL` is correct.
- Confirm health:

```text
GET http://127.0.0.1:5001/health
```

### 401/Unauthenticated API calls

- Symptom: protected routes fail.
- Fix: login and ensure `Authorization` header is sent from the frontend.

### No jobs found when uploading

Laravel requires at least one **open job** to match against.

---

## Security / Privacy Notes

- Uploaded resumes are stored on the backend disk (`local` disk).
- If deploying, replace local storage with S3 or another managed storage.
- Restrict CORS and protect AI endpoints in production.

---

## License

MIT

