document.addEventListener('DOMContentLoaded', function() {
    const billingToggle = document.getElementById('billing-toggle');
    const monthlyOptions = billingToggle.querySelectorAll('[data-period="monthly"]');
    const yearlyOptions = billingToggle.querySelectorAll('[data-period="yearly"]');
    const monthlyPrices = document.querySelectorAll('.monthly-price');
    const yearlyPrices = document.querySelectorAll('.yearly-price');

    function switchBilling(period) {
        // Update toggle buttons
        monthlyOptions.forEach(opt => opt.classList.toggle('active', period === 'monthly'));
        yearlyOptions.forEach(opt => opt.classList.toggle('active', period === 'yearly'));

        // Update prices
        monthlyPrices.forEach(price => {
            price.style.display = period === 'monthly' ? 'block' : 'none';
        });
        yearlyPrices.forEach(price => {
            price.style.display = period === 'yearly' ? 'block' : 'none';
        });
    }

    billingToggle.addEventListener('click', function(e) {
        const period = e.target.closest('[data-period]')?.dataset.period;
        if (period) {
            switchBilling(period);
        }
    });
});
