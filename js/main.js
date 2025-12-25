/**
 * Main logic for GOV-ASSIST Portal
 * Handles animations, form validation, and UI interactions
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Intersection Observer for Scroll Animations
    const observerOptions = {
        threshold: 0.15,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('active');
                // Optional: Unobserve after animation is triggered
                // observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    document.querySelectorAll('.reveal').forEach((el) => observer.observe(el));

    // 2. Form Validation Logic
    const validateForm = (formId) => {
        const form = document.getElementById(formId);
        if (!form) return;

        form.addEventListener('submit', (e) => {
            let isValid = true;
            const requiredInputs = form.querySelectorAll('[required]');
            
            // Clear previous errors if any (simple visual reset)
            requiredInputs.forEach(input => {
                input.style.borderColor = '#e2e8f0';
                input.style.backgroundColor = '#fdfdfd';
            });

            requiredInputs.forEach(input => {
                const value = input.value.trim();
                
                // General empty check
                if (!value) {
                    markInvalid(input);
                    isValid = false;
                }

                // Email specific validation
                if (input.type === 'email' && value) {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(value)) {
                        markInvalid(input);
                        isValid = false;
                    }
                }

                // Phone specific validation
                if (input.type === 'tel' && value) {
                    const phoneRegex = /^\(?([0-9]{3})\)?[-. ]?([0-9]{3})[-. ]?([0-9]{4})$/;
                    if (!phoneRegex.test(value)) {
                        markInvalid(input);
                        isValid = false;
                    }
                }
                
                // SSN specific validation (9 digits or XXX-XX-XXXX)
                if (input.id === 'ssn' && value) {
                    const ssnRegex = /^(\d{3}-\d{2}-\d{4})|(\d{9})$/;
                    if (!ssnRegex.test(value)) {
                        markInvalid(input);
                        isValid = false;
                    }
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert('Please correct the highlighted fields before submitting.');
                
                // Scroll to first error
                const firstError = form.querySelector('.input-error');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    };

    const markInvalid = (input) => {
        input.style.borderColor = '#e53e3e';
        input.style.backgroundColor = '#fff5f5';
        input.classList.add('input-error');
    };

    // Initialize validations
    validateForm('erap-form');
    validateForm('heloc-form');

    // 3. Header Scroll Effect
    const nav = document.querySelector('nav');
    window.addEventListener('scroll', () => {
        if (window.scrollY > 50) {
            nav.style.boxShadow = '0 4px 12px rgba(0,0,0,0.08)';
            nav.style.padding = '10px 0';
        } else {
            nav.style.boxShadow = '0 2px 4px rgba(0,0,0,0.02)';
            nav.style.padding = '15px 0';
        }
    });
});
