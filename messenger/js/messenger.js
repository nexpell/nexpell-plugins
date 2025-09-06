// Globale Variable für die aktuelle Benutzer-ID
let currentUser = null;
let activeUser = null;
let activeUserName = '';

const loadedMessageIds = new Set();
let lastMessageId = 0;


// Nachricht anhängen
function appendMessage(msg) {
    console.log("Versuche, Nachricht hinzuzufügen:", msg);
    if(!msg.id) return;
    const msgId = parseInt(msg.id, 10);
    if(loadedMessageIds.has(msgId)) return;
    loadedMessageIds.add(msgId);

    const chatWindow = document.getElementById('chat-window');
    const isCurrentUser = parseInt(msg.sender_id, 10) === currentUser;

    const container = document.createElement('div');
    container.className = 'd-flex mb-2 ' + (isCurrentUser ? 'justify-content-end' : 'justify-content-start');

    const bubble = document.createElement('div');
    bubble.className = `p-2 rounded ${isCurrentUser ? 'bg-info text-white' : 'bg-warning text-dark'}`;
    bubble.style.maxWidth = '70%';
    bubble.style.wordWrap = 'break-word';
    bubble.innerHTML = `
        <strong>${msg.sender_name}</strong><br>
        ${msg.text}
        <div class="text-muted small mt-1">${msg.timestamp}</div>
    `;
    container.appendChild(bubble);
    chatWindow.appendChild(container);

    if (msgId > lastMessageId) {
        lastMessageId = msgId;
    }
}


// Alle Nachrichten laden (einmalig beim Wechsel des Chats)
async function loadMessages() {
    if (!activeUser) return;
    try {
        const res = await fetch(`/includes/plugins/messenger/messenger_settings.php?receiverId=${activeUser}`);
        const messages = await res.json();

        document.getElementById('chat-window').innerHTML = '';
        loadedMessageIds.clear();
        lastMessageId = 0;

        messages.forEach(msg => appendMessage(msg));
        const chatWindow = document.getElementById('chat-window');
        chatWindow.scrollTop = chatWindow.scrollHeight;

    } catch (e) {
        console.error('Fehler beim Laden der Nachrichten:', e);
    }
}


// Nur neue Nachrichten laden (im Intervall)
async function loadNewMessages() {
    if(!activeUser) return;
    const chatWindow = document.getElementById('chat-window');
    const isAtBottom = chatWindow.scrollHeight - chatWindow.scrollTop <= chatWindow.clientHeight + 10;
    
    try {
        const res = await fetch(`/includes/plugins/messenger/messenger_settings.php?receiverId=${activeUser}&afterId=${lastMessageId}`);
        const messages = await res.json();

        messages.forEach(msg => appendMessage(msg));

        if(isAtBottom) chatWindow.scrollTop = chatWindow.scrollHeight;
    } catch(e) {
        console.error('Fehler beim Laden neuer Nachrichten:', e);
    }
}


// Userliste aus der Datenbank laden
async function loadUserList() {
    try {
        const res = await fetch(`/includes/plugins/messenger/get_users.php`);
        const users = await res.json();
        const list = document.getElementById('user-list');
        list.innerHTML = '';

        if (users.length === 0) {
            list.innerHTML = '<div class="p-3 text-center text-muted">Keine anderen Benutzer gefunden.</div>';
            return;
        }

        users.forEach(user => {
            const btn = document.createElement('button');
            btn.className = 'list-group-item list-group-item-action d-flex align-items-center justify-content-between';
            btn.classList.add('active');    // Klasse hinzufügen
            btn.classList.remove('active'); // Klasse entfernen

            // Avatar
            const avatarImg = document.createElement('img');
            avatarImg.src = user.avatar;
            avatarImg.alt = user.username;
            avatarImg.className = 'rounded-circle me-2';
            avatarImg.style.width = '32px';
            avatarImg.style.height = '32px';
            avatarImg.style.objectFit = 'cover';

            // Username
            const usernameSpan = document.createElement('span');
            usernameSpan.textContent = user.username;
            usernameSpan.style.flexGrow = '1';

            // Badge
            let badge = null;
            if (user.unread_count > 0) {
                badge = document.createElement('span');
                badge.className = 'badge rounded-pill bg-danger ms-2';
                badge.textContent = user.unread_count > 99 ? '99+' : user.unread_count;
            }

            btn.appendChild(avatarImg);
            btn.appendChild(usernameSpan);
            if (badge) btn.appendChild(badge);

            if (activeUser === user.id) {
                btn.classList.add('user-active');
            }

            btn.onclick = async () => {
                activeUser = user.id;
                activeUserName = user.username;

                document.getElementById('chat-header').textContent = 'Chat mit ' + activeUserName;

                // Badge sofort ausblenden
                if (badge) badge.style.display = 'none';

                try {
                    await markMessagesAsRead(activeUser);
                } catch (e) {
                    console.error('Fehler beim Markieren als gelesen:', e);
                }

                loadMessages();
                loadUserList(); // Neu laden, damit die Markierung aktualisiert wird
            };

            list.appendChild(btn);
        });
    } catch(e) {
        console.error('Fehler beim Laden der Userliste:', e);
    }
}




// Funktion zum Markieren von Nachrichten als gelesen
async function markMessagesAsRead(senderId) {
    try {
        const res = await fetch('/includes/plugins/messenger/mark_as_read.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ sender_id: senderId })
        });
        const result = await res.json();
        if (result.success) {
            console.log('Nachrichten als gelesen markiert.');
        } else {
            console.error('Fehler beim Markieren als gelesen:', result.error);
        }
    } catch(e) {
        console.error('Fehler beim Markieren von Nachrichten:', e);
        throw e; // Leitet den Fehler an den onclick-Handler weiter
    }
}


// Nachricht senden
async function sendMessage() {
    const input = document.getElementById('chat-input');
    const text = input.value.trim();
    if(!text || !activeUser) return;

    try {
        const res = await fetch('/includes/plugins/messenger/messenger_settings.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({receiver_id: activeUser, text})
        });
        const result = await res.json();
        if(result.error) { alert(result.error); return; }
        input.value = '';
        loadNewMessages();
    } catch(e) {
        console.error('Fehler beim Senden:', e);
    }
}

// Event Listener
document.getElementById('send-btn').addEventListener('click', sendMessage);
document.getElementById('chat-input').addEventListener('keypress', e => { if(e.key==='Enter') sendMessage(); });


// Haupt-Initialisierung
async function initChat() {
    try {
        const res = await fetch('/includes/plugins/messenger/get_current_user.php');
        const data = await res.json();
        if (data.userID) {
            currentUser = data.userID;
            loadUserList();
            setInterval(loadNewMessages, 60000);
        } else {
            console.error('Benutzer-ID konnte nicht geladen werden.');
            alert('Sie sind nicht angemeldet. Bitte melden Sie sich an.');
        }
    } catch (e) {
        console.error('Fehler bei der Initialisierung des Chats:', e);
    }
}

// Startet den Chat
initChat();