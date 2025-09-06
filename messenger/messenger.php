

<div class="container py-4">
  <div class="row">
    <!-- Linke Spalte: Userliste -->
    <div class="col-4">
      <div class="card shadow-sm">
        <div class="card-header bg-secondary">User</div>
        <div class="list-group list-group-flush" id="user-list">
          <!-- Benutzer werden hier eingefügt -->
        </div>
      </div>
    </div>

    <!-- Rechte Spalte: Chatfenster -->
    <div class="col-8">
      <div class="card shadow-sm">
        <div class="card-header bg-primary text-white" id="chat-header">Chat</div>
        <div class="card-body" id="chat-window" style="height:400px; overflow-y:auto; background:#f8f9fa;">
          <!-- Nachrichten werden hier eingefügt -->
        </div>
        <div class="card-footer d-flex gap-2">
          <input type="text" id="chat-input" class="form-control" placeholder="Nachricht schreiben...">
          <button id="send-btn" class="btn btn-primary">Senden</button>
        </div>
      </div>
    </div>
  </div>
</div>