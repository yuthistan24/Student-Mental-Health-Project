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

function createVoiceController() {
  const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition || window.mozSpeechRecognition || window.msSpeechRecognition;
  const synth = window.speechSynthesis;
  
  const micButton = document.getElementById('interview-mic');
  const voiceToggle = document.getElementById('interview-voice-toggle');
  const answerInput = document.getElementById('interview-answer');
  const status = document.getElementById('interview-voice-status');
  
  const state = {
    canListen: !!SpeechRecognition,
    canSpeak: !!synth,
    listening: false,
    speakEnabled: true,
    recognition: null,
    silenceTimer: null,
    silenceThreshold: 5000, // 5 seconds of silence
    lastSpeechTime: 0,
  };

  if (!micButton || !voiceToggle || !answerInput || !status) return state;

  // Handle unsupported browsers
  if (!state.canListen) {
    micButton.disabled = true;
    micButton.classList.add('disabled');
    micButton.title = 'Voice input not supported in your browser';
  }

  if (!state.canSpeak) {
    voiceToggle.disabled = true;
    voiceToggle.classList.add('disabled');
    voiceToggle.title = 'Voice output not supported in your browser';
    state.speakEnabled = false;
  }

  if (state.canListen) {
    state.recognition = new SpeechRecognition();
    state.recognition.lang = 'en-US';
    state.recognition.continuous = true;
    state.recognition.interimResults = true;
    state.recognition.maxAlternatives = 1;
    
    state.recognition.onstart = () => {
      state.listening = true;
      micButton.classList.add('listening');
      micButton.setAttribute('aria-pressed', 'true');
      if (voiceToggle) voiceToggle.classList.add('listening');
      status.textContent = '🎤 Listening...';
      status.classList.add('listening');
      state.lastSpeechTime = Date.now();
      
      // Start silence detection timer
      clearTimeout(state.silenceTimer);
      state.silenceTimer = setInterval(() => {
        const timeSinceSpeech = Date.now() - state.lastSpeechTime;
        if (timeSinceSpeech > state.silenceThreshold && state.listening) {
          // 5 seconds of silence detected, stop recording
          state.recognition.stop();
          status.textContent = '⏸️ Silence detected - stopped';
          setTimeout(() => {
            status.textContent = '✓ Ready to listen';
            status.classList.remove('listening');
          }, 1500);
        }
      }, 100);
    };
    
    state.recognition.onend = () => {
      state.listening = false;
      micButton.classList.remove('listening');
      micButton.setAttribute('aria-pressed', 'false');
      if (voiceToggle) voiceToggle.classList.remove('listening');
      clearTimeout(state.silenceTimer);
      status.classList.remove('listening');
      if (!status.classList.contains('error')) {
        status.textContent = '✓ Ready to listen';
      }
    };
    
    state.recognition.onerror = (event) => {
      state.listening = false;
      micButton.classList.remove('listening');
      micButton.setAttribute('aria-pressed', 'false');
      if (voiceToggle) voiceToggle.classList.remove('listening');
      clearTimeout(state.silenceTimer);
      
      let errorMsg = 'Voice input unavailable';
      if (event.error === 'network') {
        errorMsg = '🌐 Network error - check your connection';
      } else if (event.error === 'no-speech') {
        errorMsg = '🔇 No speech detected - try again';
      } else if (event.error === 'audio-capture') {
        errorMsg = '🎤 Microphone not available';
      } else if (event.error !== 'aborted') {
        errorMsg = `⚠️ ${event.error}`;
      }
      
      if (event.error !== 'aborted') {
        status.textContent = errorMsg;
        status.classList.add('error');
        setTimeout(() => status.classList.remove('error'), 2000);
      }
    };
    
    state.recognition.onresult = (event) => {
      let finalTranscript = '';
      let interimTranscript = '';
      
      for (let i = event.resultIndex; i < event.results.length; i++) {
        const transcriptPart = event.results[i][0].transcript;
        
        if (event.results[i].isFinal) {
          finalTranscript += transcriptPart + ' ';
        } else {
          interimTranscript += transcriptPart;
        }
      }
      
      // Combine final and interim transcripts for display
      const currentTranscript = finalTranscript + interimTranscript;
      
      if (currentTranscript.trim()) {
        // Update last speech time whenever we get speech
        state.lastSpeechTime = Date.now();
        answerInput.value = currentTranscript.trim();
        answerInput.focus();
        
        // Show different status messages
        if (finalTranscript.trim()) {
          status.textContent = '✓ Got your message';
        } else if (interimTranscript.trim()) {
          status.textContent = '👂 Hearing you...';
        }
      }
    };
  }

  // Talk button - start/stop listening
  if (micButton) {
    micButton.addEventListener('click', (e) => {
      e.preventDefault();
      if (!state.recognition) return;
      
      if (state.listening) {
        state.recognition.stop();
        clearTimeout(state.silenceTimer);
        micButton.classList.remove('listening');
        if (voiceToggle) voiceToggle.classList.remove('listening');
      } else {
        try {
          answerInput.focus();
          answerInput.value = ''; // Clear input when starting new recording
          state.recognition.start();
          if (voiceToggle) voiceToggle.classList.add('listening');
        } catch (error) {
          status.textContent = '❌ Error starting speech recognition';
          status.classList.add('error');
          setTimeout(() => status.classList.remove('error'), 2000);
        }
      }
    });
  }

  // Voice toggle - enable/disable audio output
  if (voiceToggle) {
    voiceToggle.addEventListener('click', (e) => {
      e.preventDefault();
      state.speakEnabled = !state.speakEnabled;
      
      if (state.speakEnabled) {
        voiceToggle.classList.remove('muted');
        voiceToggle.setAttribute('aria-pressed', 'false');
        voiceToggle.classList.add('active');
        status.textContent = '🔊 Voice output enabled';
      } else {
        voiceToggle.classList.add('muted');
        voiceToggle.classList.remove('active');
        voiceToggle.setAttribute('aria-pressed', 'true');
        status.textContent = '🔇 Voice output disabled';
        if (state.canSpeak) {
          synth.cancel();
        }
      }
      
      setTimeout(() => {
        if (!state.listening) {
          status.textContent = '✓ Ready to listen';
        }
      }, 1500);
    });
  }

  return state;
}

function speak(state, text) {
  if (!state || !state.canSpeak || !state.speakEnabled || !text) return;
  
  // Cancel any ongoing speech
  window.speechSynthesis.cancel();
  
  const utterance = new SpeechSynthesisUtterance(text);
  utterance.rate = 0.9;
  utterance.pitch = 1.0;
  utterance.volume = 1.0;
  
  // Better voice selection with fallback
  const voices = window.speechSynthesis.getVoices();
  if (voices.length > 0) {
    // Try to find English voice (prefer Google voices on Chrome)
    let selectedVoice = voices.find(v => v.lang.startsWith('en') && v.name.includes('Google'));
    if (!selectedVoice) {
      selectedVoice = voices.find(v => v.lang.startsWith('en'));
    }
    if (selectedVoice) {
      utterance.voice = selectedVoice;
    }
  }
  
  // Handle speech completion
  utterance.onend = () => {
    // Speech finished
  };
  
  utterance.onerror = (event) => {
    console.error('Speech synthesis error:', event.error);
  };
  
  try {
    window.speechSynthesis.speak(utterance);
  } catch (error) {
    console.error('Speech synthesis error:', error);
  }
}

function setupOnboardingInterview() {
  const form = document.getElementById('interview-form');
  const answerInput = document.getElementById('interview-answer');
  const saveHint = document.getElementById('interview-save-hint');
  const prompt = document.getElementById('interview-prompt');
  const voiceToggle = document.getElementById('interview-voice-toggle');
  
  if (!form || !answerInput || !prompt) return;

  const voiceState = createVoiceController();
  
  // Initialize voice toggle button state
  if (voiceToggle) {
    voiceToggle.classList.add('active');
    voiceToggle.setAttribute('aria-pressed', 'false');
  }

  async function startInterview() {
    try {
      const payload = await fetchJson('api/onboarding-assistant.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'start' }),
      });

      const reply = payload.reply || 'Let us begin.';
      appendInterviewMessage('bot', reply);
      prompt.textContent = payload.next_question || 'Interview in progress...';
      speak(voiceState, reply);
      answerInput.focus();
    } catch (error) {
      const fallback = `Interview assistant unavailable: ${error.message}`;
      appendInterviewMessage('bot', fallback);
      speak(voiceState, fallback);
    }
  }

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const message = answerInput.value.trim();
    if (!message) return;

    // Disable form while processing
    const button = form.querySelector('button[type="submit"]');
    const originalText = button.textContent;
    button.disabled = true;
    button.style.opacity = '0.6';

    appendInterviewMessage('user', message);
    answerInput.value = '';
    answerInput.focus();

    try {
      const payload = await fetchJson('api/onboarding-assistant.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'answer', message }),
      });

      const reply = payload.reply || 'Please continue.';
      appendInterviewMessage('bot', reply);
      
      // Only speak if voice is enabled
      if (voiceState.speakEnabled) {
        speak(voiceState, reply);
      }

      if (payload.done) {
        prompt.textContent = 'Interview complete. Redirecting to your student home...';
        if (saveHint) {
          saveHint.hidden = false;
        }
        const redirectTo = payload.redirect_to || 'student-home.php';
        setTimeout(() => {
          window.location.href = redirectTo;
        }, 1800);
      } else {
        prompt.textContent = payload.next_question || 'Please continue.';
      }
    } catch (error) {
      const fallback = `Interview assistant unavailable: ${error.message}`;
      appendInterviewMessage('bot', fallback);
      speak(voiceState, fallback);
    } finally {
      button.disabled = false;
      button.style.opacity = '1';
      answerInput.focus();
    }
  });

  startInterview();
}

setupOnboardingInterview();
