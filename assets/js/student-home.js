async function fetchJson(url, options = {}) {
  const response = await fetch(url, options);
  const data = await response.json();
  if (!response.ok) {
    throw new Error(data.error || 'Request failed');
  }
  return data;
}

function appendChatMessage(role, text) {
  const chatWindow = document.getElementById('chat-window');
  if (!chatWindow) return;

  const div = document.createElement('div');
  div.className = `chat-message ${role}`;
  div.textContent = text;
  chatWindow.appendChild(div);
  chatWindow.scrollTop = chatWindow.scrollHeight;
}

function setupStudentChatbot() {
  const form = document.getElementById('chat-form');
  const input = document.getElementById('chat-input');
  if (!form || !input) return;

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const message = input.value.trim();
    if (!message) return;

    appendChatMessage('user', message);
    input.value = '';

    try {
      const payload = await fetchJson('api/student-chatbot.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message }),
      });

      appendChatMessage('bot', payload.reply);
    } catch (error) {
      appendChatMessage('bot', `Chatbot unavailable: ${error.message}`);
    }
  });
}

setupStudentChatbot();
