let allStudents = [];

async function fetchStudents() {
  const response = await fetch('api/students.php');
  const data = await response.json();
  if (!response.ok) {
    throw new Error(data.error || 'Unable to load students');
  }
  return data;
}

function riskClass(label) {
  const value = String(label || '').toLowerCase();
  if (value === 'high') return 'risk-high';
  if (value === 'medium') return 'risk-medium';
  return 'risk-low';
}

function renderSummary(summary) {
  const el = document.getElementById('students-summary');
  if (!el) return;
  el.innerHTML = [
    `<div><strong>${summary.total_students}</strong> total students tracked</div>`,
    `<div><strong>${summary.high_risk}</strong> high-risk students need immediate action</div>`,
    `<div><strong>${summary.active_alerts}</strong> active intervention alerts</div>`,
  ].join('');
}

function renderCards(students) {
  const container = document.getElementById('student-cards');
  if (!container) return;

  if (!students.length) {
    container.innerHTML = '<p>No students match your filter.</p>';
    return;
  }

  container.innerHTML = students.map((student) => `
    <article class="student-card">
      <div class="student-card-top">
        <h3>${student.full_name}</h3>
        <span class="risk-pill ${riskClass(student.risk_label)}">${student.risk_label} (${student.risk_score})</span>
      </div>
      <p class="student-meta">Grade ${student.grade_level}</p>
      <p><strong>Attendance:</strong> ${student.attendance_pct.toFixed(1)}%</p>
      <p><strong>Average Score:</strong> ${student.avg_score.toFixed(1)}</p>
      <p><strong>Behavior Incidents:</strong> ${student.behavior_incidents}</p>
      <p><strong>Wellness Signal:</strong> ${student.wellness_signal}</p>
    </article>
  `).join('');
}

function applyFilters() {
  const searchValue = (document.getElementById('student-search')?.value || '').trim().toLowerCase();
  const riskValue = (document.getElementById('risk-filter')?.value || 'all').toLowerCase();

  const filtered = allStudents.filter((student) => {
    const nameMatch = student.full_name.toLowerCase().includes(searchValue);
    const gradeMatch = String(student.grade_level).toLowerCase().includes(searchValue);
    const queryMatch = searchValue === '' || nameMatch || gradeMatch;
    const riskMatch = riskValue === 'all' || student.risk_label.toLowerCase() === riskValue;
    return queryMatch && riskMatch;
  });

  renderCards(filtered);
}

async function init() {
  try {
    const payload = await fetchStudents();
    allStudents = payload.students || [];
    renderSummary(payload.summary || { total_students: 0, high_risk: 0, active_alerts: 0 });
    renderCards(allStudents);

    const searchInput = document.getElementById('student-search');
    const riskFilter = document.getElementById('risk-filter');
    if (searchInput) searchInput.addEventListener('input', applyFilters);
    if (riskFilter) riskFilter.addEventListener('change', applyFilters);
  } catch (error) {
    const container = document.getElementById('student-cards');
    if (container) container.innerHTML = `<p>${error.message}</p>`;
    const summary = document.getElementById('students-summary');
    if (summary) summary.textContent = error.message;
  }
}

init();
