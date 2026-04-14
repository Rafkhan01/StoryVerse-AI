# StoryVerse-AI

An AI-powered interactive storytelling platform that combines collaborative fiction reading with machine learning-driven reader engagement. Readers predict story outcomes chapter by chapter, and the platform evaluates those predictions using a hybrid NLP scoring pipeline. Authors manage content through a structured dashboard, and administrators oversee the full platform through a dedicated control panel.

This is a full-stack platform built on PHP/MySQL for the web layer and Python FastAPI for the ML backend, running as two separate services communicating over REST.

---

## Platform Overview

StoryVerse-AI bridges creative storytelling and machine learning by treating reader predictions as structured data. After each story chapter, readers submit what they think happens next. The ML backend scores those predictions against the actual story continuation using semantic similarity, rewarding engagement with accuracy points. The system also analyses comment sentiment, detects spam, and tracks reader performance on a live leaderboard.

---

## Architecture

```
StoryVerse-AI/
│
├── project3/                          # PHP frontend (XAMPP / Apache)
│   ├── index.php                      # Homepage
│   ├── db_connect.php                 # Database connection
│   ├── auth_portal.php                # Unified login/signup entry
│   ├── login_portal.php               # Login handler
│   ├── signup.php                     # Registration
│   ├── verify_otp.php                 # Email OTP verification
│   ├── logout.php                     # Session logout
│   ├── stories.php                    # Story listing
│   ├── story_detail.php               # Chapter reading + prediction + comments
│   ├── story_match.php                # Prediction matching interface
│   ├── story_matcher.php              # Prediction matching logic
│   ├── prediction_action.php          # Prediction submission handler
│   ├── profile.php                    # Reader profile
│   ├── leaderboard.php                # Global leaderboard
│   ├── game_arena.php                 # Game Arena hub
│   ├── flash_words.php                # Flash Words game
│   ├── story_scramble.php             # Story Scramble game
│   ├── who_said_it.php                # Who Said It? game
│   ├── arena_api.php                  # Arena ML API bridge
│   ├── chat_ajax.php                  # Chat AJAX handler
│   ├── follow_handler.php             # Follow system
│   ├── handle_like.php                # Like handler
│   ├── track_view.php                 # View tracking
│   ├── functions.php                  # Shared utility functions
│   ├── verify_email_change.php        # Email change verification
│   ├── assets/                        # CSS, JS, images
│   ├── author/                        # Author dashboard and management
│   ├── admin/                         # Admin panel
│   └── PHPMailer/                     # Email library
│
└── story_verse/                       # Python FastAPI ML backend (port 8000)
    ├── main.py                        # FastAPI app entry point
    ├── final_evaluator.py             # Hybrid SBERT + Cross-Encoder scoring
    ├── sbert_engine.py                # Sentence-BERT embedding engine
    ├── semantic_matcher.py            # Semantic similarity matching
    ├── sentiment_api.py               # Sentiment analysis endpoint
    ├── preprocessing.py               # Text preprocessing pipeline
    ├── text_cleaner.py                # Text cleaning utilities
    ├── db_connection.py               # Python-side DB connection
    ├── ml_prediction_model.pkl        # Trained ML prediction model
    ├── sentiment_model.pkl            # Trained sentiment model
    ├── spam_model.pkl                 # Trained spam detection model
    ├── spam_vectorizer.pkl            # Spam model vectorizer
    ├── calibration_model.pkl          # Score calibration model
    ├── ml_training.ipynb              # ML model training notebook
    ├── ml_training_spam.ipynb         # Spam model training notebook
    └── dataset.csv                    # Training dataset
```

---

## Key Features

### Reader Side
- Chapter-by-chapter story reading with prediction submission after each part
- Predictions scored by the ML backend using semantic similarity
- Comment system with spam detection via FastAPI before submission
- Sentiment-aware comment display
- Game Arena with three games: Flash Words (RSVP speed reading), Story Scramble (sentence reordering), Who Said It? (character attribution quiz)
- Global leaderboard tracking reader accuracy and game scores
- Follow system between readers and authors

### Author Side
- Story and chapter upload with structured content management
- Prediction accuracy review dashboard (SBERT and ML scores displayed per submission)
- Manual accuracy override with save to database
- Real-time chat with readers
- Author profile and follower management

### Admin Side
- Full admin panel with story, dataset, comment, announcement, and leaderboard management
- Dataset and dataset parts management with relational integrity
- Comment moderation with flag/unflag
- Leaderboard score management
- Admin chat interface

### ML Backend (FastAPI — Port 8000)
- `/evaluate-story` — Hybrid Cross-Encoder SBERT scoring for prediction evaluation
- `/detect-spam` — Spam classification for comment submissions
- `/sentiment` — Sentiment analysis for comments
- Arena ML endpoints bridged via `arena_api.php`

---

## Tech Stack

### Frontend / Web Layer
- PHP 8.2 with PDO
- MySQL (MariaDB 10.4) — database: `story_prediction2`
- HTML, CSS, vanilla JavaScript
- PHPMailer (email OTP verification)
- XAMPP (local development server)

### ML Backend
- Python
- FastAPI
- Sentence-BERT (SBERT) — `sentence-transformers` library
- Cross-Encoder reranking
- Scikit-learn (spam detection, ML prediction model)
- Pandas, NumPy

---

## Setup and Usage

### Prerequisites
- XAMPP (Apache + MySQL)
- Python 3.x
- pip

### 1. Clone the repository

```bash
git clone https://github.com/Rafkhan01/StoryVerse-AI.git
```

### 2. Set up the PHP frontend

- Copy the `project3/` folder into your XAMPP `htdocs/` directory
- Import the database:
  - Open phpMyAdmin
  - Create a database named `story_prediction2`
  - Import `story_prediction2.sql`
  - Import `chat_tables.sql`
  - Import `arena_questions_db.sql`

### 3. Configure the database connection

Edit `project3/db_connect.php` with your local MySQL credentials.

### 4. Install Python dependencies

```bash
cd story_verse
pip install fastapi uvicorn sentence-transformers scikit-learn pandas numpy
```

### 5. Start the ML backend

```bash
cd story_verse
uvicorn main:app --reload --port 8000
```

### 6. Start XAMPP and open the platform

- Start Apache and MySQL in XAMPP
- Navigate to `http://localhost/project3/`

---

## ML Model Details

The prediction scoring pipeline uses a two-stage hybrid approach:

- **Stage 1 — Sentence-BERT:** Computes dense vector embeddings for both the reader's prediction and the actual story continuation, producing a cosine similarity score.
- **Stage 2 — Cross-Encoder reranking:** Passes the prediction-continuation pair through a Cross-Encoder model for fine-grained relevance scoring.
- **Calibration:** A calibration model adjusts raw scores to a normalised accuracy range before storage.

Spam detection uses a Scikit-learn classifier trained on labelled SMS/comment spam datasets. Sentiment analysis is handled by a separately trained model returning polarity scores per comment.

---

## Database

- **Name:** `story_prediction2`
- **Engine:** MariaDB 10.4 (MySQL-compatible)
- **Key tables:** users, stories, story_parts, predictions, comments, leaderboard, arena_progress, chat_messages, announcements, datasets, dataset_parts

---

## Status

Fully functional on localhost. Deployment in progress.

---

## Author

Rafkhan B
github.com/Rafkhan01
