async function fetchJson(url, options = {}) {
  const response = await fetch(url, options);
  const data = await response.json();
  if (!response.ok) {
    throw new Error(data.error || 'Request failed');
  }
  return data;
}

function appendInterviewMessage(role, text) {
  const windowEl = document.getElementById('interview-window');
  if (!windowEl) return;
  const node = document.createElement('div');
  node.className = `chat-message ${role}`;
  node.textContent = text;
  windowEl.appendChild(node);
  windowEl.scrollTop = windowEl.scrollHeight;
}

function setFormValue(field, value) {
  const el = document.querySelector(`[name="${field}"]`);
  if (!el) return;
  el.value = value;
}

function createVoiceController() {
  const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
  const micButton = document.getElementById('interview-mic');
  const voiceToggle = document.getElementById('interview-voice-toggle');
  const answerInput = document.getElementById('interview-answer');
  const status = document.getElementById('interview-voice-status');
  const state = {
    canListen: !!SpeechRecognition,
    canSpeak: !!window.speechSynthesis,
    speakEnabled: true,
    recognition: null,
    listening: false,
  };

  if (!micButton || !voiceToggle || !answerInput || !status) return state;

  if (!state.canListen) {
    micButton.disabled = true;
    micButton.title = 'Voice input not supported in this browser.';
  }
  if (!state.canSpeak) {
    voiceToggle.disabled = true;
    voiceToggle.title = 'Voice output not supported in this browser.';
    state.speakEnabled = false;
  }

  if (state.canListen) {
    state.recognition = new SpeechRecognition();
    state.recognition.lang = 'en-US';
    state.recognition.continuous = false;
    state.recognition.interimResults = false;
    state.recognition.onstart = () => {
      state.listening = true;
      micButton.classList.add('active');
      status.textContent = 'Listening...';
    };
    state.recognition.onend = () => {
      state.listening = false;
      micButton.classList.remove('active');
      status.textContent = 'Voice ready';
    };
    state.recognition.onerror = () => {
      state.listening = false;
      micButton.classList.remove('active');
      status.textContent = 'Voice input failed. Type your answer.';
    };
    state.recognition.onresult = (event) => {
      const text = event.results?.[0]?.[0]?.transcript?.trim() || '';
      if (text) {
        answerInput.value = text;
        answerInput.focus();
      }
    };
  }

  micButton.addEventListener('click', () => {
    if (!state.recognition) return;
    if (state.listening) {
      state.recognition.stop();
    } else {
      state.recognition.start();
    }
  });

  voiceToggle.addEventListener('click', () => {
    state.speakEnabled = !state.speakEnabled;
    voiceToggle.textContent = state.speakEnabled ? 'Voice On' : 'Voice Off';
    if (!state.speakEnabled && state.canSpeak) {
      window.speechSynthesis.cancel();
    }
  });

  return state;
}

function speak(state, text) {
  if (!state || !state.canSpeak || !state.speakEnabled || !text) return;
  const utterance = new SpeechSynthesisUtterance(text);
  utterance.rate = 1;
  utterance.pitch = 1;
  window.speechSynthesis.cancel();
  window.speechSynthesis.speak(utterance);
}

function setupOnboardingInterview() {
  const form = document.getElementById('interview-form');
  const answerInput = document.getElementById('interview-answer');
  const saveHint = document.getElementById('interview-save-hint');
  const stepInput = document.getElementById('interview-step');
  const prompt = document.getElementById('interview-prompt');
  if (!form || !answerInput || !stepInput || !prompt) return;

  const voiceState = createVoiceController();
  speak(voiceState, prompt.textContent.trim());

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const message = answerInput.value.trim();
    const step = Number(stepInput.value || '0');
    if (!message) return;

    appendInterviewMessage('user', message);
    answerInput.value = '';

    try {
      const payload = await fetchJson('api/onboarding-assistant.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ step, message }),
      });

      if (payload.field && payload.recognized_value !== null && payload.recognized_value !== undefined) {
        setFormValue(payload.field, payload.recognized_value);
      }

      stepInput.value = String(payload.next_step ?? step);
      const reply = payload.reply || 'Please continue.';
      appendInterviewMessage('bot', reply);
      speak(voiceState, reply);

      if (payload.done) {
        prompt.textContent = 'Interview complete. Review your form and click Save and Continue.';
        if (saveHint) {
          saveHint.hidden = false;
        }
      } else if (payload.next_question) {
        prompt.textContent = payload.next_question;
      }
    } catch (error) {
      const fallback = `Interview assistant unavailable: ${error.message}`;
      appendInterviewMessage('bot', fallback);
      speak(voiceState, fallback);
    }
  });
}

setupOnboardingInterview();
