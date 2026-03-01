<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Rent AI Chatbot</title>
    <link rel="stylesheet" href="/public/css/chatbot.css">
</head>
<body>
    <!-- Chat Icon Button -->
    <div class="chat-icon" onclick="toggleChatbot()">
        <img src="/public/images/chat.svg" alt="Chat Icon" id="chat-icon-img">
    </div>

    <!-- Chatbot Window -->
    <div class="chatbot-container" id="chatbot" style="display: none;">
        <div class="chatbot-header">
            <h3>Smart Rent AI Assistant</h3>
            <button class="close-btn" onclick="toggleChatbot()">✕</button>
        </div>

        <div class="chatbot-body" id="chatbot-messages">
            <!-- Default greeting -->
            <div class="message bot-message">
                👋 Hello! How can I assist you with your property search?
                <?php if (isset($_SESSION['user_id'])): ?>
                    Welcome back! I can use your preferences to find properties.
                <?php endif; ?>
            </div>
        </div>

        <div class="chatbot-footer">
            <input 
                type="text" 
                id="user-input" 
                placeholder="Type your message..." 
                onkeypress="handleKeyPress(event)"
            >
            <button onclick="sendMessage()">Send</button>
        </div>
    </div>

    <script>
        function toggleChatbot() {
            console.log('toggleChatbot called');
            const chatbot = document.getElementById('chatbot');
            if (chatbot) {
                chatbot.style.display = chatbot.style.display === 'flex' ? 'none' : 'flex';
                console.log('Chatbot display set to:', chatbot.style.display);
            } else {
                console.error('Chatbot element not found');
            }
        }

        function handleKeyPress(event) {
            console.log('handleKeyPress called with key:', event.key);
            if (event.key === 'Enter') {
                sendMessage();
            }
        }

        function sendMessage() {
            console.log('sendMessage called');
            const input = document.getElementById('user-input');
            const message = input.value.trim();
            if (!message) {
                console.log('No message entered');
                return;
            }

            const messagesContainer = document.getElementById('chatbot-messages');
            const userMessage = document.createElement('div');
            userMessage.className = 'message user-message';
            userMessage.textContent = message;
            messagesContainer.appendChild(userMessage);
            input.value = '';
            messagesContainer.scrollTop = messagesContainer.scrollHeight;

            console.log('Sending AJAX request to /components/chatbot-handler.php');
            fetch('/components/chatbot-handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'message=' + encodeURIComponent(message)
            })
            .then(response => {
                console.log('AJAX response status:', response.status, 'OK:', response.ok);
                if (!response.ok) {
                    throw new Error(`Failed to connect: HTTP error! Status ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Bot response:', data);
                const botMessage = document.createElement('div');
                botMessage.className = 'message bot-message';
                // Sanitization to allow only <a> tags with href and target attributes
                const allowedTags = /<(a)\s+href="[^"]*"(?:\s+target="_blank")?\s*>[^<]*<\/\1>/gi;
                const sanitizedResponse = (data.response || 'Sorry, I’m having trouble connecting.')
                    .replace(/&/g, '&')
                    .replace(/</g, '<')
                    .replace(/>/g, '>')
                    .replace(allowedTags, match => match); // Restore allowed tags
                botMessage.innerHTML = sanitizedResponse;
                messagesContainer.appendChild(botMessage);
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            })
            .catch(error => {
                console.error('AJAX error:', error.message);
                const botMessage = document.createElement('div');
                botMessage.className = 'message bot-message';
                botMessage.textContent = 'Sorry, I’m having trouble connecting. Please try again!';
                messagesContainer.appendChild(botMessage);
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            });
        }
    </script>
</body>
</html>