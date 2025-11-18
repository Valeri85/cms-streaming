// Update color text inputs when color picker changes
document.getElementById('primary_color').addEventListener('input', function (e) {
	e.target.nextElementSibling.value = e.target.value;
});

document.getElementById('secondary_color').addEventListener('input', function (e) {
	e.target.nextElementSibling.value = e.target.value;
});

// Logo preview
document.getElementById('logo_file').addEventListener('change', function (e) {
	const fileName = e.target.files[0]?.name || 'No file chosen';
	document.getElementById('logoFileName').textContent = fileName;

	if (e.target.files[0]) {
		const reader = new FileReader();
		reader.onload = function (event) {
			const preview = document.getElementById('logoPreview');
			preview.innerHTML = '<img src="' + event.target.result + '" alt="Logo Preview">';
			preview.classList.remove('empty');
		};
		reader.readAsDataURL(e.target.files[0]);
	}
});
