/**
 * DS-Allroundservice - Sticky Header Functionality
 * Shows/hides sticky navigation based on scroll direction
 */

document.addEventListener('DOMContentLoaded', function() {
    
    let lastScrollPosition = 0;
    const stickyHeader = document.querySelector('.sticky-header');
    const scrollThreshold = 100; // Show after scrolling down 100px
    const scrollDelta = 5; // Minimum scroll difference to trigger

    if (!stickyHeader) {
        console.warn('Sticky header element not found!'); // Debug log
        return; // Exit if sticky header element doesn't exist
    }

    window.addEventListener('scroll', () => {
        const currentScrollPosition = window.pageYOffset || document.documentElement.scrollTop;
        
        // Avoid processing if scroll difference is too small
        if (Math.abs(currentScrollPosition - lastScrollPosition) < scrollDelta) {
            return;
        }

        // Show header when scrolling up and past threshold
        if (currentScrollPosition > scrollThreshold) {
            if (currentScrollPosition < lastScrollPosition) {
                // Scrolling up - show header
                stickyHeader.classList.add('visible');
            } else {
                // Scrolling down - hide header
                stickyHeader.classList.remove('visible');
            }
        } else {
            // Near top of page - hide header
            stickyHeader.classList.remove('visible');
        }

        lastScrollPosition = currentScrollPosition;
    });

    // Add smooth scroll behavior to sticky navigation links that start with #
    const stickyLinks = stickyHeader.querySelectorAll('a[href^="#"]');
    stickyLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href').substring(1);
            const targetElement = document.getElementById(targetId);
            
            if (targetElement) {
                e.preventDefault();
                const headerHeight = 80; // Account for sticky header height
                const targetPosition = targetElement.offsetTop - headerHeight;
                
                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });
            }
        });
    });

    // Add active state management for single-page navigation
    const observeSection = (entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const sectionId = entry.target.id;
                const navLinks = stickyHeader.querySelectorAll('.sticky-menu a');
                
                navLinks.forEach(link => {
                    link.classList.remove('active');
                    if (link.getAttribute('href') === `#${sectionId}`) {
                        link.classList.add('active');
                    }
                });
            }
        });
    };

    // Create intersection observer for section highlighting (if sections exist)
    const sections = document.querySelectorAll('section[id]');
    if (sections.length > 0) {
        const sectionObserver = new IntersectionObserver(observeSection, {
            rootMargin: '-20% 0px -80% 0px'
        });

        sections.forEach(section => {
            sectionObserver.observe(section);
        });
    }

    // Add click handler for phone button (optional analytics tracking)
    const phoneBtn = stickyHeader.querySelector('.sticky-phone-btn');
    if (phoneBtn) {
        phoneBtn.addEventListener('click', function() {
            // Optional: Track phone clicks for analytics
        });
    }
});
