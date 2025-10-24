/**
 * Login Page JavaScript
 * Handles authentication and UI interactions
 */

document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const usernameInput = document.getElementById('username');
    const loginButton = loginForm.querySelector('.login-button');
    const buttonText = loginButton.querySelector('.button-text');
    const buttonLoader = loginButton.querySelector('.button-loader');
    const errorDiv = document.getElementById('loginError');
    const successDiv = document.getElementById('loginSuccess');
    const errorText = document.getElementById('errorText');

    // Password toggle functionality
    togglePassword.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        
        const icon = this.querySelector('i');
        icon.classList.toggle('fa-eye');
        icon.classList.toggle('fa-eye-slash');
    });

    // Focus effects for inputs
    const inputs = document.querySelectorAll('.form-group input');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        
        input.addEventListener('blur', function() {
            if (!this.value) {
                this.parentElement.classList.remove('focused');
            }
        });
        
        // Clear error state on input
        input.addEventListener('input', function() {
            this.classList.remove('error');
            hideMessages();
        });
    });

    // Form submission
    loginForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const username = usernameInput.value.trim();
        const password = passwordInput.value;
        const remember = document.getElementById('remember').checked;

        // Basic validation
        if (!username || !password) {
            showError('Bitte füllen Sie alle Felder aus.');
            shakeForm();
            return;
        }

        // Show loading state
        showLoading(true);
        hideMessages();

        try {
            
            const response = await fetch('/api/auth.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'login',
                    username: username,
                    password: password,
                    remember: remember ? '1' : '0'
                })
            });
            
            const data = await response.json();

            if (data.success) {
                // ✅ CSRF-Token speichern wenn vorhanden
                if (data.csrf_token) {
                    sessionStorage.setItem('csrf_token', data.csrf_token);
                    console.log('✅ CSRF-Token gespeichert');
                }
                
                showSuccess();
                
                // Redirect after short delay
                setTimeout(() => {
                    const redirectTo = getUrlParameter('redirect') || '/admin';
                    window.location.href = redirectTo;
                }, 1500);
            } else {
                showError(data.message || 'Anmeldung fehlgeschlagen. Bitte versuchen Sie es erneut.');
                shakeForm();
                
                // Mark fields as error if credentials are wrong
                if (data.message && data.message.includes('Benutzername') || data.message.includes('Passwort')) {
                    usernameInput.classList.add('error');
                    passwordInput.classList.add('error');
                }
            }
        } catch (error) {
            console.error('Login error:', error);
            showError('Verbindungsfehler. Bitte versuchen Sie es später erneut.');
            shakeForm();
        } finally {
            showLoading(false);
        }
    });

    // Utility functions
    function showLoading(loading) {
        if (loading) {
            loginButton.classList.add('loading');
            buttonText.style.display = 'none';
            buttonLoader.style.display = 'block';
            loginButton.disabled = true;
        } else {
            loginButton.classList.remove('loading');
            buttonText.style.display = 'block';
            buttonLoader.style.display = 'none';
            loginButton.disabled = false;
        }
    }

    function showError(message) {
        errorText.textContent = message;
        errorDiv.style.display = 'flex';
        successDiv.style.display = 'none';
        
        // Auto-hide after 5 seconds
        setTimeout(hideMessages, 5000);
    }

    function showSuccess() {
        successDiv.style.display = 'flex';
        errorDiv.style.display = 'none';
    }

    function hideMessages() {
        errorDiv.style.display = 'none';
        successDiv.style.display = 'none';
    }

    function shakeForm() {
        loginForm.classList.add('shake');
        setTimeout(() => {
            loginForm.classList.remove('shake');
        }, 500);
    }

    function getUrlParameter(name) {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(name);
    }

    // Check if user is already logged in
    checkExistingSession();

    async function checkExistingSession() {
        try {
            const response = await fetch('/api/auth.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'check-session'
                })
            });
            const data = await response.json();
            
            if (data.success && data.authenticated) {
                // User is already logged in, redirect to admin
                window.location.href = '/admin';
            } else {
                console.warn('User is not authenticated, staying on login page');
            }
        } catch (error) {
            // Silently fail - user can still login normally
            console.debug('Session check failed:', error);
        }
    }

    // Add keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Enter key submits form
        if (e.key === 'Enter' && document.activeElement.tagName !== 'BUTTON') {
            loginForm.dispatchEvent(new Event('submit'));
        }
        
        // Escape key clears form
        if (e.key === 'Escape') {
            usernameInput.value = '';
            passwordInput.value = '';
            document.getElementById('remember').checked = false;
            hideMessages();
            usernameInput.focus();
        }
    });

    // Auto-focus username field
    usernameInput.focus();
});