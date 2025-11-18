// Get preview domain from PHP
const previewDomain = document.body.dataset.previewDomain || '';

// File upload preview for new sport icon
document.getElementById('new_sport_icon').addEventListener('change', function (e) {
	const fileName = e.target.files[0]?.name || 'No file chosen';
	document.getElementById('newSportFileName').textContent = fileName;
});

// File upload preview for edit sport icon
document.getElementById('sportIconFile').addEventListener('change', function (e) {
	const fileName = e.target.files[0]?.name || 'No file chosen';
	document.getElementById('editSportFileName').textContent = fileName;

	if (e.target.files[0]) {
		const reader = new FileReader();
		reader.onload = function (event) {
			const preview = document.getElementById('iconPreviewContainer');
			preview.innerHTML = '<img src="' + event.target.result + '" alt="Preview">';
			preview.classList.remove('no-icon');
		};
		reader.readAsDataURL(e.target.files[0]);
	}
});

// Open icon upload modal
function openIconModal(sportName, currentIcon) {
	document.getElementById('iconSportName').value = sportName;

	const preview = document.getElementById('iconPreviewContainer');
	const iconName = document.getElementById('currentIconName');

	if (currentIcon) {
		// Use relative path
		const iconUrl = '/images/sports/' + currentIcon + '?v=' + Date.now();
		preview.innerHTML =
			'<img src="' +
			iconUrl +
			'" alt="' +
			sportName +
			"\" onerror=\"this.parentElement.innerHTML='?'; this.parentElement.classList.add('no-icon');\">";
		preview.classList.remove('no-icon');
		iconName.textContent = 'Current: ' + currentIcon;
	} else {
		preview.innerHTML = '?';
		preview.classList.add('no-icon');
		iconName.textContent = 'No icon';
	}

	document.getElementById('editSportFileName').textContent = 'No file chosen';
	document.getElementById('sportIconFile').value = '';
	document.getElementById('iconModal').classList.add('active');
}

// Close icon modal
function closeIconModal() {
	document.getElementById('iconModal').classList.remove('active');
}

// Open rename modal
function openRenameModal(sportName) {
	document.getElementById('oldSportName').value = sportName;
	document.getElementById('newSportNameInput').value = sportName;
	document.getElementById('renameModal').classList.add('active');
	document.getElementById('newSportNameInput').focus();
	document.getElementById('newSportNameInput').select();
}

// Close rename modal
function closeRenameModal() {
	document.getElementById('renameModal').classList.remove('active');
}

// Close modals when clicking outside
document.getElementById('iconModal').addEventListener('click', function (e) {
	if (e.target === this) closeIconModal();
});

document.getElementById('renameModal').addEventListener('click', function (e) {
	if (e.target === this) closeRenameModal();
});

// ========================================
// DRAG AND DROP FUNCTIONALITY
// ========================================

let draggedElement = null;
let draggedIndex = null;

function initDragAndDrop() {
	const sportsGrid = document.getElementById('sportsGrid');
	const sportCards = sportsGrid.querySelectorAll('.sport-card');

	sportCards.forEach((card, index) => {
		// Drag start
		card.addEventListener('dragstart', function (e) {
			draggedElement = this;
			draggedIndex = index;
			this.classList.add('dragging');
			e.dataTransfer.effectAllowed = 'move';
			e.dataTransfer.setData('text/html', this.innerHTML);
		});

		// Drag end
		card.addEventListener('dragend', function (e) {
			this.classList.remove('dragging');
			// Remove all drag-over classes
			sportCards.forEach(c => c.classList.remove('drag-over'));
		});

		// Drag over
		card.addEventListener('dragover', function (e) {
			if (e.preventDefault) {
				e.preventDefault();
			}
			e.dataTransfer.dropEffect = 'move';

			// Add visual feedback
			if (this !== draggedElement) {
				this.classList.add('drag-over');
			}

			return false;
		});

		// Drag enter
		card.addEventListener('dragenter', function (e) {
			if (this !== draggedElement) {
				this.classList.add('drag-over');
			}
		});

		// Drag leave
		card.addEventListener('dragleave', function (e) {
			this.classList.remove('drag-over');
		});

		// Drop
		card.addEventListener('drop', function (e) {
			if (e.stopPropagation) {
				e.stopPropagation();
			}

			this.classList.remove('drag-over');

			if (draggedElement !== this) {
				// Get all cards again (fresh list)
				const allCards = Array.from(sportsGrid.querySelectorAll('.sport-card'));
				const draggedCard = draggedElement;
				const targetCard = this;

				// Get current positions
				const draggedPos = allCards.indexOf(draggedCard);
				const targetPos = allCards.indexOf(targetCard);

				// Reorder DOM
				if (draggedPos < targetPos) {
					// Moving down
					targetCard.parentNode.insertBefore(draggedCard, targetCard.nextSibling);
				} else {
					// Moving up
					targetCard.parentNode.insertBefore(draggedCard, targetCard);
				}

				// Save new order
				saveNewOrder();
			}

			return false;
		});
	});
}

function saveNewOrder() {
	const sportsGrid = document.getElementById('sportsGrid');
	const sportCards = sportsGrid.querySelectorAll('.sport-card');

	// Get new order
	const newOrder = [];
	sportCards.forEach(card => {
		const sportName = card.getAttribute('data-sport-name');
		if (sportName) {
			newOrder.push(sportName);
		}
	});

	// Send to server
	const formData = new FormData();
	formData.append('reorder_sports', '1');
	formData.append('sports_order', JSON.stringify(newOrder));

	fetch(window.location.href, {
		method: 'POST',
		body: formData,
	})
		.then(response => response.text())
		.then(html => {
			// Show success message
			showNotification('✅ Sports order updated!', 'success');
		})
		.catch(error => {
			console.error('Error saving order:', error);
			showNotification('❌ Failed to save order', 'error');
		});
}

function showNotification(message, type) {
	// Create notification element
	const notification = document.createElement('div');
	notification.className = 'drag-notification ' + type;
	notification.textContent = message;
	notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 25px;
        background: ${type === 'success' ? '#27ae60' : '#e74c3c'};
        color: white;
        border-radius: 8px;
        font-weight: 600;
        z-index: 10000;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        animation: slideIn 0.3s ease;
    `;

	document.body.appendChild(notification);

	// Remove after 3 seconds
	setTimeout(() => {
		notification.style.animation = 'slideOut 0.3s ease';
		setTimeout(() => {
			notification.remove();
		}, 300);
	}, 3000);
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

// Initialize drag and drop when page loads
document.addEventListener('DOMContentLoaded', function () {
	initDragAndDrop();
});
