// Globale Variablen
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

// Globale Badge in der Navigation
function updateMailBadge(count) {
    const badge = document.getElementById('total-unread-badge');
    const icon = document.getElementById('mail-icon');
    if (!badge || !icon) return;

    if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.style.display = 'inline-block';
        icon.classList.remove('bi-envelope-dash');
        icon.classList.add('bi-envelope-check');
    } else {
        badge.style.display = 'none';
        icon.classList.remove('bi-envelope-check');
        icon.classList.add('bi-envelope-dash');
    }
}

// Badge eines einzelnen Users in der Liste
function updateUserBadge(userId, count) {
    const listItem = document.querySelector(`#user-list button[data-user-id="${userId}"]`);
    if (!listItem) return;

    let badge = listItem.querySelector('.badge');
    if (count > 0) {
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'badge rounded-pill bg-danger ms-2';
            listItem.appendChild(badge);
        }
        badge.textContent = count > 99 ? '99+' : count;
        badge.style.display = 'inline-block';
    } else if (badge) {
        badge.style.display = 'none';
    }
}

// Unread-Count vom Server abrufen
async function fetchUnreadCount() {
    try {
        const res = await fetch('/includes/plugins/messenger/get_total_unread_count.php', { credentials: 'same-origin' });
        if (!res.ok) return;
        const data = await res.json();
        const unread = data.total_unread ?? 0;
        updateMailBadge(unread);
    } catch (err) {
        console.debug("Mail-Badge konnte nicht geladen werden:", err);
    }
}

// Alle Nachrichten laden (beim Chatwechsel)
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

        // Badge aktualisieren
        fetchUnreadCount();

    } catch (e) {
        console.error('Fehler beim Laden der Nachrichten:', e);
    }
}

// Nur neue Nachrichten laden (Intervall)
async function loadNewMessages() {
    if(!activeUser) return;
    const chatWindow = document.getElementById('chat-window');
    const isAtBottom = chatWindow.scrollHeight - chatWindow.scrollTop <= chatWindow.clientHeight + 10;

    try {
        const res = await fetch(`/includes/plugins/messenger/messenger_settings.php?receiverId=${activeUser}&afterId=${lastMessageId}`);
        const messages = await res.json();

        messages.forEach(msg => {
            appendMessage(msg);

            // Badge für den Sender aktualisieren (nur falls nicht aktueller User)
            if (parseInt(msg.sender_id) !== currentUser) {
                updateUserBadge(msg.sender_id, msg.unread_count ?? 1);
            }
        });

        if(isAtBottom) chatWindow.scrollTop = chatWindow.scrollHeight;

        // Globale Badge
        fetchUnreadCount();

    } catch(e) {
        console.error('Fehler beim Laden neuer Nachrichten:', e);
    }
}

// Userliste aus der Datenbank laden
async function loadUserList() {
    try {
        const res = await fetch(`/includes/plugins/messenger/get_users.php`);
        const data = await res.json();

        const list = document.getElementById('user-list');
        list.innerHTML = '';

        const select = document.getElementById('user-select');
        select.innerHTML = '<option value="">-- Benutzer auswählen --</option>';

        // 1. Bereits gechattete User
        if (data.chatted.length === 0) {
            list.innerHTML = '<div class="p-3 text-center text-muted">Noch keine Chats vorhanden.</div>';
        } else {
            data.chatted.forEach(user => {
                const btn = document.createElement('button');
                btn.className = 'list-group-item list-group-item-action d-flex align-items-center justify-content-between';
                btn.dataset.userId = user.id;

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

                    if (badge) badge.style.display = 'none';

                    try {
                        await markMessagesAsRead(activeUser);
                    } catch (e) {
                        console.error('Fehler beim Markieren als gelesen:', e);
                    }

                    loadMessages();
                    loadUserList();
                    fetchUnreadCount();
                };

                list.appendChild(btn);
            });
        }

        // 2. Andere User ins Select
        if (data.others.length > 0) {
            data.others.forEach(user => {
                const option = document.createElement('option');
                option.value = user.id;
                option.textContent = user.username;
                select.appendChild(option);
            });
        } else {
            const option = document.createElement('option');
            option.textContent = "Keine weiteren Benutzer verfügbar";
            option.disabled = true;
            select.appendChild(option);
        }

        // Event-Handler für neues Chat starten
        select.onchange = () => {
            const userId = select.value;
            if (userId) {
                const username = select.options[select.selectedIndex].text;
                activeUser = parseInt(userId);
                activeUserName = username;
                document.getElementById('chat-header').textContent = 'Chat mit ' + activeUserName;

                loadMessages();
                loadUserList();
                fetchUnreadCount();
            }
        };

    } catch (e) {
        console.error('Fehler beim Laden der Userliste:', e);
    }
}

// Nachrichten als gelesen markieren
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

        updateUserBadge(senderId, 0);
        fetchUnreadCount();

    } catch(e) {
        console.error('Fehler beim Markieren von Nachrichten:', e);
        throw e;
    }
}

// Nachricht senden
async function sendMessage() {
    const input = document.getElementById('chat-input');
    const text = input.value.trim();
    if (!text || !activeUser) return;

    try {
        const res = await fetch('/includes/plugins/messenger/messenger_settings.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({receiver_id: activeUser, text})
        });

        const result = await res.json();
        if (result.error) { 
            alert(result.error); 
            return; 
        }

        input.value = '';

        addUserToChatList(activeUser, activeUserName);

        loadNewMessages();
        updateUserBadge(activeUser, 0);
        fetchUnreadCount();

    } catch(e) {
        console.error('Fehler beim Senden:', e);
    }
}

// Neuen User nach dem Senden hinzufügen (kein Duplikat)
function addUserToChatList(userId, username) {
    const list = document.getElementById('user-list');
    const select = document.getElementById('user-select');

    if (list.querySelector(`[data-user-id="${userId}"]`)) return;

    const btn = document.createElement('button');
    btn.className = 'list-group-item list-group-item-action d-flex align-items-center justify-content-between';
    btn.dataset.userId = userId;

    const avatarImg = document.createElement('img');
    avatarImg.src = '/images/avatars/svg-avatar.php?name=' + encodeURIComponent(username);
    avatarImg.alt = username;
    avatarImg.className = 'rounded-circle me-2';
    avatarImg.style.width = '32px';
    avatarImg.style.height = '32px';
    avatarImg.style.objectFit = 'cover';

    const usernameSpan = document.createElement('span');
    usernameSpan.textContent = username;
    usernameSpan.style.flexGrow = '1';

    btn.appendChild(avatarImg);
    btn.appendChild(usernameSpan);

    btn.onclick = () => {
        activeUser = userId;
        activeUserName = username;
        document.getElementById('chat-header').textContent = 'Chat mit ' + activeUserName;
        loadMessages();
    };

    list.prepend(btn);

    const option = select.querySelector(`option[value="${userId}"]`);
    if (option) option.remove();
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
            fetchUnreadCount();
            setInterval(() => {
                loadNewMessages();
                fetchUnreadCount();
            }, 30000);
        } else {
            console.error('Benutzer-ID konnte nicht geladen werden.');
            alert('Sie sind nicht angemeldet. Bitte melden Sie sich an.');
        }
    } catch (e) {
        console.error('Fehler bei der Initialisierung des Chats:', e);
    }
}

// Start
initChat();
