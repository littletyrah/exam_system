const API_BASE = 'http://localhost/exam-system-api';

// Auth guard - redirect to login if not authenticated
const token = localStorage.getItem('token');
const userRaw = localStorage.getItem('user');

if (!token || !userRaw) {
    window.location.href = 'index.html';
}

const user = JSON.parse(userRaw);
document.getElementById('welcomeMsg').textContent = `Welcome, ${user.full_name}`;

// Logout
document.getElementById('logoutBtn').addEventListener('click', function () {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    window.location.href = 'index.html';
});

// Navigation between sections
const navLinks = document.querySelectorAll('.nav-link');
const sections = document.querySelectorAll('.content-section');

navLinks.forEach(link => {
    link.addEventListener('click', function (e) {
        e.preventDefault();
        navLinks.forEach(l => l.classList.remove('active'));
        this.classList.add('active');

        sections.forEach(s => s.style.display = 'none');
        document.getElementById(this.dataset.section).style.display = 'block';
    });
});

// Helper to call the API with auth header
async function apiGet(endpoint) {
    const response = await fetch(`${API_BASE}${endpoint}`, {
        headers: { 'Authorization': `Bearer ${token}` }
    });

    if (response.status === 401) {
        localStorage.removeItem('token');
        localStorage.removeItem('user');
        window.location.href = 'index.html';
        return null;
    }

    return response.json();
}

// Load exam schedule
async function loadSchedule() {
    const container = document.getElementById('scheduleContent');
    try {
        const data = await apiGet('/examinations?limit=50');
        if (!data) return;

        const exams = data.data || data;

        if (exams.length === 0) {
            container.innerHTML = '<p class="empty-state">No exams scheduled.</p>';
            return;
        }

        let html = '<table><tr><th>Exam</th><th>Date</th><th>Time</th><th>Venue</th></tr>';
        exams.forEach(exam => {
            html += `<tr>
                <td>${exam.exam_title}</td>
                <td>${exam.exam_date}</td>
                <td>${exam.exam_time}</td>
                <td>${exam.venue || '-'}</td>
            </tr>`;
        });
        html += '</table>';
        container.innerHTML = html;
    } catch (err) {
        container.innerHTML = '<p class="empty-state">Failed to load schedule.</p>';
    }
}

// Load results for this student
async function loadResults() {
    const container = document.getElementById('resultsContent');
    try {
        const data = await apiGet(`/results?student_id=${user.user_id}&limit=50`);
        if (!data) return;

        const results = data.data || data;

        if (results.length === 0) {
            container.innerHTML = '<p class="empty-state">No results published yet.</p>';
            return;
        }

        let html = '<table><tr><th>Exam ID</th><th>Score</th><th>Grade</th></tr>';
        results.forEach(r => {
            html += `<tr>
                <td>${r.exam_id}</td>
                <td>${r.score}</td>
                <td>${r.grade || '-'}</td>
            </tr>`;
        });
        html += '</table>';
        container.innerHTML = html;
    } catch (err) {
        container.innerHTML = '<p class="empty-state">Failed to load results.</p>';
    }
}

// Load exam list into the QR dropdown
async function loadExamSelect() {
    const select = document.getElementById('examSelect');
    try {
        const data = await apiGet('/examinations?limit=50');
        if (!data) return;

        const exams = data.data || data;
        select.innerHTML = '<option value="">-- Select an exam --</option>';
        exams.forEach(exam => {
            select.innerHTML += `<option value="${exam.exam_id}">${exam.exam_title}</option>`;
        });
    } catch (err) {
        select.innerHTML = '<option>Failed to load exams</option>';
    }
}

document.getElementById('examSelect').addEventListener('change', async function () {
    const qrContent = document.getElementById('qrContent');
    if (!this.value) {
        qrContent.innerHTML = '';
        return;
    }

    qrContent.innerHTML = '<p class="loading">Loading QR code...</p>';
    const data = await apiGet(`/examinations/${this.value}/qrcode`);
    if (!data) return;

    qrContent.innerHTML = `
        <img src="${data.qr_code_url}" class="qr-image" alt="Exam QR Code">
        <p style="text-align:center; font-size:13px; color:#666;">${data.qr_data_encoded}</p>
    `;
});

// Initial load
loadSchedule();
loadResults();
loadExamSelect();
loadProfile();

// Load and populate profile form
function loadProfile() {
    document.getElementById('profileName').value = user.full_name;
    document.getElementById('profileEmail').value = user.email;
}

// Handle profile update
document.getElementById('profileForm').addEventListener('submit', async function (e) {
    e.preventDefault();

    const name = document.getElementById('profileName').value.trim();
    const email = document.getElementById('profileEmail').value.trim();
    const msg = document.getElementById('profileMsg');

    msg.style.color = '#d33';
    msg.textContent = '';

    if (!name || !email) {
        msg.textContent = 'Name and email are required.';
        return;
    }

    try {
        const response = await fetch(`${API_BASE}/users/${user.user_id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify({ full_name: name, email: email, role: user.role })
        });

        const data = await response.json();

        if (!response.ok) {
            msg.textContent = data.error || 'Failed to update profile.';
            return;
        }

        // Update local storage so the welcome message and future edits stay in sync
        user.full_name = name;
        user.email = email;
        localStorage.setItem('user', JSON.stringify(user));
        document.getElementById('welcomeMsg').textContent = `Welcome, ${user.full_name}`;

        msg.style.color = '#2a8';
        msg.textContent = 'Profile updated successfully.';

    } catch (err) {
        msg.textContent = 'Could not connect to the server.';
    }
});