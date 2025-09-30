/**
 * ATM System Frontend JavaScript
 * 
 * This file handles all client-side functionality for the ATM system including:
 * - AJAX transaction processing (deposits and withdrawals)
 * - Real-time form validation and user feedback
 * - Dynamic UI updates and balance refreshing
 * - Error handling and user experience optimization
 * - Loading states and visual feedback
 * 
 * @author ATM System
 * @version 1.0
 */

// Initialize application when DOM is fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Cache DOM elements for better performance
    const depositForm = document.getElementById('depositForm');
    const withdrawForm = document.getElementById('withdrawForm');
    const messageArea = document.getElementById('messageArea');
    const messageContent = document.getElementById('messageContent');
    const balanceElement = document.getElementById('currentBalance');

    // Set up event listeners for transaction forms
    if (depositForm) {
        depositForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const amount = document.getElementById('deposit_amount').value;
            processTransaction('deposit', amount);
        });
    }

    if (withdrawForm) {
        withdrawForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const amount = document.getElementById('withdraw_amount').value;
            processTransaction('withdraw', amount);
        });
    }

    /**
     * Process banking transactions via AJAX API
     * 
     * This function handles the complete transaction flow including:
     * - Client-side validation
     * - API communication
     * - UI state management
     * - Error handling and user feedback
     * - Balance updates
     * 
     * @param {string} type - Transaction type ('deposit' or 'withdraw')
     * @param {string} amount - Transaction amount as string
     */
    function processTransaction(type, amount) {
        // Client-side validation
        if (!amount || parseFloat(amount) <= 0) {
            showMessage('Please enter a valid amount greater than 0', 'error');
            return;
        }

        // Show loading state for better UX
        showMessage('Processing transaction...', 'loading');
        
        // Cache button elements for state management
        const depositBtn = depositForm ? depositForm.querySelector('button[type="submit"]') : null;
        const withdrawBtn = withdrawForm ? withdrawForm.querySelector('button[type="submit"]') : null;
        
        // Set loading state on appropriate button
        if (type === 'deposit' && depositBtn) {
            setButtonLoading(depositBtn, true);
        }
        if (type === 'withdraw' && withdrawBtn) {
            setButtonLoading(withdrawBtn, true);
        }

        // Extract CSRF token for security
        const formId = type === 'deposit' ? 'depositForm' : 'withdrawForm';
        const form = document.getElementById(formId);
        const csrfInput = form ? form.querySelector('input[name="csrf_token"]') : null;
        const csrfToken = csrfInput ? csrfInput.value : '';

        // Log warning if CSRF token is missing (for debugging)
        if (!csrfToken) {
            console.warn('CSRF token not found, proceeding without it');
        }

        // Prepare transaction data for API
        const transactionData = {
            transaction_type: type,
            amount: parseFloat(amount),
            csrf_token: csrfToken
        };

        // Make AJAX API request with comprehensive error handling
        fetch('api/process_transaction.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(transactionData)
        })
        .then(response => {
            // Parse JSON response regardless of HTTP status for better error handling
            return response.json().then(data => {
                return { 
                    ok: response.ok, 
                    status: response.status, 
                    data: data 
                };
            });
        })
        .then(result => {
            // Handle successful transaction responses
            if (result.ok && result.data.success) {
                // Transaction successful
                showMessage(result.data.message, 'success');
                
                // Update balance display
                updateBalance(result.data.data.formatted_new_balance);
                
                // Clear form
                document.getElementById(type + '_amount').value = '';
                
                // Update withdraw form max value if it was a deposit
                if (type === 'deposit') {
                    const withdrawInput = document.getElementById('withdraw_amount');
                    if (withdrawInput) {
                        withdrawInput.setAttribute('max', result.data.data.new_balance);
                    }
                }
                
                // Refresh recent activities after a delay
                setTimeout(() => {
                    refreshRecentActivities();
                }, 1000);
                
            } else {
                // Transaction failed - show the specific API error message
                const errorMessage = result.data.message || 'Transaction failed';
                showMessage(errorMessage, 'error');
                
                // For withdrawal limit errors, add helpful information
                if (errorMessage.includes('withdrawal limit') || errorMessage.includes('limit exceeded')) {
                    setTimeout(() => {
                        showMessage('💡 Tip: You can try depositing money or wait until tomorrow to withdraw again.', 'info');
                    }, 3000);
                }
            }
        })
        .catch(error => {
            console.error('Transaction error:', error);
            showMessage('Network error. Please check your connection and try again.', 'error');
        })
        .finally(() => {
            // Re-enable form buttons
            resetFormButtons();
        });
    }

    /**
     * Display user feedback messages with appropriate styling
     * 
     * @param {string} message - The message text to display
     * @param {string} type - Message type: 'success', 'error', 'loading', 'info', 'warning'
     */
    function showMessage(message, type) {
        if (messageArea && messageContent) {
            messageContent.textContent = message;
            
            // Reset alert classes
            messageArea.className = 'alert alert-dismissible fade show';
            
            // Apply Bootstrap alert styling based on message type
            switch(type) {
                case 'success':
                    messageArea.classList.add('alert-success');
                    break;
                case 'error':
                    messageArea.classList.add('alert-danger');
                    break;
                case 'loading':
                    messageArea.classList.add('alert-info');
                    break;
                case 'info':
                    messageArea.classList.add('alert-primary');
                    break;
                case 'warning':
                    messageArea.classList.add('alert-warning');
                    break;
                default:
                    messageArea.classList.add('alert-secondary');
            }
            
            // Show the message area
            messageArea.classList.remove('d-none');
            
            // Auto-hide messages after appropriate delay (errors stay visible longer)
            if (type !== 'loading') {
                const hideDelay = type === 'error' ? 8000 : 5000;
                setTimeout(() => {
                    hideMessage();
                }, hideDelay);
            }
        }
    }

    /**
     * Hide the message area from view
     */
    function hideMessage() {
        if (messageArea) {
            messageArea.classList.add('d-none');
        }
    }

    /**
     * Update the balance display with smooth animation
     * 
     * @param {string} newBalance - Formatted balance string (e.g., "1,234.56")
     */
    function updateBalance(newBalance) {
        if (balanceElement) {
            // Add CSS animation class for visual feedback
            balanceElement.classList.add('balance-updating');
            
            // Update the balance text with a slight delay for smooth transition
            setTimeout(() => {
                balanceElement.textContent = '฿' + newBalance;
                balanceElement.classList.remove('balance-updating');
                balanceElement.classList.add('balance-updated');
                
                // Remove animation class after transition completes
                setTimeout(() => {
                    balanceElement.classList.remove('balance-updated');
                }, 500);
            }, 200);
        }
    }

    /**
     * Set loading state on transaction buttons
     * 
     * @param {HTMLElement} button - Button element to modify
     * @param {boolean} isLoading - Whether to show loading state
     */
    function setButtonLoading(button, isLoading) {
        if (!button) return;
        
        if (isLoading) {
            button.disabled = true;
            const buttonType = button.closest('#depositForm') ? 'Depositing' : 'Withdrawing';
            button.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${buttonType}...`;
        }
    }

    /**
     * Reset all form buttons to their original state
     */
    function resetFormButtons() {
        const depositBtn = depositForm ? depositForm.querySelector('button[type="submit"]') : null;
        const withdrawBtn = withdrawForm ? withdrawForm.querySelector('button[type="submit"]') : null;
        
        if (depositBtn) {
            depositBtn.disabled = false;
            depositBtn.innerHTML = '<i class="fas fa-plus"></i> Deposit Money';
        }
        
        if (withdrawBtn) {
            withdrawBtn.disabled = false;
            withdrawBtn.innerHTML = '<i class="fas fa-minus"></i> Withdraw Money';
        }
    }

    /**
     * Refresh recent activities display
     * 
     * Note: In a more advanced implementation, this could use AJAX to fetch
     * updated activities without full page reload. For now, we reload the page
     * to ensure all data is current.
     */
    function refreshRecentActivities() {
        // Reload page after a delay to show updated activities and balance
        setTimeout(() => {
            window.location.reload();
        }, 2000);
    }

    // Additional event listeners for better user experience
    
    // Allow clicking message area to dismiss it
    if (messageArea) {
        messageArea.addEventListener('click', function() {
            hideMessage();
        });
    }

    // Form validation and input formatting enhancements
    const amountInputs = document.querySelectorAll('input[type="number"]');
    amountInputs.forEach(input => {
        // Format number input on blur for consistent display
        input.addEventListener('blur', function() {
            if (this.value) {
                const value = parseFloat(this.value);
                if (!isNaN(value) && value > 0) {
                    this.value = value.toFixed(2);
                }
            }
        });

        // Prevent negative values and provide user feedback
        input.addEventListener('input', function() {
            if (this.value < 0) {
                this.value = '';
                showMessage('Amount must be positive', 'warning');
            }
        });
    });

    // Application initialization complete
    console.log('ATM System JavaScript loaded successfully');
});