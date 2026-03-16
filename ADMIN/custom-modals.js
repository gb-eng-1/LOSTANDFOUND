/**
 * Custom Modal System - Replace browser alerts/confirms with webpage UI
 */

// Create modal container if it doesn't exist
function createModalContainer() {
    if (document.getElementById('customModalContainer')) return;
    
    const container = document.createElement('div');
    container.id = 'customModalContainer';
    container.innerHTML = `
        <div class="custom-modal-overlay" id="customModalOverlay">
            <div class="custom-modal">
                <div class="custom-modal-header">
                    <h3 id="customModalTitle">Confirmation</h3>
                </div>
                <div class="custom-modal-body">
                    <p id="customModalMessage">Are you sure?</p>
                </div>
                <div class="custom-modal-footer">
                    <button type="button" class="btn-cancel" id="customModalCancel">Cancel</button>
                    <button type="button" class="btn-confirm" id="customModalConfirm">Confirm</button>
                </div>
            </div>
        </div>
    `;
    
    // Add styles
    const style = document.createElement('style');
    style.textContent = `
        .custom-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10000;
        }
        .custom-modal-overlay.show {
            display: flex;
        }
        .custom-modal {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            max-width: 400px;
            width: 90%;
            max-height: 90vh;
            overflow: hidden;
        }
        .custom-modal-header {
            padding: 20px 24px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .custom-modal-header h3 {
            margin: 0 0 16px 0;
            color: #1f2937;
            font-size: 18px;
            font-weight: 600;
        }
        .custom-modal-body {
            padding: 20px 24px;
        }
        .custom-modal-body p {
            margin: 0;
            color: #4b5563;
            line-height: 1.5;
        }
        .custom-modal-footer {
            padding: 0 24px 24px;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        .custom-modal-footer button {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border: 1px solid;
            transition: all 0.2s;
        }
        .custom-modal-footer .btn-cancel {
            background: white;
            color: #6b7280;
            border-color: #d1d5db;
        }
        .custom-modal-footer .btn-cancel:hover {
            background: #f9fafb;
            border-color: #9ca3af;
        }
        .custom-modal-footer .btn-confirm {
            background: #dc2626;
            color: white;
            border-color: #dc2626;
        }
        .custom-modal-footer .btn-confirm:hover {
            background: #b91c1c;
            border-color: #b91c1c;
        }
        .custom-modal-footer .btn-confirm.primary {
            background: #059669;
            border-color: #059669;
        }
        .custom-modal-footer .btn-confirm.primary:hover {
            background: #047857;
            border-color: #047857;
        }
    `;
    
    document.head.appendChild(style);
    document.body.appendChild(container);
}

// Custom confirm function
function customConfirm(message, title = 'Confirmation') {
    return new Promise((resolve) => {
        createModalContainer();
        
        const overlay = document.getElementById('customModalOverlay');
        const titleEl = document.getElementById('customModalTitle');
        const messageEl = document.getElementById('customModalMessage');
        const cancelBtn = document.getElementById('customModalCancel');
        const confirmBtn = document.getElementById('customModalConfirm');
        
        titleEl.textContent = title;
        messageEl.textContent = message;
        
        // Show modal
        overlay.classList.add('show');
        
        // Handle buttons
        const handleCancel = () => {
            overlay.classList.remove('show');
            resolve(false);
        };
        
        const handleConfirm = () => {
            overlay.classList.remove('show');
            resolve(true);
        };
        
        // Remove old listeners
        cancelBtn.replaceWith(cancelBtn.cloneNode(true));
        confirmBtn.replaceWith(confirmBtn.cloneNode(true));
        
        // Add new listeners
        document.getElementById('customModalCancel').addEventListener('click', handleCancel);
        document.getElementById('customModalConfirm').addEventListener('click', handleConfirm);
        
        // Close on overlay click
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) handleCancel();
        });
        
        // Close on escape key
        const handleEscape = (e) => {
            if (e.key === 'Escape') {
                document.removeEventListener('keydown', handleEscape);
                handleCancel();
            }
        };
        document.addEventListener('keydown', handleEscape);
    });
}

// Custom alert function
function customAlert(message, title = 'Notice') {
    return new Promise((resolve) => {
        createModalContainer();
        
        const overlay = document.getElementById('customModalOverlay');
        const titleEl = document.getElementById('customModalTitle');
        const messageEl = document.getElementById('customModalMessage');
        const cancelBtn = document.getElementById('customModalCancel');
        const confirmBtn = document.getElementById('customModalConfirm');
        
        titleEl.textContent = title;
        messageEl.textContent = message;
        
        // Hide cancel button for alerts
        cancelBtn.style.display = 'none';
        confirmBtn.textContent = 'OK';
        confirmBtn.className = 'btn-confirm primary';
        
        // Show modal
        overlay.classList.add('show');
        
        // Handle confirm
        const handleConfirm = () => {
            overlay.classList.remove('show');
            cancelBtn.style.display = 'block'; // Reset for next use
            confirmBtn.textContent = 'Confirm';
            confirmBtn.className = 'btn-confirm';
            resolve(true);
        };
        
        // Remove old listeners
        confirmBtn.replaceWith(confirmBtn.cloneNode(true));
        
        // Add new listener
        document.getElementById('customModalConfirm').addEventListener('click', handleConfirm);
        
        // Close on overlay click
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) handleConfirm();
        });
        
        // Close on escape key
        const handleEscape = (e) => {
            if (e.key === 'Escape') {
                document.removeEventListener('keydown', handleEscape);
                handleConfirm();
            }
        };
        document.addEventListener('keydown', handleEscape);
    });
}

// Make functions globally available
window.customConfirm = customConfirm;
window.customAlert = customAlert;