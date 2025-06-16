/**
 * Modern Pricing Page JavaScript
 * Handles billing toggle, FAQ interactions, and animations
 */

class PricingPage {
    constructor() {
        this.init();
    }

    init() {
        this.initBillingToggle();
        this.initFAQ();
        this.initAnimations();
        this.initUsageBars();
    }

    /**
     * Initialize billing toggle functionality
     */
    initBillingToggle() {
        const billingToggle = document.getElementById('billing-toggle');
        if (!billingToggle) return;

        const monthlyOptions = billingToggle.querySelectorAll('[data-period="monthly"]');
        const yearlyOptions = billingToggle.querySelectorAll('[data-period="yearly"]');

        // Set initial state
        this.currentBillingPeriod = 'monthly';
        document.body.classList.add('billing-monthly');

        // Handle toggle clicks
        billingToggle.addEventListener('click', (e) => {
            const periodElement = e.target.closest('[data-period]');
            if (!periodElement) return;

            const period = periodElement.dataset.period;
            if (period === this.currentBillingPeriod) return;

            this.switchBilling(period);
        });

        // Handle keyboard navigation
        billingToggle.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
                e.preventDefault();
                const newPeriod = this.currentBillingPeriod === 'monthly' ? 'yearly' : 'monthly';
                this.switchBilling(newPeriod);
            }
        });
    }

    /**
     * Switch between monthly and yearly billing
     */
    switchBilling(period) {
        const billingToggle = document.getElementById('billing-toggle');
        const monthlyOptions = billingToggle.querySelectorAll('[data-period="monthly"]');
        const yearlyOptions = billingToggle.querySelectorAll('[data-period="yearly"]');

        // Update current period
        this.currentBillingPeriod = period;

        // Update toggle buttons
        monthlyOptions.forEach(opt => {
            opt.classList.toggle('pricing-hero__billing-option--active', period === 'monthly');
        });
        yearlyOptions.forEach(opt => {
            opt.classList.toggle('pricing-hero__billing-option--active', period === 'yearly');
        });

        // Update body class for CSS targeting
        document.body.classList.remove('billing-monthly', 'billing-yearly');
        document.body.classList.add(`billing-${period}`);

        // Animate price changes
        this.animatePriceChanges();
    }

    /**
     * Animate price changes when switching billing periods
     */
    animatePriceChanges() {
        const priceElements = document.querySelectorAll('.pricing-plan__price');

        priceElements.forEach(price => {
            price.style.transform = 'scale(1.05)';
            price.style.transition = 'transform 0.2s ease';

            setTimeout(() => {
                price.style.transform = 'scale(1)';
            }, 200);
        });
    }

    /**
     * Initialize FAQ functionality
     */
    initFAQ() {
        const faqItems = document.querySelectorAll('[data-faq-item]');

        faqItems.forEach(item => {
            const trigger = item.querySelector('[data-faq-trigger]');
            if (!trigger) return;

            trigger.addEventListener('click', () => {
                this.toggleFAQ(item);
            });

            // Keyboard accessibility
            trigger.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.toggleFAQ(item);
                }
            });
        });
    }

    /**
     * Toggle FAQ item
     */
    toggleFAQ(item) {
        const isActive = item.classList.contains('active');

        // Close all other FAQ items
        document.querySelectorAll('[data-faq-item].active').forEach(activeItem => {
            if (activeItem !== item) {
                activeItem.classList.remove('active');
            }
        });

        // Toggle current item
        item.classList.toggle('active', !isActive);

        // Update aria-expanded for accessibility
        const trigger = item.querySelector('[data-faq-trigger]');
        trigger.setAttribute('aria-expanded', !isActive);
    }

    /**
     * Initialize scroll-triggered animations
     */
    initAnimations() {
        // Check if IntersectionObserver is supported
        if (!('IntersectionObserver' in window)) {
            // Fallback: show all elements immediately
            document.querySelectorAll('.pricing-plan').forEach(el => {
                el.style.opacity = '1';
                el.style.transform = 'translateY(0)';
            });
            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate-in');
                        observer.unobserve(entry.target);
                    }
                });
            },
            {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            }
        );

        // Observe pricing plans
        document.querySelectorAll('.pricing-plan').forEach(plan => {
            observer.observe(plan);
        });

        // Observe FAQ items
        document.querySelectorAll('[data-faq-item]').forEach(item => {
            observer.observe(item);
        });
    }

    /**
     * Initialize usage bar animations
     */
    initUsageBars() {
        const usageBars = document.querySelectorAll('.pricing-plan__usage-fill');

        if (!usageBars.length) return;

        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const bar = entry.target;
                        const width = bar.dataset.width || '60';

                        // Animate the bar fill
                        setTimeout(() => {
                            bar.style.width = `${width}%`;
                        }, 500);

                        observer.unobserve(bar);
                    }
                });
            },
            { threshold: 0.5 }
        );

        usageBars.forEach(bar => observer.observe(bar));
    }
}

/**
 * Enhanced CTA button interactions
 */
class CTAEnhancer {
    constructor() {
        this.init();
    }

    init() {
        this.enhanceCTAButtons();
        this.addHoverEffects();
    }

    enhanceCTAButtons() {
        const ctaButtons = document.querySelectorAll('.pricing-plan__cta');

        ctaButtons.forEach(btn => {
            // Add loading state functionality
            btn.addEventListener('click', (e) => {
                if (btn.classList.contains('loading')) {
                    e.preventDefault();
                    return;
                }

                // Add loading state
                this.setLoadingState(btn, true);

                // Remove loading state after navigation (in case of same-page interaction)
                setTimeout(() => {
                    this.setLoadingState(btn, false);
                }, 3000);
            });

            // Add ripple effect
            btn.addEventListener('click', (e) => {
                this.createRipple(e, btn);
            });
        });
    }

    setLoadingState(btn, isLoading) {
        if (isLoading) {
            btn.classList.add('loading');
            btn.setAttribute('aria-busy', 'true');

            // Store original content
            const text = btn.querySelector('.pricing-plan__cta-text');
            const icon = btn.querySelector('.pricing-plan__cta-icon');

            if (text) {
                text.dataset.originalText = text.textContent;
                text.textContent = 'Chargement...';
            }

            if (icon) {
                icon.style.display = 'none';
            }
        } else {
            btn.classList.remove('loading');
            btn.setAttribute('aria-busy', 'false');

            // Restore original content
            const text = btn.querySelector('.pricing-plan__cta-text');
            const icon = btn.querySelector('.pricing-plan__cta-icon');

            if (text && text.dataset.originalText) {
                text.textContent = text.dataset.originalText;
                delete text.dataset.originalText;
            }

            if (icon) {
                icon.style.display = '';
            }
        }
    }

    createRipple(event, button) {
        const ripple = document.createElement('span');
        const rect = button.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = event.clientX - rect.left - size / 2;
        const y = event.clientY - rect.top - size / 2;

        ripple.style.cssText = `
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: scale(0);
            animation: ripple 0.6s linear;
            width: ${size}px;
            height: ${size}px;
            left: ${x}px;
            top: ${y}px;
            pointer-events: none;
        `;

        button.style.position = 'relative';
        button.style.overflow = 'hidden';
        button.appendChild(ripple);

        // Remove ripple after animation
        setTimeout(() => {
            ripple.remove();
        }, 600);
    }

    addHoverEffects() {
        const planCards = document.querySelectorAll('.pricing-plan');

        planCards.forEach(card => {
            card.addEventListener('mouseenter', () => {
                this.highlightCard(card, true);
            });

            card.addEventListener('mouseleave', () => {
                this.highlightCard(card, false);
            });
        });
    }

    highlightCard(card, isHovered) {
        const metrics = card.querySelectorAll('.pricing-plan__metric');
        const features = card.querySelectorAll('.pricing-plan__feature');

        metrics.forEach((metric, index) => {
            setTimeout(() => {
                metric.style.transform = isHovered ? 'translateY(-2px)' : '';
            }, index * 50);
        });

        features.forEach((feature, index) => {
            setTimeout(() => {
                feature.style.transform = isHovered ? 'translateX(4px)' : '';
            }, index * 30);
        });
    }
}

/**
 * Pricing comparison table enhancements
 */
class ComparisonTableEnhancer {
    constructor() {
        this.init();
    }

    init() {
        this.makeTableResponsive();
        this.addColumnHighlighting();
    }

    makeTableResponsive() {
        const table = document.querySelector('.pricing-comparison__table');
        if (!table) return;

        // Add horizontal scroll for mobile
        const wrapper = table.closest('.pricing-comparison__table-wrapper');
        if (wrapper) {
            wrapper.style.overflowX = 'auto';
            wrapper.style.WebkitOverflowScrolling = 'touch';
        }
    }

    addColumnHighlighting() {
        const table = document.querySelector('.pricing-comparison__table');
        if (!table) return;

        const columnHeaders = table.querySelectorAll('.pricing-comparison__plan-column');

        columnHeaders.forEach((header, index) => {
            header.addEventListener('mouseenter', () => {
                this.highlightColumn(index + 1, true);
            });

            header.addEventListener('mouseleave', () => {
                this.highlightColumn(index + 1, false);
            });
        });
    }

    highlightColumn(columnIndex, isHighlighted) {
        const table = document.querySelector('.pricing-comparison__table');
        if (!table) return;

        const cells = table.querySelectorAll(`td:nth-child(${columnIndex + 1}), th:nth-child(${columnIndex + 1})`);

        cells.forEach(cell => {
            cell.style.backgroundColor = isHighlighted ? 'rgba(59, 130, 246, 0.05)' : '';
            cell.style.transform = isHighlighted ? 'scale(1.02)' : '';
        });
    }
}

/**
 * Initialize everything when DOM is ready
 */
document.addEventListener('DOMContentLoaded', () => {
    // Initialize main pricing functionality
    new PricingPage();

    // Initialize CTA enhancements
    new CTAEnhancer();

    // Initialize comparison table enhancements
    new ComparisonTableEnhancer();

    // Add CSS for animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes ripple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
        
        .pricing-plan {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .pricing-plan.animate-in {
            opacity: 1 !important;
            transform: translateY(0) !important;
        }
        
        .pricing-plan__metric {
            transition: transform 0.2s ease;
        }
        
        .pricing-plan__feature {
            transition: transform 0.2s ease;
        }
        
        .pricing-plan__cta.loading {
            pointer-events: none;
            opacity: 0.8;
        }
        
        .pricing-plan__cta.loading .pricing-plan__cta-text {
            opacity: 0.7;
        }
    `;
    document.head.appendChild(style);
});

// Export for potential module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { PricingPage, CTAEnhancer, ComparisonTableEnhancer };
}
