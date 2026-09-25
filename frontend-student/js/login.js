const API_BASE = 'http://localhost/exam-system-api';

document.getElementById('loginForm').addEventListener('submit', async function (e) {
    e.preventDefault();

    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const errorMsg = document.getElementById('errorMsg');
    const loginBtn = document.getElementById('loginBtn');

    errorMsg.textContent = '';
    loginBtn.disabled = true;
    loginBtn.textContent = 'Logging in...';

    try {
        const response = await fetch(`${API_BASE}/login`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });

        const data = await response.json();

        if (!response.ok) {
            errorMsg.textContent = data.error || 'Login failed. Please try again.';
            loginBtn.disabled = false;
            loginBtn.textContent = 'Log In';
            return;
        }

        if (data.user.role !== 'student') {
            errorMsg.textContent = 'This portal is for students only.';
            loginBtn.disabled = false;
            loginBtn.textContent = 'Log In';
            return;
        }

        // Store token and user info for the dashboard to use
        localStorage.setItem('token', data.token);
        localStorage.setItem('user', JSON.stringify(data.user));

        window.location.href = 'dashboard.html';

    } catch (err) {
        errorMsg.textContent = 'Could not connect to the server. Is XAMPP running?';
        loginBtn.disabled = false;
        loginBtn.textContent = 'Log In';
    }
});