<?php
// ============================================================
// SMS Gateway Cloud — api.sms-gate.app
// Deploy บน Render.com ได้ทันที (PHP service)
// ============================================================

$USERNAME  = "JTFBNP";
$PASSWORD  = "cle1dbdoccuv0i";
$DEVICE_ID = "U-ucDm6OQfO6FlCytxNIE";
$API_BASE  = "https://api.sms-gate.app/3rdparty/v1";

// ไฟล์เก็บข้อความขาออก (inbox ดึงจาก API โดยตรง)
$OUTBOX_FILE = (getenv("RENDER") ? "/var/www/html/data" : __DIR__) . "/sms_outbox.json";

// ============================================================
// Helper: Basic Auth
// ============================================================
function authHeader() {
    global $USERNAME, $PASSWORD;
    return "Authorization: Basic " . base64_encode("$USERNAME:$PASSWORD");
}

// ============================================================
// Helper: เรียก API
// ============================================================
function apiRequest($method, $endpoint, $body = null, $queryParams = []) {
    global $API_BASE;
    $url = $API_BASE . $endpoint;
    if (!empty($queryParams)) {
        $url .= '?' . http_build_query($queryParams);
    }
    $ch = curl_init($url);
    $headers = [ authHeader(), "Content-Type: application/json", "Accept: application/json" ];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return ["code" => $code, "data" => json_decode($res, true), "raw" => $res, "error" => $err];
}

// ============================================================
// Helper: JSON file (outbox)
// ============================================================
function readJson($file) {
    if (!file_exists($file)) return [];
    $content = @file_get_contents($file);
    return $content ? (json_decode($content, true) ?? []) : [];
}
function writeJson($file, $data) {
    $dir = dirname($file);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

// ============================================================
// WEBHOOK RECEIVER — รับข้อความขาเข้า sms:received
// POST ?webhook=1
// ============================================================
if (isset($_GET["webhook"]) && $_SERVER["REQUEST_METHOD"] === "POST") {
    $raw  = file_get_contents("php://input");
    $data = json_decode($raw, true);

    // sms-gate ส่ง payload แบบ: { "event": "sms:received", "payload": { ... } }
    if ($data) {
        $event   = $data["event"] ?? "";
        $payload = $data["payload"] ?? $data;

        if ($event === "sms:received" || isset($payload["sender"])) {
            // เก็บลง inbox file
            $INBOX_FILE = (getenv("RENDER") ? "/var/www/html/data" : __DIR__) . "/sms_inbox.json";
            $inbox = readJson($INBOX_FILE);
            $msg = [
                "id"         => $payload["id"] ?? uniqid("in_"),
                "from"       => $payload["phoneNumber"] ?? $payload["sender"] ?? "",
                "message"    => $payload["message"] ?? $payload["text"] ?? $payload["contentPreview"] ?? "",
                "receivedAt" => $payload["receivedAt"] ?? $payload["createdAt"] ?? date("c"),
                "dir"        => "in",
            ];
            $ids = array_column($inbox, "id");
            if (!in_array($msg["id"], $ids)) {
                $inbox[] = $msg;
                if (count($inbox) > 1000) $inbox = array_slice($inbox, -1000);
                writeJson($INBOX_FILE, $inbox);
            }
        }
    }
    http_response_code(200);
    header("Content-Type: application/json");
    echo json_encode(["ok" => true]);
    exit;
}

// ============================================================
// AJAX ACTIONS (POST form)
// ============================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {
    header("Content-Type: application/json; charset=utf-8");

    // ── ส่ง SMS ──────────────────────────────────────────────
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
            "textMessage"  => ["text" => $message],
            "withDeliveryReport" => true,
        ]);
        $ok = $res["code"] === 202 || ($res["code"] >= 200 && $res["code"] < 300);
        if ($ok) {
            $outbox = readJson($OUTBOX_FILE);
            $outbox[] = [
                "id"      => $res["data"]["id"] ?? uniqid("out_"),
                "to"      => $phone,
                "message" => $message,
                "sentAt"  => date("c"),
                "state"   => $res["data"]["state"] ?? "Pending",
                "dir"     => "out",
            ];
            if (count($outbox) > 1000) $outbox = array_slice($outbox, -1000);
            writeJson($OUTBOX_FILE, $outbox);
        }
        echo json_encode([
            "success"  => $ok,
            "data"     => $res["data"],
            "httpCode" => $res["code"],
            "error"    => $res["error"],
        ]);
        exit;
    }

    // ── ดึงข้อความ inbox จาก API + outbox จากไฟล์ ────────────
    if ($_POST["action"] === "fetch") {
        $phone = trim($_POST["phone"] ?? "");

        // ดึง inbox จาก API โดยตรง
        $params = [
            "deviceId" => $GLOBALS["DEVICE_ID"],
            "limit"    => 100,
            "offset"   => 0,
        ];
        $inboxRes = apiRequest("GET", "/inbox", null, $params);

        $inbox = [];
        if ($inboxRes["code"] === 200 && is_array($inboxRes["data"])) {
            foreach ($inboxRes["data"] as $m) {
                $inbox[] = [
                    "id"         => $m["id"] ?? uniqid(),
                    "from"       => $m["sender"] ?? "",
                    "message"    => $m["contentPreview"] ?? "",
                    "receivedAt" => $m["createdAt"] ?? "",
                    "dir"        => "in",
                ];
            }
        }

        // ถ้า API inbox ไม่ได้ (501 Not Implemented ใน cloud) ใช้ webhook inbox file แทน
        if ($inboxRes["code"] === 501 || $inboxRes["code"] === 403) {
            $INBOX_FILE = (getenv("RENDER") ? "/var/www/html/data" : __DIR__) . "/sms_inbox.json";
            $inbox = readJson($INBOX_FILE);
        }

        // รวมกับ outbox
        $outbox = readJson($OUTBOX_FILE);
        $all    = array_merge($inbox, $outbox);

        // เรียงตามเวลา
        usort($all, function($a, $b) {
            $ta = $a["receivedAt"] ?? $a["sentAt"] ?? "";
            $tb = $b["receivedAt"] ?? $b["sentAt"] ?? "";
            return strcmp($ta, $tb);
        });

        // กรองตามเบอร์
        if ($phone) {
            $p   = preg_replace('/\s+/', '', $phone);
            $all = array_values(array_filter($all, function($m) use ($p) {
                $n = preg_replace('/\s+/', '', $m["from"] ?? $m["to"] ?? "");
                return $n === $p || str_ends_with($n, $p) || str_ends_with($p, $n);
            }));
        }

        echo json_encode([
            "success"       => true,
            "messages"      => array_values($all),
            "inboxApiCode"  => $inboxRes["code"],
        ]);
        exit;
    }

    // ── ลงทะเบียน webhook ──────────────────────────────────
    if ($_POST["action"] === "register_webhook") {
        $proto   = $_SERVER["HTTP_X_FORWARDED_PROTO"] ?? ($_SERVER["HTTPS"] ?? "http");
        $scheme  = ($proto === "https" || $proto === "on") ? "https" : "https"; // force https on Render
        $selfUrl = $scheme . "://" . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"];
        $res = apiRequest("POST", "/webhooks", [
            "deviceId" => $GLOBALS["DEVICE_ID"],
            "event"    => "sms:received",
            "url"      => $selfUrl . "?webhook=1",
        ]);
        echo json_encode([
            "success"    => $res["code"] === 201 || $res["code"] === 200,
            "data"       => $res["data"],
            "httpCode"   => $res["code"],
            "webhookUrl" => $selfUrl . "?webhook=1",
        ]);
        exit;
    }

    // ── ดูรายการ webhook ───────────────────────────────────
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
$proto   = $_SERVER["HTTP_X_FORWARDED_PROTO"] ?? ($_SERVER["HTTPS"] ?? "http");
$scheme  = ($proto === "https" || $proto === "on") ? "https" : "https"; // force https on Render
$selfUrl = $scheme . "://" . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"];
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SMS Gateway Cloud</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',Tahoma,sans-serif;background:#eef0f4;min-height:100vh;display:flex;flex-direction:column;align-items:center;padding:20px 12px;gap:14px}
h1{font-size:17px;font-weight:700;color:#185FA5;letter-spacing:.3px}

/* Setup box */
.setup{width:100%;max-width:520px;background:#fff;border:1.5px solid #d0dff5;border-radius:14px;padding:14px 18px;font-size:13px;color:#444}
.setup b{color:#185FA5}
.setup code{background:#eef3fb;padding:2px 7px;border-radius:5px;font-size:11.5px;word-break:break-all;color:#1a4a80}
.setup-row{display:flex;align-items:center;gap:10px;margin-top:10px;flex-wrap:wrap}
.reg-btn{background:#185FA5;color:#fff;border:none;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap}
.reg-btn:disabled{opacity:.45;cursor:not-allowed}
#regStatus{font-size:12px;color:#555}

/* App */
.app{width:100%;max-width:520px;background:#fff;border-radius:16px;box-shadow:0 4px 28px rgba(0,0,0,.09);display:flex;flex-direction:column;overflow:hidden;height:570px}
.hdr{padding:13px 18px;background:#185FA5;color:#fff;display:flex;align-items:center;gap:10px;flex-shrink:0}
.dot{width:9px;height:9px;border-radius:50%;background:#59e05b;border:2px solid rgba(255,255,255,.3);flex-shrink:0;transition:background .3s}
.dot.off{background:#e24b4a}
.hdr-info .title{font-size:15px;font-weight:600}
.hdr-info .sub{font-size:11px;opacity:.75;margin-top:1px}
.badge{margin-left:auto;font-size:11px;background:rgba(255,255,255,.15);padding:3px 10px;border-radius:20px}

/* To bar */
.tobar{padding:9px 16px;border-bottom:1px solid #eee;display:flex;align-items:center;gap:8px;background:#f8f9fb;flex-shrink:0}
.tolabel{font-size:13px;color:#888;flex-shrink:0}
.toinput{flex:1;border:1px solid #ddd;border-radius:8px;padding:7px 11px;font-size:14px;background:#fff;outline:none;color:#222}
.toinput:focus{border-color:#185FA5}

/* Messages */
.msgs{flex:1;overflow-y:auto;padding:14px 16px;display:flex;flex-direction:column;gap:9px;background:#f0f2f6}
.empty{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:#bbb;gap:8px;font-size:14px;text-align:center}
.empty span{line-height:1.6}
.brow{display:flex}
.brow.out{justify-content:flex-end}
.brow.in {justify-content:flex-start}
.bwrap{display:flex;flex-direction:column;max-width:76%}
.brow.out .bwrap{align-items:flex-end}
.from-lbl{font-size:11px;color:#999;margin-bottom:2px}
.bubble{padding:9px 13px;border-radius:16px;font-size:14px;line-height:1.55;word-break:break-word}
.brow.out .bubble{background:#185FA5;color:#fff;border-bottom-right-radius:4px}
.brow.in  .bubble{background:#fff;color:#222;border:1px solid #e0e0e0;border-bottom-left-radius:4px}
.meta{font-size:11px;color:#bbb;margin-top:3px}
.brow.out .meta{text-align:right}

/* Compose */
.compose{padding:10px 14px;border-top:1px solid #eee;display:flex;gap:8px;align-items:flex-end;flex-shrink:0;background:#fff}
.compose textarea{flex:1;border:1px solid #ddd;border-radius:10px;padding:9px 12px;font-size:14px;font-family:inherit;resize:none;max-height:100px;outline:none;line-height:1.5;background:#f8f9fb}
.compose textarea:focus{border-color:#185FA5;background:#fff}
.send-btn{background:#185FA5;color:#fff;border:none;border-radius:10px;padding:9px 18px;font-size:14px;font-weight:600;cursor:pointer;white-space:nowrap;transition:opacity .15s;flex-shrink:0}
.send-btn:hover{opacity:.85}
.send-btn:disabled{opacity:.4;cursor:not-allowed}

/* Toast */
.toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#1e1e1e;color:#fff;padding:8px 22px;border-radius:20px;font-size:13px;opacity:0;transition:opacity .3s;pointer-events:none;white-space:nowrap;z-index:9999}
.toast.show{opacity:1}
.toast.err{background:#a32d2d}
.toast.ok{background:#2a6a2a}

::-webkit-scrollbar{width:4px}
::-webkit-scrollbar-thumb{background:#ddd;border-radius:4px}
</style>
</head>
<body>

<h1>💬 SMS Gateway Cloud</h1>

<div class="setup" id="setupBox">
  <b>⚙️ ลงทะเบียน Webhook</b> เพื่อรับข้อความตอบกลับแบบ realtime<br>
  Webhook URL: <code id="whUrl"><?= htmlspecialchars($selfUrl) ?>?webhook=1</code>
  <div class="setup-row">
    <button class="reg-btn" onclick="registerWebhook()">🔗 ลงทะเบียน Webhook</button>
    <button class="reg-btn" style="background:#4a7a4a" onclick="listWebhooks()">📋 ดู Webhooks</button>
    <span id="regStatus"></span>
  </div>
</div>

<div class="app">
  <div class="hdr">
    <div class="dot off" id="dot"></div>
    <div class="hdr-info">
      <div class="title">SMS Cloud Chat</div>
      <div class="sub" id="statusText">กำลังเชื่อมต่อ...</div>
    </div>
    <span class="badge" id="liveBadge">⏳</span>
  </div>

  <div class="tobar">
    <span class="tolabel">ถึง:</span>
    <input class="toinput" id="toInput" type="tel" placeholder="เบอร์ เช่น +66812345678" oninput="onPhoneChange()">
  </div>

  <div class="msgs" id="msgs">
    <div class="empty" id="emptyState">
      <span style="font-size:38px">💬</span>
      <span>พิมพ์เบอร์ปลายทาง<br>แล้วส่งข้อความได้เลย</span>
      <span style="font-size:12px;color:#ccc">ข้อความตอบกลับจะแสดงอัตโนมัติ</span>
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
let pollTimer   = null;
let lastPhone   = '';

// ── Toast ─────────────────────────────────────────────────
function showToast(msg, type='') {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = 'toast show' + (type ? ' '+type : '');
  clearTimeout(t._t);
  t._t = setTimeout(() => t.className = 'toast', 3200);
}

// ── Status ────────────────────────────────────────────────
function setStatus(online, text) {
  document.getElementById('dot').className       = 'dot' + (online ? '' : ' off');
  document.getElementById('statusText').textContent = text;
  document.getElementById('liveBadge').textContent  = online ? '🟢 live' : '🔴 offline';
}

// ── Format ────────────────────────────────────────────────
function fmtTime(ts) {
  if (!ts) return '';
  try {
    const d = new Date(ts);
    return d.toLocaleDateString('th-TH',{day:'2-digit',month:'2-digit'}) + ' ' +
           d.toLocaleTimeString('th-TH',{hour:'2-digit',minute:'2-digit'});
  } catch(e) { return ts; }
}
function escHtml(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
}

// ── Render ────────────────────────────────────────────────
function renderMsgs() {
  const phone     = document.getElementById('toInput').value.trim().replace(/\s/g,'');
  const container = document.getElementById('msgs');
  const empty     = document.getElementById('emptyState');

  const filtered = phone
    ? allMessages.filter(m => {
        const n = (m.from || m.to || '').replace(/\s/g,'');
        return n === phone || n.endsWith(phone) || phone.endsWith(n);
      })
    : allMessages;

  Array.from(container.querySelectorAll('.brow')).forEach(el => el.remove());

  if (!filtered.length) { empty.style.display='flex'; return; }
  empty.style.display = 'none';

  filtered.forEach(m => {
    const out  = m.dir === 'out';
    const name = out ? ('→ '+(m.to||'')) : (m.from||'ไม่ทราบเบอร์');
    const ts   = m.sentAt || m.receivedAt || '';
    const st   = out ? (m.state==='Delivered'?' ✓✓' : m.state==='Failed'?' ✗' : ' ✓') : '';
    const row  = document.createElement('div');
    row.className = 'brow '+(out?'out':'in');
    row.innerHTML = `<div class="bwrap">
      <div class="from-lbl">${escHtml(name)}</div>
      <div class="bubble">${escHtml(m.message)}</div>
      <div class="meta">${fmtTime(ts)}${st}</div>
    </div>`;
    container.appendChild(row);
  });
  container.scrollTop = container.scrollHeight;
}

// ── Fetch messages ────────────────────────────────────────
async function fetchMessages() {
  const phone = document.getElementById('toInput').value.trim();
  const fd    = new FormData();
  fd.append('action','fetch');
  if (phone) fd.append('phone', phone);
  try {
    const res  = await fetch('', {method:'POST', body:fd});
    const json = await res.json();
    if (json.success) {
      allMessages = json.messages || [];
      const src = json.inboxApiCode === 200 ? 'API inbox' : 'webhook inbox';
      setStatus(true, `เชื่อมต่อแล้ว · ${src} · polling 5s`);
      renderMsgs();
    } else {
      setStatus(false, 'ดึงข้อมูลไม่ได้');
    }
  } catch(e) {
    setStatus(false, 'ออฟไลน์: '+e.message);
  }
}

// ── Send SMS ──────────────────────────────────────────────
async function sendSMS() {
  const phone = document.getElementById('toInput').value.trim();
  const text  = document.getElementById('msgInput').value.trim();
  if (!phone) { showToast('กรุณาใส่เบอร์ปลายทาง','err'); return; }
  if (!text)  { showToast('กรุณาพิมพ์ข้อความ','err'); return; }

  const btn = document.getElementById('sendBtn');
  btn.disabled = true; btn.textContent = 'กำลังส่ง...';
  const fd = new FormData();
  fd.append('action','send'); fd.append('phone',phone); fd.append('message',text);
  try {
    const res  = await fetch('', {method:'POST', body:fd});
    const json = await res.json();
    if (json.success) {
      document.getElementById('msgInput').value = '';
      document.getElementById('msgInput').style.height = 'auto';
      showToast('ส่งสำเร็จ ✓','ok');
      fetchMessages();
    } else {
      const e = json.data?.message || json.data?.error || json.error || ('HTTP '+json.httpCode);
      showToast('ส่งไม่สำเร็จ: '+e,'err');
    }
  } catch(e) {
    showToast('เกิดข้อผิดพลาด: '+e.message,'err');
  } finally {
    btn.disabled = false; btn.textContent = 'ส่ง ➤';
  }
}

// ── Register webhook ──────────────────────────────────────
async function registerWebhook() {
  const btn = document.querySelector('.reg-btn');
  const st  = document.getElementById('regStatus');
  btn.disabled = true; btn.textContent = 'กำลังลงทะเบียน...';
  const fd = new FormData(); fd.append('action','register_webhook');
  try {
    const res  = await fetch('', {method:'POST', body:fd});
    const json = await res.json();
    if (json.success) {
      st.innerHTML = '✅ สำเร็จ! URL: <code style="font-size:11px">'+json.webhookUrl+'</code>';
      st.style.color = '#2a6a2a';
      document.getElementById('setupBox').style.borderColor = '#4a9a4a';
      showToast('Webhook ลงทะเบียนสำเร็จ ✓','ok');
    } else {
      const e = json.data?.message || ('code '+json.httpCode);
      st.textContent = '❌ '+e; st.style.color='#a32d2d';
      showToast('ลงทะเบียนไม่สำเร็จ: '+e,'err');
    }
  } catch(e) {
    st.textContent = '❌ '+e.message; st.style.color='#a32d2d';
  } finally {
    btn.disabled = false; btn.textContent = '🔗 ลงทะเบียน Webhook';
  }
}

// ── List webhooks ─────────────────────────────────────────
async function listWebhooks() {
  const st = document.getElementById('regStatus');
  st.textContent = 'กำลังดึง...';
  const fd = new FormData(); fd.append('action','list_webhooks');
  const res  = await fetch('', {method:'POST', body:fd});
  const json = await res.json();
  if (json.success && Array.isArray(json.data)) {
    if (json.data.length === 0) {
      st.textContent = 'ยังไม่มี webhook ลงทะเบียน';
    } else {
      st.innerHTML = json.data.map(w =>
        `✅ <code style="font-size:11px">${w.event} → ${w.url}</code>`
      ).join('<br>');
    }
    st.style.color = '#444';
  } else {
    st.textContent = '❌ ดึงรายการไม่ได้';
    st.style.color = '#a32d2d';
  }
}

// ── Helpers ───────────────────────────────────────────────
function autoResize(el) {
  el.style.height='auto';
  el.style.height=Math.min(el.scrollHeight,100)+'px';
}
function onEnter(e) {
  if (e.key==='Enter' && !e.shiftKey) { e.preventDefault(); sendSMS(); }
}
function onPhoneChange() {
  clearTimeout(pollTimer);
  fetchMessages();
  pollTimer = setInterval(fetchMessages, 5000);
}

// ── Start ─────────────────────────────────────────────────
fetchMessages();
pollTimer = setInterval(fetchMessages, 5000);
</script>
</body>
</html>
