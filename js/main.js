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

    const validateForm = (formId, options = {}) => {
        const form = document.getElementById(formId);
        if (!form) return;

        const submitBtn = form.querySelector('.btn-submit');
        const statusEl = form.querySelector('.form-status');

        const runValidation = (showErrors = false) => {
            let isValid = true;
            const requiredInputs = form.querySelectorAll('[required]');
            requiredInputs.forEach(input => clearInvalid(input));

            requiredInputs.forEach(input => {
                const value = (input.type === 'checkbox') ? (input.checked ? 'checked' : '') : input.value.trim();
                
                if (!value) {
                    isValid = false;
                    if (showErrors) {
                        markInvalid(input);
                    }
                }

                if (input.type === 'email' && value) {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(value)) {
                        isValid = false;
                        if (showErrors) {
                            markInvalid(input);
                        }
                    }
                }

                if (input.type === 'tel' && value) {
                    const phoneRegex = /^\(?([0-9]{3})\)?[-. ]?([0-9]{3})[-. ]?([0-9]{4})$/;
                    if (!phoneRegex.test(value)) {
                        isValid = false;
                        if (showErrors) {
                            markInvalid(input);
                        }
                    }
                }
                
                if (input.id === 'ssn' && value) {
                    const ssnRegex = /^(\d{3}-\d{2}-\d{4})|(\d{9})$/;
                    if (!ssnRegex.test(value)) {
                        isValid = false;
                        if (showErrors) {
                            markInvalid(input);
                        }
                    }
                }
            });

            const utilityGroups = form.querySelectorAll('.utility-options');
            utilityGroups.forEach(group => {
                const boxes = group.querySelectorAll('input[type="checkbox"]');
                const anyChecked = Array.from(boxes).some(box => box.checked);
                if (!anyChecked) {
                    isValid = false;
                    if (showErrors) {
                        boxes.forEach(box => markInvalid(box));
                    }
                }
            });

            if (submitBtn) submitBtn.disabled = !isValid;
            if (statusEl) {
                const errors = form.querySelectorAll('.input-error').length;
                if (isValid) {
                    statusEl.textContent = 'All required items complete. Ready to submit.';
                    statusEl.style.color = 'var(--success)';
                } else {
                    statusEl.textContent = `${errors || 'Some'} required item${errors === 1 ? '' : 's'} remaining.`;
                    statusEl.style.color = 'var(--text-muted)';
                }
            }
            return isValid;
        };

        form.addEventListener('input', () => runValidation(false));
        form.addEventListener('change', () => runValidation(false));

        form.addEventListener('submit', (e) => {
            const ok = runValidation(true);
            if (!ok) {
                e.preventDefault();
                alert('Please correct the highlighted fields before submitting.');
                const firstError = form.querySelector('.input-error');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            } else {
                const intercepted = typeof options.onValidSubmit === 'function'
                    ? options.onValidSubmit({ event: e, form, submitBtn, statusEl })
                    : false;

                if (intercepted) {
                    return;
                }

                if (submitBtn) {
                    submitBtn.textContent = 'Submitting...';
                    submitBtn.disabled = true;
                }
            }
        });

        runValidation();
    };

    const erapModal = document.getElementById('erap-urs-modal');
    const erapModalBackdrop = document.getElementById('erap-urs-modal-backdrop');
    const erapProceed = document.getElementById('erap-urs-proceed');
    const erapSkip = document.getElementById('erap-urs-skip');
    const erapForm = document.getElementById('erap-form');

    const toggleErapModal = (open) => {
        if (!erapModal || !erapModalBackdrop) return;
        erapModal.classList.toggle('open', open);
        erapModalBackdrop.classList.toggle('open', open);
        document.body.style.overflow = open ? 'hidden' : '';
    };

    if (erapForm) {
        validateForm('erap-form', {
            onValidSubmit: ({ event, submitBtn }) => {
                event.preventDefault();
                toggleErapModal(true);
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Submit Official Application';
                }
                return true; // intercept submit
            }
        });
    } else {
        validateForm('erap-form');
    }

    validateForm('heloc-form');
    validateForm('erap-urs-form');

    if (erapProceed) {
        erapProceed.addEventListener('click', () => {
            toggleErapModal(false);
            window.location.href = 'erap-urs-details.html';
        });
    }

    if (erapSkip) {
        erapSkip.addEventListener('click', () => {
            toggleErapModal(false);
            window.location.href = 'success.html';
        });
    }

    if (erapModalBackdrop) {
        erapModalBackdrop.addEventListener('click', () => toggleErapModal(false));
    }

    // Stepper navigation and active state
    const initStepper = (formId) => {
        const form = document.getElementById(formId);
        const stepper = document.querySelector('.stepper');
        if (!form || !stepper) return;

        const stepButtons = stepper.querySelectorAll('.step');
        const sections = form.querySelectorAll('[data-step]');

        stepButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const target = document.querySelector(btn.dataset.target);
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });

        const handleActiveStep = () => {
            let currentId = '';
            sections.forEach(section => {
                const rect = section.getBoundingClientRect();
                if (rect.top <= 140 && rect.bottom >= 140) {
                    currentId = `#${section.id}`;
                }
            });
            stepButtons.forEach(btn => {
                btn.classList.toggle('active', btn.dataset.target === currentId);
            });
        };

        window.addEventListener('scroll', handleActiveStep);
        handleActiveStep();
    };

    initStepper('erap-form');
    initStepper('heloc-form');

    // 3. Animated counters in impact section
    const formatNumber = (num, decimals) => {
        const fixed = num.toFixed(decimals);
        const parts = fixed.split('.');
        parts[0] = Number(parts[0]).toLocaleString();
        return parts.join(decimals > 0 ? '.' : '');
    };

    const animateCounter = (el) => {
        const target = parseFloat(el.dataset.target || '0');
        const decimals = parseInt(el.dataset.decimals || '0', 10);
        const prefix = el.dataset.prefix || '';
        const suffix = el.dataset.suffix || '';
        const duration = 1500;
        const startTime = performance.now();

        const tick = (now) => {
            const progress = Math.min((now - startTime) / duration, 1);
            const current = target * progress;
            el.textContent = `${prefix}${formatNumber(current, decimals)}${suffix}`;
            if (progress < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    };

    const counterObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateCounter(entry.target);
                counterObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.4 });

    document.querySelectorAll('.counter').forEach(counter => counterObserver.observe(counter));

    // 4. Header Scroll Effect
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

    // 5. Accordions
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

    // 6. File input labels
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

    // 7. Drag-and-drop highlighting
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

    // 8. Clear file buttons
    document.querySelectorAll('.upload-card').forEach(card => {
        const clearBtn = card.querySelector('.clear-file');
        const input = card.querySelector('input[type="file"]');
        if (clearBtn && input) {
            clearBtn.addEventListener('click', () => {
                input.value = '';
                input.dispatchEvent(new Event('change'));
            });
        }
    });

    // 9. CSRF token fetch
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
