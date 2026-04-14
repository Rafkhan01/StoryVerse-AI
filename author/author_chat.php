<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'author') {
    header('Location: signin.php'); exit;
}

$user_id   = (int)$_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get admin user_id — look up from session cache or DB
// admin_user_id is set by admin_chat.php on first admin login to chat
$admin_id = 0;
if (isset($_SESSION['admin_user_id']) && $_SESSION['admin_user_id'] > 0) {
    $admin_id = (int)$_SESSION['admin_user_id'];
} else {
    $admin_id = (int)$pdo->query("SELECT user_id FROM users WHERE user_type='admin' LIMIT 1")->fetchColumn();
}
// If still 0, the admin has never opened admin_chat.php yet.
// Show a warning in JS — author cannot private-chat until admin logs into chat once.
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Chat — Story Pulse</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    body { font-family: 'Inter', sans-serif; }
    .gradient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }

    /* ── Split screen layout ── */
    .chat-shell {
        display: flex;
        height: calc(100vh - 64px); /* subtract navbar height */
        overflow: hidden;
    }

    /* Left panel — 50% default. Change this value to adjust initial split */
    .panel-left {
        width: 50%;           /* ← ADJUST SPLIT HERE (e.g. 40%, 60%) */
        min-width: 280px;
        max-width: calc(100% - 280px);
        display: flex;
        flex-direction: column;
        border-right: 2px solid #E5E7EB;
        background: #fff;
    }

    /* Draggable divider */
    .divider {
        width: 6px;
        cursor: col-resize;
        background: #E5E7EB;
        flex-shrink: 0;
        transition: background 0.15s;
        position: relative;
        z-index: 10;
    }
    .divider:hover, .divider.dragging { background: #667eea; }
    .divider::after {
        content: '';
        position: absolute;
        top: 50%; left: 50%;
        transform: translate(-50%, -50%);
        width: 2px; height: 32px;
        background: rgba(102,126,234,0.4);
        border-radius: 2px;
    }

    /* Right panel — remaining space */
    .panel-right {
        flex: 1;
        min-width: 280px;
        display: flex;
        flex-direction: column;
        background: #fff;
    }

    /* ── Chat panel internals ── */
    .chat-header {
        padding: 14px 18px;
        border-bottom: 1px solid #E5E7EB;
        background: linear-gradient(to right, #f5f3ff, #fff);
        flex-shrink: 0;
    }
    .chat-header-title {
        font-size: 15px; font-weight: 700; color: #1f2937;
        display: flex; align-items: center; gap: 8px;
    }
    .chat-header-sub { font-size: 11px; color: #9CA3AF; margin-top: 2px; }

    .online-dot {
        width: 8px; height: 8px; border-radius: 50%;
        background: #10B981;
        display: inline-block;
        box-shadow: 0 0 0 2px rgba(16,185,129,0.2);
    }

    .admin-badge {
        font-size: 9px; font-weight: 700; letter-spacing: 0.06em;
        padding: 2px 6px; border-radius: 4px;
        background: rgba(99,102,241,0.12);
        border: 1px solid rgba(99,102,241,0.3);
        color: #4F46E5;
    }

    /* ── Message list ── */
    .msg-list {
        flex: 1;
        overflow-y: auto;
        padding: 16px 14px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        background: #F9FAFB;
    }
    .msg-list::-webkit-scrollbar { width: 4px; }
    .msg-list::-webkit-scrollbar-thumb { background: #D1D5DB; border-radius: 99px; }

    /* ── Bubble ── */
    .msg-row { display: flex; flex-direction: column; max-width: 78%; }
    .msg-row.mine { align-self: flex-end; align-items: flex-end; }
    .msg-row.theirs { align-self: flex-start; align-items: flex-start; }

    .msg-sender {
        font-size: 10px; font-weight: 600; color: #6B7280;
        margin-bottom: 3px;
        display: flex; align-items: center; gap-5px;
    }

    .msg-bubble {
        padding: 9px 14px;
        border-radius: 16px;
        font-size: 13.5px;
        line-height: 1.5;
        word-break: break-word;
        position: relative;
    }
    .mine .msg-bubble {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: #fff;
        border-bottom-right-radius: 4px;
    }
    .theirs .msg-bubble {
        background: #fff;
        color: #1f2937;
        border: 1px solid #E5E7EB;
        border-bottom-left-radius: 4px;
    }

    .msg-deleted .msg-bubble {
        background: #F3F4F6 !important;
        color: #9CA3AF !important;
        font-style: italic;
        border: 1px solid #E5E7EB !important;
    }

    .msg-meta {
        font-size: 10px; color: #9CA3AF;
        margin-top: 3px;
        display: flex; align-items: center; gap: 6px;
    }
    .mine .msg-meta { flex-direction: row-reverse; }
    .edited-tag { font-style: italic; }

    /* Hover actions */
    .msg-actions {
        display: none;
        gap: 4px;
        align-items: center;
    }
    .msg-row:hover .msg-actions { display: flex; }
    .msg-act-btn {
        padding: 3px 7px; border-radius: 5px; border: none;
        font-size: 10px; font-weight: 600; cursor: pointer;
        transition: all 0.15s;
    }
    .msg-act-btn.edit-btn  { background: #EEF2FF; color: #4F46E5; }
    .msg-act-btn.del-btn   { background: #FEF2F2; color: #EF4444; }
    .msg-act-btn:hover { opacity: 0.8; }

    /* ── Input bar ── */
    .msg-input-bar {
        padding: 12px 14px;
        border-top: 1px solid #E5E7EB;
        background: #fff;
        display: flex;
        align-items: flex-end;
        gap: 10px;
        flex-shrink: 0;
    }
    .msg-textarea {
        flex: 1;
        resize: none;
        border: 1px solid #D1D5DB;
        border-radius: 20px;
        padding: 9px 16px;
        font-family: 'Inter', sans-serif;
        font-size: 13.5px;
        color: #1f2937;
        outline: none;
        max-height: 120px;
        overflow-y: auto;
        line-height: 1.5;
        transition: border-color 0.15s;
    }
    .msg-textarea:focus { border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }
    .send-btn {
        width: 38px; height: 38px; border-radius: 50%;
        background: linear-gradient(135deg, #667eea, #764ba2);
        border: none; color: #fff; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
        transition: opacity 0.15s, transform 0.15s;
    }
    .send-btn:hover { opacity: 0.88; transform: scale(1.06); }
    .send-btn:disabled { opacity: 0.4; cursor: not-allowed; transform: none; }

    /* Edit mode banner */
    .edit-banner {
        display: none;
        background: #EEF2FF;
        border-top: 1px solid #C7D2FE;
        padding: 6px 14px;
        font-size: 11px; color: #4F46E5; font-weight: 600;
        align-items: center; justify-content: space-between;
        flex-shrink: 0;
    }
    .edit-banner.active { display: flex; }
    .edit-cancel { background: none; border: none; color: #9CA3AF; cursor: pointer; font-size: 13px; }

    /* Group header accent */
    .group-header { background: linear-gradient(to right, #fdf4ff, #fff); }

    /* Toast */
    .toast-wrap {
        position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%);
        z-index: 9999; display: flex; flex-direction: column; gap: 8px;
        pointer-events: none; align-items: center;
    }
    .toast {
        background: #1f2937; color: #fff;
        padding: 9px 20px; border-radius: 99px;
        font-size: 12.5px; font-weight: 600;
        animation: toast-in 0.25s ease;
        border-left: 3px solid #667eea;
        pointer-events: all;
    }
    .toast.err { border-left-color: #EF4444; }
    .toast.fade-out { animation: toast-out 0.22s ease forwards; }
    @keyframes toast-in  { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:none; } }
    @keyframes toast-out { to   { opacity:0; transform:translateY(8px); } }
</style>
</head>
<body class="bg-gray-50">

<!-- ── Navbar ── -->
<nav class="bg-white shadow-lg sticky top-0 z-50" style="height:64px">
    <div class="max-w-full px-4 sm:px-6 lg:px-8 h-full">
        <div class="flex justify-between h-full">
            <div class="flex items-center">
                <div class="flex-shrink-0 flex items-center">
                    <i class="fas fa-book-open text-3xl text-purple-600"></i>
                    <span class="ml-2 text-2xl font-bold gradient-bg bg-clip-text text-transparent">Story Pulse</span>
                </div>
                <div class="hidden md:ml-10 md:flex md:space-x-6">
                    <a href="index.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                        <i class="fas fa-home mr-2"></i> Home
                    </a>
                    <a href="add_story.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                        <i class="fas fa-plus-circle mr-2"></i> Add Story
                    </a>
                    <a href="manage_story.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                        <i class="fas fa-tasks mr-2"></i> Manage Stories
                    </a>
                    <a href="bonus_supply.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                        <i class="fas fa-clipboard-check mr-2"></i> Accuracy Review
                    </a>
                    <a href="dashboard.php" class="border-purple-500 text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                        <i class="fas fa-chart-line mr-2"></i> Dashboard
                    </a>
                    <a href="author_chat.php" class="border-purple-500 text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                        <i class="fas fa-comments mr-2"></i> Chat
                    </a>
                </div>
            </div>
            <div class="flex items-center">
                <a href="logout.php" class="ml-4 px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700">
                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- ── Split-screen chat shell ── -->
<div class="chat-shell" id="chatShell">

    <!-- ═══ LEFT PANEL — Admin Private Chat ═══ -->
    <div class="panel-left" id="panelLeft">

        <!-- Header -->
        <div class="chat-header">
            <div class="chat-header-title">
                <span class="online-dot" id="adminOnlineDot"></span>
                <i class="fas fa-user-shield text-purple-500"></i>
                Admin
            </div>
            <div class="chat-header-sub">Private conversation with the site admin</div>
        </div>

        <!-- Messages -->
        <div class="msg-list" id="privateMessages">
            <!-- messages load via JS -->
        </div>

        <!-- Edit banner -->
        <div class="edit-banner" id="privateEditBanner">
            <span><i class="fas fa-pen mr-1"></i> Editing message</span>
            <button class="edit-cancel" onclick="cancelEdit('private')"><i class="fas fa-times"></i></button>
        </div>

        <!-- Input -->
        <div class="msg-input-bar">
            <textarea class="msg-textarea" id="privateInput" rows="1"
                      placeholder="Message Admin..."
                      onkeydown="handleKey(event,'private')"
                      oninput="autoResize(this)"></textarea>
            <button class="send-btn" id="privateSendBtn" onclick="sendMessage('private')">
                <i class="fas fa-paper-plane" style="font-size:13px"></i>
            </button>
        </div>
    </div>

    <!-- ═══ DIVIDER ═══ -->
    <div class="divider" id="divider" title="Drag to resize"></div>

    <!-- ═══ RIGHT PANEL — Group Chat ═══ -->
    <div class="panel-right" id="panelRight">

        <!-- Header -->
        <div class="chat-header group-header">
            <div class="chat-header-title">
                <i class="fas fa-users text-purple-500"></i>
                Quill &amp; Compass
            </div>
            <div class="chat-header-sub">Author community &amp; admin — all members</div>
        </div>

        <!-- Messages -->
        <div class="msg-list" id="groupMessages">
            <!-- messages load via JS -->
        </div>

        <!-- Edit banner -->
        <div class="edit-banner" id="groupEditBanner">
            <span><i class="fas fa-pen mr-1"></i> Editing message</span>
            <button class="edit-cancel" onclick="cancelEdit('group')"><i class="fas fa-times"></i></button>
        </div>

        <!-- Input -->
        <div class="msg-input-bar">
            <textarea class="msg-textarea" id="groupInput" rows="1"
                      placeholder="Message Quill &amp; Compass..."
                      onkeydown="handleKey(event,'group')"
                      oninput="autoResize(this)"></textarea>
            <button class="send-btn" id="groupSendBtn" onclick="sendMessage('group')">
                <i class="fas fa-paper-plane" style="font-size:13px"></i>
            </button>
        </div>
    </div>

</div><!-- /chat-shell -->

<div class="toast-wrap" id="toastWrap"></div>

<script>
const MY_ID      = <?= $user_id ?>;
const ADMIN_ID   = <?= $admin_id ?>;
if (!ADMIN_ID) {
    // Admin hasn't set up chat yet — disable private panel
    document.getElementById('privateMessages').innerHTML =
        '<div class="text-center text-gray-400 text-xs py-8 italic">Admin has not initialised chat yet.<br>Ask admin to open the admin chat page first.</div>';
    document.getElementById('privateInput').disabled = true;
    document.getElementById('privateSendBtn').disabled = true;
}
const AJAX_URL   = '../chat_ajax.php';
const POLL_MS    = 3000; // 3 second poll interval

let privateLastId = 0;
let groupLastId   = 0;
let privateEditId = null;
let groupEditId   = null;

// ── Toast ─────────────────────────────────────────────────────────
function showToast(msg, isErr = false) {
    const wrap = document.getElementById('toastWrap');
    const t    = document.createElement('div');
    t.className = 'toast' + (isErr ? ' err' : '');
    t.textContent = msg;
    wrap.appendChild(t);
    setTimeout(() => { t.classList.add('fade-out'); setTimeout(() => t.remove(), 220); }, 2800);
}

// ── Auto-resize textarea ──────────────────────────────────────────
function autoResize(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 120) + 'px';
}

// ── Send on Enter (Shift+Enter = newline) ─────────────────────────
function handleKey(e, panel) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage(panel);
    }
}

// ── Scroll to bottom ──────────────────────────────────────────────
function scrollBottom(el) {
    el.scrollTop = el.scrollHeight;
}

// ── Format timestamp ──────────────────────────────────────────────
function fmtTime(ts) {
    const d = new Date(ts.replace(' ', 'T'));
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

// ── Can modify (3 min window) ─────────────────────────────────────
function canModify(createdAt) {
    return (Date.now() - new Date(createdAt.replace(' ','T')).getTime()) <= 180000;
}

// ── Build a message bubble HTML ───────────────────────────────────
function buildBubble(msg, panel) {
    const isMine    = parseInt(msg.sender_id) === MY_ID;
    const isDeleted = parseInt(msg.is_deleted) === 1;
    const isAdmin   = msg.user_type === 'admin';
    const within3   = canModify(msg.created_at);
    const showActs  = isMine && !isDeleted && within3;

    const name = msg.first_name + (msg.last_name ? ' ' + msg.last_name : '');
    const adminBadge = isAdmin ? `<span class="admin-badge ml-1">ADMIN</span>` : '';

    const editedTag = (parseInt(msg.is_edited) === 1 && !isDeleted)
        ? `<span class="edited-tag">edited</span>` : '';

    const actBtns = showActs
        ? `<span class="msg-actions">
               <button class="msg-act-btn edit-btn" onclick="startEdit('${panel}', ${msg.message_id}, this)">
                   <i class="fas fa-pen"></i>
               </button>
               <button class="msg-act-btn del-btn" onclick="deleteMsg('${panel}', ${msg.message_id})">
                   <i class="fas fa-trash"></i>
               </button>
           </span>`
        : '';

    const bubbleText = isDeleted
        ? '<i class="fas fa-ban mr-1" style="font-size:11px"></i> Message deleted'
        : escHtml(msg.message_text);

    return `
    <div class="msg-row ${isMine ? 'mine' : 'theirs'} ${isDeleted ? 'msg-deleted' : ''}"
         id="msg-${panel}-${msg.message_id}"
         data-created="${msg.created_at}">
        ${!isMine ? `<div class="msg-sender">${escHtml(name)} ${adminBadge}</div>` : ''}
        <div class="msg-bubble">${bubbleText}</div>
        <div class="msg-meta">
            <span>${fmtTime(msg.created_at)}</span>
            ${editedTag}
            ${actBtns}
        </div>
    </div>`;
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/\n/g,'<br>');
}

// ── Render message list ───────────────────────────────────────────
function renderMessages(panel, messages, append = false) {
    const container = document.getElementById(panel === 'private' ? 'privateMessages' : 'groupMessages');
    const wasAtBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 40;

    if (!append) container.innerHTML = '';

    if (!messages.length && !append) {
        container.innerHTML = '<div class="text-center text-gray-400 text-xs py-8 italic">No messages yet — say hello!</div>';
        return;
    }

    messages.forEach(msg => {
        const existing = document.getElementById(`msg-${panel}-${msg.message_id}`);
        if (existing) {
            // Update if edited/deleted
            existing.outerHTML = buildBubble(msg, panel);
        } else {
            container.insertAdjacentHTML('beforeend', buildBubble(msg, panel));
        }
        const lastId = parseInt(msg.message_id);
        if (panel === 'private') { if (lastId > privateLastId) privateLastId = lastId; }
        else                     { if (lastId > groupLastId)   groupLastId   = lastId; }
    });

    if (!append || wasAtBottom) scrollBottom(container);
}

// ── Load initial messages ─────────────────────────────────────────
async function loadInitial(panel) {
    const action = panel === 'private' ? 'load_private' : 'load_group';
    const extra  = panel === 'private' ? `&other_id=${ADMIN_ID}` : '';
    try {
        const r = await fetch(`${AJAX_URL}?action=${action}${extra}`);
        const d = await r.json();
        if (d.success) renderMessages(panel, d.messages);
        else showToast(d.msg || 'Failed to load', true);
    } catch { showToast('Network error', true); }
}

// ── Poll for new messages ─────────────────────────────────────────
async function poll(panel) {
    const action   = panel === 'private' ? 'poll_private' : 'poll_group';
    const since_id = panel === 'private' ? privateLastId : groupLastId;
    try {
        const extra2 = panel === 'private' ? `&other_id=${ADMIN_ID}` : '';
        const r = await fetch(`${AJAX_URL}?action=${action}&since_id=${since_id}${extra2}`);
        const d = await r.json();
        if (d.success && d.messages.length) renderMessages(panel, d.messages, true);
    } catch { /* silent poll failure */ }
}

// ── Send message ──────────────────────────────────────────────────
async function sendMessage(panel) {
    const inputEl  = document.getElementById(panel === 'private' ? 'privateInput' : 'groupInput');
    const sendBtn  = document.getElementById(panel === 'private' ? 'privateSendBtn' : 'groupSendBtn');
    const text     = inputEl.value.trim();
    if (!text) return;

    // Editing mode
    const editId = panel === 'private' ? privateEditId : groupEditId;
    if (editId) {
        await saveEdit(panel, editId, text);
        return;
    }

    sendBtn.disabled = true;
    const fd = new FormData();
    fd.append('action', panel === 'private' ? 'send_private' : 'send_group');
    fd.append('message_text', text);
    if (panel === 'private') fd.append('other_id', ADMIN_ID);

    try {
        const r = await fetch(AJAX_URL, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.success) {
            inputEl.value = '';
            autoResize(inputEl);
            renderMessages(panel, [d.message], true);
        } else {
            showToast(d.msg || 'Send failed', true);
        }
    } catch { showToast('Network error', true); }
    sendBtn.disabled = false;
}

// ── Edit flow ─────────────────────────────────────────────────────
function startEdit(panel, msgId, btn) {
    const msgEl  = document.getElementById(`msg-${panel}-${msgId}`);
    const bubble = msgEl?.querySelector('.msg-bubble');
    if (!bubble) return;

    const currentText = bubble.innerText.trim();
    const inputEl     = document.getElementById(panel === 'private' ? 'privateInput' : 'groupInput');
    const banner      = document.getElementById(panel === 'private' ? 'privateEditBanner' : 'groupEditBanner');

    inputEl.value = currentText;
    autoResize(inputEl);
    inputEl.focus();
    banner.classList.add('active');

    if (panel === 'private') privateEditId = msgId;
    else                     groupEditId   = msgId;
}

function cancelEdit(panel) {
    const inputEl = document.getElementById(panel === 'private' ? 'privateInput' : 'groupInput');
    const banner  = document.getElementById(panel === 'private' ? 'privateEditBanner' : 'groupEditBanner');
    inputEl.value = '';
    autoResize(inputEl);
    banner.classList.remove('active');
    if (panel === 'private') privateEditId = null;
    else                     groupEditId   = null;
}

async function saveEdit(panel, msgId, text) {
    const fd = new FormData();
    fd.append('action', panel === 'private' ? 'edit_private' : 'edit_group');
    fd.append('message_id', msgId);
    fd.append('message_text', text);
    if (panel === 'private') fd.append('other_id', ADMIN_ID);

    try {
        const r = await fetch(AJAX_URL, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.success) {
            cancelEdit(panel);
            // Reload to get updated message
            await loadInitial(panel);
            showToast('Message updated');
        } else {
            showToast(d.msg || 'Edit failed', true);
        }
    } catch { showToast('Network error', true); }
}

// ── Delete ────────────────────────────────────────────────────────
async function deleteMsg(panel, msgId) {
    if (!confirm('Delete this message?')) return;
    const fd = new FormData();
    fd.append('action', panel === 'private' ? 'delete_private' : 'delete_group');
    fd.append('message_id', msgId);
    if (panel === 'private') fd.append('other_id', ADMIN_ID);

    try {
        const r = await fetch(AJAX_URL, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.success) {
            await loadInitial(panel);
            showToast('Message deleted');
        } else {
            showToast(d.msg || 'Delete failed', true);
        }
    } catch { showToast('Network error', true); }
}

// ── Draggable divider ─────────────────────────────────────────────
(function() {
    const divider   = document.getElementById('divider');
    const shell     = document.getElementById('chatShell');
    const panelLeft = document.getElementById('panelLeft');
    let dragging    = false;

    divider.addEventListener('mousedown', (e) => {
        dragging = true;
        divider.classList.add('dragging');
        document.body.style.cursor     = 'col-resize';
        document.body.style.userSelect = 'none';
    });

    document.addEventListener('mousemove', (e) => {
        if (!dragging) return;
        const rect    = shell.getBoundingClientRect();
        let   newPct  = ((e.clientX - rect.left) / rect.width) * 100;
        newPct = Math.max(20, Math.min(80, newPct)); // clamp 20%–80%
        panelLeft.style.width = newPct + '%';
    });

    document.addEventListener('mouseup', () => {
        if (!dragging) return;
        dragging = false;
        divider.classList.remove('dragging');
        document.body.style.cursor     = '';
        document.body.style.userSelect = '';
    });
})();

// ── Boot ──────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
    await loadInitial('private');
    await loadInitial('group');

    // Start polling both panels
    setInterval(() => poll('private'), POLL_MS);
    setInterval(() => poll('group'),   POLL_MS);
});
</script>
</body>
</html>