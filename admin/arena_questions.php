<?php
session_start();

// ── Auth: admin only ──────────────────────────────────────────
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    http_response_code(403);
    die('<!DOCTYPE html><html><body style="background:#0b0f14;color:#f2503d;font-family:monospace;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;font-size:14px;letter-spacing:2px;">ACCESS DENIED — ADMIN ONLY</body></html>');
}

// db_connect.php is one level up (in project root)
require_once '../db_connect.php';

// ── Fetch all stories for the selector ───────────────────────
try {
    $stmt = $pdo->query("SELECT story_id, title, current_part_no FROM stories ORDER BY created_at DESC");
    $all_stories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $all_stories = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Arena Admin — Question Manager</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;900&family=Rajdhani:wght@300;400;500;600;700&family=Share+Tech+Mono&display=swap" rel="stylesheet"/>

<style>
:root {
  --void:        #0b0f14;
  --void-2:      #111820;
  --void-3:      #161e28;
  --void-4:      #1c2530;
  --cyan:        #3df2e0;
  --cyan-dim:    #1ab8a8;
  --cyan-glow:   rgba(61,242,224,0.15);
  --cyan-faint:  rgba(61,242,224,0.05);
  --amber:       #f2a73d;
  --amber-dim:   rgba(242,167,61,0.15);
  --red:         #f2503d;
  --red-dim:     rgba(242,80,61,0.12);
  --green:       #3df27a;
  --green-dim:   rgba(61,242,122,0.12);
  --purple:      #9d6fff;
  --text:        #c8d8e8;
  --text-dim:    #4a6070;
  --text-mid:    #7a96a8;
  --border:      rgba(61,242,224,0.1);
}

*{box-sizing:border-box;margin:0;padding:0;}
html{scroll-behavior:smooth;}

body {
  background: var(--void);
  color: var(--text);
  font-family: 'Rajdhani', sans-serif;
  font-size: 15px;
  min-height: 100vh;
}

/* Grid bg */
body::before {
  content:'';
  position:fixed;inset:0;
  background-image:
    linear-gradient(rgba(61,242,224,0.025) 1px, transparent 1px),
    linear-gradient(90deg, rgba(61,242,224,0.025) 1px, transparent 1px);
  background-size:50px 50px;
  pointer-events:none;z-index:0;
}

.z1{position:relative;z-index:1;}

/* Typography */
.font-orb{font-family:'Orbitron',monospace;}
.font-mono{font-family:'Share Tech Mono',monospace;}
.font-raj{font-family:'Rajdhani',sans-serif;}

/* Sidebar */
#sidebar {
  width: 240px;
  min-height: 100vh;
  background: var(--void-2);
  border-right: 1px solid var(--border);
  position: fixed; left:0; top:0;
  z-index:50;
  display:flex;flex-direction:column;
}

.sidebar-logo {
  padding: 20px;
  border-bottom: 1px solid var(--border);
}

.nav-item {
  display:flex;align-items:center;gap:10px;
  padding: 11px 20px;
  font-family:'Orbitron',monospace;
  font-size:9px;letter-spacing:2px;
  color: var(--text-dim);
  cursor:pointer;
  border-left: 2px solid transparent;
  transition: all 0.2s;
  text-decoration:none;
}
.nav-item:hover, .nav-item.active {
  color: var(--cyan);
  border-left-color: var(--cyan);
  background: var(--cyan-faint);
}
.nav-item .nav-dot{
  width:6px;height:6px;border-radius:50%;
  background:currentColor;
  opacity:0.5;
  transition:opacity 0.2s;
}
.nav-item:hover .nav-dot, .nav-item.active .nav-dot{opacity:1;}

/* Main area */
#main {
  margin-left: 240px;
  min-height: 100vh;
  padding: 32px;
}

/* Panels / tabs */
.panel { display:none; }
.panel.active { display:block; }

/* Section heading */
.sec-title {
  font-family:'Orbitron',monospace;
  font-size:13px;font-weight:700;
  letter-spacing:3px;color:var(--cyan);
  text-transform:uppercase;
  margin-bottom:4px;
}
.sec-sub {
  font-size:13px;color:var(--text-dim);
  letter-spacing:1px;margin-bottom:24px;
}

/* Story selector */
.story-select-wrap {
  display:grid;grid-template-columns:1fr auto;
  gap:12px;align-items:end;margin-bottom:28px;
}

/* Styled select */
select.styled {
  background: var(--void-2);
  border: 1px solid var(--border);
  color: var(--text);
  font-family:'Rajdhani',sans-serif;
  font-size:14px;
  padding: 10px 14px;
  border-radius:4px;
  width:100%;
  outline:none;
  cursor:pointer;
  transition:border-color 0.2s;
}
select.styled:focus { border-color:var(--cyan-dim); }

/* Input */
input.styled, textarea.styled {
  background: var(--void-2);
  border: 1px solid var(--border);
  color: var(--text);
  font-family:'Rajdhani',sans-serif;
  font-size:14px;
  padding: 10px 14px;
  border-radius:4px;
  width:100%;
  outline:none;
  transition:border-color 0.2s, box-shadow 0.2s;
}
input.styled:focus, textarea.styled:focus {
  border-color:var(--cyan-dim);
  box-shadow:0 0 0 2px rgba(61,242,224,0.08);
}
textarea.styled { resize:vertical; line-height:1.6; }

input.styled.correct:focus {
  border-color:var(--green);
  box-shadow:0 0 0 2px var(--green-dim);
}
input.styled.wrong:focus {
  border-color:var(--red);
  box-shadow:0 0 0 2px var(--red-dim);
}

/* Label */
label.field-label {
  display:block;
  font-family:'Orbitron',monospace;
  font-size:9px;letter-spacing:2px;
  color:var(--text-dim);
  margin-bottom:6px;
  text-transform:uppercase;
}

/* Buttons */
.btn {
  font-family:'Orbitron',monospace;
  font-size:9px;letter-spacing:2px;text-transform:uppercase;
  padding:10px 22px;
  border-radius:3px;
  cursor:pointer;
  transition:all 0.2s;
  border:1px solid;
}
.btn-cyan {
  border-color:var(--cyan);color:var(--cyan);background:transparent;
}
.btn-cyan:hover { background:var(--cyan-faint); box-shadow:0 0 12px var(--cyan-glow); }
.btn-cyan:disabled { opacity:0.3;cursor:not-allowed; }

.btn-amber {
  border-color:var(--amber);color:var(--amber);background:transparent;
}
.btn-amber:hover { background:var(--amber-dim); }

.btn-green {
  border-color:var(--green);color:var(--green);background:transparent;
}
.btn-green:hover { background:var(--green-dim); }

.btn-red {
  border-color:var(--red);color:var(--red);background:transparent;
}
.btn-red:hover { background:var(--red-dim); }

.btn-sm { padding:6px 14px; font-size:8px; }

/* Chunk cards */
.chunk-card {
  background: var(--void-2);
  border: 1px solid var(--border);
  border-radius:5px;
  padding:16px;
  cursor:pointer;
  transition:all 0.2s;
  position:relative;
  overflow:hidden;
}
.chunk-card::before {
  content:'';
  position:absolute;left:0;top:0;
  width:3px;height:100%;
  background:var(--cyan-dim);
  opacity:0;transition:opacity 0.2s;
}
.chunk-card:hover {
  border-color:var(--cyan-dim);
  transform:translateY(-1px);
  box-shadow:0 4px 20px rgba(61,242,224,0.08);
}
.chunk-card:hover::before { opacity:1; }
.chunk-card.used {
  border-color:rgba(61,242,122,0.15);
  opacity:0.55;
  cursor:default;
  pointer-events:none;
}
.chunk-card.used::before { background:var(--green); opacity:1; }

.chunk-text-preview {
  font-size:13px;line-height:1.7;
  color:var(--text-mid);
  display:-webkit-box;
  -webkit-line-clamp:3;
  -webkit-box-orient:vertical;
  overflow:hidden;
}

.chunk-meta {
  font-family:'Share Tech Mono',monospace;
  font-size:10px;color:var(--text-dim);
  margin-top:10px;
  display:flex;gap:14px;align-items:center;
}

/* Part badge */
.part-badge {
  display:inline-flex;align-items:center;
  padding:3px 10px;border-radius:2px;
  font-family:'Orbitron',monospace;font-size:9px;letter-spacing:1.5px;
  background:rgba(61,242,224,0.06);
  color:var(--cyan-dim);
  border:1px solid rgba(61,242,224,0.15);
}

.used-badge {
  display:inline-flex;align-items:center;gap:5px;
  padding:3px 10px;border-radius:2px;
  font-family:'Orbitron',monospace;font-size:9px;letter-spacing:1.5px;
  background:var(--green-dim);color:var(--green);
  border:1px solid rgba(61,242,122,0.2);
}

/* Stats bar */
.stat-pill {
  display:inline-flex;align-items:center;gap:8px;
  padding:8px 16px;border-radius:3px;
  background:var(--void-2);border:1px solid var(--border);
}
.stat-val {
  font-family:'Orbitron',monospace;font-size:16px;font-weight:700;
  color:var(--cyan);
}
.stat-lbl {
  font-size:11px;color:var(--text-dim);letter-spacing:1px;
}

/* Modal backdrop */
#modal-backdrop {
  position:fixed;inset:0;
  background:rgba(0,0,0,0.75);
  backdrop-filter:blur(4px);
  z-index:200;
  display:none;align-items:center;justify-content:center;
}
#modal-backdrop.open { display:flex; }

/* Modal box */
.modal-box {
  background:var(--void-2);
  border:1px solid var(--cyan-dim);
  border-radius:6px;
  box-shadow:0 0 40px rgba(61,242,224,0.12);
  width:min(680px, 95vw);
  max-height:88vh;
  overflow-y:auto;
  animation:modalIn 0.25s ease;
}
@keyframes modalIn {
  from{opacity:0;transform:scale(0.95) translateY(10px);}
  to{opacity:1;transform:scale(1) translateY(0);}
}

.modal-header {
  padding:20px 24px;
  border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:between;
  gap:12px;
}

/* Existing questions list */
.q-row {
  display:grid;
  grid-template-columns:auto 1fr auto;
  gap:14px;align-items:start;
  padding:14px 16px;
  border-bottom:1px solid rgba(61,242,224,0.05);
  transition:background 0.15s;
}
.q-row:hover { background:rgba(61,242,224,0.02); }
.q-row:last-child { border-bottom:none; }

.q-num {
  font-family:'Orbitron',monospace;font-size:10px;
  color:var(--text-dim);padding-top:2px;
}

/* Toast */
#toast {
  position:fixed;bottom:28px;right:28px;z-index:500;
  font-family:'Orbitron',monospace;font-size:10px;letter-spacing:2px;
  padding:12px 20px;border-radius:3px;
  transform:translateY(60px);opacity:0;
  transition:all 0.3s cubic-bezier(0.34,1.56,0.64,1);
  pointer-events:none;
}
#toast.show{transform:translateY(0);opacity:1;}
#toast.success{background:var(--green-dim);color:var(--green);border:1px solid var(--green);}
#toast.error  {background:var(--red-dim);  color:var(--red);  border:1px solid var(--red);}
#toast.info   {background:var(--cyan-faint);color:var(--cyan);border:1px solid var(--cyan-dim);}

/* Loading spinner */
.spinner {
  display:inline-block;
  width:14px;height:14px;
  border:2px solid rgba(61,242,224,0.2);
  border-top-color:var(--cyan);
  border-radius:50%;
  animation:spin 0.7s linear infinite;
}
@keyframes spin{to{transform:rotate(360deg);}}

/* Divider */
.divider {
  border:none;border-top:1px solid var(--border);
  margin:20px 0;
}

/* Scrollbar */
::-webkit-scrollbar{width:4px;}
::-webkit-scrollbar-track{background:var(--void);}
::-webkit-scrollbar-thumb{background:var(--cyan-dim);border-radius:2px;}

/* Highlight text */
.hl-cyan  { color:var(--cyan); }
.hl-amber { color:var(--amber); }
.hl-green { color:var(--green); }
.hl-red   { color:var(--red); }
.hl-dim   { color:var(--text-dim); }

/* Grid layouts */
.field-row { display:grid;grid-template-columns:1fr 1fr;gap:14px; }
@media(max-width:600px){ .field-row{grid-template-columns:1fr;} }

/* Chunk grid */
.chunk-grid {
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(300px,1fr));
  gap:14px;
  margin-top:16px;
}

/* Filter tabs */
.filter-tab {
  padding:6px 16px;
  font-family:'Orbitron',monospace;font-size:9px;letter-spacing:2px;
  border:1px solid var(--border);border-radius:2px;
  color:var(--text-dim);background:transparent;
  cursor:pointer;transition:all 0.2s;
}
.filter-tab:hover, .filter-tab.active {
  border-color:var(--cyan);color:var(--cyan);background:var(--cyan-faint);
}

/* Empty state */
.empty-state {
  text-align:center;padding:48px 20px;
  font-family:'Orbitron',monospace;font-size:10px;letter-spacing:3px;
  color:var(--text-dim);
}

/* Glow */
.glow-cyan{ text-shadow:0 0 8px var(--cyan),0 0 20px rgba(61,242,224,0.3); }
</style>
</head>
<body>
<div class="z1 flex">

<!-- ══ SIDEBAR ══ -->
<aside id="sidebar">
  <div class="sidebar-logo">
    <div class="font-orb text-xs font-black glow-cyan" style="color:var(--cyan);letter-spacing:3px;">ARENA ADMIN</div>
    <div class="font-mono text-xs mt-1" style="color:var(--text-dim);font-size:10px;">QUESTION MANAGER</div>
  </div>

  <nav style="padding:16px 0;">
    <div class="nav-item active" onclick="showPanel('story-chunks')" id="nav-story">
      <span class="nav-dot"></span>STORY CHUNKS
    </div>
    <div class="nav-item" onclick="showPanel('external')" id="nav-external">
      <span class="nav-dot"></span>EXTERNAL Q&amp;A
    </div>
    <div class="nav-item" onclick="showPanel('all-questions')" id="nav-all">
      <span class="nav-dot"></span>ALL QUESTIONS
    </div>
  </nav>

  <div style="margin-top:auto;padding:12px 20px;border-top:1px solid var(--border);display:flex;flex-direction:column;gap:4px;">
    <a href="admin_games.php" class="nav-item" style="padding:8px 0;font-size:9px;color:var(--cyan);border-left-color:var(--cyan);background:var(--cyan-faint);">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M19 12H5M12 5l-7 7 7 7"/>
      </svg>
      ADMIN DASHBOARD
    </a>
    <a href="../index.php" class="nav-item" style="padding:8px 0;font-size:9px;">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M19 12H5M12 5l-7 7 7 7"/>
      </svg>
      BACK TO SITE
    </a>
  </div>
</aside>

<!-- ══ MAIN ══ -->
<main id="main" class="flex-1">

  <!-- ═══════════════════════════════════════════════
       PANEL 1: Story Chunks
  ══════════════════════════════════════════════════ -->
  <div id="panel-story-chunks" class="panel active">
    <div class="sec-title">Story Chunk Questions</div>
    <div class="sec-sub">Generate chunks from story parts · Click a chunk to create a question</div>

    <!-- Story + Part selector -->
    <div style="display:grid;grid-template-columns:1fr 180px auto;gap:12px;align-items:end;margin-bottom:24px;">
      <div>
        <label class="field-label">Select Story</label>
        <select class="styled" id="sc-story-select" onchange="onStoryChange()">
          <option value="">— Choose a story —</option>
          <?php foreach ($all_stories as $s): ?>
          <option value="<?= $s['story_id'] ?>" data-parts="<?= $s['current_part_no'] ?>">
            <?= htmlspecialchars($s['title']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="field-label">Part Number</label>
        <select class="styled" id="sc-part-select" disabled>
          <option value="">— Part —</option>
        </select>
      </div>
      <div style="padding-bottom:1px;">
        <button class="btn btn-cyan" onclick="generateChunks()" id="btn-generate">
          GENERATE CHUNKS
        </button>
      </div>
    </div>

    <!-- Stats bar -->
    <div id="sc-stats" style="display:none;" class="flex gap-4 mb-6 flex-wrap">
      <div class="stat-pill">
        <div>
          <div class="stat-val" id="stat-total">0</div>
          <div class="stat-lbl">TOTAL CHUNKS</div>
        </div>
      </div>
      <div class="stat-pill">
        <div>
          <div class="stat-val hl-green" id="stat-used">0</div>
          <div class="stat-lbl">WITH QUESTIONS</div>
        </div>
      </div>
      <div class="stat-pill">
        <div>
          <div class="stat-val hl-amber" id="stat-unused">0</div>
          <div class="stat-lbl">AVAILABLE</div>
        </div>
      </div>
    </div>

    <!-- Filter tabs -->
    <div id="sc-filters" style="display:none;gap:8px;margin-bottom:16px;" class="flex">
      <button class="filter-tab active" onclick="filterChunks('available')" id="ft-available">AVAILABLE</button>
      <button class="filter-tab" onclick="filterChunks('used')" id="ft-used">USED</button>
      <button class="filter-tab" onclick="filterChunks('all')" id="ft-all">ALL</button>
    </div>

    <!-- Chunk loading state -->
    <div id="sc-loading" style="display:none;" class="empty-state">
      <div class="spinner" style="width:20px;height:20px;margin:0 auto 12px;"></div>
      <div>PROCESSING STORY CONTENT...</div>
    </div>

    <!-- Chunk grid -->
    <div id="chunk-grid" class="chunk-grid"></div>

    <!-- Placeholder -->
    <div id="sc-placeholder" class="empty-state">
      <div style="font-size:24px;margin-bottom:12px;opacity:0.2;">⬡</div>
      <div>SELECT A STORY AND PART TO BEGIN</div>
    </div>
  </div><!-- /panel story chunks -->


  <!-- ═══════════════════════════════════════════════
       PANEL 2: External Q&A
  ══════════════════════════════════════════════════ -->
  <div id="panel-external" class="panel">
    <div class="sec-title">External Knowledge Questions</div>
    <div class="sec-sub">Add custom passages with Q&amp;A — used as bonus questions in Flash Words</div>

    <div style="max-width:680px;">
      <!-- Custom passage -->
      <div class="mb-5">
        <label class="field-label">
          Knowledge Passage
          <span class="hl-dim">(must be exactly 60, 100, 140, 180, or 220 words)</span>
        </label>
        <textarea class="styled" id="ext-passage" rows="5"
          placeholder="Write or paste a passage here. Word count must match a WPM tier exactly: 60, 100, 140, 180, or 220 words."
          oninput="onPassageInput()" onblur="onPassageBlur()"></textarea>

        <!-- Word count feedback -->
        <div class="mt-2 flex items-center justify-between">
          <div id="ext-wpm-feedback" style="font-size:13px; font-family:'Share Tech Mono',monospace;"></div>
          <div id="ext-passage-count" class="font-mono text-xs" style="color:var(--text-dim);">0 words</div>
        </div>

        <!-- WPM tier pills -->
        <div class="flex gap-2 mt-2 flex-wrap">
          <?php foreach ([60,100,140,180,220] as $tier): ?>
          <div class="wpm-tier-pill" data-wpm="<?= $tier ?>" style="
            padding:3px 10px;border-radius:2px;
            font-family:'Orbitron',monospace;font-size:9px;letter-spacing:1.5px;
            border:1px solid rgba(61,242,224,0.15);color:var(--text-dim);
            background:transparent;transition:all 0.2s;
          "><?= $tier ?> WPM</div>
          <?php endforeach; ?>
        </div>
      </div>

      <hr class="divider"/>

      <!-- Question -->
      <div class="mb-5">
        <label class="field-label">Question</label>
        <input class="styled" type="text" id="ext-question"
          placeholder="e.g. Which planet is known as the Red Planet?"/>
      </div>

      <!-- Options -->
      <div class="field-row mb-6">
        <div>
          <label class="field-label" style="color:var(--green);">
            ✓ CORRECT OPTION
          </label>
          <input class="styled correct" type="text" id="ext-correct"
            placeholder="e.g. Mars"/>
        </div>
        <div>
          <label class="field-label" style="color:var(--red);">
            ✗ DISTRACTOR OPTION
          </label>
          <input class="styled wrong" type="text" id="ext-distractor"
            placeholder="e.g. Venus"/>
        </div>
      </div>

      <div class="flex gap-3 items-center">
        <button class="btn btn-cyan" onclick="saveExternal()">SAVE QUESTION</button>
        <button class="btn btn-amber btn-sm" onclick="clearExternal()">CLEAR</button>
        <div id="ext-saving" style="display:none;" class="flex items-center gap-2">
          <div class="spinner"></div>
          <span class="font-mono text-xs hl-dim">SAVING...</span>
        </div>
      </div>
    </div>

    <!-- Recently added external questions -->
    <div class="mt-10" id="ext-recent-wrap">
      <div class="font-orb text-xs mb-4" style="color:var(--text-dim);letter-spacing:2px;">RECENT EXTERNAL QUESTIONS</div>
      <div id="ext-recent-list">
        <div class="empty-state" style="padding:24px;">
          <div class="spinner" style="width:16px;height:16px;margin:0 auto 8px;"></div>
          LOADING...
        </div>
      </div>
    </div>
  </div><!-- /panel external -->


  <!-- ═══════════════════════════════════════════════
       PANEL 3: All Questions
  ══════════════════════════════════════════════════ -->
  <div id="panel-all-questions" class="panel">
    <div class="sec-title">All Arena Questions</div>
    <div class="sec-sub">Browse, filter, and delete questions</div>

    <div style="display:grid;grid-template-columns:1fr auto;gap:12px;align-items:end;margin-bottom:20px;">
      <div>
        <label class="field-label">Filter by Story</label>
        <select class="styled" id="aq-story-filter" onchange="loadAllQuestions()">
          <option value="">All Stories</option>
          <?php foreach ($all_stories as $s): ?>
          <option value="<?= $s['story_id'] ?>"><?= htmlspecialchars($s['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="padding-bottom:1px;">
        <button class="btn btn-cyan btn-sm" onclick="loadAllQuestions()">REFRESH</button>
      </div>
    </div>

    <div id="aq-loading" class="empty-state" style="padding:24px;">
      <div class="spinner" style="width:16px;height:16px;margin:0 auto 8px;"></div>
      LOADING...
    </div>
    <div id="aq-list" style="background:var(--void-2);border:1px solid var(--border);border-radius:5px;overflow:hidden;display:none;"></div>
    <div id="aq-empty" class="empty-state" style="display:none;">NO QUESTIONS FOUND</div>
  </div><!-- /panel all questions -->

</main><!-- /main -->
</div><!-- /flex -->


<!-- ══ CHUNK QUESTION MODAL ══ -->
<div id="modal-backdrop" onclick="closeModal(event)">
  <div class="modal-box" onclick="event.stopPropagation()">
    <div class="modal-header" style="justify-content:space-between;">
      <div>
        <div class="font-orb text-xs font-bold hl-cyan" style="letter-spacing:2px;">CREATE QUESTION</div>
        <div class="font-mono text-xs hl-dim mt-1" id="modal-part-label"></div>
      </div>
      <button onclick="closeModalBtn()" class="btn btn-red btn-sm">✕ CLOSE</button>
    </div>

    <div style="padding:24px;">
      <!-- Chunk passage (read-only display) -->
      <div class="mb-5">
        <label class="field-label">Story Chunk <span class="hl-dim">(the passage players will read)</span></label>
        <div id="modal-chunk-text"
          style="background:var(--void-3);border:1px solid var(--border);border-radius:4px;
                 padding:14px;font-size:13px;line-height:1.75;color:var(--text-mid);
                 max-height:180px;overflow-y:auto;">
        </div>
        <div id="modal-chunk-meta" class="font-mono text-xs hl-dim mt-2"></div>
      </div>

      <hr class="divider"/>

      <!-- Question -->
      <div class="mb-4">
        <label class="field-label">Question about this passage</label>
        <input class="styled" type="text" id="modal-question"
          placeholder="e.g. What did Aris discover in the Revelation Chamber?"/>
      </div>

      <!-- Options -->
      <div class="field-row mb-6">
        <div>
          <label class="field-label" style="color:var(--green);">✓ CORRECT OPTION</label>
          <input class="styled correct" type="text" id="modal-correct"
            placeholder="The correct answer"/>
        </div>
        <div>
          <label class="field-label" style="color:var(--red);">✗ DISTRACTOR OPTION</label>
          <input class="styled wrong" type="text" id="modal-distractor"
            placeholder="A convincing wrong answer"/>
        </div>
      </div>

      <div class="flex gap-3 items-center">
        <button class="btn btn-green" onclick="saveChunkQuestion()" id="modal-save-btn">
          SAVE QUESTION
        </button>
        <button class="btn btn-amber btn-sm" onclick="clearModalFields()">CLEAR</button>
        <div id="modal-saving" style="display:none;" class="flex items-center gap-2">
          <div class="spinner"></div>
          <span class="font-mono text-xs hl-dim">SAVING...</span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Toast -->
<div id="toast"></div>

<script>
// ═══════════════════════════════════════════════════════════════
// GLOBALS
// ═══════════════════════════════════════════════════════════════
const API = 'arena_admin_api.php';

let chunks = [];           // current generated chunks array
let usedChunkTexts = new Set(); // chunk_text values already in DB
let currentChunk = null;   // chunk selected for modal
let filterMode = 'available';

// ═══════════════════════════════════════════════════════════════
// PANEL NAVIGATION
// ═══════════════════════════════════════════════════════════════
function showPanel(name) {
  document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  document.getElementById('panel-' + name).classList.add('active');
  const navMap = {'story-chunks':'nav-story','external':'nav-external','all-questions':'nav-all'};
  document.getElementById(navMap[name]).classList.add('active');

  if (name === 'all-questions') loadAllQuestions();
  if (name === 'external') loadRecentExternal();
}

// ═══════════════════════════════════════════════════════════════
// TOAST
// ═══════════════════════════════════════════════════════════════
let toastTimer;
function toast(msg, type='info') {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = `show ${type}`;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { t.className = ''; }, 3000);
}

// ═══════════════════════════════════════════════════════════════
// STORY / PART SELECTORS
// ═══════════════════════════════════════════════════════════════
function onStoryChange() {
  const sel = document.getElementById('sc-story-select');
  const partSel = document.getElementById('sc-part-select');
  const opt = sel.options[sel.selectedIndex];
  const parts = parseInt(opt.dataset.parts) || 1;

  partSel.innerHTML = '<option value="">— Part —</option>';
  if (!sel.value) { partSel.disabled = true; return; }

  for (let i = 1; i <= parts; i++) {
    partSel.innerHTML += `<option value="${i}">Part ${i}</option>`;
  }
  partSel.disabled = false;

  // Reset chunk display
  clearChunkDisplay();
}

function clearChunkDisplay() {
  document.getElementById('chunk-grid').innerHTML = '';
  document.getElementById('sc-stats').style.display = 'none';
  document.getElementById('sc-filters').style.display = 'none';
  document.getElementById('sc-placeholder').style.display = 'block';
  chunks = [];
  usedChunkTexts.clear();
}

// ═══════════════════════════════════════════════════════════════
// CHUNK GENERATION
// ═══════════════════════════════════════════════════════════════
async function generateChunks() {
  const storyId = document.getElementById('sc-story-select').value;
  const partNum = document.getElementById('sc-part-select').value;

  if (!storyId || !partNum) { toast('Select a story and part first.', 'error'); return; }

  document.getElementById('sc-placeholder').style.display = 'none';
  document.getElementById('sc-loading').style.display = 'block';
  document.getElementById('chunk-grid').innerHTML = '';
  document.getElementById('sc-stats').style.display = 'none';
  document.getElementById('sc-filters').style.display = 'none';

  try {
    const res = await fetch(`${API}?action=get_chunks&story_id=${storyId}&part_number=${partNum}`);
    const data = await res.json();
    document.getElementById('sc-loading').style.display = 'none';

    if (!data.success) { toast(data.error || 'Failed to load.', 'error'); return; }

    chunks = data.chunks;
    usedChunkTexts = new Set(data.used_chunk_texts);

    updateStats();
    document.getElementById('sc-stats').style.display = 'flex';
    document.getElementById('sc-filters').style.display = 'flex';
    renderChunks('available');

  } catch(e) {
    document.getElementById('sc-loading').style.display = 'none';
    toast('Network error. Try again.', 'error');
  }
}

function updateStats() {
  const used = chunks.filter(c => usedChunkTexts.has(c.text)).length;
  const unused = chunks.length - used;
  document.getElementById('stat-total').textContent  = chunks.length;
  document.getElementById('stat-used').textContent   = used;
  document.getElementById('stat-unused').textContent = unused;
}

function filterChunks(mode) {
  filterMode = mode;
  ['available','used','all'].forEach(m => {
    document.getElementById('ft-' + m).classList.toggle('active', m === mode);
  });
  renderChunks(mode);
}

function renderChunks(mode) {
  const grid = document.getElementById('chunk-grid');
  grid.innerHTML = '';

  let filtered = chunks;
  if (mode === 'available') filtered = chunks.filter(c => !usedChunkTexts.has(c.text));
  if (mode === 'used')      filtered = chunks.filter(c =>  usedChunkTexts.has(c.text));

  if (!filtered.length) {
    grid.innerHTML = `<div class="empty-state" style="grid-column:1/-1;padding:32px;">
      <div style="margin-bottom:8px;">⬡</div>
      ${mode === 'available' ? 'ALL CHUNKS HAVE QUESTIONS — GREAT COVERAGE!' : 'NO CHUNKS IN THIS CATEGORY'}
    </div>`;
    return;
  }

  filtered.forEach((chunk, idx) => {
    const isUsed = usedChunkTexts.has(chunk.text);
    const card   = document.createElement('div');
    card.className = 'chunk-card' + (isUsed ? ' used' : '');

    const words = chunk.text.trim().split(/\s+/).length;
    const sents = (chunk.text.match(/[.!?]+/g) || []).length;

    card.innerHTML = `
      <div class="chunk-meta" style="margin-bottom:8px;">
        <span class="part-badge">CHUNK ${chunk.index + 1}</span>
        ${isUsed
          ? '<span class="used-badge"><span>✓</span> HAS QUESTION</span>'
          : '<span class="font-mono text-xs" style="color:var(--amber);">CLICK TO ADD QUESTION</span>'
        }
      </div>
      <div class="chunk-text-preview">${escHtml(chunk.text)}</div>
      <div class="chunk-meta">
        <span>${words} words</span>
        <span>${sents} sentence${sents!==1?'s':''}</span>
      </div>
    `;

    if (!isUsed) {
      card.addEventListener('click', () => openModal(chunk));
    }
    grid.appendChild(card);
  });
}

// ═══════════════════════════════════════════════════════════════
// MODAL
// ═══════════════════════════════════════════════════════════════
function openModal(chunk) {
  currentChunk = chunk;
  const storyId  = document.getElementById('sc-story-select').value;
  const partNum  = document.getElementById('sc-part-select').value;
  const storyTxt = document.getElementById('sc-story-select').options[document.getElementById('sc-story-select').selectedIndex].text;

  document.getElementById('modal-part-label').textContent = `${storyTxt} — Part ${partNum} — Chunk ${chunk.index + 1}`;
  document.getElementById('modal-chunk-text').textContent  = chunk.text;

  const words = chunk.text.trim().split(/\s+/).length;
  document.getElementById('modal-chunk-meta').textContent = `${words} words · This exact text will be shown to players during the Flash Words game`;

  clearModalFields();
  document.getElementById('modal-backdrop').classList.add('open');
  setTimeout(() => document.getElementById('modal-question').focus(), 300);
}

function closeModal(e) {
  if (e.target === document.getElementById('modal-backdrop')) closeModalBtn();
}
function closeModalBtn() {
  document.getElementById('modal-backdrop').classList.remove('open');
  currentChunk = null;
}

function clearModalFields() {
  ['modal-question','modal-correct','modal-distractor'].forEach(id => {
    document.getElementById(id).value = '';
  });
}

async function saveChunkQuestion() {
  if (!currentChunk) return;

  const storyId  = document.getElementById('sc-story-select').value;
  const partNum  = document.getElementById('sc-part-select').value;
  const question = document.getElementById('modal-question').value.trim();
  const correct  = document.getElementById('modal-correct').value.trim();
  const distract = document.getElementById('modal-distractor').value.trim();

  if (!question || !correct || !distract) {
    toast('Fill in all fields before saving.', 'error'); return;
  }

  document.getElementById('modal-saving').style.display = 'flex';
  document.getElementById('modal-save-btn').disabled = true;

  try {
    const res = await fetch(API, {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({
        action:'save_chunk_question',
        story_id: storyId,
        part_number: partNum,
        chunk_text: currentChunk.text,
        question_text: question,
        correct_option: correct,
        misleading_option: distract,
      })
    });
    const data = await res.json();

    if (data.success) {
      usedChunkTexts.add(currentChunk.text);
      updateStats();
      renderChunks(filterMode);
      closeModalBtn();
      toast('Question saved successfully!', 'success');
    } else {
      toast(data.error || 'Save failed.', 'error');
    }
  } catch(e) {
    toast('Network error.', 'error');
  } finally {
    document.getElementById('modal-saving').style.display = 'none';
    document.getElementById('modal-save-btn').disabled = false;
  }
}

// ═══════════════════════════════════════════════════════════════
// EXTERNAL Q&A
// ═══════════════════════════════════════════════════════════════
// ── WPM tiers config ────────────────────────────────────────
const WPM_TIERS = [60, 100, 140, 180, 220];

function countWords(str) {
  return str.trim() ? str.trim().split(/\s+/).length : 0;
}

function getWpmGuidance(wordCount) {
  if (wordCount === 0) return { valid: false, msg: '', tier: null };

  // Check exact match
  if (WPM_TIERS.includes(wordCount)) {
    return { valid: true, msg: `✓ Perfect — ${wordCount} words matches the ${wordCount} WPM tier`, tier: wordCount };
  }

  // Find nearest tiers below and above
  const below = WPM_TIERS.filter(t => t < wordCount).at(-1);
  const above  = WPM_TIERS.find(t => t > wordCount);

  let msg = '';
  if (below && above) {
    const removeN = wordCount - below;
    const addN    = above - wordCount;
    msg = `Remove ${removeN} word${removeN>1?'s':''} to reach ${below} WPM  ·  Add ${addN} word${addN>1?'s':''} to reach ${above} WPM`;
  } else if (below) {
    const removeN = wordCount - below;
    msg = `Remove ${removeN} word${removeN>1?'s':''} to reach ${below} WPM (max tier is 220)`;
  } else {
    const addN = above - wordCount;
    msg = `Add ${addN} word${addN>1?'s':''} to reach the minimum ${above} WPM tier`;
  }

  return { valid: false, msg, tier: null };
}

function updateTierPills(activeTier) {
  document.querySelectorAll('.wpm-tier-pill').forEach(pill => {
    const t = parseInt(pill.dataset.wpm);
    if (t === activeTier) {
      pill.style.borderColor = 'var(--cyan)';
      pill.style.color = 'var(--cyan)';
      pill.style.background = 'var(--cyan-faint)';
      pill.style.boxShadow = '0 0 8px var(--cyan-glow)';
    } else {
      pill.style.borderColor = 'rgba(61,242,224,0.15)';
      pill.style.color = 'var(--text-dim)';
      pill.style.background = 'transparent';
      pill.style.boxShadow = 'none';
    }
  });
}

function onPassageInput() {
  const val = document.getElementById('ext-passage').value;
  const wc  = countWords(val);
  document.getElementById('ext-passage-count').textContent = wc + ' words';
  // Live pill highlight
  updateTierPills(WPM_TIERS.includes(wc) ? wc : null);
  // Update save button state
  const saveBtn = document.querySelector('#panel-external .btn-cyan');
  if (saveBtn) saveBtn.disabled = !WPM_TIERS.includes(wc);
}

function onPassageBlur() {
  const val = document.getElementById('ext-passage').value;
  const wc  = countWords(val);
  const feedback = document.getElementById('ext-wpm-feedback');
  const guidance = getWpmGuidance(wc);

  if (wc === 0) {
    feedback.textContent = '';
    return;
  }
  if (guidance.valid) {
    feedback.textContent = guidance.msg;
    feedback.style.color = 'var(--green)';
  } else {
    feedback.textContent = guidance.msg;
    feedback.style.color = 'var(--amber)';
  }
}

async function saveExternal() {
  const passage  = document.getElementById('ext-passage').value.trim();
  const question = document.getElementById('ext-question').value.trim();
  const correct  = document.getElementById('ext-correct').value.trim();
  const distract = document.getElementById('ext-distractor').value.trim();

  if (!passage)  { toast('Enter a knowledge passage.', 'error'); return; }
  const wc = countWords(passage);
  if (!WPM_TIERS.includes(wc)) {
    onPassageBlur(); // show guidance
    toast(`Word count (${wc}) must be exactly 60, 100, 140, 180, or 220.`, 'error');
    return;
  }
  if (!question) { toast('Enter a question.', 'error'); return; }
  if (!correct)  { toast('Enter the correct option.', 'error'); return; }
  if (!distract) { toast('Enter a distractor option.', 'error'); return; }

  document.getElementById('ext-saving').style.display = 'flex';

  try {
    const res = await fetch(API, {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({
        action:'save_external_question',
        external_passage: passage,
        question_text: question,
        correct_option: correct,
        misleading_option: distract,
      })
    });
    const data = await res.json();

    if (data.success) {
      toast('External question saved!', 'success');
      clearExternal();
      loadRecentExternal();
    } else {
      toast(data.error || 'Save failed.', 'error');
    }
  } catch(e) {
    toast('Network error.', 'error');
  } finally {
    document.getElementById('ext-saving').style.display = 'none';
  }
}

function clearExternal() {
  ['ext-passage','ext-question','ext-correct','ext-distractor']
    .forEach(id => { document.getElementById(id).value = ''; });
  document.getElementById('ext-passage-count').textContent = '0 words';
  document.getElementById('ext-passage-count').style.color = 'var(--text-dim)';
  document.getElementById('ext-wpm-feedback').textContent = '';
  updateTierPills(null);
  const saveBtn = document.querySelector('#panel-external .btn-cyan');
  if (saveBtn) saveBtn.disabled = true; // disabled until word count matches a tier
}

async function loadRecentExternal() {
  const listEl = document.getElementById('ext-recent-list');
  listEl.innerHTML = `<div class="empty-state" style="padding:20px;">
    <div class="spinner" style="width:14px;height:14px;margin:0 auto 8px;"></div>
    LOADING...
  </div>`;

  try {
    const res = await fetch(`${API}?action=get_questions&type=external&limit=8`);
    const data = await res.json();
    if (!data.success || !data.questions.length) {
      listEl.innerHTML = `<div class="empty-state" style="padding:20px;">NO EXTERNAL QUESTIONS YET</div>`;
      return;
    }
    renderQuestionList(listEl, data.questions);
  } catch(e) {
    listEl.innerHTML = `<div class="empty-state" style="padding:20px;color:var(--red);">ERROR LOADING</div>`;
  }
}

// ═══════════════════════════════════════════════════════════════
// ALL QUESTIONS
// ═══════════════════════════════════════════════════════════════
async function loadAllQuestions() {
  const storyId = document.getElementById('aq-story-filter').value;

  const listEl = document.getElementById('aq-list');
  const emptyEl = document.getElementById('aq-empty');
  const loadEl  = document.getElementById('aq-loading');

  listEl.style.display  = 'none';
  emptyEl.style.display = 'none';
  loadEl.style.display  = 'block';

  let url = `${API}?action=get_questions`;
  if (storyId) url += `&story_id=${storyId}`;

  try {
    const res  = await fetch(url);
    const data = await res.json();
    loadEl.style.display = 'none';

    if (!data.success || !data.questions.length) {
      emptyEl.style.display = 'block'; return;
    }
    listEl.style.display = 'block';
    renderQuestionList(listEl, data.questions, true);
  } catch(e) {
    loadEl.style.display = 'none';
    emptyEl.textContent  = 'ERROR LOADING QUESTIONS';
    emptyEl.style.display = 'block';
  }
}

function renderQuestionList(container, questions, showDelete = false) {
  container.innerHTML = '';
  questions.forEach((q, i) => {
    const row = document.createElement('div');
    row.className = 'q-row';
    row.id = 'qrow-' + q.question_id;

    const typeBadge = q.question_type === 'external'
      ? `<span style="padding:2px 8px;background:var(--amber-dim);color:var(--amber);border:1px solid rgba(242,167,61,0.3);border-radius:2px;font-family:'Share Tech Mono',monospace;font-size:9px;letter-spacing:1px;">EXTERNAL</span>`
      : `<span style="padding:2px 8px;background:var(--cyan-faint);color:var(--cyan-dim);border:1px solid var(--border);border-radius:2px;font-family:'Share Tech Mono',monospace;font-size:9px;letter-spacing:1px;">STORY CHUNK</span>`;

    const passagePreview = q.question_type === 'external' && q.external_passage
      ? `<div class="font-mono text-xs hl-dim mt-1" style="font-size:10px;margin-top:4px;">"${escHtml(q.external_passage.substring(0,80))}..."</div>`
      : (q.chunk_text
          ? `<div class="font-mono text-xs hl-dim" style="font-size:10px;margin-top:4px;">"${escHtml(q.chunk_text.substring(0,80))}..."</div>`
          : '');

    row.innerHTML = `
      <div class="q-num">${String(i+1).padStart(2,'0')}</div>
      <div>
        <div style="display:flex;gap:8px;align-items:center;margin-bottom:4px;flex-wrap:wrap;">
          ${typeBadge}
          ${q.story_title ? `<span class="font-mono text-xs hl-dim" style="font-size:10px;">${escHtml(q.story_title)}</span>` : ''}
          ${q.part_number > 0 ? `<span class="font-mono text-xs hl-dim" style="font-size:10px;">Part ${q.part_number}</span>` : ''}
        </div>
        <div class="font-raj" style="font-size:14px;font-weight:600;color:var(--text);margin-bottom:4px;">${escHtml(q.question_text)}</div>
        <div class="font-mono text-xs" style="font-size:10px;">
          <span style="color:var(--green);">✓ ${escHtml(q.correct_option)}</span>
          <span style="color:var(--text-dim);margin:0 8px;">·</span>
          <span style="color:var(--red);">✗ ${escHtml(q.misleading_option)}</span>
        </div>
        ${passagePreview}
      </div>
      ${showDelete
        ? `<button class="btn btn-red btn-sm" onclick="deleteQuestion(${q.question_id})">DELETE</button>`
        : ''
      }
    `;
    container.appendChild(row);
  });
}

async function deleteQuestion(id) {
  if (!confirm('Delete this question? This cannot be undone.')) return;
  try {
    const res  = await fetch(API, {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({action:'delete_question', question_id: id})
    });
    const data = await res.json();
    if (data.success) {
      const row = document.getElementById('qrow-' + id);
      if (row) {
        row.style.transition = 'opacity 0.3s, transform 0.3s';
        row.style.opacity = '0';
        row.style.transform = 'translateX(20px)';
        setTimeout(() => row.remove(), 300);
      }
      // Also update used set if chunk question deleted
      usedChunkTexts.clear(); // force refresh on next generate
      toast('Question deleted.', 'info');
    } else {
      toast(data.error || 'Delete failed.', 'error');
    }
  } catch(e) {
    toast('Network error.', 'error');
  }
}

// ═══════════════════════════════════════════════════════════════
// UTIL
// ═══════════════════════════════════════════════════════════════
function escHtml(str) {
  if (!str) return '';
  return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Enter key shortcuts in modal
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    if (document.getElementById('modal-backdrop').classList.contains('open')) {
      closeModalBtn();
    }
  }
});
</script>
</body>
</html>