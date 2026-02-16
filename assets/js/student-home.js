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

function createVoiceAssistant() {
  const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
  const synth = window.speechSynthesis;
  const micButton = document.getElementById('chat-mic');
  const voiceToggle = document.getElementById('chat-voice-toggle');
  const input = document.getElementById('chat-input');
  const status = document.getElementById('chat-voice-status');
  const state = {
    canListen: !!SpeechRecognition,
    canSpeak: !!synth,
    isListening: false,
    speakEnabled: true,
    recognition: null,
  };

  if (!micButton || !voiceToggle || !input || !status) {
    return state;
  }

  if (!state.canListen) {
    micButton.disabled = true;
    micButton.title = 'Voice input is not supported in this browser.';
  }

  if (!state.canSpeak) {
    voiceToggle.disabled = true;
    voiceToggle.title = 'Voice output is not supported in this browser.';
    state.speakEnabled = false;
  }

  if (state.canListen) {
    state.recognition = new SpeechRecognition();
    state.recognition.lang = 'en-US';
    state.recognition.continuous = false;
    state.recognition.interimResults = false;
    state.recognition.onstart = () => {
      state.isListening = true;
      micButton.classList.add('active');
      status.textContent = 'Listening...';
    };
    state.recognition.onend = () => {
      state.isListening = false;
      micButton.classList.remove('active');
      status.textContent = 'Voice ready';
    };
    state.recognition.onerror = () => {
      state.isListening = false;
      micButton.classList.remove('active');
      status.textContent = 'Voice input unavailable. Try typing.';
    };
    state.recognition.onresult = (event) => {
      const text = event.results?.[0]?.[0]?.transcript?.trim() || '';
      if (text) {
        input.value = text;
        input.focus();
      }
    };
  }

  micButton.addEventListener('click', () => {
    if (!state.recognition) return;
    if (state.isListening) {
      state.recognition.stop();
    } else {
      state.recognition.start();
    }
  });

  voiceToggle.addEventListener('click', () => {
    state.speakEnabled = !state.speakEnabled;
    voiceToggle.textContent = state.speakEnabled ? 'Voice On' : 'Voice Off';
    if (!state.speakEnabled && state.canSpeak) {
      synth.cancel();
    }
  });

  return state;
}

function speakText(voiceState, text) {
  if (!voiceState || !voiceState.canSpeak || !voiceState.speakEnabled) return;
  const utterance = new SpeechSynthesisUtterance(text);
  utterance.rate = 1;
  utterance.pitch = 1;
  window.speechSynthesis.cancel();
  window.speechSynthesis.speak(utterance);
}

function setupStudentChatbot() {
  const form = document.getElementById('chat-form');
  const input = document.getElementById('chat-input');
  if (!form || !input) return;
  const voiceState = createVoiceAssistant();

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
      speakText(voiceState, payload.reply);
    } catch (error) {
      const fallback = `Chatbot unavailable: ${error.message}`;
      appendChatMessage('bot', fallback);
      speakText(voiceState, fallback);
    }
  });
}

setupStudentChatbot();
