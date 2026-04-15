<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Experimental Chat — Wiseelik</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --teal: #11998e;
            --teal-light: #38ef7d;
            --bg: #f0f7f4;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, var(--teal) 0%, var(--teal-light) 100%);
            min-height: 100vh;
            display: flex;
            align-items: stretch;
            padding: 16px;
        }

        .chat-shell {
            display: flex;
            flex-direction: column;
            width: 100%;
            max-width: 860px;
            margin: auto;
            background: #fff;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 24px 64px rgba(0,0,0,.25);
            height: calc(100vh - 32px);
        }

        /* --- Header --- */
        .chat-header {
            background: linear-gradient(135deg, var(--teal), var(--teal-light));
            padding: 18px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #fff;
        }
        .chat-header h1 { font-size: 1.1rem; font-weight: 700; }
        .badge-exp {
            background: rgba(255,255,255,.25);
            border: 1px solid rgba(255,255,255,.4);
            color: #fff;
            font-size: .7rem;
            padding: 3px 9px;
            border-radius: 20px;
            letter-spacing: .04em;
        }

        /* --- Messages --- */
        .messages {
            flex: 1;
            overflow-y: auto;
            padding: 24px;
            background: var(--bg);
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .msg { display: flex; }
        .msg.user  { justify-content: flex-end; }
        .msg.bot   { justify-content: flex-start; }

        .bubble {
            max-width: 80%;
            padding: 12px 16px;
            border-radius: 18px;
            font-size: .9rem;
            line-height: 1.5;
        }
        .msg.user .bubble {
            background: linear-gradient(135deg, var(--teal), var(--teal-light));
            color: #fff;
            border-bottom-right-radius: 4px;
        }
        .msg.bot .bubble {
            background: #fff;
            border: 1px solid #dce8e5;
            border-bottom-left-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
        }

        /* --- Garage Cards --- */
        .garage-grid {
            margin-top: 12px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .garage-card {
            background: #f8fdfb;
            border: 1px solid #c8e6e0;
            border-radius: 12px;
            padding: 14px 16px;
            transition: box-shadow .2s;
        }
        .garage-card:hover { box-shadow: 0 4px 14px rgba(17,153,142,.15); }
        .garage-name { font-weight: 700; font-size: .95rem; color: #0d7a72; margin-bottom: 4px; }
        .garage-meta { font-size: .8rem; color: #555; display: flex; flex-wrap: wrap; gap: 8px; }
        .garage-meta span { display: flex; align-items: center; gap: 4px; }
        .garage-meta i { color: var(--teal); }
        .tag-pill {
            display: inline-block;
            background: #e0f5f1;
            color: var(--teal);
            font-size: .72rem;
            padding: 2px 8px;
            border-radius: 12px;
            margin: 2px;
        }

        /* --- Typing indicator --- */
        .typing { display: flex; gap: 5px; padding: 8px 12px; }
        .typing span {
            width: 8px; height: 8px;
            background: var(--teal);
            border-radius: 50%;
            animation: pulse 1.4s infinite ease-in-out;
        }
        .typing span:nth-child(2) { animation-delay: .2s; }
        .typing span:nth-child(3) { animation-delay: .4s; }
        @keyframes pulse { 0%,80%,100% { transform: scale(0); } 40% { transform: scale(1); } }

        /* --- Input --- */
        .input-bar {
            padding: 16px 20px;
            background: #fff;
            border-top: 1px solid #e0eeeb;
            display: flex;
            gap: 10px;
        }
        .input-bar input {
            flex: 1;
            border: 2px solid #d0e8e4;
            border-radius: 24px;
            padding: 10px 18px;
            font-size: .9rem;
            outline: none;
            transition: border-color .2s;
        }
        .input-bar input:focus { border-color: var(--teal); }
        .btn-send {
            background: linear-gradient(135deg, var(--teal), var(--teal-light));
            color: #fff; border: none;
            border-radius: 24px;
            padding: 10px 22px;
            font-weight: 600;
            font-size: .9rem;
            cursor: pointer;
            transition: opacity .2s;
        }
        .btn-send:disabled { opacity: .5; cursor: not-allowed; }
    </style>
</head>
<body>
<div class="chat-shell">
    <!-- Header -->
    <div class="chat-header">
        <i class="fa fa-flask fa-lg"></i>
        <h1>Wiseelik Experimental Chat</h1>
        <span class="badge-exp">⚡ New Architecture</span>
    </div>

    <!-- Messages -->
    <div class="messages" id="msgs">
        <div class="msg bot">
            <div class="bubble">
                👋 Hi! I can help you find garages in Kuwait.<br>
                Tell me your location, car problem, or just ask to <em>"show me some garages."</em>
            </div>
        </div>
    </div>

    <!-- Input -->
    <div class="input-bar">
        <input id="msgInput" type="text" placeholder="e.g. Find AC repair in Salmiya..." autocomplete="off"
               onkeydown="if(event.key==='Enter')send()">
        <button class="btn-send" id="sendBtn" onclick="send()">
            <i class="fa fa-paper-plane"></i> Send
        </button>
    </div>
</div>

<script>
    let convId = null;

    async function send() {
        const input   = document.getElementById('msgInput');
        const btn     = document.getElementById('sendBtn');
        const text    = input.value.trim();
        if (!text) return;

        addMsg('user', text);
        input.value = '';
        btn.disabled = true;

        const typingId = addTyping();

        try {
            const res  = await fetch('/api/v2/chat', {
                method:  'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ message: text, conversation_id: convId }),
            });

            const data = await res.json();
            removeMsg(typingId);

            if (data.conversation_id) convId = data.conversation_id;
            addMsg('bot', data.message, data.garages);

        } catch (e) {
            removeMsg(typingId);
            addMsg('bot', '⚠️ Connection error. Make sure Docker is running.');
        } finally {
            btn.disabled = false;
            input.focus();
        }
    }

    function addMsg(role, text, garages = []) {
        const msgs = document.getElementById('msgs');
        const id   = 'msg-' + Date.now();
        const div  = document.createElement('div');
        div.className = `msg ${role}`;
        div.id        = id;

        let garageHtml = '';
        if (garages && garages.length > 0) {
            garageHtml = '<div class="garage-grid">';
            garages.forEach(g => {
                const stars   = g.rating ? '⭐ ' + parseFloat(g.rating).toFixed(1) : 'Unrated';
                const phone   = g.phone   ? `<span><i class="fa fa-phone"></i>${esc(g.phone)}</span>` : '';
                const map     = g.maps_link ? `<a href="${esc(g.maps_link)}" target="_blank" style="color:var(--teal)"><i class="fa fa-map-marker-alt"></i> Map</a>` : '';
                const home    = g.home_service ? `<span><i class="fa fa-truck"></i> Home service</span>` : '';
                const svcTags = [...(g.services || []), ...(g.tags || [])]
                    .slice(0, 6)
                    .map(t => `<span class="tag-pill">${esc(t)}</span>`).join('');

                garageHtml += `
                    <div class="garage-card">
                        <div class="garage-name">${esc(g.title)}</div>
                        <div class="garage-meta">
                            <span><i class="fa fa-location-dot"></i>${esc(g.area || g.city || '')}</span>
                            <span>${stars}</span>
                            ${phone}
                            ${home}
                            ${map}
                        </div>
                        ${svcTags ? `<div style="margin-top:8px">${svcTags}</div>` : ''}
                    </div>`;
            });
            garageHtml += '</div>';
        }

        div.innerHTML = `<div class="bubble">${esc(text)}${garageHtml}</div>`;
        msgs.appendChild(div);
        msgs.scrollTop = msgs.scrollHeight;
        return id;
    }

    function addTyping() {
        const msgs = document.getElementById('msgs');
        const id   = 'typing-' + Date.now();
        const div  = document.createElement('div');
        div.id        = id;
        div.className = 'msg bot';
        div.innerHTML = `<div class="bubble"><div class="typing"><span></span><span></span><span></span></div></div>`;
        msgs.appendChild(div);
        msgs.scrollTop = msgs.scrollHeight;
        return id;
    }

    function removeMsg(id) {
        const el = document.getElementById(id);
        if (el) el.remove();
    }

    function esc(str) {
        if (!str) return '';
        const d = document.createElement('div');
        d.textContent = String(str);
        return d.innerHTML;
    }

    document.getElementById('msgInput').focus();
    document.getElementById('msgInput').addEventListener('keydown', e => {
        if (e.key === 'Enter') send();
    });
</script>
</body>
</html>
