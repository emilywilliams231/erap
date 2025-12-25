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
            }
        });
    }, observerOptions);

    document.querySelectorAll('.reveal').forEach((el) => observer.observe(el));

    // 2. Form Validation Logic
    const markInvalid = (input) => {
        input.style.borderColor = '#e53e3e';
        input.style.backgroundColor = '#fff5f5';
        input.classList.add('input-error');
        const error = input.parentElement.querySelector('.inline-error');
        if (error) error.style.display = 'block';
    };

    const clearInvalid = (input) => {
        input.style.borderColor = '#e2e8f0';
        input.style.backgroundColor = '#fdfdfd';
        input.classList.remove('input-error');
        const error = input.parentElement.querySelector('.inline-error');
        if (error) error.style.display = 'none';
    };

    const validateForm = (formId) => {
        const form = document.getElementById(formId);
        if (!form) return;

        const submitBtn = form.querySelector('.btn-submit');

        const runValidation = (e) => {
            let isValid = true;
            const requiredInputs = form.querySelectorAll('[required]');
            requiredInputs.forEach(input => clearInvalid(input));

            requiredInputs.forEach(input => {
                const value = (input.type === 'checkbox') ? (input.checked ? 'checked' : '') : input.value.trim();
                
                if (!value) {
                    markInvalid(input);
                    isValid = false;
                }

                if (input.type === 'email' && value) {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(value)) {
                        markInvalid(input);
                        isValid = false;
                    }
                }

                if (input.type === 'tel' && value) {
                    const phoneRegex = /^\(?([0-9]{3})\)?[-. ]?([0-9]{3})[-. ]?([0-9]{4})$/;
                    if (!phoneRegex.test(value)) {
                        markInvalid(input);
                        isValid = false;
                    }
                }
                
                if (input.id === 'ssn' && value) {
                    const ssnRegex = /^(\d{3}-\d{2}-\d{4})|(\d{9})$/;
                    if (!ssnRegex.test(value)) {
                        markInvalid(input);
                        isValid = false;
                    }
                }
            });

            const utilityGroups = form.querySelectorAll('.utility-options');
            utilityGroups.forEach(group => {
                const boxes = group.querySelectorAll('input[type="checkbox"]');
                const anyChecked = Array.from(boxes).some(box => box.checked);
                if (!anyChecked) {
                    boxes.forEach(box => markInvalid(box));
                    isValid = false;
                }
            });

            if (submitBtn) submitBtn.disabled = !isValid;
            return isValid;
        };

        form.addEventListener('input', () => runValidation());
        form.addEventListener('change', () => runValidation());

        form.addEventListener('submit', (e) => {
            const ok = runValidation();
            if (!ok) {
                e.preventDefault();
                alert('Please correct the highlighted fields before submitting.');
                const firstError = form.querySelector('.input-error');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            } else if (submitBtn) {
                submitBtn.textContent = 'Submitting...';
                submitBtn.disabled = true;
            }
        });
    };

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

    // 4. Accordions
    document.querySelectorAll('.accordion-trigger').forEach(trigger => {
        trigger.addEventListener('click', () => {
            const expanded = trigger.getAttribute('aria-expanded') === 'true';
            trigger.setAttribute('aria-expanded', String(!expanded));
            const content = trigger.nextElementSibling;
            if (content) {
                content.classList.toggle('open', !expanded);
            }
        });
    });

    // 5. File input labels
    document.querySelectorAll('[data-file-input]').forEach(input => {
        input.addEventListener('change', (event) => {
            const target = event.target;
            const container = target.closest('.upload-card');
            const label = container ? container.querySelector('.file-name') : null;
            const selectedBadge = container ? container.querySelector('.upload-selected') : null;
            if (!label) return;

            if (target.files && target.files.length > 0) {
                const names = Array.from(target.files).map(f => f.name).join(', ');
                label.textContent = names;
                container.classList.add('has-file');
                if (selectedBadge) selectedBadge.style.display = 'inline';
            } else {
                label.textContent = 'No file chosen';
                container.classList.remove('has-file');
                if (selectedBadge) selectedBadge.style.display = 'none';
            }
        });
    });

    // 6. Drag-and-drop highlighting
    document.querySelectorAll('[data-dropzone]').forEach(zone => {
        ['dragenter', 'dragover'].forEach(evt => {
            zone.addEventListener(evt, (e) => {
                e.preventDefault();
                zone.style.borderColor = '#2b6cb0';
                zone.style.backgroundColor = '#edf2f7';
            });
        });
        ['dragleave', 'drop'].forEach(evt => {
            zone.addEventListener(evt, (e) => {
                e.preventDefault();
                zone.style.borderColor = '#cbd5e0';
                zone.style.backgroundColor = '#f7fafc';
            });
        });
        zone.addEventListener('drop', (e) => {
            const input = zone.querySelector('input[type="file"]');
            if (input) {
                input.files = e.dataTransfer.files;
                input.dispatchEvent(new Event('change'));
            }
        });
    });

    // 7. CSRF token fetch
    fetch('includes/csrf-token.php')
        .then(res => res.json())
        .then(data => {
            const token = data.token;
            const erapToken = document.getElementById('csrf_token');
            const helocToken = document.getElementById('csrf_token_heloc');
            if (erapToken) erapToken.value = token;
            if (helocToken) helocToken.value = token;
        })
        .catch(() => {});
});
