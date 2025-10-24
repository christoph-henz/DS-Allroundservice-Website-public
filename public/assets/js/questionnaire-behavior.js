/**
 * DS-Allroundservice - Questionnaire Behavior
 * Enhanced form handling and validation
 */

document.addEventListener('DOMContentLoaded', function() {
    initializeQuestionnaire();
});

/**
 * Initialize the questionnaire functionality
 */
function initializeQuestionnaire() {
    const form = document.querySelector('.questionnaire-form');
    if (!form) {
        console.warn('No questionnaire form found');
        return;
    }

    // Add form validation
    addFormValidation(form);
    
    // Add interactive features
    addInteractiveFeatures(form);
    
    // Handle form submission
    handleFormSubmission(form);
}

/**
 * Add form validation
 */
function addFormValidation(form) {
    // Only add validation to visible input fields, not hidden ones
    const inputs = form.querySelectorAll('input:not([type="hidden"]), select, textarea');
    
    inputs.forEach(input => {
        // Add real-time validation
        input.addEventListener('blur', function() {
            validateField(this);
        });
        
        // Remove error styling when user starts typing
        input.addEventListener('input', function() {
            clearFieldError(this);
        });
    });
}

/**
 * Validate a single field
 */
function validateField(field) {
    const questionGroup = field.closest('.question-group');
    
    // If no question group found, skip validation for this field
    if (!questionGroup) {
        console.warn('No question-group found for field:', field);
        return true;
    }
    
    const errorElement = questionGroup.querySelector('.field-error');
    
    // Remove existing error
    if (errorElement) {
        errorElement.remove();
    }
    
    // Check if field is required and empty
    if (field.hasAttribute('required') && !field.value.trim()) {
        showFieldError(questionGroup, 'Dieses Feld ist erforderlich');
        field.classList.add('error');
        return false;
    }
    
    // Email validation
    if (field.type === 'email' && field.value.trim()) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(field.value)) {
            showFieldError(questionGroup, 'Bitte geben Sie eine gültige E-Mail-Adresse ein');
            field.classList.add('error');
            return false;
        }
    }
    
    // Phone validation
    if (field.type === 'tel' && field.value.trim()) {
        const phoneRegex = /^[\d\s\-\+\(\)]+$/;
        if (!phoneRegex.test(field.value)) {
            showFieldError(questionGroup, 'Bitte geben Sie eine gültige Telefonnummer ein');
            field.classList.add('error');
            return false;
        }
    }
    
    field.classList.remove('error');
    return true;
}

/**
 * Show field error
 */
function showFieldError(questionGroup, message) {
    const errorElement = document.createElement('small');
    errorElement.className = 'field-error';
    errorElement.style.color = '#e74c3c';
    errorElement.style.display = 'block';
    errorElement.style.marginTop = '6px';
    errorElement.textContent = message;
    
    questionGroup.appendChild(errorElement);
}

/**
 * Clear field error
 */
function clearFieldError(field) {
    const questionGroup = field.closest('.question-group');
    
    // If no question group found, just remove error class from field
    if (!questionGroup) {
        field.classList.remove('error');
        return;
    }
    
    const errorElement = questionGroup.querySelector('.field-error');
    
    if (errorElement) {
        errorElement.remove();
    }
    
    field.classList.remove('error');
}

/**
 * Add interactive features
 */
function addInteractiveFeatures(form) {
    // Auto-format phone numbers
    const phoneInputs = form.querySelectorAll('input[type="tel"]');
    phoneInputs.forEach(input => {
        input.addEventListener('input', function() {
            formatPhoneNumber(this);
        });
    });
    
    // Auto-format postal codes
    const addressInputs = form.querySelectorAll('input[name*="plz"]');
    addressInputs.forEach(input => {
        input.addEventListener('input', function() {
            formatPostalCode(this);
        });
    });
    
    // Progressive enhancement for date inputs
    addDatePicker(form);
}

/**
 * Format phone number input
 */
function formatPhoneNumber(input) {
    let value = input.value.replace(/\D/g, '');
    
    if (value.length > 0) {
        if (value.startsWith('49')) {
            // German international format
            value = '+49 ' + value.substring(2);
        } else if (value.startsWith('0')) {
            // German national format
            value = value.replace(/^0/, '+49 ');
        }
    }
    
    input.value = value;
}

/**
 * Format postal code input
 */
function formatPostalCode(input) {
    let value = input.value.replace(/\D/g, '');
    
    if (value.length > 5) {
        value = value.substring(0, 5);
    }
    
    input.value = value;
}

/**
 * Add date picker functionality
 */
function addDatePicker(form) {
    const dateInputs = form.querySelectorAll('input[name*="termin"], input[name*="datum"]');
    
    dateInputs.forEach(input => {
        // Set minimum date to today
        const today = new Date();
        const minDate = today.toISOString().split('T')[0];
        
        // Convert to date input if browser supports it
        if (input.type !== 'date') {
            input.type = 'date';
            input.min = minDate;
        }
    });
}

/**
 * Handle form submission
 */
function handleFormSubmission(form) {
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Validate entire form
        const isValid = validateForm(form);
        
        if (!isValid) {
            console.error('Form validation failed');
            showMessage('Bitte korrigieren Sie die Fehler im Formular.', 'error');
            // Scroll to first error
            const firstError = form.querySelector('.error');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstError.focus();
            }
            return;
        }
        // Submit form
        submitForm(form);
    });
}

/**
 * Validate entire form
 */
function validateForm(form) {
    // Only validate visible input fields, not hidden ones
    const inputs = form.querySelectorAll('input:not([type="hidden"]), select, textarea');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!validateField(input)) {
            isValid = false;
        }
    });
    return isValid;
}

/**
 * Add question texts to form data for PDF generation
 */
function addQuestionTexts(form, formData) {
    // Find all question fields and their corresponding labels
    const questionFields = form.querySelectorAll('input[name^="question_"], select[name^="question_"], textarea[name^="question_"]');
    
    questionFields.forEach(field => {
        const fieldName = field.name;
        
        // Skip hidden question_text fields that are already added
        if (fieldName.startsWith('question_text_')) {
            return;
        }
        
        // Get the question index (question_0, question_1, etc.)
        const questionMatch = fieldName.match(/^question_(\d+)$/);
        if (!questionMatch) {
            return;
        }
        
        const questionIndex = questionMatch[1];
        const questionTextFieldName = 'question_text_' + questionIndex;
        
        // Find the corresponding label
        const questionGroup = field.closest('.question-group');
        if (questionGroup) {
            const label = questionGroup.querySelector('.question-label');
            if (label) {
                const questionText = label.textContent.trim();
                formData.append(questionTextFieldName, questionText);
            }
        }
    });
}

/**
 * Submit form via AJAX
 */
function submitForm(form) {
    const submitBtn = form.querySelector('.questionnaire-btn');
    const originalText = submitBtn.textContent;
    
    // Show loading state
    submitBtn.disabled = true;
    submitBtn.classList.add('loading');
    submitBtn.textContent = 'Wird gesendet...';
    
    // Prepare form data
    const formData = new FormData(form);
    
    // Add question texts for each question field
    addQuestionTexts(form, formData);
    
    // Add CSRF token
    if (window.sessionToken) {
        formData.append('token', window.sessionToken);
    } else {
        console.warn('No CSRF token available');
    }
    
    // Log form data
    for (let [key, value] of formData.entries()) {
    }
    
    // Send request
    fetch('/submit-inquiry', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        body: formData
    })
    .then(response => {
        return response.text();
    })
    .then(text => {
        
        // Try to extract JSON from response (in case there are PHP warnings before the JSON)
        let jsonData;
        try {
            // First try to parse as direct JSON
            jsonData = JSON.parse(text);
        } catch (e) {
            // If that fails, try to find JSON in the response text
            const jsonMatch = text.match(/\{.*\}$/s);
            if (jsonMatch) {
                jsonData = JSON.parse(jsonMatch[0]);
            } else {
                throw new Error('No valid JSON found in response');
            }
        }
        
        if (jsonData.success) {
            // Redirect to success page
            if (jsonData.redirect) {
                window.location.href = jsonData.redirect;
            } else {
                window.location.href = '/anfrage-erfolgreich?ref=' + encodeURIComponent(jsonData.reference);
            }
        } else {
            throw new Error(jsonData.message || 'Fehler beim Senden der Anfrage');
        }
    })
    .catch(error => {
        console.error('Form submission error:', error);
        showMessage('Entschuldigung, beim Senden Ihrer Anfrage ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut oder kontaktieren Sie uns telefonisch.', 'error');
        
        // Reset button state on error
        submitBtn.disabled = false;
        submitBtn.classList.remove('loading');
        submitBtn.textContent = originalText;
    });
}

/**
 * Show message to user
 */
function showMessage(message, type = 'success') {
    // Remove existing messages
    const existingMessages = document.querySelectorAll('.success-message, .error-message');
    existingMessages.forEach(msg => msg.remove());
    
    // Create message element
    const messageElement = document.createElement('div');
    messageElement.className = type === 'success' ? 'success-message' : 'error-message';
    messageElement.textContent = message;
    
    // Insert at top of form
    const form = document.querySelector('.questionnaire-form');
    form.insertBefore(messageElement, form.firstChild);
    
    // Auto-remove success messages after 10 seconds
    if (type === 'success') {
        setTimeout(() => {
            if (messageElement.parentNode) {
                messageElement.remove();
            }
        }, 10000);
    }
}

/**
 * Progressive enhancement for better UX
 */
function addProgressiveEnhancements() {
    // Add character counter for textareas
    const textareas = document.querySelectorAll('.form-textarea');
    textareas.forEach(textarea => {
        addCharacterCounter(textarea);
    });
    
    // Add smart completion for common fields
    addSmartCompletion();
}

/**
 * Add character counter to textareas
 */
function addCharacterCounter(textarea) {
    const maxLength = textarea.getAttribute('maxlength') || 500;
    
    const counter = document.createElement('small');
    counter.className = 'character-counter';
    counter.style.color = '#6c757d';
    counter.style.float = 'right';
    counter.style.marginTop = '4px';
    
    const updateCounter = () => {
        const remaining = maxLength - textarea.value.length;
        counter.textContent = `${remaining} Zeichen übrig`;
        
        if (remaining < 50) {
            counter.style.color = '#e74c3c';
        } else {
            counter.style.color = '#6c757d';
        }
    };
    
    textarea.addEventListener('input', updateCounter);
    updateCounter();
    
    textarea.parentNode.appendChild(counter);
}

/**
 * Add smart completion for common fields
 */
function addSmartCompletion() {
    // This could be extended to include autocomplete for addresses, etc.
    const emailInputs = document.querySelectorAll('input[type="email"]');
    emailInputs.forEach(input => {
        input.setAttribute('autocomplete', 'email');
    });
    
    const phoneInputs = document.querySelectorAll('input[type="tel"]');
    phoneInputs.forEach(input => {
        input.setAttribute('autocomplete', 'tel');
    });
}

// Initialize progressive enhancements when DOM is loaded
document.addEventListener('DOMContentLoaded', addProgressiveEnhancements);
