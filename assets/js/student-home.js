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
  // Check for browser support with better fallback
  const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition || window.mozSpeechRecognition || window.msSpeechRecognition;
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
    silenceTimer: null,
    silenceThreshold: 5000, // 5 seconds of silence
    lastSpeechTime: 0,
  };

  if (!micButton || !voiceToggle || !input || !status) {
    return state;
  }

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

  // Initialize speech recognition
  if (state.canListen) {
    state.recognition = new SpeechRecognition();
    state.recognition.lang = 'en-US';
    state.recognition.continuous = true;
    state.recognition.interimResults = true;
    state.recognition.maxAlternatives = 1;
    
    state.recognition.onstart = () => {
      state.isListening = true;
      micButton.classList.add('listening');
      micButton.setAttribute('aria-pressed', 'true');
      status.textContent = '🎤 Listening...';
      status.classList.add('listening');
      state.lastSpeechTime = Date.now();
      
      // Start silence detection timer
      clearTimeout(state.silenceTimer);
      state.silenceTimer = setInterval(() => {
        const timeSinceSpeech = Date.now() - state.lastSpeechTime;
        if (timeSinceSpeech > state.silenceThreshold && state.isListening) {
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
      state.isListening = false;
      micButton.classList.remove('listening');
      micButton.setAttribute('aria-pressed', 'false');
      clearTimeout(state.silenceTimer);
      status.classList.remove('listening');
      if (!status.classList.contains('error')) {
        status.textContent = '✓ Ready to listen';
      }
    };
    
    state.recognition.onerror = (event) => {
      state.isListening = false;
      micButton.classList.remove('listening');
      micButton.setAttribute('aria-pressed', 'false');
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
        input.value = currentTranscript.trim();
        input.focus();
        
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
      
      if (state.isListening) {
        state.recognition.stop();
        clearTimeout(state.silenceTimer);
        micButton.classList.remove('listening');
        if (voiceToggle) voiceToggle.classList.remove('listening');
      } else {
        try {
          input.focus();
          input.value = ''; // Clear input when starting new recording
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
        status.textContent = '🔊 Voice on';
      } else {
        voiceToggle.classList.add('muted');
        voiceToggle.classList.remove('active');
        voiceToggle.setAttribute('aria-pressed', 'true');
        status.textContent = '🔇 Voice off';
        if (state.canSpeak) {
          synth.cancel();
        }
      }
      
      setTimeout(() => {
        if (!state.isListening) {
          status.textContent = '✓ Ready to listen';
        }
      }, 1500);
    });
  }

  return state;
}

function speakText(voiceState, text) {
  if (!voiceState || !voiceState.canSpeak || !voiceState.speakEnabled) return;
  
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

function setupStudentChatbot() {
  const form = document.getElementById('chat-form');
  const input = document.getElementById('chat-input');
  const status = document.getElementById('chat-voice-status');
  if (!form || !input) return;
  
  const voiceState = createVoiceAssistant();
  
  // Initialize voice toggle button state
  const voiceToggle = document.getElementById('chat-voice-toggle');
  if (voiceToggle) {
    voiceToggle.classList.add('active');
    voiceToggle.setAttribute('aria-pressed', 'false');
  }

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const message = input.value.trim();
    if (!message) return;

    // Disable form while processing
    const button = form.querySelector('button[type="submit"]');
    const originalText = button.textContent;
    button.disabled = true;
    button.style.opacity = '0.6';
    
    appendChatMessage('user', message);
    input.value = '';
    input.focus();

    try {
      const payload = await fetchJson('api/student-chatbot.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message }),
      });

      appendChatMessage('bot', payload.reply);
      
      // Only speak if voice is enabled
      if (voiceState.speakEnabled) {
        speakText(voiceState, payload.reply);
      }
      
      // Update status
      if (status && !voiceState.isListening) {
        status.textContent = '✓ Ready to listen';
      }
    } catch (error) {
      const fallback = `I'm having trouble responding: ${error.message}`;
      appendChatMessage('bot', fallback);
      speakText(voiceState, fallback);
    } finally {
      button.disabled = false;
      button.style.opacity = '1';
      input.focus();
    }
  });
}

setupStudentChatbot();
