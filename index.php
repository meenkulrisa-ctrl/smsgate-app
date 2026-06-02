<?php
// ============================================================
// SMS Gateway Cloud — sms-gate.app
// ไม่ต้องแก้ไขอะไร ใช้งานได้ทันที
// ============================================================

$USERNAME  = "JTFBNP";
$PASSWORD  = "cle1dbdoccuv0i";
$DEVICE_ID = "U-ucDm6OQfO6FlCytxNIE";
$API_BASE  = "https://api.sms-gate.app/3rdparty/v1";

// ไฟล์เก็บข้อความขาเข้า (webhook จะเขียนที่นี่)
$INBOX_FILE  = __DIR__ . "/sms_inbox.json";
// ไฟล์เก็บข้อความขาออก
$OUTBOX_FILE = __DIR__ . "/sms_outbox.json";

// ============================================================
// Helper: Basic Auth header
// ============================================================
function authHeader() {
    global $USERNAME, $PASSWORD;
    return "Authorization: Basic " . base64_encode("$USERNAME:$PASSWORD");
}

// ============================================================
// Helper: เรียก API
// ============================================================
function apiRequest($method, $endpoint, $body = null) {
    global $API_BASE;
    $ch = curl_init($API_BASE . $endpoint);
    $headers = [ authHeader(), "Content-Type: application/json", "Accept: application/json" ];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_TIMEOUT        => 15,
    ]);
    if ($body) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return ["code" => $code, "data" => json_decode($res, true), "error" => $err];
}

// ============================================================
// Helper: JSON file read/write (thread-safe)
// ============================================================
function readJson($file) {
    if (!file_exists($file)) return [];
    $fp = fopen($file, 'r');
    flock($fp, LOCK_SH);
    $data = json_decode(file_get_contents($file), true) ?? [];
    flock($fp, LOCK_UN);
    fclose($fp);
    return $data;
}

function writeJson($file, $data) {
    $fp = fopen($file, 'c');
    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    flock($fp, LOCK_UN);
    fclose($fp);
}

// ============================================================
// Register webhook (เรียกครั้งแรก)
// ============================================================
function registerWebhook($selfUrl) {
    $res = apiRequest("POST", "/webhooks", [
        "url"    => $selfUrl . "?webhook=1",
        "events" => ["sms:received"],
    ]);
    return $res;
}

// ============================================================
// WEBHOOK RECEIVER — รับข้อความขาเข้าจาก sms-gate.app
// ============================================================
if (isset($_GET["webhook"])) {
    $raw  = file_get_contents("php://input");
    $data = json_decode($raw, true);

    if ($data && isset($data["event"]) && $data["event"] === "sms:received") {
        $payload = $data["payload"] ?? $data;
        $msg = [
            "id"         => $payload["id"] ?? uniqid(),
            "from"       => $payload["phoneNumber"] ?? $payload["sender"] ?? "",
            "message"    => $payload["message"] ?? $payload["text"] ?? "",
            "receivedAt" => $payload["receivedAt"] ?? date("c"),
            "dir"        => "in",
        ];
        $inbox = readJson($INBOX_FILE);
        // ป้องกัน duplicate
        $ids = array_column($inbox, "id");
        if (!in_array($msg["id"], $ids)) {
            $inbox[] = $msg;
            // เก็บแค่ 500 ข้อความล่าสุด
            if (count($inbox) > 500) $inbox = array_slice($inbox, -500);
            writeJson($INBOX_FILE, $inbox);
        }
    }
    http_response_code(200);
    echo "ok";
    exit;
}

// ============================================================
// AJAX ACTIONS
// ============================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {
    header("Content-Type: application/json; charset=utf-8");

    // --- ส่ง SMS ---
    if ($_POST["action"] === "send") {
        $phone   = trim($_POST["phone"] ?? "");
        $message = trim($_POST["message"] ?? "");
        if (!$phone || !$message) {
            echo json_encode(["success" => false, "error" => "กรุณาระบุเบอร์และข้อความ"]);
            exit;
        }
        $res = apiRequest("POST", "/messages", [
            "deviceId"    => $GLOBALS["DEVICE_ID"],
            "phoneNumbers" => [$phone],
            "textMessage" => ["text" => $message],
        ]);
        $ok = $res["code"] >= 200 && $res["code"] < 300;
        if ($ok) {
            $outbox = readJson($OUTBOX_FILE);
            $outbox[] = [
                "id"      => $res["data"]["id"] ?? uniqid(),
                "to"      => $phone,
                "message" => $message,
                "sentAt"  => date("c"),
                "status"  => "Sent",
                "dir"     => "out",
            ];
            if (count($outbox) > 500) $outbox = array_slice($outbox, -500);
            writeJson($OUTBOX_FILE, $outbox);
        }
        echo json_encode(["success" => $ok, "data" => $res["data"], "httpCode" => $res["code"], "curlError" => $res["error"]]);
        exit;
    }

    // --- ดึงข้อความทั้งหมด ---
    if ($_POST["action"] === "fetch") {
        $phone  = trim($_POST["phone"] ?? "");
        $inbox  = readJson($INBOX_FILE);
        $outbox = readJson($OUTBOX_FILE);
        $all    = array_merge($inbox, $outbox);
        usort($all, fn($a,$b) => strcmp(
            $a["receivedAt"] ?? $a["sentAt"] ?? "",
            $b["receivedAt"] ?? $b["sentAt"] ?? ""
        ));
        // ถ้าระบุเบอร์ ให้กรอง
        if ($phone) {
            $phone = preg_replace('/\s+/', '', $phone);
            $all = array_values(array_filter($all, function($m) use ($phone) {
                $n = preg_replace('/\s+/', '', $m["from"] ?? $m["to"] ?? "");
                return $n === $phone || str_ends_with($n, $phone) || str_ends_with($phone, $n);
            }));
        }
        echo json_encode(["success" => true, "messages" => array_values($all)]);
        exit;
    }

    // --- ลงทะเบียน webhook ---
    if ($_POST["action"] === "register_webhook") {
        $selfUrl = (isset($_SERVER["HTTPS"]) ? "https" : "http") . "://" . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"];
        $res = registerWebhook($selfUrl);
        echo json_encode(["success" => $res["code"] >= 200 && $res["code"] < 300, "data" => $res["data"], "code" => $res["code"]]);
        exit;
    }

    // --- ดึงรายการ webhook ---
    if ($_POST["action"] === "list_webhooks") {
        $res = apiRequest("GET", "/webhooks");
        echo json_encode(["success" => $res["code"] === 200, "data" => $res["data"]]);
        exit;
    }

    echo json_encode(["success" => false, "error" => "unknown action"]);
    exit;
}

// ============================================================
// หน้า HTML
// ============================================================
$selfUrl = (isset($_SERVER["HTTPS"]) ? "https" : "http") . "://" . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"];
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SMS Gateway Cloud</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #eef0f3; min-height: 100vh; display: flex; flex-direction: column; align-items: center; padding: 24px 12px; gap: 16px; }
h1 { font-size: 18px; font-weight: 700; color: #185FA5; letter-spacing: .5px; }

/* Setup banner */
.setup-box { width: 100%; max-width: 520px; background: #fff8e1; border: 1.5px solid #f5c518; border-radius: 12px; padding: 14px 18px; font-size: 13px; color: #6b5000; }
.setup-box b { color: #b8860b; }
.setup-box code { background: #f5f0d0; padding: 1px 6px; border-radius: 4px; font-size: 12px; word-break: break-all; }
.reg-btn { margin-top: 10px; background: #185FA5; color: #fff; border: none; border-radius: 8px; padding: 8px 18px; font-size: 13px; font-weight: 600; cursor: pointer; }
.reg-btn:disabled { opacity: .5; cursor: not-allowed; }

/* Chat window */
.app { width: 100%; max-width: 520px; background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,.09); display: flex; flex-direction: column; overflow: hidden; height: 560px; }
.hdr { padding: 13px 18px; background: #185FA5; color: #fff; display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
.dot { width: 9px; height: 9px; border-radius: 50%; background: #69e06a; border: 2px solid rgba(255,255,255,.35); flex-shrink: 0; }
.dot.off { background: #e24b4a; }
.hdr-title { font-size: 15px; font-weight: 600; }
.hdr-sub { font-size: 11px; opacity: .75; margin-top: 1px; }
.badge { margin-left: auto; font-size: 11px; background: rgba(255,255,255,.15); padding: 3px 10px; border-radius: 20px; }
.tobar { padding: 9px 16px; border-bottom: 1px solid #ebebeb; display: flex; align-items: center; gap: 8px; background: #f7f8fa; flex-shrink: 0; }
.tolabel { font-size: 13px; color: #888; }
.toinput { flex: 1; border: 1px solid #ddd; border-radius: 8px; padding: 7px 11px; font-size: 14px; background: #fff; outline: none; }
.toinput:focus { border-color: #185FA5; }
.msgs { flex: 1; overflow-y: auto; padding: 14px 16px; display: flex; flex-direction: column; gap: 8px; background: #f0f2f5; }
.empty { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: #bbb; gap: 8px; font-size: 14px; }
.brow { display: flex; }
.brow.out { justify-content: flex-end; }
.brow.in  { justify-content: flex-start; }
.bwrap { display: flex; flex-direction: column; max-width: 74%; }
.brow.out .bwrap { align-items: flex-end; }
.from-lbl { font-size: 11px; color: #888; margin-bottom: 2px; }
.bubble { padding: 9px 13px; border-radius: 16px; font-size: 14px; line-height: 1.55; word-break: break-word; }
.brow.out .bubble { background: #185FA5; color: #fff; border-bottom-right-radius: 4px; }
.brow.in  .bubble { background: #fff; color: #222; border: 1px solid #e0e0e0; border-bottom-left-radius: 4px; }
.meta { font-size: 11px; color: #aaa; margin-top: 3px; }
.compose { padding: 10px 14px; border-top: 1px solid #ebebeb; display: flex; gap: 8px; align-items: flex-end; flex-shrink: 0; background: #fff; }
.compose textarea { flex: 1; border: 1px solid #ddd; border-radius: 10px; padding: 9px 12px; font-size: 14px; font-family: inherit; resize: none; max-height: 100px; outline: none; line-height: 1.5; background: #f7f8fa; }
.compose textarea:focus { border-color: #185FA5; background: #fff; }
.send-btn { background: #185FA5; color: #fff; border: none; border-radius: 10px; padding: 9px 18px; font-size: 14px; font-weight: 600; cursor: pointer; white-space: nowrap; transition: opacity .15s; flex-shrink: 0; }
.send-btn:hover { opacity: .85; }
.send-btn:disabled { opacity: .4; cursor: not-allowed; }
.toast { position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%); background: #222; color: #fff; padding: 8px 22px; border-radius: 20px; font-size: 13px; opacity: 0; transition: opacity .3s; pointer-events: none; white-space: nowrap; z-index: 999; }
.toast.show { opacity: 1; }
.toast.err { background: #a32d2d; }
::-webkit-scrollbar { width: 4px; } ::-webkit-scrollbar-thumb { background: #ddd; border-radius: 4px; }
</style>
</head>
<body>

<h1>💬 SMS Gateway Cloud</h1>

<!-- Setup: ลงทะเบียน webhook -->
<div class="setup-box" id="setupBox">
  <b>⚙️ ขั้นตอนแรก:</b> ลงทะเบียน Webhook เพื่อรับข้อความตอบกลับ<br>
  URL ที่จะลงทะเบียน: <code id="webhookUrl"><?= htmlspecialchars($selfUrl) ?>?webhook=1</code><br><br>
  <button class="reg-btn" onclick="registerWebhook()">🔗 ลงทะเบียน Webhook</button>
  <span id="regStatus" style="margin-left:10px;font-size:12px;"></span>
</div>

<div class="app">
  <div class="hdr">
    <div class="dot off" id="dot"></div>
    <div>
      <div class="hdr-title">SMS Cloud Chat</div>
      <div class="hdr-sub" id="statusText">กำลังเชื่อมต่อ...</div>
    </div>
    <span class="badge" id="liveBadge">⏳</span>
  </div>

  <div class="tobar">
    <span class="tolabel">ถึง:</span>
    <input class="toinput" id="toInput" type="tel" placeholder="เบอร์ เช่น +66812345678" oninput="renderMsgs()">
  </div>

  <div class="msgs" id="msgs">
    <div class="empty" id="emptyState">
      <span style="font-size:36px">💬</span>
      <span>พิมพ์เบอร์แล้วส่งข้อความได้เลย</span>
      <span style="font-size:12px;color:#ccc">ข้อความตอบกลับจะขึ้น realtime อัตโนมัติ</span>
    </div>
  </div>

  <div class="compose">
    <textarea id="msgInput" rows="1" placeholder="พิมพ์ข้อความ..." oninput="autoResize(this)" onkeydown="onEnter(event)"></textarea>
    <button class="send-btn" id="sendBtn" onclick="sendSMS()">ส่ง ➤</button>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
let allMessages = [];
let pollTimer = null;

// ============================================================
// Toast
// ============================================================
function showToast(msg, isErr) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = 'toast show' + (isErr ? ' err' : '');
  clearTimeout(t._t);
  t._t = setTimeout(() => t.className = 'toast', 3000);
}

// ============================================================
// Status indicator
// ============================================================
function setStatus(online, text) {
  document.getElementById('dot').className = 'dot' + (online ? '' : ' off');
  document.getElementById('statusText').textContent = text;
  document.getElementById('liveBadge').textContent = online ? '🟢 live' : '🔴 offline';
}

// ============================================================
// Format time
// ============================================================
function fmtTime(ts) {
  if (!ts) return '';
  try { return new Date(ts).toLocaleString('th-TH', { day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit' }); }
  catch(e) { return ts; }
}

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
}

// ============================================================
// Render messages
// ============================================================
function renderMsgs() {
  const phone = document.getElementById('toInput').value.trim().replace(/\s/g,'');
  const container = document.getElementById('msgs');
  const empty = document.getElementById('emptyState');

  let filtered = phone
    ? allMessages.filter(m => {
        const n = (m.from || m.to || '').replace(/\s/g,'');
        return n === phone || n.endsWith(phone) || phone.endsWith(n);
      })
    : allMessages;

  // ลบ bubble เก่าออก
  Array.from(container.querySelectorAll('.brow')).forEach(el => el.remove());

  if (!filtered.length) { empty.style.display = 'flex'; return; }
  empty.style.display = 'none';

  filtered.forEach(m => {
    const out  = m.dir === 'out';
    const name = out ? ('→ ' + (m.to || '')) : (m.from || 'ไม่ทราบเบอร์');
    const ts   = m.sentAt || m.receivedAt || '';
    const tick = out ? (m.status === 'Delivered' ? ' ✓✓' : ' ✓') : '';
    const row  = document.createElement('div');
    row.className = 'brow ' + (out ? 'out' : 'in');
    row.innerHTML = `<div class="bwrap">
      <div class="from-lbl">${escHtml(name)}</div>
      <div class="bubble">${escHtml(m.message)}</div>
      <div class="meta">${fmtTime(ts)}${tick}</div>
    </div>`;
    container.appendChild(row);
  });
  container.scrollTop = container.scrollHeight;
}

// ============================================================
// Fetch messages (polling)
// ============================================================
async function fetchMessages() {
  const phone = document.getElementById('toInput').value.trim();
  const fd = new FormData();
  fd.append('action', 'fetch');
  if (phone) fd.append('phone', phone);
  try {
    const res  = await fetch('', { method: 'POST', body: fd });
    const json = await res.json();
    if (json.success) {
      allMessages = json.messages || [];
      setStatus(true, 'เชื่อมต่อแล้ว · รีเฟรชทุก 5 วิ');
      renderMsgs();
    } else {
      setStatus(false, 'ดึงข้อมูลไม่ได้');
    }
  } catch(e) {
    setStatus(false, 'ออฟไลน์: ' + e.message);
  }
}

// ============================================================
// Send SMS
// ============================================================
async function sendSMS() {
  const phone = document.getElementById('toInput').value.trim();
  const text  = document.getElementById('msgInput').value.trim();
  if (!phone) { showToast('กรุณาใส่เบอร์ปลายทาง', true); return; }
  if (!text)  { showToast('กรุณาพิมพ์ข้อความ', true); return; }

  const btn = document.getElementById('sendBtn');
  btn.disabled = true; btn.textContent = 'กำลังส่ง...';

  const fd = new FormData();
  fd.append('action',  'send');
  fd.append('phone',   phone);
  fd.append('message', text);
  try {
    const res  = await fetch('', { method: 'POST', body: fd });
    const json = await res.json();
    if (json.success) {
      document.getElementById('msgInput').value = '';
      document.getElementById('msgInput').style.height = 'auto';
      showToast('ส่งสำเร็จ ✓');
      fetchMessages(); // refresh ทันที
    } else {
      const errMsg = json.data?.message || json.data?.error || json.curlError || ('HTTP ' + json.httpCode);
      showToast('ส่งไม่สำเร็จ: ' + errMsg, true);
    }
  } catch(e) {
    showToast('เกิดข้อผิดพลาด: ' + e.message, true);
  } finally {
    btn.disabled = false; btn.textContent = 'ส่ง ➤';
  }
}

// ============================================================
// Register webhook
// ============================================================
async function registerWebhook() {
  const btn = document.querySelector('.reg-btn');
  const status = document.getElementById('regStatus');
  btn.disabled = true; btn.textContent = 'กำลังลงทะเบียน...';
  const fd = new FormData();
  fd.append('action', 'register_webhook');
  try {
    const res  = await fetch('', { method: 'POST', body: fd });
    const json = await res.json();
    if (json.success) {
      status.textContent = '✅ ลงทะเบียนสำเร็จ!';
      status.style.color = '#3B6D11';
      document.getElementById('setupBox').style.borderColor = '#3B6D11';
      showToast('Webhook ลงทะเบียนสำเร็จ ✓');
    } else {
      const msg = json.data?.message || json.data?.error || ('code ' + json.code);
      status.textContent = '❌ ' + msg;
      status.style.color = '#a32d2d';
      showToast('ลงทะเบียนไม่สำเร็จ: ' + msg, true);
    }
  } catch(e) {
    status.textContent = '❌ ' + e.message;
    status.style.color = '#a32d2d';
  } finally {
    btn.disabled = false; btn.textContent = '🔗 ลงทะเบียน Webhook';
  }
}

// ============================================================
// Input helpers
// ============================================================
function autoResize(el) {
  el.style.height = 'auto';
  el.style.height = Math.min(el.scrollHeight, 100) + 'px';
}
function onEnter(e) {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendSMS(); }
}
document.getElementById('toInput').addEventListener('input', fetchMessages);

// ============================================================
// Start
// ============================================================
fetchMessages();
pollTimer = setInterval(fetchMessages, 5000);
</script>
</body>
</html>
