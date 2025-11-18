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
