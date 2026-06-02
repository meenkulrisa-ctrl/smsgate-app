
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: var(--font-sans); }
.app { display: flex; flex-direction: column; height: 600px; border: 0.5px solid var(--color-border-tertiary); border-radius: var(--border-radius-lg); overflow: hidden; background: var(--color-background-primary); }
.header { padding: 14px 18px; border-bottom: 0.5px solid var(--color-border-tertiary); background: var(--color-background-secondary); display: flex; align-items: center; gap: 10px; }
.status-dot { width: 8px; height: 8px; border-radius: 50%; background: #3B6D11; flex-shrink: 0; }
.status-dot.offline { background: #A32D2D; }
.header-title { font-size: 15px; font-weight: 500; color: var(--color-text-primary); }
.header-sub { font-size: 12px; color: var(--color-text-secondary); margin-top: 1px; }
.to-bar { padding: 10px 18px; border-bottom: 0.5px solid var(--color-border-tertiary); display: flex; align-items: center; gap: 10px; background: var(--color-background-primary); }
.to-label { font-size: 13px; color: var(--color-text-secondary); flex-shrink: 0; }
.to-input { flex: 1; border: 0.5px solid var(--color-border-secondary); border-radius: var(--border-radius-md); padding: 7px 12px; font-size: 14px; font-family: var(--font-sans); background: var(--color-background-secondary); color: var(--color-text-primary); outline: none; }
.to-input:focus { border-color: var(--color-border-primary); }
.msgs { flex: 1; overflow-y: auto; padding: 16px 18px; display: flex; flex-direction: column; gap: 10px; }
.bubble-row { display: flex; }
.bubble-row.sent { justify-content: flex-end; }
.bubble-row.recv { justify-content: flex-start; }
.bubble { max-width: 72%; padding: 9px 14px; border-radius: 16px; font-size: 14px; line-height: 1.5; }
.bubble-row.sent .bubble { background: #185FA5; color: #E6F1FB; border-bottom-right-radius: 4px; }
.bubble-row.recv .bubble { background: var(--color-background-secondary); color: var(--color-text-primary); border: 0.5px solid var(--color-border-tertiary); border-bottom-left-radius: 4px; }
.bubble-meta { font-size: 11px; margin-top: 4px; text-align: right; }
.bubble-row.sent .bubble-meta { color: #85B7EB; }
.bubble-row.recv .bubble-meta { color: var(--color-text-secondary); text-align: left; }
.bubble-from { font-size: 11px; color: var(--color-text-secondary); margin-bottom: 3px; }
.compose { padding: 12px 18px; border-top: 0.5px solid var(--color-border-tertiary); display: flex; gap: 10px; align-items: flex-end; background: var(--color-background-primary); }
.compose-input { flex: 1; border: 0.5px solid var(--color-border-secondary); border-radius: var(--border-radius-lg); padding: 9px 14px; font-size: 14px; font-family: var(--font-sans); background: var(--color-background-secondary); color: var(--color-text-primary); outline: none; resize: none; max-height: 100px; line-height: 1.5; }
.compose-input:focus { border-color: var(--color-border-primary); }
.send-btn { background: #185FA5; color: #E6F1FB; border: none; border-radius: var(--border-radius-md); padding: 9px 18px; font-size: 14px; font-weight: 500; cursor: pointer; font-family: var(--font-sans); flex-shrink: 0; transition: opacity 0.15s; }
.send-btn:hover { opacity: 0.85; }
.send-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--color-text-secondary); gap: 8px; }
.empty-icon { font-size: 32px; }
.empty-text { font-size: 14px; }
.toast { position: absolute; bottom: 80px; left: 50%; transform: translateX(-50%); background: var(--color-background-primary); border: 0.5px solid var(--color-border-secondary); border-radius: var(--border-radius-md); padding: 8px 16px; font-size: 13px; color: var(--color-text-secondary); opacity: 0; transition: opacity 0.3s; pointer-events: none; white-space: nowrap; z-index: 10; }
.toast.show { opacity: 1; }
.toast.err { color: #A32D2D; border-color: #F09595; }
.polling-badge { font-size: 11px; padding: 2px 8px; border-radius: 10px; background: #EAF3DE; color: #3B6D11; margin-left: auto; }
</style>

<h2 class="sr-only">SMS Chat Interface — ส่งและรับข้อความ SMS แบบ realtime</h2>

<div class="app" style="position:relative;">
  <div class="header">
    <div class="status-dot" id="statusDot"></div>
    <div>
      <div class="header-title">SMS Gateway</div>
      <div class="header-sub" id="statusText">กำลังเชื่อมต่อ...</div>
    </div>
    <span class="polling-badge" id="pollBadge">⏳ polling</span>
  </div>

  <div class="to-bar">
    <span class="to-label">ถึง:</span>
    <input class="to-input" id="toInput" type="tel" placeholder="เบอร์โทรศัพท์ เช่น +66812345678" />
  </div>

  <div class="msgs" id="msgs">
    <div class="empty-state" id="emptyState">
      <span class="empty-icon"><i class="ti ti-message-circle" style="font-size:40px;" aria-hidden="true"></i></span>
      <span class="empty-text">พิมพ์เบอร์ปลายทางแล้วส่งข้อความได้เลย</span>
    </div>
  </div>

  <div class="compose">
    <textarea class="compose-input" id="msgInput" rows="1" placeholder="พิมพ์ข้อความ..."></textarea>
    <button class="send-btn" id="sendBtn" onclick="sendSMS()">ส่ง <i class="ti ti-send" aria-hidden="true"></i></button>
  </div>

  <div class="toast" id="toast"></div>
</div>

<script>
const API_BASE = "https://sms-gate.app/3rdparty/v1";
const TOKEN = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzbXMtZ2F0ZS5hcHAiLCJzdWIiOiJKVEZCTlAiLCJleHAiOjE3ODA0Mjc3NzIsImlhdCI6MTc4MDQyNjg3MiwianRpIjoibmJNYTFtQXh0V0xHNi1mU2RvY0twIiwidXNlcl9pZCI6IkpURkJOUCIsInNjb3BlcyI6WyJkZXZpY2VzOmxpc3QiLCJtZXNzYWdlczpyZWFkIiwibWVzc2FnZXM6d3JpdGUiLCJtZXNzYWdlczpzZW5kIiwibWVzc2FnZXM6bGlzdCJdfQ.j2r-eBtNf-BJTquS59rMGPhjHHuN2s0tm4UorJ0svYU";
const DEVICE_ID = "U-ucDm6OQfO6FlCytxNIE";
const HEADERS = { "Authorization": "Bearer " + TOKEN, "Content-Type": "application/json" };

let messages = [];
let lastPollTime = null;
let pollInterval = null;
let isOnline = false;

function showToast(msg, isErr) {
  const t = document.getElementById("toast");
  t.textContent = msg;
  t.className = "toast show" + (isErr ? " err" : "");
  setTimeout(() => t.className = "toast", 2500);
}

function fmtTime(ts) {
  const d = ts ? new Date(ts) : new Date();
  return d.toLocaleTimeString("th-TH", { hour: "2-digit", minute: "2-digit" });
}

function setStatus(online, text) {
  isOnline = online;
  document.getElementById("statusDot").className = "status-dot" + (online ? "" : " offline");
  document.getElementById("statusText").textContent = text;
}

function renderMsgs() {
  const container = document.getElementById("msgs");
  const empty = document.getElementById("emptyState");
  if (messages.length === 0) {
    empty.style.display = "flex";
    return;
  }
  empty.style.display = "none";
  const phone = document.getElementById("toInput").value.trim();
  const filtered = phone ? messages.filter(m => {
    const num = (m.phoneNumber || m.from || "").replace(/\s/g,"");
    const clean = phone.replace(/\s/g,"");
    return num === clean || num.endsWith(clean) || clean.endsWith(num);
  }) : messages;
  
  container.innerHTML = '<div class="empty-state" id="emptyState" style="display:none"></div>';
  filtered.forEach(m => {
    const row = document.createElement("div");
    const isSent = m.dir === "out";
    row.className = "bubble-row " + (isSent ? "sent" : "recv");
    row.innerHTML = `
      ${!isSent ? `<div style="display:flex;flex-direction:column;max-width:72%;">
        <div class="bubble-from">${m.phoneNumber || m.from || "ไม่ทราบเบอร์"}</div>
        <div class="bubble">${m.message || m.text || ""}</div>
        <div class="bubble-meta">${fmtTime(m.receivedAt || m.createdAt)}</div>
      </div>` : `
      <div style="display:flex;flex-direction:column;align-items:flex-end;max-width:72%;">
        <div class="bubble">${m.message || m.text || ""}</div>
        <div class="bubble-meta">${fmtTime(m.sentAt || m.createdAt)} ${m.status === "Delivered" ? "✓✓" : m.status === "Sent" ? "✓" : "⏳"}</div>
      </div>`}
    `;
    container.appendChild(row);
  });
  container.scrollTop = container.scrollHeight;
}

async function fetchMessages() {
  try {
    const params = lastPollTime ? `?since=${encodeURIComponent(lastPollTime)}` : "?limit=50";
    const res = await fetch(`${API_BASE}/messages${params}`, { headers: HEADERS });
    if (!res.ok) throw new Error(res.status);
    const data = await res.json();
    setStatus(true, "เชื่อมต่อแล้ว · Device: " + DEVICE_ID.slice(0,8) + "...");
    document.getElementById("pollBadge").textContent = "🟢 live";
    
    const arr = Array.isArray(data) ? data : (data.messages || data.data || []);
    if (arr.length > 0) {
      arr.forEach(m => {
        const id = m.id || m.messageId || (m.phoneNumber + m.receivedAt);
        if (!messages.find(x => (x.id || x.messageId) === id)) {
          m.dir = (m.direction === "outgoing" || m.type === "outgoing" || m.dir === "out") ? "out" : "in";
          messages.push(m);
        }
      });
      messages.sort((a,b) => new Date(a.receivedAt || a.createdAt || 0) - new Date(b.receivedAt || b.createdAt || 0));
      lastPollTime = new Date().toISOString();
      renderMsgs();
    }
  } catch(e) {
    setStatus(false, "ไม่สามารถเชื่อมต่อ: " + e.message);
    document.getElementById("pollBadge").textContent = "🔴 offline";
  }
}

async function sendSMS() {
  const to = document.getElementById("toInput").value.trim();
  const text = document.getElementById("msgInput").value.trim();
  if (!to) { showToast("กรุณาใส่เบอร์ปลายทาง", true); return; }
  if (!text) { showToast("กรุณาพิมพ์ข้อความ", true); return; }
  
  const btn = document.getElementById("sendBtn");
  btn.disabled = true;
  btn.textContent = "กำลังส่ง...";
  
  try {
    const body = { deviceId: DEVICE_ID, phoneNumber: to, message: text };
    const res = await fetch(`${API_BASE}/message`, { method: "POST", headers: HEADERS, body: JSON.stringify(body) });
    const data = await res.json();
    if (!res.ok) throw new Error(data.message || data.error || res.status);
    
    messages.push({ id: data.id || Date.now(), phoneNumber: to, message: text, dir: "out", status: "Sent", sentAt: new Date().toISOString() });
    document.getElementById("msgInput").value = "";
    renderMsgs();
    showToast("ส่งสำเร็จ ✓");
  } catch(e) {
    showToast("ส่งไม่สำเร็จ: " + e.message, true);
  } finally {
    btn.disabled = false;
    btn.innerHTML = 'ส่ง <i class="ti ti-send" aria-hidden="true"></i>';
  }
}

document.getElementById("msgInput").addEventListener("keydown", e => {
  if (e.key === "Enter" && !e.shiftKey) { e.preventDefault(); sendSMS(); }
});

document.getElementById("toInput").addEventListener("input", () => renderMsgs());

document.getElementById("msgInput").addEventListener("input", function() {
  this.style.height = "auto";
  this.style.height = Math.min(this.scrollHeight, 100) + "px";
});

fetchMessages();
pollInterval = setInterval(fetchMessages, 5000);
</script>
