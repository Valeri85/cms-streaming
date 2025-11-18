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
// DRAG AND DROP FUNCTIONALITY WITH AUTO-SCROLL
// ========================================

let draggedElement = null;
let draggedIndex = null;
let autoScrollInterval = null;

function initDragAndDrop() {
	console.log('🔧 Initializing drag and drop...');

	const sportsGrid = document.getElementById('sportsGrid');
	if (!sportsGrid) {
		console.error('❌ Sports grid not found!');
		return;
	}

	const sportCards = sportsGrid.querySelectorAll('.sport-card');
	console.log('📦 Found sport cards:', sportCards.length);

	if (sportCards.length === 0) {
		console.warn('⚠️ No sport cards to make draggable');
		return;
	}

	sportCards.forEach((card, index) => {
		console.log(`✅ Setting up drag for card ${index}:`, card.dataset.sportName);

		// Make sure draggable attribute is set
		card.setAttribute('draggable', 'true');

		// Drag start
		card.addEventListener('dragstart', function (e) {
			console.log('🎯 Drag started:', this.dataset.sportName);
			draggedElement = this;
			draggedIndex = index;
			this.classList.add('dragging');

			// Set data for drag operation
			e.dataTransfer.effectAllowed = 'move';
			e.dataTransfer.setData('text/html', this.innerHTML);

			// Make the drag operation visible
			this.style.opacity = '0.4';

			// Start auto-scroll monitoring
			startAutoScroll();
		});

		// Drag end
		card.addEventListener('dragend', function (e) {
			console.log('🏁 Drag ended:', this.dataset.sportName);
			this.classList.remove('dragging');
			this.style.opacity = '1';

			// Stop auto-scroll
			stopAutoScroll();

			// Remove all drag-over classes
			sportCards.forEach(c => c.classList.remove('drag-over'));
		});

		// Drag over - IMPORTANT: Must prevent default!
		card.addEventListener('dragover', function (e) {
			e.preventDefault(); // CRITICAL: Allow drop
			e.dataTransfer.dropEffect = 'move';

			// Add visual feedback
			if (this !== draggedElement) {
				this.classList.add('drag-over');
			}

			// Update mouse position for auto-scroll
			updateMousePosition(e);

			return false;
		});

		// Drag enter
		card.addEventListener('dragenter', function (e) {
			e.preventDefault();
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
			e.stopPropagation();
			e.preventDefault();

			console.log('📍 Dropped on:', this.dataset.sportName);
			this.classList.remove('drag-over');

			if (draggedElement !== this) {
				// Get all cards again (fresh list)
				const allCards = Array.from(sportsGrid.querySelectorAll('.sport-card'));
				const draggedCard = draggedElement;
				const targetCard = this;

				// Get current positions
				const draggedPos = allCards.indexOf(draggedCard);
				const targetPos = allCards.indexOf(targetCard);

				console.log(`📊 Moving from position ${draggedPos} to ${targetPos}`);

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

	console.log('✅ Drag and drop initialized successfully!');
}

// ========================================
// AUTO-SCROLL FUNCTIONALITY
// ========================================

let mouseY = 0;
const SCROLL_THRESHOLD = 100; // Distance from edge to trigger scroll (pixels)
const SCROLL_SPEED = 8; // Pixels per frame

function updateMousePosition(e) {
	mouseY = e.clientY;
}

function startAutoScroll() {
	// Clear any existing interval
	if (autoScrollInterval) {
		clearInterval(autoScrollInterval);
	}

	// Start new interval for smooth scrolling
	autoScrollInterval = setInterval(() => {
		if (!draggedElement) {
			stopAutoScroll();
			return;
		}

		const windowHeight = window.innerHeight;
		const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
		const documentHeight = document.documentElement.scrollHeight;

		// Scroll up when near top
		if (mouseY < SCROLL_THRESHOLD && scrollTop > 0) {
			const intensity = (SCROLL_THRESHOLD - mouseY) / SCROLL_THRESHOLD;
			const scrollAmount = Math.ceil(SCROLL_SPEED * intensity);
			window.scrollBy(0, -scrollAmount);
			console.log('⬆️ Auto-scrolling up:', scrollAmount);
		}

		// Scroll down when near bottom
		else if (mouseY > windowHeight - SCROLL_THRESHOLD && scrollTop + windowHeight < documentHeight) {
			const intensity = (mouseY - (windowHeight - SCROLL_THRESHOLD)) / SCROLL_THRESHOLD;
			const scrollAmount = Math.ceil(SCROLL_SPEED * intensity);
			window.scrollBy(0, scrollAmount);
			console.log('⬇️ Auto-scrolling down:', scrollAmount);
		}
	}, 16); // ~60fps
}

function stopAutoScroll() {
	if (autoScrollInterval) {
		clearInterval(autoScrollInterval);
		autoScrollInterval = null;
		console.log('🛑 Auto-scroll stopped');
	}
}

// ========================================
// SAVE NEW ORDER
// ========================================

function saveNewOrder() {
	console.log('💾 Saving new order...');

	const sportsGrid = document.getElementById('sportsGrid');
	const sportCards = sportsGrid.querySelectorAll('.sport-card');

	// Get new order
	const newOrder = [];
	sportCards.forEach((card, index) => {
		const sportName = card.getAttribute('data-sport-name');
		if (sportName) {
			newOrder.push(sportName);
			console.log(`${index + 1}. ${sportName}`);
		}
	});

	console.log('📤 Sending order to server:', newOrder);

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
			console.log('✅ Order saved successfully!');
			showNotification('✅ Sports order updated!', 'success');
		})
		.catch(error => {
			console.error('❌ Error saving order:', error);
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

// ========================================
// INITIALIZATION - MULTIPLE METHODS
// ========================================

// Method 1: DOMContentLoaded (standard)
if (document.readyState === 'loading') {
	console.log('⏳ Document still loading, waiting for DOMContentLoaded...');
	document.addEventListener('DOMContentLoaded', initDragAndDrop);
} else {
	// Method 2: Document already loaded (fallback)
	console.log('✅ Document already loaded, initializing immediately...');
	initDragAndDrop();
}

// Method 3: Additional fallback with slight delay
setTimeout(() => {
	const sportsGrid = document.getElementById('sportsGrid');
	if (sportsGrid && sportsGrid.querySelectorAll('.sport-card').length > 0) {
		console.log('🔄 Fallback initialization check...');
		// Only reinitialize if not already done
		if (!draggedElement && sportsGrid.querySelectorAll('.sport-card[draggable="true"]').length === 0) {
			initDragAndDrop();
		}
	}
}, 500);
