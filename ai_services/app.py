"""
Resume AI microservice — semantic matching with sentence-transformers.
"""

from __future__ import annotations

import json
import os
import re
from functools import lru_cache

from flask import Flask, jsonify, request
from flask_cors import CORS
from sklearn.metrics.pairwise import cosine_similarity
from werkzeug.utils import secure_filename

app = Flask(__name__)
CORS(app)

UPLOAD_DIR = os.path.join(os.path.dirname(__file__), "uploads")
os.makedirs(UPLOAD_DIR, exist_ok=True)

KNOWN_SKILLS = [
    "react", "javascript", "typescript", "node", "nodejs", "vue", "angular",
    "laravel", "php", "python", "django", "flask", "fastapi", "java", "spring",
    "machine learning", "deep learning", "nlp", "natural language processing",
    "tensorflow", "pytorch", "scikit-learn", "sql", "mysql", "postgresql",
    "mongodb", "redis", "docker", "kubernetes", "aws", "azure", "gcp",
    "tailwind", "css", "html", "git", "ci/cd", "agile", "scrum",
    "rest api", "graphql", "microservices", "figma", "ui/ux",
    "data analysis", "pandas", "numpy", "spark", "hadoop",
    "c++", "c#", ".net", "ruby", "rails", "go", "golang", "rust", "swift",
    "kotlin", "android", "ios", "selenium", "jest", "cypress",
]

EMAIL_RE = re.compile(r"[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}")
PHONE_RE = re.compile(r"(\+?\d{1,3}[-.\s]?)?\(?\d{2,4}\)?[-.\s]?\d{3,4}[-.\s]?\d{3,4}")


_USE_TRANSFORMERS: bool | None = None


def _transformers_available() -> bool:
    global _USE_TRANSFORMERS
    if _USE_TRANSFORMERS is None:
        try:
            import sentence_transformers  # noqa: F401
            _USE_TRANSFORMERS = True
        except ImportError:
            _USE_TRANSFORMERS = False
    return _USE_TRANSFORMERS


@lru_cache(maxsize=1)
def get_model():
    from sentence_transformers import SentenceTransformer

    return SentenceTransformer("all-MiniLM-L6-v2")


def parse_resume_text(file_bytes: bytes, filename: str, fallback_text: str = "") -> str:
    ext = (filename or "").lower().split(".")[-1]

    if fallback_text and fallback_text.strip():
        return fallback_text.strip()

    if ext == "pdf":
        try:
            from pypdf import PdfReader
            import io

            reader = PdfReader(io.BytesIO(file_bytes))
            pages = [p.extract_text() or "" for p in reader.pages]
            return "\n".join(pages).strip()
        except Exception:
            return ""

    if ext in ("doc", "docx"):
        try:
            import io
            from docx import Document

            doc = Document(io.BytesIO(file_bytes))
            return "\n".join(p.text for p in doc.paragraphs).strip()
        except Exception:
            return ""

    try:
        return file_bytes.decode("utf-8", errors="ignore").strip()
    except Exception:
        return ""


def extract_contact(text: str) -> dict:
    email = None
    phone = None
    name = None

    emails = EMAIL_RE.findall(text or "")
    if emails:
        email = emails[0]

    phones = PHONE_RE.findall(text or "")
    if phones:
        phone = phones[0] if isinstance(phones[0], str) else "".join(phones[0])

    lines = [ln.strip() for ln in (text or "").splitlines() if ln.strip()]
    for line in lines[:8]:
        if EMAIL_RE.search(line) or PHONE_RE.search(line):
            continue
        if 2 <= len(line.split()) <= 5 and len(line) < 60:
            name = line
            break

    return {"name": name, "email": email, "phone": phone}


def extract_skills(text: str) -> list[str]:
    lowered = (text or "").lower()
    found = []
    for skill in KNOWN_SKILLS:
        if skill in lowered:
            found.append(skill.title() if skill != "ci/cd" else "CI/CD")
    return list(dict.fromkeys(found))


def extract_education(text: str) -> list[dict]:
    entries = []
    keywords = ["bachelor", "master", "phd", "b.sc", "m.sc", "mba", "university", "college", "degree"]
    for line in (text or "").splitlines():
        ln = line.strip()
        if not ln:
            continue
        low = ln.lower()
        if any(k in low for k in keywords) and len(ln) < 200:
            entries.append({"summary": ln})
    return entries[:5]


def extract_experience(text: str) -> list[dict]:
    entries = []
    keywords = ["experience", "worked", "developer", "engineer", "manager", "intern", "years"]
    for line in (text or "").splitlines():
        ln = line.strip()
        if not ln:
            continue
        low = ln.lower()
        if any(k in low for k in keywords) and len(ln) < 250:
            entries.append({"summary": ln})
    return entries[:8]


def _tfidf_similarity(job_text: str, resume_text: str) -> float:
    from sklearn.feature_extraction.text import TfidfVectorizer

    vectorizer = TfidfVectorizer(stop_words="english", max_features=5000)
    matrix = vectorizer.fit_transform([job_text, resume_text])
    score = cosine_similarity(matrix[0:1], matrix[1:2])[0][0]
    return float(max(0.0, min(1.0, score)))


def semantic_match_score(job_text: str, resume_text: str) -> float:
    job_text = (job_text or "").strip()
    resume_text = (resume_text or "").strip()

    if not job_text or not resume_text:
        return 0.0

    if _transformers_available():
        try:
            model = get_model()
            embeddings = model.encode([job_text, resume_text])
            score = cosine_similarity([embeddings[0]], [embeddings[1]])[0][0]
            return float(max(0.0, min(1.0, score)))
        except Exception:
            pass

    return _tfidf_similarity(job_text, resume_text)


def skill_overlap_score(required: list[str], extracted: list[str], job_description: str) -> tuple[int, list]:
    jd = (job_description or "").lower()
    req = [s.lower() for s in required] if required else []
    ext = [s.lower() for s in extracted]

    if not req:
        req = ext

    matched = []
    missing = []
    for skill in req:
        if skill in ext or skill in jd:
            matched.append(skill.title())
        else:
            missing.append(skill.title())

    if not req:
        pct = 50
    else:
        pct = int((len(matched) / len(req)) * 100)

    comparison = {
        "matched": matched,
        "missing": missing,
        "extracted": [s.title() for s in ext],
    }
    return pct, comparison


def generate_interview_questions(skills: list[str], job_title: str) -> list[str]:
    base = [
        f"Describe your experience relevant to the {job_title or 'role'}.",
        "Tell us about a challenging project and how you delivered it.",
        "How do you prioritize tasks when handling multiple deadlines?",
    ]
    for skill in (skills or [])[:4]:
        base.append(f"Rate your proficiency in {skill} and give a concrete example.")
    return base[:8]


def combined_match_percentage(semantic: float, skill_pct: int) -> int:
    semantic_pct = int(semantic * 100)
    return int(semantic_pct * 0.65 + skill_pct * 0.35)


@app.route("/health", methods=["GET"])
def health():
    return jsonify({
        "status": "ok",
        "service": "resume-ai",
        "semantic_engine": "transformers" if _transformers_available() else "tfidf",
    })


@app.route("/parse-and-match", methods=["POST"])
def parse_and_match():
    job_description = request.form.get("job_description", "")
    job_title = request.form.get("job_title", "")
    candidate_id = request.form.get("candidate_id", "")
    resume_text_override = request.form.get("resume_text", "")

    required_raw = request.form.get("required_skills", "[]")
    try:
        required_skills = json.loads(required_raw) if required_raw else []
    except json.JSONDecodeError:
        required_skills = []

    f = request.files.get("resume")
    if not f:
        return jsonify({"error": "resume file missing"}), 400

    filename = secure_filename(f.filename or "resume.pdf")
    path = os.path.join(UPLOAD_DIR, filename)
    f.save(path)

    with open(path, "rb") as fb:
        file_bytes = fb.read()

    resume_text = parse_resume_text(file_bytes, filename, resume_text_override)
    contact = extract_contact(resume_text)
    skills = extract_skills(resume_text)
    education = extract_education(resume_text)
    experience = extract_experience(resume_text)

    semantic = semantic_match_score(
        f"{job_title}\n{job_description}",
        resume_text,
    )
    skill_pct, skill_comparison = skill_overlap_score(
        required_skills,
        skills,
        job_description,
    )
    match_percentage = combined_match_percentage(semantic, skill_pct)
    interview_questions = generate_interview_questions(
        skills or required_skills,
        job_title,
    )

    status = "shortlisted" if match_percentage >= 60 else "needs_review"

    return jsonify({
        "candidate_id": candidate_id,
        "name": contact["name"],
        "email": contact["email"],
        "phone": contact["phone"],
        "skills": skills,
        "education": education,
        "experience": experience,
        "match_percentage": match_percentage,
        "semantic_score": round(semantic * 100, 1),
        "skill_score": skill_pct,
        "rank": 1,
        "skill_comparison": skill_comparison,
        "interview_questions": interview_questions,
        "matches": [
            {
                "job_title": job_title,
                "rank": 1,
                "match_percentage": match_percentage,
                "status": status,
                "skills": skills,
                "skill_comparison": skill_comparison,
            }
        ],
    })


if __name__ == "__main__":
    port = int(os.environ.get("PORT", 5001))
    print(f"Resume AI service listening on http://127.0.0.1:{port}")
    app.run(host="0.0.0.0", port=port, debug=os.environ.get("FLASK_DEBUG", "0") == "1")
