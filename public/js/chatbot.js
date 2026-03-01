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

    console.log('Sending AJAX request to /chatbot.php');
    fetch('/chatbot.php', {
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
        const allowedTags = /<(a)\s+href="[^"]*"(?:\s+target="_blank")?\s*>[^<]*<\/\1>/gi;
        const sanitizedResponse = (data.response || 'Sorry, I’m having trouble connecting.')
            .replace(/&/g, '&')
            .replace(/</g, '<')
            .replace(/>/g, '>')
            .replace(allowedTags, match => match);
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

document.addEventListener('DOMContentLoaded', () => {
    console.log('chatbot.js loaded');
    const messagesContainer = document.getElementById('chatbot-messages');
    if (messagesContainer) {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
        console.log('Messages container initialized');
    } else {
        console.error('Messages container not found');
    }
});