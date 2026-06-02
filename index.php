<?php
// ==========================================
// SMS Gateway - sms-gate.app
// ==========================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
$TOKEN     = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzbXMtZ2F0ZS5hcHAiLCJzdWIiOiJKVEZCTlAiLCJleHAiOjE3ODA0Mjc3NzIsImlhdCI6MTc4MDQyNjg3MiwianRpIjoibmJNYTFtQXh0V0xHNi1mU2RvY0twIiwidXNlcl9pZCI6IkpURkJOUCIsInNjb3BlcyI6WyJkZXZpY2VzOmxpc3QiLCJtZXNzYWdlczpyZWFkIiwibWVzc2FnZXM6d3JpdGUiLCJtZXNzYWdlczpzZW5kIiwibWVzc2FnZXM6bGlzdCJdfQ.j2r-eBtNf-BJTquS59rMGPhjHHuN2s0tm4UorJ0svYU";
$DEVICE_ID = "U-ucDm6OQfO6FlCytxNIE";
$API_BASE  = "https://sms-gate.app/3rdparty/v1";

// ==========================================
// Helper: เรียก API
// ==========================================
function apiRequest($method, $endpoint, $body = null) {
    global $TOKEN, $API_BASE;

    $ch = curl_init($API_BASE . $endpoint);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $TOKEN",
            "Content-Type: application/json",
            "Accept: application/json"
        ],
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 30
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = curl_exec($ch);

    $result = [
        "code" => curl_getinfo($ch, CURLINFO_HTTP_CODE),
        "error" => curl_error($ch),
        "raw" => $response,
        "data" => json_decode($response, true)
    ];

    curl_close($ch);

    return $result;
}

// ==========================================
// Action: ส่ง SMS
// ==========================================
function sendSMS($phone, $message) {
    // แปลงเบอร์เป็นรูปแบบ +66xxxxxxxxx
    $phone = preg_replace('/\D/', '', $phone);

    if (substr($phone, 0, 1) === '0') {
        $phone = '+66' . substr($phone, 1);
    } elseif (substr($phone, 0, 2) === '66') {
        $phone = '+' . $phone;
    }

    return apiRequest("POST", "/message", [
        "deviceId"    => $DEVICE_ID,
        "phoneNumber" => $phone,
        "message"     => $message,
    ]);
}

// ==========================================
// Action: ดึงข้อความทั้งหมด (polling)
// ==========================================
function getMessages($since = null) {
    $query = $since ? "?since=" . urlencode($since) : "?limit=50";
    return apiRequest("GET", "/messages" . $query);
}

// ==========================================
// Handle AJAX requests
// ==========================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {
    header("Content-Type: application/json");

    if ($_POST["action"] === "send") {
        $phone   = trim($_POST["phone"] ?? "");
        $message = trim($_POST["message"] ?? "");
        if (!$phone || !$message) {
            echo json_encode(["success" => false, "error" => "กรุณาระบุเบอร์และข้อความ"]);
            exit;
        }
$res = sendSMS($phone, $message);

echo json_encode($res, JSON_PRETTY_PRINT);
exit;
    }

    if ($_POST["action"] === "fetch") {
        $since = $_POST["since"] ?? null;
        $res   = getMessages($since);
        echo json_encode([
            "success" => $res["code"] >= 200 && $res["code"] < 300,
            "data"    => $res["data"],
        ]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SMS Gateway</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
  .app { width: 480px; height: 620px; background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.10); display: flex; flex-direction: column; overflow: hidden; }
  .header { padding: 14px 18px; background: #185FA5; color: #fff; display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
  .dot { width: 9px; height: 9px; border-radius: 50%; background: #3B6D11; border: 2px solid rgba(255,255,255,0.4); flex-shrink: 0; }
  .dot.off { background: #e24b4a; }
  .header-title { font-size: 15px; font-weight: 600; }
  .header-sub { font-size: 11px; opacity: 0.75; margin-top: 1px; }
  .live-badge { margin-left: auto; font-size: 11px; background: rgba(255,255,255,0.15); padding: 3px 10px; border-radius: 20px; }
  .to-bar { padding: 10px 16px; border-bottom: 1px solid #e8e8e8; display: flex; align-items: center; gap: 8px; flex-shrink: 0; background: #f7f8fa; }
  .to-label { font-size: 13px; color: #777; }
  .to-input { flex: 1; border: 1px solid #ddd; border-radius: 8px; padding: 7px 11px; font-size: 14px; background: #fff; outline: none; }
  .to-input:focus { border-color: #185FA5; }
  .msgs { flex: 1; overflow-y: auto; padding: 14px 16px; display: flex; flex-direction: column; gap: 8px; background: #f0f2f5; }
  .empty { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: #aaa; gap: 8px; font-size: 14px; }
  .brow { display: flex; }
  .brow.out { justify-content: flex-end; }
  .brow.in { justify-content: flex-start; }
  .bwrap { display: flex; flex-direction: column; max-width: 72%; }
  .brow.out .bwrap { align-items: flex-end; }
  .from-label { font-size: 11px; color: #888; margin-bottom: 2px; }
  .bubble { padding: 9px 13px; border-radius: 16px; font-size: 14px; line-height: 1.5; word-break: break-word; }
  .brow.out .bubble { background: #185FA5; color: #fff; border-bottom-right-radius: 4px; }
  .brow.in  .bubble { background: #fff; color: #222; border: 1px solid #e0e0e0; border-bottom-left-radius: 4px; }
  .meta { font-size: 11px; color: #aaa; margin-top: 3px; }
  .compose { padding: 10px 14px; border-top: 1px solid #e8e8e8; display: flex; gap: 8px; align-items: flex-end; flex-shrink: 0; background: #fff; }
  .compose textarea { flex: 1; border: 1px solid #ddd; border-radius: 10px; padding: 9px 12px; font-size: 14px; font-family: inherit; resize: none; max-height: 96px; outline: none; line-height: 1.5; background: #f7f8fa; }
  .compose textarea:focus { border-color: #185FA5; background: #fff; }
  .send-btn { background: #185FA5; color: #fff; border: none; border-radius: 10px; padding: 9px 18px; font-size: 14px; font-weight: 600; cursor: pointer; white-space: nowrap; transition: opacity .15s; }
  .send-btn:hover { opacity: 0.85; }
  .send-btn:disabled { opacity: 0.4; cursor: not-allowed; }
  .toast { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); background: #222; color: #fff; padding: 8px 20px; border-radius: 20px; font-size: 13px; opacity: 0; transition: opacity .3s; pointer-events: none; white-space: nowrap; z-index: 999; }
  .toast.show { opacity: 1; }
  .toast.err { background: #a32d2d; }
  ::-webkit-scrollbar { width: 4px; } ::-webkit-scrollbar-thumb { background: #ddd; border-radius: 4px; }
</style>
</head>
<body>

<div class="app">
  <div class="header">
    <div class="dot off" id="dot"></div>
    <div>
      <div class="header-title">SMS Gateway</div>
      <div class="header-sub" id="statusText">กำลังเชื่อมต่อ...</div>
    </div>
    <span class="live-badge" id="liveBadge">⏳ polling</span>
  </div>

  <div class="to-bar">
    <span class="to-label">ถึง:</span>
    <input class="to-input" id="toInput" type="tel" placeholder="เบอร์ปลายทาง เช่น +66812345678" oninput="renderMsgs()">
  </div>

  <div class="msgs" id="msgs">
    <div class="empty" id="empty">
      <span style="font-size:36px">💬</span>
      <span>พิมพ์เบอร์แล้วส่งข้อความได้เลย</span>
    </div>
  </div>

  <div class="compose">
    <textarea id="msgInput" rows="1" placeholder="พิมพ์ข้อความ..." oninput="autoResize(this)" onkeydown="onKey(event)"></textarea>
    <button class="send-btn" id="sendBtn" onclick="sendSMS()">ส่ง ➤</button>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
let messages = [];
let lastSince = null;

function showToast(msg, isErr) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = 'toast show' + (isErr ? ' err' : '');
  clearTimeout(t._t);
  t._t = setTimeout(() => t.className = 'toast', 2800);
}

function fmtTime(ts) {
  if (!ts) return '';
  return new Date(ts).toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });
}

function setStatus(online, text) {
  document.getElementById('dot').className = 'dot' + (online ? '' : ' off');
  document.getElementById('statusText').textContent = text;
  document.getElementById('liveBadge').textContent = online ? '🟢 live' : '🔴 offline';
}

function renderMsgs() {
  const phone = document.getElementById('toInput').value.trim();
  const container = document.getElementById('msgs');
  const empty = document.getElementById('empty');

  const filtered = phone
    ? messages.filter(m => {
        const n = (m.phoneNumber || m.from || '').replace(/\s/g,'');
        const p = phone.replace(/\s/g,'');
        return n === p || n.endsWith(p) || p.endsWith(n);
      })
    : messages;

  if (!filtered.length) { empty.style.display = 'flex'; return; }
  empty.style.display = 'none';

  let html = '';
  filtered.forEach(m => {
    const out = m.dir === 'out';
    const tick = out ? (m.status === 'Delivered' ? ' ✓✓' : m.status === 'Sent' ? ' ✓' : ' ⏳') : '';
    html += `<div class="brow ${out ? 'out' : 'in'}">
      <div class="bwrap">
        ${!out ? `<div class="from-label">${m.phoneNumber || m.from || ''}</div>` : ''}
        <div class="bubble">${escHtml(m.message || m.text || '')}</div>
        <div class="meta">${fmtTime(m.receivedAt || m.sentAt || m.createdAt)}${tick}</div>
      </div>
    </div>`;
  });

  // แทนที่เฉพาะ bubble rows ไม่แตะ empty
  const rows = container.querySelectorAll('.brow');
  rows.forEach(r => r.remove());
  const tmp = document.createElement('div');
  tmp.innerHTML = html;
  while (tmp.firstChild) container.appendChild(tmp.firstChild);
  container.scrollTop = container.scrollHeight;
}

function escHtml(s) {
  return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

async function fetchMessages() {
  const form = new FormData();
  form.append('action', 'fetch');
  if (lastSince) form.append('since', lastSince);
  try {
    const res = await fetch('', { method: 'POST', body: form });
    const json = await res.json();
    if (!json.success) throw new Error('API error');

    setStatus(true, 'เชื่อมต่อแล้ว · Device: <?= htmlspecialchars(substr($DEVICE_ID,0,8)) ?>...');
    const arr = Array.isArray(json.data) ? json.data : (json.data?.messages || json.data?.data || []);
    arr.forEach(m => {
      const id = m.id || m.messageId || (m.phoneNumber + m.receivedAt);
      if (!messages.find(x => (x.id || x.messageId || (x.phoneNumber + x.receivedAt)) === id)) {
        m.dir = (m.direction === 'outgoing' || m.type === 'outgoing') ? 'out' : 'in';
        messages.push(m);
      }
    });
    messages.sort((a,b) => new Date(a.receivedAt||a.createdAt||0) - new Date(b.receivedAt||b.createdAt||0));
    lastSince = new Date().toISOString();
    renderMsgs();
  } catch(e) {
    setStatus(false, 'ไม่สามารถเชื่อมต่อได้');
  }
}

async function sendSMS() {
  const phone = document.getElementById('toInput').value.trim();
  const text  = document.getElementById('msgInput').value.trim();
  if (!phone) { showToast('กรุณาใส่เบอร์ปลายทาง', true); return; }
  if (!text)  { showToast('กรุณาพิมพ์ข้อความ', true); return; }

  const btn = document.getElementById('sendBtn');
  btn.disabled = true; btn.textContent = 'กำลังส่ง...';

  const form = new FormData();
  form.append('action', 'send');
  form.append('phone', phone);
  form.append('message', text);

  try {
    const res  = await fetch('', { method: 'POST', body: form });
    const json = await res.json();
    if (!json.success) throw new Error(json.data?.message || json.data?.error || 'ส่งไม่สำเร็จ');

    messages.push({ id: json.data?.id || Date.now(), phoneNumber: phone, message: text, dir: 'out', status: 'Sent', sentAt: new Date().toISOString() });
    document.getElementById('msgInput').value = '';
    document.getElementById('msgInput').style.height = 'auto';
    renderMsgs();
    showToast('ส่งสำเร็จ ✓');
  } catch(e) {
    showToast('ส่งไม่สำเร็จ: ' + e.message, true);
  } finally {
    btn.disabled = false; btn.textContent = 'ส่ง ➤';
  }
}

function autoResize(el) {
  el.style.height = 'auto';
  el.style.height = Math.min(el.scrollHeight, 96) + 'px';
}

function onKey(e) {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendSMS(); }
}

fetchMessages();
setInterval(fetchMessages, 5000);
</script>
</body>
</html>
