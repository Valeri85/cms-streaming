// Get preview domain from PHP
const previewDomain = document.body.dataset.previewDomain || '';

// ==========================================
// SCROLL POSITION MANAGEMENT
// ==========================================

function saveScrollPosition() {
	const scrollPos = window.pageYOffset || document.documentElement.scrollTop;
	sessionStorage.setItem('pagesScrollPos', scrollPos);
}

function restoreScrollPosition() {
	const scrollPos = sessionStorage.getItem('pagesScrollPos');
	if (scrollPos) {
		window.scrollTo(0, parseInt(scrollPos));
		sessionStorage.removeItem('pagesScrollPos');
	}
}

window.addEventListener('DOMContentLoaded', function () {
	restoreScrollPosition();

	// Handle "Add New Sport" form at top
	const addSportForm = document.querySelector('form[method="POST"][enctype="multipart/form-data"]');
	if (addSportForm && addSportForm.querySelector('button[name="add_sport"]')) {
		addSportForm.addEventListener('submit', function (e) {
			saveScrollPosition();
		});
	}
});

// ==========================================
// FILE UPLOAD PREVIEWS
// ==========================================

// Preview for NEW sport icon
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

	// Preview for EDIT sport icon (in modal)
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

// ==========================================
// ICON UPLOAD MODAL
// Shows current sport name in title
// ==========================================

function openIconModal(sportName, currentIcon) {
	const iconModal = document.getElementById('iconModal');
	const iconSportName = document.getElementById('iconSportName');
	const modalTitle = document.getElementById('iconModalTitle');
	const preview = document.getElementById('iconPreviewContainer');
	const iconName = document.getElementById('currentIconName');
	const fileDisplay = document.getElementById('editSportFileName');
	const sportIconFile = document.getElementById('sportIconFile');

	if (!iconModal || !iconSportName) return;

	// Set sport name in hidden field
	iconSportName.value = sportName;

	// UPDATE: Set sport name in modal title
	if (modalTitle) {
		modalTitle.textContent = 'Upload/Change Icon - ' + sportName;
	}

	// Show current icon preview
	if (currentIcon && preview && iconName) {
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

	// Reset file input
	if (fileDisplay) fileDisplay.textContent = 'No file chosen';
	if (sportIconFile) sportIconFile.value = '';

	iconModal.classList.add('active');
}

function closeIconModal() {
	const iconModal = document.getElementById('iconModal');
	if (iconModal) {
		iconModal.classList.remove('active');
	}
}

// ==========================================
// RENAME MODAL
// Shows current sport name in title and updates after rename
// ==========================================

function openRenameModal(sportName) {
	const renameModal = document.getElementById('renameModal');
	const oldSportName = document.getElementById('oldSportName');
	const newSportNameInput = document.getElementById('newSportNameInput');
	const modalTitle = document.getElementById('renameModalTitle');

	if (!renameModal || !oldSportName || !newSportNameInput) return;

	// Set old sport name
	oldSportName.value = sportName;
	newSportNameInput.value = sportName;

	// UPDATE: Set sport name in modal title
	if (modalTitle) {
		modalTitle.textContent = 'Rename Sport - ' + sportName;
	}

	renameModal.classList.add('active');
	newSportNameInput.focus();
	newSportNameInput.select();
}

function closeRenameModal() {
	const renameModal = document.getElementById('renameModal');
	if (renameModal) {
		renameModal.classList.remove('active');
	}
}

// ==========================================
// MODAL EVENT LISTENERS
// ==========================================

document.addEventListener('DOMContentLoaded', function () {
	// Close icon modal on outside click
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

	// Close rename modal on outside click
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

	// Save scroll position for delete icon forms
	const deleteIconForms = document.querySelectorAll('form[onsubmit*="Delete icon"]');
	deleteIconForms.forEach(form => {
		form.addEventListener('submit', function (e) {
			saveScrollPosition();
		});
	});

	// Save scroll position for delete sport forms
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

// ==========================================
// DRAG AND DROP WITH CONFIRMATION MODAL
// ==========================================

let draggedElement = null;
let draggedIndex = null;
let autoScrollInterval = null;
let pendingOrder = null; // Store the new order before confirmation

function initDragAndDrop() {
	const accordionsContainer = document.getElementById('pagesAccordions');
	if (!accordionsContainer) {
		return;
	}

	const accordions = accordionsContainer.querySelectorAll('details');

	if (accordions.length === 0) {
		return;
	}

	accordions.forEach((details, index) => {
		details.setAttribute('draggable', 'true');

		details.addEventListener('dragstart', function (e) {
			draggedElement = this;
			draggedIndex = index;
			this.classList.add('dragging');

			e.dataTransfer.effectAllowed = 'move';
			e.dataTransfer.setData('text/html', this.innerHTML);

			this.style.opacity = '0.4';

			startAutoScroll();
		});

		details.addEventListener('dragend', function (e) {
			this.classList.remove('dragging');
			this.style.opacity = '1';

			stopAutoScroll();

			accordions.forEach(d => d.classList.remove('drag-over'));
		});

		details.addEventListener('dragover', function (e) {
			e.preventDefault();
			e.dataTransfer.dropEffect = 'move';

			if (this !== draggedElement) {
				this.classList.add('drag-over');
			}

			updateMousePosition(e);

			return false;
		});

		details.addEventListener('dragenter', function (e) {
			e.preventDefault();
			if (this !== draggedElement) {
				this.classList.add('drag-over');
			}
		});

		details.addEventListener('dragleave', function (e) {
			this.classList.remove('drag-over');
		});

		details.addEventListener('drop', function (e) {
			e.stopPropagation();
			e.preventDefault();

			this.classList.remove('drag-over');

			if (draggedElement !== this) {
				const allDetails = Array.from(accordionsContainer.querySelectorAll('details'));
				const draggedCard = draggedElement;
				const targetCard = this;

				const draggedPos = allDetails.indexOf(draggedCard);
				const targetPos = allDetails.indexOf(targetCard);

				// Move the element in DOM
				if (draggedPos < targetPos) {
					targetCard.parentNode.insertBefore(draggedCard, targetCard.nextSibling);
				} else {
					targetCard.parentNode.insertBefore(draggedCard, targetCard);
				}

				// Show confirmation modal
				showSaveOrderModal();
			}

			return false;
		});
	});
}

// ==========================================
// AUTO-SCROLL DURING DRAG
// ==========================================

let mouseY = 0;
const SCROLL_THRESHOLD = 100;
const SCROLL_SPEED = 8;

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

// ==========================================
// SAVE ORDER CONFIRMATION MODAL
// ==========================================

function showSaveOrderModal() {
	const modal = document.getElementById('saveOrderModal');
	if (modal) {
		modal.classList.add('active');
	}
}

function closeSaveOrderModal() {
	const modal = document.getElementById('saveOrderModal');
	if (modal) {
		modal.classList.remove('active');
	}
}

function cancelOrderChange() {
	// Reload page to restore original order
	location.reload();
}

function confirmSaveOrder() {
	closeSaveOrderModal();
	saveNewOrder();
}

// ==========================================
// SAVE NEW ORDER TO SERVER
// ==========================================

function saveNewOrder() {
	const accordionsContainer = document.getElementById('pagesAccordions');
	if (!accordionsContainer) {
		return;
	}

	const accordions = accordionsContainer.querySelectorAll('details');

	const newOrder = [];
	accordions.forEach((details, index) => {
		const sportName = details.getAttribute('data-sport-name');
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
	const notification = document.createElement('div');
	notification.className = 'drag-notification ' + type;
	notification.textContent = message;

	document.body.appendChild(notification);

	setTimeout(() => {
		notification.style.animation = 'slideOut 0.3s ease';
		setTimeout(() => {
			notification.remove();
		}, 300);
	}, 3000);
}

// ==========================================
// INITIALIZATION
// ==========================================

window.addEventListener('DOMContentLoaded', function () {
	setTimeout(function () {
		initDragAndDrop();
	}, 100);
});

window.addEventListener('load', function () {
	const accordionsContainer = document.getElementById('pagesAccordions');
	if (accordionsContainer) {
		const accordions = accordionsContainer.querySelectorAll('details[draggable="true"]');
		if (accordions.length === 0) {
			initDragAndDrop();
		}
	}
});

// Close modals on outside click
document.addEventListener('DOMContentLoaded', function () {
	const saveOrderModal = document.getElementById('saveOrderModal');
	if (saveOrderModal) {
		saveOrderModal.addEventListener('click', function (e) {
			if (e.target === this) {
				cancelOrderChange();
			}
		});
	}
});
