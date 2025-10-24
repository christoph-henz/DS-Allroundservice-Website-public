/**
 * Dynamic Questionnaire JavaScript
 * Enhanced multi-step questionnaire with progress tracking
 */

document.addEventListener('DOMContentLoaded', function() {
    initializeDynamicQuestionnaire();
});

function initializeDynamicQuestionnaire() {
    const form = document.getElementById('dynamicQuestionnaireForm');
    if (!form) {
        console.warn('No dynamic questionnaire form found');
        return;
    }

    const steps = form.querySelectorAll('.question-step');
    const totalSteps = steps.length - 1; // Exclude final step
    let currentStep = 0;

    // Initialize progress tracking
    updateProgress(currentStep, totalSteps);
    
    // Add navigation event listeners
    setupNavigation(form, steps, totalSteps);
    
    // Add form submission handler
    setupFormSubmission(form);
    
    // Add input validation
    setupInputValidation(form);
}

function setupNavigation(form, steps, totalSteps) {
    // Next buttons
    form.addEventListener('click', function(e) {
        if (e.target.classList.contains('next-btn')) {
            e.preventDefault();
            const currentStepIndex = parseInt(e.target.getAttribute('data-target')) - 1;
            
            if (validateCurrentStep(currentStepIndex)) {
                navigateToStep(parseInt(e.target.getAttribute('data-target')), totalSteps);
            }
        }
    });
    
    // Previous buttons
    form.addEventListener('click', function(e) {
        if (e.target.classList.contains('prev-btn')) {
            e.preventDefault();
            navigateToStep(parseInt(e.target.getAttribute('data-target')), totalSteps);
        }
    });
    
    // Final summary button
    form.addEventListener('click', function(e) {
        if (e.target.classList.contains('final-btn')) {
            e.preventDefault();
            if (validateCurrentStep(totalSteps - 1)) {
                showSummary();
            }
        }
    });
    
    // Back from summary
    form.addEventListener('click', function(e) {
        if (e.target.id === 'prevBtnFinal') {
            e.preventDefault();
            navigateToStep(totalSteps - 1, totalSteps);
        }
    });
}

function navigateToStep(stepIndex, totalSteps) {
    const steps = document.querySelectorAll('.question-step');
    
    // Hide all steps
    steps.forEach(step => {
        step.style.display = 'none';
    });
    
    // Show target step
    if (steps[stepIndex]) {
        steps[stepIndex].style.display = 'block';
        updateProgress(stepIndex, totalSteps);
        
        // Focus first input in new step
        const firstInput = steps[stepIndex].querySelector('input, select, textarea');
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 100);
        }
        
        // Smooth scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

function validateCurrentStep(stepIndex) {
    const currentStep = document.querySelector(`[data-step="${stepIndex}"]`);
    if (!currentStep) return true;
    
    const requiredInputs = currentStep.querySelectorAll('[required]');
    let isValid = true;
    
    requiredInputs.forEach(input => {
        if (!validateInput(input)) {
            isValid = false;
        }
    });
    
    return isValid;
}

function validateInput(input) {
    const value = input.value ? input.value.trim() : '';
    let isValid = true;
    let errorMessage = '';
    
    // Clear previous errors
    clearInputError(input);
    
    // Check if required field is empty
    if (input.hasAttribute('required')) {
        if (input.type === 'radio') {
            const radioGroup = input.closest('.radio-group');
            const checkedRadio = radioGroup ? radioGroup.querySelector('input[type="radio"]:checked') : null;
            if (!checkedRadio) {
                isValid = false;
                errorMessage = 'Bitte wählen Sie eine Option aus.';
            }
        } else if (input.type === 'checkbox') {
            const checkboxGroup = input.closest('.checkbox-group');
            const checkedBoxes = checkboxGroup ? checkboxGroup.querySelectorAll('input[type="checkbox"]:checked') : [];
            if (checkedBoxes.length === 0) {
                isValid = false;
                errorMessage = 'Bitte wählen Sie mindestens eine Option aus.';
            }
        } else if (!value) {
            isValid = false;
            errorMessage = 'Dieses Feld ist erforderlich.';
        }
    }
    
    // Email validation
    if (input.type === 'email' && value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            isValid = false;
            errorMessage = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
        }
    }
    
    // Phone validation
    if (input.type === 'tel' && value) {
        const phoneRegex = /^[\d\s\-\+\(\)]+$/;
        if (!phoneRegex.test(value) || value.length < 6) {
            isValid = false;
            errorMessage = 'Bitte geben Sie eine gültige Telefonnummer ein.';
        }
    }
    
    // Show error if validation failed
    if (!isValid) {
        showInputError(input, errorMessage);
    }
    
    return isValid;
}

function showInputError(input, message) {
    input.classList.add('error');
    
    // Find the appropriate container for the error message
    let container = input.closest('.question-input-container');
    if (!container) {
        container = input.parentNode;
    }
    
    // Remove existing error message
    const existingError = container.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
    
    // Add new error message
    const errorElement = document.createElement('div');
    errorElement.className = 'field-error';
    errorElement.textContent = message;
    container.appendChild(errorElement);
}

function clearInputError(input) {
    input.classList.remove('error');
    
    let container = input.closest('.question-input-container');
    if (!container) {
        container = input.parentNode;
    }
    
    const errorElement = container.querySelector('.field-error');
    if (errorElement) {
        errorElement.remove();
    }
}

function updateProgress(currentStep, totalSteps, isSummary = false) {
    const progressBar = document.getElementById('progressBar');
    const currentStepSpan = document.getElementById('currentStep');
    const totalStepsSpan = document.getElementById('totalSteps');
    const progressText = document.querySelector('.progress-text');
    
    if (progressBar) {
        const percentage = (currentStep / totalSteps) * 100;
        progressBar.style.width = percentage + '%';
    }
    
    if (isSummary && progressText) {
        // Bei der Zusammenfassung zeige "Überprüfung" an
        progressText.innerHTML = '<span>Überprüfung</span>';
    } else {
        // Normale Progress-Anzeige
        if (currentStepSpan) {
            currentStepSpan.textContent = currentStep + 1;
        }
        
        if (totalStepsSpan) {
            totalStepsSpan.textContent = totalSteps;
        }
    }
}

function showSummary() {
    // Collect all form data
    const form = document.getElementById('dynamicQuestionnaireForm');
    const formData = new FormData(form);
    const summaryContainer = document.getElementById('formSummary');
    
    let summaryHTML = '<div class="summary-items">';
    
    // Get all question steps (both individual and grouped)
    const questionSteps = form.querySelectorAll('.question-step:not(.final-step)');
    
    questionSteps.forEach((step, index) => {
        // Check if this is a group step
        if (step.classList.contains('group-step')) {
            // Handle grouped questions
            const groupTitle = step.querySelector('.group-title');
            const groupName = groupTitle ? groupTitle.textContent : `Gruppe ${index + 1}`;
            
            // Add group header to summary
            summaryHTML += `<div class="summary-group-header">${groupName}</div>`;
            
            // Process each question in the group
            const groupQuestions = step.querySelectorAll('.group-question');
            groupQuestions.forEach(groupQuestion => {
                const questionLabel = groupQuestion.querySelector('.question-label');
                if (!questionLabel) return;
                
                const questionText = questionLabel.textContent.replace(' *', '');
                const inputs = groupQuestion.querySelectorAll('input, select, textarea');
                let answer = '';
                
                inputs.forEach(input => {
                    if (input.type === 'radio' && input.checked) {
                        answer = input.value;
                    } else if (input.type === 'checkbox' && input.checked) {
                        answer += (answer ? ', ' : '') + input.value;
                    } else if (input.type !== 'radio' && input.type !== 'checkbox' && input.value) {
                        answer = input.value;
                    }
                });
                
                if (answer) {
                    summaryHTML += `
                        <div class="summary-item">
                            <div class="summary-question">${questionText}</div>
                            <div class="summary-answer">${answer}</div>
                        </div>
                    `;
                }
            });
        } else {
            // Handle individual questions (legacy format)
            const questionTitle = step.querySelector('.question-title');
            if (!questionTitle) return;
            
            const questionText = questionTitle.textContent.replace(' *', '');
            const inputs = step.querySelectorAll('input, select, textarea');
            let answer = '';
            
            inputs.forEach(input => {
                if (input.type === 'radio' && input.checked) {
                    answer = input.value;
                } else if (input.type === 'checkbox' && input.checked) {
                    answer += (answer ? ', ' : '') + input.value;
                } else if (input.type !== 'radio' && input.type !== 'checkbox' && input.value) {
                    answer = input.value;
                }
            });
            
            if (answer) {
                summaryHTML += `
                    <div class="summary-item">
                        <div class="summary-question">${questionText}</div>
                        <div class="summary-answer">${answer}</div>
                    </div>
                `;
            }
        }
    });
    
    summaryHTML += '</div>';
    
    if (summaryContainer) {
        summaryContainer.innerHTML = summaryHTML;
    }
    
    // Show final step
    const steps = document.querySelectorAll('.question-step');
    const totalSteps = steps.length - 1;
    
    steps.forEach(step => step.style.display = 'none');
    const finalStep = document.querySelector('.final-step');
    if (finalStep) {
        finalStep.style.display = 'block';
    }
    
    updateProgress(totalSteps, totalSteps, true);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function setupFormSubmission(form) {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const submitButton = form.querySelector('.btn-submit');
        const btnText = submitButton.querySelector('.btn-text');
        const btnLoading = submitButton.querySelector('.btn-loading');
        
        // Show loading state
        btnText.style.display = 'none';
        btnLoading.style.display = 'inline-flex';
        submitButton.disabled = true;
        
        // Prepare form data
        const formData = new FormData(form);
        const jsonData = {};
        
        // Convert FormData to JSON object
        formData.forEach((value, key) => {
            if (jsonData[key]) {
                // Handle multiple values for same key (checkboxes)
                if (!Array.isArray(jsonData[key])) {
                    jsonData[key] = [jsonData[key]];
                }
                jsonData[key].push(value);
            } else {
                jsonData[key] = value;
            }
        });
        
        // Submit to new API endpoint
        fetch('/api/submit-questionnaire.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(jsonData)
        })
        .then(response => {
            console.log('API Response Status:', response.status);
            console.log('API Response Headers:', Array.from(response.headers.entries()));
            
            // First get the text, then try to parse as JSON
            return response.text().then(text => {
                console.log('📄 Raw Response Body (first 500 chars):', text.substring(0, 500));
                console.log('📏 Response Body Length:', text.length);
                
                // Check if response is empty
                if (!text || text.trim() === '') {
                    console.error('❌ Server returned empty response!');
                    console.error('Status:', response.status);
                    console.error('Headers:', Array.from(response.headers.entries()));
                    throw new Error('Server-Fehler: Leere Antwort (möglicherweise PHP Fatal Error). Bitte Server-Logs prüfen!');
                }
                
                // Try to parse as JSON
                try {
                    const data = JSON.parse(text);
                    console.log('✅ Parsed JSON response:', data);
                    return data;
                } catch (jsonError) {
                    console.error('❌ Failed to parse JSON:');
                    console.error('JSON Parse Error:', jsonError.message);
                    console.error('Response Status:', response.status);
                    console.error('Content-Type:', response.headers.get('content-type'));
                    console.error('Full Response Body:', text);
                    
                    // Try to extract PHP error from HTML
                    const errorMatch = text.match(/<b>(.+?)<\/b>/);
                    if (errorMatch) {
                        console.error('🔍 Possible PHP Error:', errorMatch[1]);
                    }
                    
                    throw new Error('Server-Fehler: Ungültige JSON-Antwort. Siehe Console für Details.');
                }
            });
        })
        .then(data => {
            if (data.success) {
                console.log('✅ Submission successful:', data);
                // Show success notification
                showNotification(
                    `Ihre ${data.service_name || 'Service'} Anfrage wurde erfolgreich übermittelt! Referenz: ${data.reference}`, 
                    'success'
                );
                
                // Redirect to success page (data from session, not URL)
                setTimeout(() => {
                    if (data.redirect) {
                        // Use the redirect URL provided by the API
                        window.location.href = data.redirect;
                    } else {
                        // Fallback to success page without parameters
                        window.location.href = `/questionnaire-success`;
                    }
                }, 2000);
                
            } else {
                console.error('❌ Submission failed:', data);
                console.error('Error details:', {
                    error: data.error,
                    debug: data.debug,
                    fullResponse: data
                });
                throw new Error(data.error || 'Unbekannter Fehler beim Senden der Anfrage');
            }
        })
        .catch(error => {
            console.error('❌ Submission error:', error);
            console.error('Error stack:', error.stack);
            showNotification('Fehler beim Senden der Anfrage: ' + error.message, 'error');
            
            // Reset button state
            btnText.style.display = 'inline';
            btnLoading.style.display = 'none';
            submitButton.disabled = false;
        });
    });
}

function setupInputValidation(form) {
    // Add real-time validation
    form.addEventListener('blur', function(e) {
        if (e.target.matches('input, select, textarea')) {
            validateInput(e.target);
        }
    }, true);
    
    // Clear errors on input
    form.addEventListener('input', function(e) {
        if (e.target.matches('input, select, textarea')) {
            clearInputError(e.target);
        }
    });
}

function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <span class="notification-message">${message}</span>
        <button class="notification-close">&times;</button>
    `;
    
    // Add to page
    document.body.appendChild(notification);
    
    // Show notification
    setTimeout(() => {
        notification.classList.add('show');
    }, 100);
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        hideNotification(notification);
    }, 5000);
    
    // Add close button functionality
    notification.querySelector('.notification-close').addEventListener('click', () => {
        hideNotification(notification);
    });
}

function hideNotification(notification) {
    notification.classList.remove('show');
    setTimeout(() => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 300);
}

// Export functions for external use
window.DynamicQuestionnaire = {
    showNotification,
    validateInput,
    navigateToStep
};
