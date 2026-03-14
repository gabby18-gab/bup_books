/**
 * BUP BOOKS - Unified JavaScript File
 * Contains all functionality for the BUP Book Resale Platform
 * Includes: Add to Cart, UI Interactions, Form Validation, Dark Mode, etc.
 * 
 * Version: 1.0.0
 * Author: BUP Platform Team
 */

// ============================================
// GLOBAL VARIABLES & CONFIGURATION
// ============================================

const BUP = {
    config: {
        toastDuration: 3000,        // Toast message display duration (ms)
        searchDebounceDelay: 500,    // Search input debounce delay (ms)
        apiEndpoints: {
            addToCart: 'add-to-cart.php',
            updateCart: 'cart.php',
            applyCoupon: 'cart.php',
            search: 'browse-books.php'
        },
        debug: false                  // Set to true for console logging
    },
    
    state: {
        loading: false,
        currentUser: null,
        cartCount: 0,
        darkMode: false
    }
};

// ============================================
// INITIALIZATION & DOM READY
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    BUP.utils.log('BUP Platform JS initialized');
    
    // Initialize all components
    BUP.init();
});

BUP.init = function() {
    // Check for dark mode preference
    BUP.darkMode.init();
    
    // Initialize sidebar if exists
    BUP.sidebar.init();
    
    // Initialize cart functionality
    BUP.cart.init();
    
    // Initialize quantity controls
    BUP.quantityControls.init();
    
    // Initialize search functionality
    BUP.search.init();
    
    // Initialize profile dropdown
    BUP.profile.init();
    
    // Initialize forms and validation
    BUP.forms.init();
    
    // Initialize AOS animation if available
    if (typeof AOS !== 'undefined') {
        AOS.init({
            duration: 800,
            once: true,
            offset: 100
        });
    }
    
    // Hide loading spinner on page load
    BUP.ui.hideLoading();
    
    // Add loading spinner to all navigation links
    BUP.navigation.init();
};

// ============================================
// UTILITY FUNCTIONS
// ============================================

BUP.utils = {
    log: function(message, type = 'info') {
        if (BUP.config.debug) {
            const prefix = '[BUP]';
            switch(type) {
                case 'error':
                    console.error(prefix, message);
                    break;
                case 'warn':
                    console.warn(prefix, message);
                    break;
                default:
                    console.log(prefix, message);
            }
        }
    },
    
    formatMoney: function(amount) {
        return '$' + parseFloat(amount).toFixed(2);
    },
    
    getInitials: function(name) {
        if (!name) return '?';
        return name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
    },
    
    debounce: function(func, delay) {
        let timeout;
        return function() {
            const context = this;
            const args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(context, args), delay);
        };
    },
    
    getCookie: function(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
    },
    
    setCookie: function(name, value, days = 365) {
        const date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        document.cookie = `${name}=${value}; expires=${date.toUTCString()}; path=/`;
    },
    
    getElement: function(selector) {
        return document.querySelector(selector);
    },
    
    getElements: function(selector) {
        return document.querySelectorAll(selector);
    },
    
    addEvent: function(element, event, handler) {
        if (element) {
            element.addEventListener(event, handler);
        }
    }
};

// ============================================
// UI COMPONENTS
// ============================================

BUP.ui = {
    showLoading: function() {
        const spinner = BUP.utils.getElement('#loadingSpinner');
        if (spinner) {
            spinner.style.display = 'flex';
            BUP.state.loading = true;
        }
    },
    
    hideLoading: function() {
        const spinner = BUP.utils.getElement('#loadingSpinner');
        if (spinner) {
            spinner.style.display = 'none';
            BUP.state.loading = false;
        }
    },
    
    showToast: function(message, type = 'success') {
        const toast = BUP.utils.getElement('#toastContainer');
        const toastMessage = BUP.utils.getElement('#toastMessage');
        const toastIcon = toast?.querySelector('i');
        
        if (!toast || !toastMessage) {
            // Create toast if it doesn't exist
            this.createToast(message, type);
            return;
        }
        
        toastMessage.textContent = message;
        
        // Set color based on type
        const toastElement = toast.querySelector('.toast-message');
        if (toastElement) {
            switch(type) {
                case 'success':
                    toastElement.style.background = '#28a745';
                    if (toastIcon) toastIcon.className = 'bi bi-check-circle-fill';
                    break;
                case 'error':
                    toastElement.style.background = '#dc3545';
                    if (toastIcon) toastIcon.className = 'bi bi-exclamation-triangle-fill';
                    break;
                case 'warning':
                    toastElement.style.background = '#ffc107';
                    if (toastIcon) toastIcon.className = 'bi bi-exclamation-circle-fill';
                    break;
                case 'info':
                    toastElement.style.background = '#17a2b8';
                    if (toastIcon) toastIcon.className = 'bi bi-info-circle-fill';
                    break;
            }
        }
        
        toast.style.display = 'block';
        
        // Auto hide after duration
        setTimeout(() => {
            toast.style.display = 'none';
        }, BUP.config.toastDuration);
        
        // Hide on click
        toast.addEventListener('click', function() {
            this.style.display = 'none';
        });
    },
    
    createToast: function(message, type = 'success') {
        // Create toast container if it doesn't exist
        let toastContainer = BUP.utils.getElement('#toastContainer');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toastContainer';
            toastContainer.className = 'toast-container';
            document.body.appendChild(toastContainer);
        }
        
        // Create toast message
        const toast = document.createElement('div');
        toast.className = 'toast-message';
        
        // Set icon based on type
        let icon = 'bi-check-circle-fill';
        let bgColor = '#28a745';
        
        switch(type) {
            case 'success':
                icon = 'bi-check-circle-fill';
                bgColor = '#28a745';
                break;
            case 'error':
                icon = 'bi-exclamation-triangle-fill';
                bgColor = '#dc3545';
                break;
            case 'warning':
                icon = 'bi-exclamation-circle-fill';
                bgColor = '#ffc107';
                break;
            case 'info':
                icon = 'bi-info-circle-fill';
                bgColor = '#17a2b8';
                break;
        }
        
        toast.style.background = bgColor;
        toast.innerHTML = `
            <i class="bi ${icon}"></i>
            <span>${message}</span>
        `;
        
        toastContainer.innerHTML = '';
        toastContainer.appendChild(toast);
        toastContainer.style.display = 'block';
        
        // Auto hide
        setTimeout(() => {
            toastContainer.style.display = 'none';
        }, BUP.config.toastDuration);
        
        // Hide on click
        toast.addEventListener('click', function() {
            toastContainer.style.display = 'none';
        });
    },
    
    showAlert: function(message, type = 'success') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        const container = BUP.utils.getElement('.main-content .container, .container');
        if (container) {
            container.insertBefore(alertDiv, container.firstChild);
            
            // Auto dismiss after 5 seconds
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }
    },
    
    disableButton: function(button, text = 'Processing...') {
        if (button) {
            button.dataset.originalText = button.innerHTML;
            button.innerHTML = `<i class="bi bi-hourglass-split me-2"></i>${text}`;
            button.disabled = true;
        }
    },
    
    enableButton: function(button) {
        if (button && button.dataset.originalText) {
            button.innerHTML = button.dataset.originalText;
            button.disabled = false;
        }
    }
};

// ============================================
// SIDEBAR FUNCTIONALITY
// ============================================

BUP.sidebar = {
    init: function() {
        const sidebar = BUP.utils.getElement('#sidebar');
        const toggleBtn = BUP.utils.getElement('#sidebarToggle');
        const toggleIcon = BUP.utils.getElement('#toggleIcon');
        const mobileBtn = BUP.utils.getElement('#mobileMenuBtn');
        
        if (toggleBtn && sidebar) {
            BUP.utils.addEvent(toggleBtn, 'click', () => this.toggle(sidebar, toggleIcon));
        }
        
        // Check localStorage for saved sidebar state
        const savedState = localStorage.getItem('sidebarCollapsed');
        if (savedState === 'true' && window.innerWidth > 992 && sidebar && toggleIcon) {
            sidebar.classList.add('collapsed');
            toggleIcon.classList.remove('bi-chevron-left');
            toggleIcon.classList.add('bi-chevron-right');
        }
        
        // Handle mobile view
        this.handleMobileView(sidebar, mobileBtn);
        window.addEventListener('resize', () => this.handleMobileView(sidebar, mobileBtn));
    },
    
    toggle: function(sidebar, toggleIcon) {
        sidebar.classList.toggle('collapsed');
        
        if (sidebar.classList.contains('collapsed')) {
            toggleIcon?.classList.remove('bi-chevron-left');
            toggleIcon?.classList.add('bi-chevron-right');
            localStorage.setItem('sidebarCollapsed', 'true');
        } else {
            toggleIcon?.classList.remove('bi-chevron-right');
            toggleIcon?.classList.add('bi-chevron-left');
            localStorage.setItem('sidebarCollapsed', 'false');
        }
    },
    
    toggleMobile: function() {
        const sidebar = BUP.utils.getElement('#sidebar');
        sidebar?.classList.toggle('active');
    },
    
    handleMobileView: function(sidebar, mobileBtn) {
        if (window.innerWidth <= 992) {
            if (mobileBtn) mobileBtn.style.display = 'flex';
            sidebar?.classList.remove('active');
        } else {
            if (mobileBtn) mobileBtn.style.display = 'none';
            sidebar?.classList.add('active');
        }
    }
};

// ============================================
// DARK MODE FUNCTIONALITY
// ============================================

BUP.darkMode = {
    init: function() {
        const toggle = BUP.utils.getElement('#darkModeToggle');
        if (!toggle) return;
        
        // Check saved preference
        const savedMode = BUP.utils.getCookie('dark_mode');
        if (savedMode === 'enabled') {
            document.body.setAttribute('data-theme', 'dark');
            toggle.checked = true;
            BUP.state.darkMode = true;
        }
        
        BUP.utils.addEvent(toggle, 'change', () => this.toggle());
    },
    
    toggle: function() {
        const isChecked = BUP.utils.getElement('#darkModeToggle')?.checked;
        const theme = isChecked ? 'dark' : 'light';
        
        document.body.setAttribute('data-theme', theme);
        BUP.utils.setCookie('dark_mode', isChecked ? 'enabled' : 'disabled');
        BUP.state.darkMode = isChecked;
        
        BUP.ui.showToast('Dark mode ' + (isChecked ? 'enabled' : 'disabled'));
    }
};

// ============================================
// PROFILE DROPDOWN
// ============================================

BUP.profile = {
    init: function() {
        const profileTrigger = BUP.utils.getElement('.profile-trigger');
        const profileMenu = BUP.utils.getElement('#profileMenu');
        
        if (profileTrigger && profileMenu) {
            BUP.utils.addEvent(profileTrigger, 'click', (e) => {
                e.stopPropagation();
                this.toggleMenu(profileMenu);
            });
            
            // Close when clicking outside
            document.addEventListener('click', (event) => {
                if (!profileTrigger.contains(event.target) && profileMenu.classList.contains('show')) {
                    profileMenu.classList.remove('show');
                }
            });
        }
    },
    
    toggleMenu: function(menu) {
        menu.classList.toggle('show');
    }
};

// ============================================
// CART FUNCTIONALITY
// ============================================

BUP.cart = {
    init: function() {
        // Initialize cart count from server if available
        const cartBadge = BUP.utils.getElement('.cart-count, .cart-badge, .cart-count-badge');
        if (cartBadge) {
            BUP.state.cartCount = parseInt(cartBadge.textContent) || 0;
        }
    },
    
    add: function(productId, productName, quantity = 1, button = null) {
        // Check if user is logged in (PHP will handle this, but we'll check for session indicator)
        const isLoggedIn = BUP.utils.getElement('.user-avatar, .profile-trigger') !== null;
        if (!isLoggedIn && window.location.pathname.includes('landing')) {
            window.location.href = 'login.php';
            return;
        }
        
        // Show loading state
        BUP.ui.showLoading();
        
        if (button) {
            BUP.ui.disableButton(button, 'Adding...');
        }
        
        // Validate quantity
        if (quantity < 1) quantity = 1;
        
        // Create form data
        const formData = new FormData();
        formData.append('product_id', productId);
        formData.append('quantity', quantity);
        
        // Determine correct path for add-to-cart.php
        let url = 'add-to-cart.php';
        if (window.location.pathname.includes('/user/')) {
            url = 'add-to-cart.php'; // Already in user directory
        } else if (window.location.pathname.includes('/admin/')) {
            BUP.ui.hideLoading();
            BUP.ui.showToast('Please login as a user to add items to cart', 'error');
            return;
        } else {
            url = 'user/add-to-cart.php'; // From root directory
        }
        
        BUP.utils.log(`Adding to cart: ${productName} (ID: ${productId}, Qty: ${quantity})`);
        
        // Send AJAX request
        fetch(url, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            BUP.ui.hideLoading();
            
            if (button) {
                BUP.ui.enableButton(button);
            }
            
            if (data.success) {
                BUP.ui.showToast(`"${productName}" added to cart!`);
                this.updateCount(data.cart_count);
            } else {
                BUP.ui.showToast(data.message || 'Failed to add item', 'error');
            }
        })
        .catch(error => {
            BUP.utils.log(error, 'error');
            BUP.ui.hideLoading();
            
            if (button) {
                BUP.ui.enableButton(button);
            }
            
            BUP.ui.showToast('Failed to add item to cart. Please try again.', 'error');
        });
    },
    
    update: function(cartId, newQuantity, element = null) {
        if (newQuantity < 1) return;
        
        // Disable buttons
        const buttons = BUP.utils.getElements(`.quantity-btn[data-cart-id="${cartId}"]`);
        buttons.forEach(btn => btn.disabled = true);
        
        // Show loading on quantity input
        const quantityInput = BUP.utils.getElement(`.quantity-input[data-cart-id="${cartId}"]`);
        if (quantityInput) {
            quantityInput.dataset.originalValue = quantityInput.value;
            quantityInput.value = '...';
        }
        
        const formData = new FormData();
        formData.append('cart_id', cartId);
        formData.append('quantity', newQuantity);
        formData.append('update_quantity', 1);
        
        let url = 'cart.php';
        if (window.location.pathname.includes('/user/')) {
            url = 'cart.php';
        } else {
            url = 'user/cart.php';
        }
        
        fetch(url, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return response.json();
            } else {
                window.location.reload();
                return null;
            }
        })
        .then(data => {
            if (data && data.success) {
                this.updateItemDisplay(cartId, data);
                BUP.ui.showToast('Cart updated successfully');
            } else if (data) {
                BUP.ui.showToast(data.message || 'Update failed', 'error');
            }
        })
        .catch(error => {
            BUP.utils.log(error, 'error');
            buttons.forEach(btn => btn.disabled = false);
            if (quantityInput) {
                quantityInput.value = quantityInput.dataset.originalValue;
            }
            BUP.ui.showToast('Failed to update quantity', 'error');
        });
    },
    
    remove: function(cartId, itemName) {
        if (!confirm(`Are you sure you want to remove "${itemName}" from your cart?`)) {
            return;
        }
        
        const formData = new FormData();
        formData.append('cart_id', cartId);
        formData.append('remove_item', 1);
        
        let url = 'cart.php?remove=' + cartId;
        window.location.href = url;
    },
    
    clear: function() {
        if (!confirm('Are you sure you want to clear your entire cart?')) {
            return;
        }
        
        let url = 'cart.php?clear=1';
        if (window.location.pathname.includes('/user/')) {
            url = 'cart.php?clear=1';
        } else {
            url = 'user/cart.php?clear=1';
        }
        
        window.location.href = url;
    },
    
    updateCount: function(count) {
        BUP.state.cartCount = count;
        
        // Update all possible cart badge elements
        const selectors = ['.cart-count', '.cart-badge', '.cart-count-badge'];
        
        selectors.forEach(selector => {
            const badges = BUP.utils.getElements(selector);
            badges.forEach(badge => {
                badge.textContent = count;
                if (count <= 0) {
                    badge.style.display = 'none';
                } else {
                    badge.style.display = 'flex';
                }
            });
        });
        
        // Create badge if it doesn't exist but count > 0
        if (count > 0) {
            const cartWrappers = BUP.utils.getElements('.cart-wrapper, .cart-icon-wrapper, .cart-badge-container');
            cartWrappers.forEach(wrapper => {
                if (!wrapper.querySelector('.cart-count, .cart-badge, .cart-count-badge')) {
                    const badge = document.createElement('span');
                    badge.className = 'cart-count';
                    badge.textContent = count;
                    wrapper.appendChild(badge);
                }
            });
        }
    },
    
    updateItemDisplay: function(cartId, data) {
        const cartItem = BUP.utils.getElement(`.cart-item[data-cart-id="${cartId}"]`);
        if (!cartItem) return;
        
        // Update quantity input
        const quantityInput = cartItem.querySelector('.quantity-input');
        if (quantityInput) quantityInput.value = data.new_quantity;
        
        // Update item subtotal
        const itemTotal = cartItem.querySelector('.item-total strong');
        if (itemTotal && data.item_total) {
            itemTotal.textContent = BUP.utils.formatMoney(data.item_total);
        }
        
        // Update cart summary
        if (data.cart_total) {
            const summaryTotal = BUP.utils.getElement('.summary-row.total span:last-child');
            if (summaryTotal) {
                summaryTotal.textContent = BUP.utils.formatMoney(data.cart_total);
            }
        }
        
        // Update cart count
        if (data.cart_count !== undefined) {
            this.updateCount(data.cart_count);
        }
        
        // Re-enable buttons
        const buttons = cartItem.querySelectorAll('.quantity-btn');
        buttons.forEach(btn => btn.disabled = false);
    },
    
    applyCoupon: function() {
        const couponInput = BUP.utils.getElement('#couponCode');
        if (!couponInput) return;
        
        const coupon = couponInput.value.trim();
        if (!coupon) {
            BUP.ui.showToast('Please enter a coupon code', 'warning');
            return;
        }
        
        const applyBtn = BUP.utils.getElement('.btn-apply-coupon');
        BUP.ui.disableButton(applyBtn, 'Applying...');
        
        const formData = new FormData();
        formData.append('coupon', coupon);
        formData.append('apply_coupon', 1);
        
        let url = 'cart.php';
        if (window.location.pathname.includes('/user/')) {
            url = 'cart.php';
        } else {
            url = 'user/cart.php';
        }
        
        fetch(url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            BUP.ui.enableButton(applyBtn);
            
            if (data.success) {
                this.updateTotalWithDiscount(data);
                BUP.ui.showToast('Coupon applied successfully!');
            } else {
                BUP.ui.showToast(data.message || 'Invalid coupon code', 'error');
            }
        })
        .catch(error => {
            BUP.utils.log(error, 'error');
            BUP.ui.enableButton(applyBtn);
            BUP.ui.showToast('Failed to apply coupon', 'error');
        });
    },
    
    updateTotalWithDiscount: function(data) {
        const subtotalElem = BUP.utils.getElement('.summary-row:first-child span:last-child');
        const discountElem = BUP.utils.getElement('.summary-row.discount span:last-child');
        const totalElem = BUP.utils.getElement('.summary-row.total span:last-child');
        
        if (subtotalElem && discountElem && totalElem) {
            discountElem.textContent = `-$${data.discount}`;
            totalElem.textContent = BUP.utils.formatMoney(data.new_total);
        }
    },
    
    checkout: function() {
        const cartItems = BUP.utils.getElements('.cart-item');
        if (cartItems.length === 0) {
            BUP.ui.showToast('Your cart is empty!', 'warning');
            return;
        }
        
        const checkoutBtn = BUP.utils.getElement('.btn-checkout');
        BUP.ui.disableButton(checkoutBtn, 'Processing...');
        
        let url = 'checkout.php';
        if (window.location.pathname.includes('/user/')) {
            url = 'checkout.php';
        } else {
            url = 'user/checkout.php';
        }
        
        window.location.href = url;
    }
};

// ============================================
// QUANTITY CONTROLS
// ============================================

BUP.quantityControls = {
    init: function() {
        this.initSingleControls();
        this.initCartControls();
    },
    
    initSingleControls: function() {
        const decrementBtn = BUP.utils.getElement('#decrementQty');
        const incrementBtn = BUP.utils.getElement('#incrementQty');
        const quantityInput = BUP.utils.getElement('#quantity');
        const maxStock = parseInt(quantityInput?.getAttribute('max') || '0');
        
        if (decrementBtn && incrementBtn && quantityInput) {
            BUP.utils.addEvent(decrementBtn, 'click', () => {
                let val = parseInt(quantityInput.value);
                if (val > 1) {
                    quantityInput.value = val - 1;
                }
            });
            
            BUP.utils.addEvent(incrementBtn, 'click', () => {
                let val = parseInt(quantityInput.value);
                if (val < maxStock) {
                    quantityInput.value = val + 1;
                }
            });
            
            BUP.utils.addEvent(quantityInput, 'change', function() {
                let val = parseInt(this.value);
                if (isNaN(val) || val < 1) {
                    this.value = 1;
                } else if (val > maxStock) {
                    this.value = maxStock;
                }
            });
            
            // Keyboard shortcuts
            document.addEventListener('keydown', (e) => {
                if (e.key === '-' && e.ctrlKey) {
                    e.preventDefault();
                    decrementBtn.click();
                } else if (e.key === '+' && e.ctrlKey) {
                    e.preventDefault();
                    incrementBtn.click();
                }
            });
        }
    },
    
    initCartControls: function() {
        const quantityInputs = BUP.utils.getElements('.quantity-input');
        quantityInputs.forEach(input => {
            BUP.utils.addEvent(input, 'keypress', function(e) {
                if (e.key === 'Enter') {
                    const cartId = this.dataset.cartId;
                    const newQuantity = parseInt(this.value);
                    if (!isNaN(newQuantity) && newQuantity >= 1) {
                        BUP.cart.update(cartId, newQuantity);
                    }
                }
            });
        });
    }
};

// ============================================
// SEARCH FUNCTIONALITY
// ============================================

BUP.search = {
    init: function() {
        const searchInput = BUP.utils.getElement('#searchInput, .search-input');
        if (!searchInput) return;
        
        // Enter key search
        BUP.utils.addEvent(searchInput, 'keypress', (e) => {
            if (e.key === 'Enter') {
                this.performSearch(searchInput.value);
            }
        });
        
        // Debounced input search
        const debouncedSearch = BUP.utils.debounce((value) => {
            if (value.length >= 3 || value.length === 0) {
                // Optional: Implement live search here
                BUP.utils.log('Searching for: ' + value);
            }
        }, BUP.config.searchDebounceDelay);
        
        BUP.utils.addEvent(searchInput, 'input', (e) => {
            debouncedSearch(e.target.value);
        });
    },
    
    performSearch: function(query) {
        query = query.trim();
        if (query.length > 0) {
            let url = 'browse-books.php?search=' + encodeURIComponent(query);
            if (window.location.pathname.includes('/user/')) {
                url = 'browse-books.php?search=' + encodeURIComponent(query);
            } else {
                url = 'user/browse-books.php?search=' + encodeURIComponent(query);
            }
            window.location.href = url;
        }
    }
};

// ============================================
// FORM VALIDATION
// ============================================

BUP.forms = {
    init: function() {
        this.initLoginForm();
        this.initRegisterForm();
        this.initPasswordStrength();
    },
    
    initLoginForm: function() {
        const form = BUP.utils.getElement('#loginForm');
        if (!form) return;
        
        BUP.utils.addEvent(form, 'submit', (e) => {
            let isValid = true;
            
            const email = BUP.utils.getElement('#email');
            const password = BUP.utils.getElement('#password');
            
            this.clearErrors();
            
            // Validate email
            if (!email.value.trim()) {
                this.showError(email, 'Email is required');
                isValid = false;
            } else if (!this.validateEmail(email.value.trim())) {
                this.showError(email, 'Please enter a valid email address');
                isValid = false;
            }
            
            // Validate password
            if (!password.value) {
                this.showError(password, 'Password is required');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
            } else {
                BUP.ui.showLoading();
            }
        });
    },
    
    initRegisterForm: function() {
        const form = BUP.utils.getElement('#registerForm');
        if (!form) return;
        
        BUP.utils.addEvent(form, 'submit', (e) => {
            let isValid = true;
            
            const name = BUP.utils.getElement('#name');
            const email = BUP.utils.getElement('#email');
            const password = BUP.utils.getElement('#password');
            const confirm = BUP.utils.getElement('#confirm_password');
            const terms = BUP.utils.getElement('#terms');
            
            this.clearErrors();
            
            // Validate name
            if (!name.value.trim()) {
                this.showError(name, 'Full name is required');
                isValid = false;
            }
            
            // Validate email
            if (!email.value.trim()) {
                this.showError(email, 'Email is required');
                isValid = false;
            } else if (!this.validateEmail(email.value.trim())) {
                this.showError(email, 'Please enter a valid email address');
                isValid = false;
            }
            
            // Validate password
            if (!password.value) {
                this.showError(password, 'Password is required');
                isValid = false;
            } else if (password.value.length < 6) {
                this.showError(password, 'Password must be at least 6 characters');
                isValid = false;
            }
            
            // Validate confirm password
            if (!confirm.value) {
                this.showError(confirm, 'Please confirm your password');
                isValid = false;
            } else if (password.value !== confirm.value) {
                this.showError(confirm, 'Passwords do not match');
                isValid = false;
            }
            
            // Validate terms
            if (!terms.checked) {
                BUP.ui.showToast('Please agree to the Terms of Service', 'warning');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
            } else {
                BUP.ui.showLoading();
            }
        });
    },
    
    initPasswordStrength: function() {
        const passwordInput = BUP.utils.getElement('#password');
        const strengthBar = BUP.utils.getElement('#strengthBar');
        const strengthText = BUP.utils.getElement('#strengthText');
        
        if (!passwordInput || !strengthBar || !strengthText) return;
        
        BUP.utils.addEvent(passwordInput, 'input', function() {
            const password = this.value;
            let strength = 0;
            let feedback = '';
            
            if (password.length >= 6) strength += 1;
            if (password.length >= 8) strength += 1;
            if (/[a-z]/.test(password)) strength += 1;
            if (/[A-Z]/.test(password)) strength += 1;
            if (/[0-9]/.test(password)) strength += 1;
            if (/[^a-zA-Z0-9]/.test(password)) strength += 1;
            
            if (strength <= 2) {
                feedback = 'Weak';
                strengthBar.style.background = '#E64A19';
                strengthBar.style.width = '33%';
            } else if (strength <= 4) {
                feedback = 'Moderate';
                strengthBar.style.background = '#FFB347';
                strengthBar.style.width = '66%';
            } else {
                feedback = 'Strong';
                strengthBar.style.background = '#28A745';
                strengthBar.style.width = '100%';
            }
            
            if (password.length === 0) {
                feedback = 'Enter a password';
                strengthBar.style.width = '0';
            }
            
            strengthText.textContent = feedback;
        });
    },
    
    validateEmail: function(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    },
    
    showError: function(input, message) {
        const inputGroup = input.closest('.input-group');
        if (!inputGroup) return;
        
        const errorElement = inputGroup.querySelector('.error-message');
        inputGroup.classList.add('error');
        
        if (errorElement) {
            errorElement.textContent = message;
            errorElement.classList.add('show');
        }
        
        // Clear error on input
        input.addEventListener('input', function() {
            const group = this.closest('.input-group');
            const error = group.querySelector('.error-message');
            group.classList.remove('error');
            if (error) {
                error.classList.remove('show');
                error.textContent = '';
            }
        }, { once: true });
    },
    
    clearErrors: function() {
        const errorInputs = BUP.utils.getElements('.input-group.error');
        errorInputs.forEach(group => {
            group.classList.remove('error');
            const error = group.querySelector('.error-message');
            if (error) {
                error.classList.remove('show');
                error.textContent = '';
            }
        });
    }
};

// ============================================
// NAVIGATION & PAGE TRANSITIONS
// ============================================

BUP.navigation = {
    init: function() {
        this.initSmoothScroll();
        this.initLinkLoading();
        this.initNavbarScroll();
    },
    
    initSmoothScroll: function() {
        BUP.utils.getElements('a[href^="#"]').forEach(anchor => {
            BUP.utils.addEvent(anchor, 'click', function(e) {
                e.preventDefault();
                const target = BUP.utils.getElement(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    },
    
    initLinkLoading: function() {
        BUP.utils.getElements('a:not([href^="#"]):not([href^="javascript:"]):not(.no-loader):not(.btn-add-to-cart):not(.cart-icon-wrapper)').forEach(link => {
            BUP.utils.addEvent(link, 'click', function() {
                if (!this.classList.contains('btn-add-to-cart') && !this.classList.contains('cart-icon-wrapper')) {
                    BUP.ui.showLoading();
                }
            });
        });
    },
    
    initNavbarScroll: function() {
        const navbar = BUP.utils.getElement('#mainNav, .navbar');
        if (!navbar) return;
        
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
    }
};

// ============================================
// FILTER FUNCTIONALITY (Browse Books)
// ============================================

BUP.filters = {
    init: function() {
        this.initPriceRangeValidation();
        this.initFilterState();
    },
    
    initPriceRangeValidation: function() {
        const minPrice = BUP.utils.getElement('input[name="min_price"]');
        const maxPrice = BUP.utils.getElement('input[name="max_price"]');
        
        if (minPrice && maxPrice) {
            BUP.utils.addEvent(minPrice, 'change', function() {
                const min = parseFloat(this.value) || 0;
                const max = parseFloat(maxPrice.value) || 1000;
                
                if (min > max) {
                    maxPrice.value = min;
                }
            });
            
            BUP.utils.addEvent(maxPrice, 'change', function() {
                const min = parseFloat(minPrice.value) || 0;
                const max = parseFloat(this.value) || 1000;
                
                if (max < min) {
                    minPrice.value = max;
                }
            });
        }
    },
    
    initFilterState: function() {
        const filterForm = BUP.utils.getElement('#filterForm');
        if (!filterForm) return;
        
        // Save filter state to sessionStorage
        BUP.utils.addEvent(filterForm, 'submit', () => {
            this.saveFilterState();
        });
        
        // Update sort select
        const sortSelect = BUP.utils.getElement('.sort-select');
        if (sortSelect) {
            BUP.utils.addEvent(sortSelect, 'change', function() {
                const sortInput = BUP.utils.getElement('#sortInput');
                if (sortInput) {
                    sortInput.value = this.value;
                    filterForm.submit();
                }
            });
        }
    },
    
    saveFilterState: function() {
        const filters = {
            search: BUP.utils.getElement('input[name="search"]')?.value,
            category: BUP.utils.getElement('select[name="category"]')?.value,
            min_price: BUP.utils.getElement('input[name="min_price"]')?.value,
            max_price: BUP.utils.getElement('input[name="max_price"]')?.value,
            sort: BUP.utils.getElement('#sortInput, select[name="sort"]')?.value
        };
        sessionStorage.setItem('bookFilters', JSON.stringify(filters));
    },
    
    updateSort: function(value) {
        const sortInput = BUP.utils.getElement('#sortInput');
        const filterForm = BUP.utils.getElement('#filterForm');
        
        if (sortInput && filterForm) {
            sortInput.value = value;
            filterForm.submit();
        }
    }
};

// ============================================
// EXPOSE GLOBAL FUNCTIONS FOR INLINE CALLS
// ============================================

// Cart functions
window.addToCart = function(productId, productName, quantity = 1) {
    const button = event?.currentTarget;
    BUP.cart.add(productId, productName, quantity, button);
};

window.updateCartQuantity = function(cartId, newQuantity) {
    BUP.cart.update(cartId, newQuantity);
};

window.removeFromCart = function(cartId, itemName) {
    BUP.cart.remove(cartId, itemName);
};

window.clearCart = function() {
    BUP.cart.clear();
};

window.applyCoupon = function() {
    BUP.cart.applyCoupon();
};

window.proceedToCheckout = function() {
    BUP.cart.checkout();
};

// UI functions
window.showToast = function(message, type = 'success') {
    BUP.ui.showToast(message, type);
};

window.toggleSidebar = function() {
    BUP.sidebar.toggleMobile();
};

window.toggleProfileMenu = function() {
    const menu = BUP.utils.getElement('#profileMenu');
    BUP.profile.toggleMenu(menu);
};

window.toggleDarkMode = function() {
    BUP.darkMode.toggle();
};

// Filter functions
window.updateSort = function(value) {
    BUP.filters.updateSort(value);
};

// ============================================
// EXPORT FOR MODULE USE (if needed)
// ============================================

if (typeof module !== 'undefined' && module.exports) {
    module.exports = BUP;
}

// ============================================
// END OF BUP MAIN JS FILE
// ============================================
console.log('✅ BUP Platform JS loaded successfully | Version 1.0.0');