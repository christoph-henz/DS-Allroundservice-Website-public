/**
 * DS-Allroundservice - Modern Home Page JavaScript
 * Enhanced functionality for improved user experience
 */

document.addEventListener("DOMContentLoaded", function () {
    
    // ==========================================
    // Mobile Menu Functionality
    // ==========================================
    const toggleButton = document.querySelector(".menu-toggle");
    const mobileMenu = document.getElementById("mobile-menu");
    const heroHeader = document.querySelector(".hero-header");

    if (toggleButton && mobileMenu) {
        toggleButton.addEventListener("click", () => {
            const isOpen = mobileMenu.classList.contains("show");
            
            if (isOpen) {
                mobileMenu.classList.remove("show");
                heroHeader.classList.remove("menu-open");
                toggleButton.innerHTML = "&#9776;"; // Hamburger icon
                toggleButton.setAttribute("aria-label", "Menü öffnen");
            } else {
                mobileMenu.classList.add("show");
                heroHeader.classList.add("menu-open");
                toggleButton.innerHTML = "&#10005;"; // Close X icon
                toggleButton.setAttribute("aria-label", "Menü schließen");
            }
        });

        // Close mobile menu when clicking outside
        document.body.addEventListener("click", (event) => {
            if (
                !mobileMenu.contains(event.target) &&
                !toggleButton.contains(event.target)
            ) {
                mobileMenu.classList.remove("show");
                heroHeader.classList.remove("menu-open");
                toggleButton.innerHTML = "&#9776;";
                toggleButton.setAttribute("aria-label", "Menü öffnen");
            }
        });

        // Prevent menu from closing when clicking inside it
        mobileMenu.addEventListener("click", (event) => {
            event.stopPropagation();
        });

        // Close menu when clicking on a menu link
        const menuLinks = mobileMenu.querySelectorAll("a");
        menuLinks.forEach(link => {
            link.addEventListener("click", () => {
                mobileMenu.classList.remove("show");
                heroHeader.classList.remove("menu-open");
                toggleButton.innerHTML = "&#9776;";
                toggleButton.setAttribute("aria-label", "Menü öffnen");
            });
        });
    }

    // ==========================================
    // Smooth Scrolling for Navigation Links
    // ==========================================
    const navLinks = document.querySelectorAll('a[href^="#"]');
    
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            const targetId = this.getAttribute('href');
            const targetElement = document.querySelector(targetId);
            
            if (targetElement) {
                const headerOffset = 80;
                const elementPosition = targetElement.offsetTop;
                const offsetPosition = elementPosition - headerOffset;
                
                window.scrollTo({
                    top: offsetPosition,
                    behavior: 'smooth'
                });
            }
        });
    });

    // ==========================================
    // Form Enhancement with Custom Validation
    // ==========================================
    const contactForm = document.querySelector('.quote-form');
    
    if (contactForm) {
        // Validation rules
        const validationRules = {
            name: {
                required: true,
                minLength: 2,
                message: 'Bitte geben Sie Ihren Namen ein (mindestens 2 Zeichen)'
            },
            email: {
                required: true,
                email: true,
                message: 'Bitte geben Sie eine gültige E-Mail-Adresse ein'
            },
            phone: {
                required: false,
                pattern: /^[\+]?[0-9\s\-\(\)]{6,}$/,
                message: 'Bitte geben Sie eine gültige Telefonnummer ein'
            },
            message: {
                required: false,
                maxLength: 1000,
                message: 'Die Nachricht darf maximal 1000 Zeichen lang sein'
            }
        };

        // Function to validate a single field
        function validateField(field) {
            const fieldName = field.name;
            const value = field.value.trim();
            const rules = validationRules[fieldName];
            const errorElement = document.getElementById(fieldName + '-error');
            
            if (!rules) return true;

            // Clear previous error
            clearFieldError(field, errorElement);

            // Check if required field is empty
            if (rules.required && !value) {
                showFieldError(field, errorElement, rules.message);
                return false;
            }

            // Skip other validations if field is empty and not required
            if (!rules.required && !value) return true;

            // Check minimum length
            if (rules.minLength && value.length < rules.minLength) {
                showFieldError(field, errorElement, rules.message);
                return false;
            }

            // Check maximum length
            if (rules.maxLength && value.length > rules.maxLength) {
                showFieldError(field, errorElement, rules.message);
                return false;
            }

            // Check email format
            if (rules.email && !isValidEmail(value)) {
                showFieldError(field, errorElement, rules.message);
                return false;
            }

            // Check pattern (for phone)
            if (rules.pattern && value && !rules.pattern.test(value)) {
                showFieldError(field, errorElement, rules.message);
                return false;
            }

            return true;
        }

        // Function to show field error
        function showFieldError(field, errorElement, message) {
            field.classList.add('error');
            errorElement.textContent = message;
            errorElement.classList.add('show');
        }

        // Function to clear field error
        function clearFieldError(field, errorElement) {
            field.classList.remove('error');
            errorElement.textContent = '';
            errorElement.classList.remove('show');
        }

        // Email validation helper
        function isValidEmail(email) {
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailPattern.test(email);
        }

        // Add real-time validation on blur and input events
        const formFields = contactForm.querySelectorAll('input, textarea, select');
        formFields.forEach(field => {
            // Validate on blur (when user leaves field)
            field.addEventListener('blur', function() {
                validateField(this);
            });

            // Clear errors on input (while user is typing)
            field.addEventListener('input', function() {
                if (this.classList.contains('error')) {
                    const errorElement = document.getElementById(this.name + '-error');
                    clearFieldError(this, errorElement);
                }
            });
        });

        // Form submission
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            let isFormValid = true;
            
            // Validate all fields
            formFields.forEach(field => {
                const fieldValid = validateField(field);
                if (!fieldValid) {
                    isFormValid = false;
                }
            });

            // Check for required fields specifically
            const requiredFields = contactForm.querySelectorAll('[data-required="true"]');
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isFormValid = false;
                }
            });
            
            if (isFormValid) {
                // Here you would normally send the data to your server
                showNotification('Vielen Dank! Wir werden uns bald bei Ihnen melden.', 'success');
                
                // Reset form and clear all errors
                this.reset();
                formFields.forEach(field => {
                    const errorElement = document.getElementById(field.name + '-error');
                    if (errorElement) {
                        clearFieldError(field, errorElement);
                    }
                });
            } else {
                showNotification('Bitte korrigieren Sie die markierten Felder.', 'error');
                
                // Focus on first error field
                const firstErrorField = contactForm.querySelector('.error');
                if (firstErrorField) {
                    firstErrorField.focus();
                }
            }
        });
    }

    // ==========================================
    // CTA Button Interactions
    // ==========================================
    const ctaButtons = document.querySelectorAll('.btn-primary');
    
    ctaButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            // If it's not a form submit button and doesn't have an href
            if (this.type !== 'submit' && !this.href) {
                e.preventDefault();
                
                // Scroll to contact form
                const contactSection = document.getElementById('contact');
                if (contactSection) {
                    const headerOffset = 80;
                    const elementPosition = contactSection.offsetTop;
                    const offsetPosition = elementPosition - headerOffset;
                    
                    window.scrollTo({
                        top: offsetPosition,
                        behavior: 'smooth'
                    });
                    
                    // Focus on first input field after scrolling
                    setTimeout(() => {
                        const firstInput = contactSection.querySelector('input');
                        if (firstInput) {
                            firstInput.focus();
                        }
                    }, 800);
                }
            }
        });
    });

    // ==========================================
    // Scroll Animations
    // ==========================================
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.animationPlayState = 'running';
            }
        });
    }, observerOptions);

    // Observe elements for animations
    const animatedElements = document.querySelectorAll('.service-card, .pricing-card, .benefit-item');
    animatedElements.forEach((el, index) => {
        el.style.animationDelay = `${index * 0.1}s`;
        el.style.animationPlayState = 'paused';
        observer.observe(el);
    });

    // ==========================================
    // Service Cards Hover Effects
    // ==========================================
    const serviceCards = document.querySelectorAll('.service-card');
    
    serviceCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = '';
        });
    });

    // ==========================================
    // Header Scroll Behavior
    // ==========================================
    let lastScrollTop = 0;
    const heroSection = document.querySelector('.hero-section');
    
    window.addEventListener('scroll', function() {
        const currentScroll = window.pageYOffset || document.documentElement.scrollTop;
        
        // Add/remove scroll class for styling
        if (currentScroll > 100) {
            document.body.classList.add('scrolled');
        } else {
            document.body.classList.remove('scrolled');
        }
        
        lastScrollTop = currentScroll <= 0 ? 0 : currentScroll;
    });

    // ==========================================
    // Statistics Counter Animation
    // ==========================================
    const statNumbers = document.querySelectorAll('.stat-number');
    let hasAnimated = false;
    
    const animateCounters = () => {
        if (hasAnimated) return;
        
        statNumbers.forEach(stat => {
            const finalNumber = stat.textContent;
            const isPlus = finalNumber.includes('+');
            const number = parseInt(finalNumber.replace(/\D/g, ''));
            const duration = 500;
            const increment = number / (duration / 16);
            let current = 0;
            
            const counter = setInterval(() => {
                current += increment;
                if (current >= number) {
                    current = number;
                    clearInterval(counter);
                }
                stat.textContent = Math.floor(current) + (isPlus ? '+' : '');
            }, 16);
        });
        
        hasAnimated = true;
    };
    
    // Trigger counter animation when stats section comes into view
    const statsObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateCounters();
            }
        });
    }, { threshold: 0.5 });
    
    const aboutStats = document.querySelector('.about-stats');
    if (aboutStats) {
        statsObserver.observe(aboutStats);
    }

    // ==========================================
    // Utility Functions
    // ==========================================
    
    /**
     * Show notification to user
     * @param {string} message - The message to display
     * @param {string} type - 'success' or 'error'
     */
    function showNotification(message, type = 'info') {
        // Remove existing notifications
        const existingNotification = document.querySelector('.notification');
        if (existingNotification) {
            existingNotification.remove();
        }
        
        // Create new notification
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <span class="notification-message">${message}</span>
                <button class="notification-close">&times;</button>
            </div>
        `;
        
        // Add styles
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            background: ${type === 'success' ? '#28a745' : '#dc3545'};
            color: white;
            padding: 16px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateX(100%);
            transition: transform 0.3s ease;
            max-width: 400px;
        `;
        
        document.body.appendChild(notification);
        
        // Show notification
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 100);
        
        // Add close functionality
        const closeBtn = notification.querySelector('.notification-close');
        closeBtn.addEventListener('click', () => {
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => notification.remove(), 300);
        });
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => notification.remove(), 300);
            }
        }, 5000);
    }

    /**
     * Debounce function for performance optimization
     * @param {Function} func - Function to debounce
     * @param {number} wait - Wait time in milliseconds
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    // Optimize scroll events
    const debouncedScroll = debounce(() => {
        // Any heavy scroll operations can be placed here
    }, 10);
    
    window.addEventListener('scroll', debouncedScroll);
});

// ==========================================
// Additional CSS for notifications (added via JavaScript)
// ==========================================
const notificationStyles = `
    .notification-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }
    
    .notification-message {
        flex: 1;
        font-weight: 500;
    }
    
    .notification-close {
        background: none;
        border: none;
        color: white;
        font-size: 20px;
        cursor: pointer;
        padding: 0;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: background-color 0.2s ease;
    }
    
    .notification-close:hover {
        background-color: rgba(255, 255, 255, 0.2);
    }
`;

// Inject notification styles
const styleSheet = document.createElement('style');
styleSheet.textContent = notificationStyles;
document.head.appendChild(styleSheet);
