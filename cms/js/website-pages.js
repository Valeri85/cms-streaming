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

// ==========================================
// INITIALIZATION
// ==========================================

window.addEventListener('DOMContentLoaded', function () {
	restoreScrollPosition();
	attachScrollSaveToForms();
	initFileInputListeners();
	initDeleteConfirmation();
	setTimeout(initDragAndDrop, 100);
	initModalCloseHandlers();
});

function attachScrollSaveToForms() {
	const allForms = document.querySelectorAll('form');
	allForms.forEach(form => {
		form.addEventListener('submit', function (e) {
			saveScrollPosition();
		});
	});
}

// ==========================================
// FILE INPUT LISTENERS
// ==========================================

function initFileInputListeners() {
	const fileInputs = document.querySelectorAll('.file-upload-input');
	fileInputs.forEach(input => {
		input.addEventListener('change', function (e) {
			const fileName = e.target.files[0]?.name || 'No file chosen';

			const wrapper = e.target.closest('.file-upload-wrapper') || e.target.closest('.icon-upload-area');
			if (wrapper) {
				const display = wrapper.querySelector('.file-name-display, .file-name-inline');
				if (display) {
					display.textContent = fileName;
				}
			}

			const inputId = e.target.id;

			if (inputId === 'home_icon_file') {
				const display = document.getElementById('homeIconFileName');
				if (display) display.textContent = fileName;
			}

			if (inputId === 'new_sport_icon') {
				const display = document.getElementById('newSportFileName');
				if (display) display.textContent = fileName;
			}
		});
	});
}

// ==========================================
// MODAL FUNCTIONS
// ==========================================

function closeModal(modalId) {
	const modal = document.getElementById(modalId);
	if (modal) {
		modal.classList.remove('active');
	}
}

function initModalCloseHandlers() {
	const modals = document.querySelectorAll('.modal');
	modals.forEach(modal => {
		modal.addEventListener('click', function (e) {
			if (e.target === this) {
				if (this.id === 'saveOrderModal') {
					cancelOrderChange();
				} else {
					this.classList.remove('active');
				}
			}
		});
	});
}

// ==========================================
// DELETE ICON MODAL
// ==========================================

function openDeleteIconModal(sportName, displayName) {
	const modal = document.getElementById('deleteIconModal');
	const form = document.getElementById('deleteIconForm');
	const sportNameInput = document.getElementById('deleteIconSportName');
	const messageElement = document.getElementById('deleteIconMessage');

	if (!modal || !form) return;

	const isHome = sportName === 'home';

	const deleteInput = form.querySelector('input[name="delete_icon"], input[name="delete_home_icon"]');
	if (deleteInput) {
		deleteInput.name = isHome ? 'delete_home_icon' : 'delete_icon';
	}

	if (sportNameInput) {
		sportNameInput.value = sportName;
	}

	if (messageElement) {
		messageElement.textContent = 'Are you sure you want to delete the icon for "' + displayName + '"?';
	}

	modal.classList.add('active');
}

// ==========================================
// RENAME MODAL
// ==========================================

function openRenameModal(sportName) {
	const modal = document.getElementById('renameModal');
	const oldNameInput = document.getElementById('renameOldSportName');
	const newNameInput = document.getElementById('renameNewSportName');

	if (!modal) return;

	if (oldNameInput) oldNameInput.value = sportName;

	if (newNameInput) {
		newNameInput.value = sportName;
		setTimeout(() => {
			newNameInput.focus();
			newNameInput.select();
		}, 100);
	}

	modal.classList.add('active');
}

// ==========================================
// DELETE SPORT MODAL
// ==========================================

function openDeleteModal(sportName) {
	const modal = document.getElementById('deleteModal');
	const sportNameDisplay = document.getElementById('deleteSportNameDisplay');
	const sportNameInput = document.getElementById('deleteSportName');
	const confirmInput = document.getElementById('confirmSportNameInput');
	const confirmBtn = document.getElementById('confirmDeleteBtn');

	if (!modal) return;

	if (sportNameDisplay) sportNameDisplay.textContent = sportName;
	if (sportNameInput) sportNameInput.value = sportName;

	if (confirmInput) confirmInput.value = '';
	if (confirmBtn) confirmBtn.disabled = true;

	modal.classList.add('active');

	if (confirmInput) {
		setTimeout(() => confirmInput.focus(), 100);
	}
}

function initDeleteConfirmation() {
	const confirmInput = document.getElementById('confirmSportNameInput');
	const confirmBtn = document.getElementById('confirmDeleteBtn');
	const sportNameInput = document.getElementById('deleteSportName');

	if (confirmInput && confirmBtn && sportNameInput) {
		confirmInput.addEventListener('input', function () {
			const typedName = confirmInput.value.trim();
			const expectedName = sportNameInput.value;
			confirmBtn.disabled = typedName !== expectedName;
		});
	}
}

// ==========================================
// DRAG AND DROP
// ==========================================

let draggedElement = null;
let autoScrollInterval = null;
let mouseY = 0;
const SCROLL_THRESHOLD = 100;
const SCROLL_SPEED = 8;

function initDragAndDrop() {
	const accordionsContainer = document.getElementById('pagesAccordions');
	if (!accordionsContainer) return;

	const accordions = accordionsContainer.querySelectorAll('details');
	if (accordions.length === 0) return;

	accordions.forEach((details, index) => {
		if (details.getAttribute('data-page-type') === 'home') return;

		details.setAttribute('draggable', 'true');

		details.addEventListener('dragstart', function (e) {
			draggedElement = this;
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

				if (draggedPos < targetPos) {
					targetCard.parentNode.insertBefore(draggedCard, targetCard.nextSibling);
				} else {
					targetCard.parentNode.insertBefore(draggedCard, targetCard);
				}

				showSaveOrderModal();
			}

			return false;
		});
	});
}

function updateMousePosition(e) {
	mouseY = e.clientY;
}

function startAutoScroll() {
	if (autoScrollInterval) clearInterval(autoScrollInterval);

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
			window.scrollBy(0, -Math.ceil(SCROLL_SPEED * intensity));
		} else if (mouseY > windowHeight - SCROLL_THRESHOLD && scrollTop + windowHeight < documentHeight) {
			const intensity = (mouseY - (windowHeight - SCROLL_THRESHOLD)) / SCROLL_THRESHOLD;
			window.scrollBy(0, Math.ceil(SCROLL_SPEED * intensity));
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
// SAVE ORDER MODAL
// ==========================================

function showSaveOrderModal() {
	const modal = document.getElementById('saveOrderModal');
	if (modal) {
		modal.classList.add('active');
	}
}

function cancelOrderChange() {
	location.reload();
}

function confirmSaveOrder() {
	closeModal('saveOrderModal');
	saveNewOrder();
}

function saveNewOrder() {
	const accordionsContainer = document.getElementById('pagesAccordions');
	if (!accordionsContainer) return;

	const accordions = accordionsContainer.querySelectorAll('details');
	const newOrder = [];

	accordions.forEach(details => {
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
		setTimeout(() => notification.remove(), 300);
	}, 3000);
}
