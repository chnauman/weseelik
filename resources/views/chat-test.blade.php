<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Chat Test - Wiseelik</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .chat-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: calc(100vh - 40px);
        }

        .chat-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .chat-header h1 {
            font-size: 24px;
            font-weight: 600;
            margin: 0;
        }

        .header-actions {
            display: flex;
            gap: 10px;
        }

        .btn-header {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }

        .btn-header:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .conversation-id-section {
            padding: 20px 30px;
            background: #f8f9fa;
            border-bottom: 1px solid #e0e0e0;
        }

        .conversation-id-section label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            display: block;
        }

        .conversation-id-input-group {
            display: flex;
            gap: 10px;
        }

        .conversation-id-input-group input {
            flex: 1;
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
        }

        .conversation-id-input-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 30px;
            background: #f5f7fa;
        }

        .message {
            display: flex;
            margin-bottom: 20px;
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message.user {
            justify-content: flex-end;
        }

        .message.assistant {
            justify-content: flex-start;
        }

        .message-bubble {
            max-width: 70%;
            padding: 12px 18px;
            border-radius: 18px;
            word-wrap: break-word;
            position: relative;
        }

        .message.user .message-bubble {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-bottom-right-radius: 4px;
        }

        .message.assistant .message-bubble {
            background: white;
            color: #333;
            border: 1px solid #e0e0e0;
            border-bottom-left-radius: 4px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .message-time {
            font-size: 11px;
            color: #999;
            margin-top: 5px;
            text-align: right;
        }

        .message.assistant .message-time {
            text-align: left;
        }

        .garage-results {
            margin-top: 15px;
        }

        .garage-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .garage-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .garage-card-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .garage-card-detail {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }

        .garage-card-detail i {
            width: 20px;
            color: #667eea;
        }

        .external-service-results {
            margin-top: 15px;
        }

        .external-service-item {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
        }

        .external-service-item .name {
            font-weight: 600;
            color: #856404;
            margin-bottom: 5px;
        }

        .external-service-item .name a {
            color: #856404;
            text-decoration: none;
        }

        .external-service-item .name a:hover {
            text-decoration: underline;
        }

        .external-service-item .detail {
            font-size: 13px;
            color: #856404;
            margin-bottom: 3px;
        }

        .input-section {
            padding: 20px 30px;
            background: white;
            border-top: 1px solid #e0e0e0;
        }

        .input-group {
            display: flex;
            gap: 10px;
        }

        .input-group input {
            flex: 1;
            padding: 12px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 14px;
        }

        .input-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn-send {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn-send:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-send:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .loading-indicator {
            display: inline-flex;
            gap: 5px;
            padding: 12px 18px;
        }

        .loading-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #667eea;
            animation: bounce 1.4s infinite ease-in-out both;
        }

        .loading-dot:nth-child(1) {
            animation-delay: -0.32s;
        }

        .loading-dot:nth-child(2) {
            animation-delay: -0.16s;
        }

        @keyframes bounce {
            0%, 80%, 100% {
                transform: scale(0);
            }
            40% {
                transform: scale(1);
            }
        }

        .debug-panel {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            max-height: 300px;
            overflow-y: auto;
        }

        .debug-panel pre {
            margin: 0;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .debug-toggle {
            margin-top: 10px;
            font-size: 12px;
            color: #667eea;
            cursor: pointer;
            text-decoration: underline;
        }

        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }

        .toast {
            background: #333;
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            animation: slideIn 0.3s ease-out;
        }

        .toast.error {
            background: #dc3545;
        }

        .toast.success {
            background: #28a745;
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .empty-state p {
            font-size: 16px;
        }
    </style>
</head>
<body>
    <div class="chat-container">
        <div class="chat-header">
            <h1><i class="fas fa-comments"></i> Chat Test - Wiseelik</h1>
            <div class="header-actions">
                <button class="btn-header" onclick="clearConversation()">
                    <i class="fas fa-trash"></i> Clear
                </button>
                <button class="btn-header" onclick="exportConversation()">
                    <i class="fas fa-download"></i> Export
                </button>
            </div>
        </div>

        <div class="conversation-id-section">
            <label for="conversationId">Conversation ID (optional - leave empty for new conversation):</label>
            <div class="conversation-id-input-group">
                <input type="text" id="conversationId" placeholder="Enter conversation ID or leave empty for new conversation">
            </div>
        </div>

        <div class="messages-container" id="messagesContainer">
            <div class="empty-state">
                <i class="fas fa-comments"></i>
                <p>Start a conversation by typing a message below</p>
            </div>
        </div>

        <div class="input-section">
            <div class="input-group">
                <input type="text" id="messageInput" placeholder="Type your message here..." onkeypress="handleKeyPress(event)">
                <button class="btn-send" id="sendButton" onclick="sendMessage()">
                    <i class="fas fa-paper-plane"></i> Send
                </button>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script>
        const API_BASE_URL = '/api/v1';
        let conversationHistory = [];
        let currentConversationId = null;

        // Handle Enter key press
        function handleKeyPress(event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                sendMessage();
            }
        }

        // Send message to API
        async function sendMessage() {
            const messageInput = document.getElementById('messageInput');
            const conversationIdInput = document.getElementById('conversationId');
            const sendButton = document.getElementById('sendButton');
            const messagesContainer = document.getElementById('messagesContainer');

            const message = messageInput.value.trim();
            const conversationId = conversationIdInput.value.trim() || null;

            if (!message) {
                showToast('Please enter a message', 'error');
                return;
            }

            // Clear empty state
            const emptyState = messagesContainer.querySelector('.empty-state');
            if (emptyState) {
                emptyState.remove();
            }

            // Add user message to UI
            addMessage('user', message);
            messageInput.value = '';
            sendButton.disabled = true;
            sendButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

            // Show loading indicator
            const loadingId = addMessage('assistant', '', true);

            try {
                const response = await fetch(`${API_BASE_URL}/chat`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        message: message,
                        conversation_id: conversationId
                    })
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.message || 'Failed to send message');
                }

                // Update conversation ID if returned
                if (data.conversation_id) {
                    currentConversationId = data.conversation_id;
                    conversationIdInput.value = data.conversation_id;
                }

                // Remove loading indicator
                removeMessage(loadingId);

                // Add assistant response
                addMessage('assistant', data.message, false, data);

                // Store in history
                conversationHistory.push({
                    role: 'user',
                    content: message,
                    timestamp: new Date().toISOString()
                });
                conversationHistory.push({
                    role: 'assistant',
                    content: data.message,
                    data: data,
                    timestamp: new Date().toISOString()
                });

            } catch (error) {
                removeMessage(loadingId);
                showToast('Error: ' + error.message, 'error');
                console.error('Error:', error);
            } finally {
                sendButton.disabled = false;
                sendButton.innerHTML = '<i class="fas fa-paper-plane"></i> Send';
            }
        }

        // Add message to chat
        function addMessage(role, content, isLoading = false, apiData = null) {
            const messagesContainer = document.getElementById('messagesContainer');
            const messageId = 'msg-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${role}`;
            messageDiv.id = messageId;

            const bubble = document.createElement('div');
            bubble.className = 'message-bubble';

            if (isLoading) {
                bubble.innerHTML = `
                    <div class="loading-indicator">
                        <div class="loading-dot"></div>
                        <div class="loading-dot"></div>
                        <div class="loading-dot"></div>
                    </div>
                `;
            } else {
                bubble.innerHTML = `<div>${escapeHtml(content)}</div>`;
                
                // Add garage results if present
                if (apiData && apiData.garage && Array.isArray(apiData.garage) && apiData.garage.length > 0) {
                    const garageResults = document.createElement('div');
                    garageResults.className = 'garage-results';
                    garageResults.innerHTML = '<strong>Garages Found:</strong>';
                    
                    apiData.garage.forEach(garage => {
                        const garageCard = document.createElement('div');
                        garageCard.className = 'garage-card';
                        garageCard.innerHTML = `
                            <div class="garage-card-title">${escapeHtml(garage.title || 'Unknown')}</div>
                            ${garage.address ? `<div class="garage-card-detail"><i class="fas fa-map-marker-alt"></i> ${escapeHtml(garage.address)}</div>` : ''}
                            ${garage.contact_no ? `<div class="garage-card-detail"><i class="fas fa-phone"></i> ${escapeHtml(garage.contact_no)}</div>` : ''}
                            ${garage.area ? `<div class="garage-card-detail"><i class="fas fa-building"></i> ${escapeHtml(garage.area)}</div>` : ''}
                            ${garage.rating ? `<div class="garage-card-detail"><i class="fas fa-star"></i> Rating: ${garage.rating}</div>` : ''}
                            ${garage.maps_share_link ? `<div class="garage-card-detail"><a href="${escapeHtml(garage.maps_share_link)}" target="_blank"><i class="fas fa-map"></i> View on Maps</a></div>` : ''}
                        `;
                        garageResults.appendChild(garageCard);
                    });
                    
                    bubble.appendChild(garageResults);
                }

                // Add external service results if present (from history parsing)
                // Note: External service results come in the message content as formatted text
                // We'll parse markdown links if present
                if (content.includes('[') && content.includes('](')) {
                    // This might be external service results - format them differently
                    const externalResults = parseExternalServiceResults(content);
                    if (externalResults.length > 0) {
                        const externalDiv = document.createElement('div');
                        externalDiv.className = 'external-service-results';
                        externalDiv.innerHTML = '<strong>External Services Found:</strong>';
                        
                        externalResults.forEach(service => {
                            const serviceItem = document.createElement('div');
                            serviceItem.className = 'external-service-item';
                            const nameHtml = service.url 
                                ? `<a href="${escapeHtml(service.url)}" target="_blank">${escapeHtml(service.name)}</a>`
                                : escapeHtml(service.name);
                            serviceItem.innerHTML = `
                                <div class="name">${nameHtml}</div>
                                ${service.address ? `<div class="detail"><i class="fas fa-map-marker-alt"></i> ${escapeHtml(service.address)}</div>` : ''}
                                ${service.phone ? `<div class="detail"><i class="fas fa-phone"></i> ${escapeHtml(service.phone)}</div>` : ''}
                                ${service.url ? `<div class="detail"><a href="${escapeHtml(service.url)}" target="_blank" style="color: #856404;"><i class="fas fa-external-link-alt"></i> Open in Google Maps</a></div>` : ''}
                            `;
                            externalDiv.appendChild(serviceItem);
                        });
                        
                        bubble.appendChild(externalDiv);
                    }
                }

                // Add debug panel
                if (apiData) {
                    const debugToggle = document.createElement('div');
                    debugToggle.className = 'debug-toggle';
                    debugToggle.textContent = 'Show Debug Info';
                    debugToggle.onclick = function() {
                        const panel = this.nextElementSibling;
                        if (panel.style.display === 'none') {
                            panel.style.display = 'block';
                            this.textContent = 'Hide Debug Info';
                        } else {
                            panel.style.display = 'none';
                            this.textContent = 'Show Debug Info';
                        }
                    };

                    const debugPanel = document.createElement('div');
                    debugPanel.className = 'debug-panel';
                    debugPanel.style.display = 'none';
                    debugPanel.innerHTML = `<pre>${JSON.stringify(apiData, null, 2)}</pre>`;

                    bubble.appendChild(debugToggle);
                    bubble.appendChild(debugPanel);
                }

                const time = document.createElement('div');
                time.className = 'message-time';
                time.textContent = new Date().toLocaleTimeString();
                bubble.appendChild(time);
            }

            messageDiv.appendChild(bubble);
            messagesContainer.appendChild(messageDiv);
            scrollToBottom();

            return messageId;
        }

        // Remove message (for loading indicator)
        function removeMessage(messageId) {
            const message = document.getElementById(messageId);
            if (message) {
                message.remove();
            }
        }

        // Parse external service results from markdown format
        function parseExternalServiceResults(content) {
            const results = [];
            const lines = content.split('\n');
            let currentService = null;

            for (let i = 0; i < lines.length; i++) {
                let line = lines[i].trim();
                if (!line) {
                    // Empty line might indicate end of a service entry
                    if (currentService && i > 0 && lines[i-1].trim()) {
                        // Only add if previous line wasn't empty
                        continue;
                    }
                }

                // Match numbered list items with markdown links: "1. [Name](url)"
                const listMatch = line.match(/^\d+\.\s*\[([^\]]+)\]\(([^)]+)\)/);
                if (listMatch) {
                    if (currentService) {
                        results.push(currentService);
                    }
                    currentService = {
                        name: listMatch[1],
                        url: listMatch[2],
                        address: null,
                        phone: null
                    };
                    continue;
                }

                // Match address line: "Address: ..." (case insensitive)
                if (currentService && /^Address:\s*/i.test(line)) {
                    currentService.address = line.replace(/^Address:\s*/i, '').trim();
                    continue;
                }

                // Match phone line: "Phone: ..." (case insensitive)
                if (currentService && /^Phone:\s*/i.test(line)) {
                    currentService.phone = line.replace(/^Phone:\s*/i, '').trim();
                    continue;
                }

                // If we hit a new numbered item or non-matching line after a service, save it
                if (currentService && line.match(/^\d+\./)) {
                    results.push(currentService);
                    currentService = null;
                    // Process this line as a new service
                    i--; // Go back one line to process it
                    continue;
                }
            }

            if (currentService) {
                results.push(currentService);
            }

            return results;
        }

        // Clear conversation
        function clearConversation() {
            if (confirm('Are you sure you want to clear the conversation?')) {
                const messagesContainer = document.getElementById('messagesContainer');
                messagesContainer.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-comments"></i>
                        <p>Start a conversation by typing a message below</p>
                    </div>
                `;
                conversationHistory = [];
                currentConversationId = null;
                document.getElementById('conversationId').value = '';
                showToast('Conversation cleared', 'success');
            }
        }

        // Export conversation
        function exportConversation() {
            if (conversationHistory.length === 0) {
                showToast('No conversation to export', 'error');
                return;
            }

            const exportData = {
                conversation_id: currentConversationId,
                exported_at: new Date().toISOString(),
                messages: conversationHistory
            };

            const blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `conversation-${currentConversationId || 'new'}-${Date.now()}.json`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);

            showToast('Conversation exported successfully', 'success');
        }

        // Show toast notification
        function showToast(message, type = 'info') {
            const toastContainer = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.textContent = message;
            toastContainer.appendChild(toast);

            setTimeout(() => {
                toast.style.animation = 'slideIn 0.3s ease-out reverse';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // Scroll to bottom
        function scrollToBottom() {
            const messagesContainer = document.getElementById('messagesContainer');
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        // Escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Auto-scroll on new messages
        const observer = new MutationObserver(() => {
            scrollToBottom();
        });

        observer.observe(document.getElementById('messagesContainer'), {
            childList: true,
            subtree: true
        });
    </script>
</body>
</html>

