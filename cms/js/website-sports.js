// Get preview domain from PHP
const previewDomain = document.body.dataset.previewDomain || '';

// ========================================
// SCROLL POSITION MANAGEMENT
// ========================================

// Save scroll position before any form submit
function saveScrollPosition() {
	const scrollPos = window.pageYOffset || document.documentElement.scrollTop;
	sessionStorage.setItem('sportsPageScrollPos', scrollPos);
}

// Restore scroll position after page load
function restoreScrollPosition() {
	const scrollPos = sessionStorage.getItem('sportsPageScrollPos');
	if (scrollPos) {
		window.scrollTo(0, parseInt(scrollPos));
		sessionStorage.removeItem('sportsPageScrollPos');
	}
}

// Initialize scroll position restoration on page load
window.addEventListener('DOMContentLoaded', function () {
	restoreScrollPosition();

	// Also handle the "Add New Sport" form at top of page
	const addSportForm = document.querySelector('form[method="POST"][enctype="multipart/form-data"]');
	if (addSportForm && addSportForm.querySelector('button[name="add_sport"]')) {
		addSportForm.addEventListener('submit', function (e) {
			saveScrollPosition();
		});
	}
});

// File upload preview for new sport icon
document.addEventListener('DOMContentLoaded', function () {
	const newSportIcon = document.getElementById('new_sport_icon');
	if (newSportIcon) {
		newSportIcon.addEventListener('change', function (e) {
			const fileName = e.target.files[0]?.name || 'No file chosen';
			const fileDisplay = document.getElementById('newSportFileName');
			if (fileDisplay) {
				fileDisplay.textContent = fileName;
			}
		});
	}

	// File upload preview for edit sport icon
	const sportIconFile = document.getElementById('sportIconFile');
	if (sportIconFile) {
		sportIconFile.addEventListener('change', function (e) {
			const fileName = e.target.files[0]?.name || 'No file chosen';
			const fileDisplay = document.getElementById('editSportFileName');
			if (fileDisplay) {
				fileDisplay.textContent = fileName;
			}

			if (e.target.files[0]) {
				const reader = new FileReader();
				reader.onload = function (event) {
					const preview = document.getElementById('iconPreviewContainer');
					if (preview) {
						preview.innerHTML = '<img src="' + event.target.result + '" alt="Preview">';
						preview.classList.remove('no-icon');
					}
				};
				reader.readAsDataURL(e.target.files[0]);
			}
		});
	}
});

// Open icon upload modal
function openIconModal(sportName, currentIcon) {
	const iconModal = document.getElementById('iconModal');
	const iconSportName = document.getElementById('iconSportName');
	const preview = document.getElementById('iconPreviewContainer');
	const iconName = document.getElementById('currentIconName');
	const fileDisplay = document.getElementById('editSportFileName');
	const sportIconFile = document.getElementById('sportIconFile');

	if (!iconModal || !iconSportName) return;

	iconSportName.value = sportName;

	if (currentIcon && preview && iconName) {
		// FIX: Icon URL should point to streaming website with www.
		const iconUrl = 'https://www.' + previewDomain + '/images/sports/' + currentIcon + '?v=' + Date.now();
		preview.innerHTML =
			'<img src="' +
			iconUrl +
			'" alt="' +
			sportName +
			"\" onerror=\"this.parentElement.innerHTML='?'; this.parentElement.classList.add('no-icon');\">";
		preview.classList.remove('no-icon');
		iconName.textContent = 'Current: ' + currentIcon;
	} else if (preview && iconName) {
		preview.innerHTML = '?';
		preview.classList.add('no-icon');
		iconName.textContent = 'No icon';
	}

	if (fileDisplay) fileDisplay.textContent = 'No file chosen';
	if (sportIconFile) sportIconFile.value = '';

	iconModal.classList.add('active');
}

// Close icon modal
function closeIconModal() {
	const iconModal = document.getElementById('iconModal');
	if (iconModal) {
		iconModal.classList.remove('active');
	}
}

// Open rename modal
function openRenameModal(sportName) {
	const renameModal = document.getElementById('renameModal');
	const oldSportName = document.getElementById('oldSportName');
	const newSportNameInput = document.getElementById('newSportNameInput');

	if (!renameModal || !oldSportName || !newSportNameInput) return;

	oldSportName.value = sportName;
	newSportNameInput.value = sportName;
	renameModal.classList.add('active');
	newSportNameInput.focus();
	newSportNameInput.select();
}

// Close rename modal
function closeRenameModal() {
	const renameModal = document.getElementById('renameModal');
	if (renameModal) {
		renameModal.classList.remove('active');
	}
}

// Close modals when clicking outside
document.addEventListener('DOMContentLoaded', function () {
	const iconModal = document.getElementById('iconModal');
	if (iconModal) {
		iconModal.addEventListener('click', function (e) {
			if (e.target === this) closeIconModal();
		});

		const iconForm = iconModal.querySelector('form');
		if (iconForm) {
			iconForm.addEventListener('submit', function (e) {
				saveScrollPosition();
			});
		}
	}

	const renameModal = document.getElementById('renameModal');
	if (renameModal) {
		renameModal.addEventListener('click', function (e) {
			if (e.target === this) closeRenameModal();
		});

		const renameForm = renameModal.querySelector('form');
		if (renameForm) {
			renameForm.addEventListener('submit', function (e) {
				saveScrollPosition();
			});
		}
	}

	const deleteIconForms = document.querySelectorAll('form[onsubmit*="Delete icon"]');
	deleteIconForms.forEach(form => {
		form.addEventListener('submit', function (e) {
			saveScrollPosition();
		});
	});

	const deleteSportForms = document.querySelectorAll('form button[name="delete_sport"]');
	deleteSportForms.forEach(button => {
		const form = button.closest('form');
		if (form) {
			form.addEventListener('submit', function (e) {
				saveScrollPosition();
			});
		}
	});
});

// ========================================
// DRAG AND DROP FUNCTIONALITY WITH AUTO-SCROLL
// ========================================

let draggedElement = null;
let draggedIndex = null;
let autoScrollInterval = null;

function initDragAndDrop() {
	const sportsGrid = document.getElementById('sportsGrid');
	if (!sportsGrid) {
		return;
	}

	const sportCards = sportsGrid.querySelectorAll('.sport-card');

	if (sportCards.length === 0) {
		return;
	}

	sportCards.forEach((card, index) => {
		card.setAttribute('draggable', 'true');

		card.addEventListener('dragstart', function (e) {
			draggedElement = this;
			draggedIndex = index;
			this.classList.add('dragging');

			e.dataTransfer.effectAllowed = 'move';
			e.dataTransfer.setData('text/html', this.innerHTML);

			this.style.opacity = '0.4';

			startAutoScroll();
		});

		card.addEventListener('dragend', function (e) {
			this.classList.remove('dragging');
			this.style.opacity = '1';

			stopAutoScroll();

			sportCards.forEach(c => c.classList.remove('drag-over'));
		});

		card.addEventListener('dragover', function (e) {
			e.preventDefault();
			e.dataTransfer.dropEffect = 'move';

			if (this !== draggedElement) {
				this.classList.add('drag-over');
			}

			updateMousePosition(e);

			return false;
		});

		card.addEventListener('dragenter', function (e) {
			e.preventDefault();
			if (this !== draggedElement) {
				this.classList.add('drag-over');
			}
		});

		card.addEventListener('dragleave', function (e) {
			this.classList.remove('drag-over');
		});

		card.addEventListener('drop', function (e) {
			e.stopPropagation();
			e.preventDefault();

			this.classList.remove('drag-over');

			if (draggedElement !== this) {
				const allCards = Array.from(sportsGrid.querySelectorAll('.sport-card'));
				const draggedCard = draggedElement;
				const targetCard = this;

				const draggedPos = allCards.indexOf(draggedCard);
				const targetPos = allCards.indexOf(targetCard);

				if (draggedPos < targetPos) {
					targetCard.parentNode.insertBefore(draggedCard, targetCard.nextSibling);
				} else {
					targetCard.parentNode.insertBefore(draggedCard, targetCard);
				}

				saveNewOrder();
			}

			return false;
		});
	});
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
	if (autoScrollInterval) {
		clearInterval(autoScrollInterval);
	}

	autoScrollInterval = setInterval(() => {
		if (!draggedElement) {
			stopAutoScroll();
			return;
		}

		const windowHeight = window.innerHeight;
		const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
		const documentHeight = document.documentElement.scrollHeight;

		if (mouseY < SCROLL_THRESHOLD && scrollTop > 0) {
			const intensity = (SCROLL_THRESHOLD - mouseY) / SCROLL_THRESHOLD;
			const scrollAmount = Math.ceil(SCROLL_SPEED * intensity);
			window.scrollBy(0, -scrollAmount);
		} else if (mouseY > windowHeight - SCROLL_THRESHOLD && scrollTop + windowHeight < documentHeight) {
			const intensity = (mouseY - (windowHeight - SCROLL_THRESHOLD)) / SCROLL_THRESHOLD;
			const scrollAmount = Math.ceil(SCROLL_SPEED * intensity);
			window.scrollBy(0, scrollAmount);
		}
	}, 16);
}

function stopAutoScroll() {
	if (autoScrollInterval) {
		clearInterval(autoScrollInterval);
		autoScrollInterval = null;
	}
}

// ========================================
// SAVE NEW ORDER
// ========================================

function saveNewOrder() {
	const sportsGrid = document.getElementById('sportsGrid');
	if (!sportsGrid) {
		return;
	}

	const sportCards = sportsGrid.querySelectorAll('.sport-card');

	const newOrder = [];
	sportCards.forEach((card, index) => {
		const sportName = card.getAttribute('data-sport-name');
		if (sportName) {
			newOrder.push(sportName);
		}
	});

	const formData = new FormData();
	formData.append('reorder_sports', '1');
	formData.append('sports_order', JSON.stringify(newOrder));

	fetch(window.location.href, {
		method: 'POST',
		body: formData,
	})
		.then(response => response.text())
		.then(html => {
			showNotification('✅ Sports order updated!', 'success');
		})
		.catch(error => {
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
// INITIALIZATION - WAIT FOR DOM COMPLETELY
// ========================================

// This is the CORRECT way to initialize
window.addEventListener('DOMContentLoaded', function () {
	setTimeout(function () {
		initDragAndDrop();
	}, 100);
});

window.addEventListener('load', function () {
	const sportsGrid = document.getElementById('sportsGrid');
	if (sportsGrid) {
		const sportCards = sportsGrid.querySelectorAll('.sport-card[draggable="true"]');
		if (sportCards.length === 0) {
			initDragAndDrop();
		}
	}
});
