(function () {
	var root = document.querySelector('.npcink-openclaw-adapter-connection');
	if (!root) {
		return;
	}

	var copiedLabel = root.getAttribute('data-maa-copied-label') || 'Copied';

	root.querySelectorAll('[data-maa-copy-target]').forEach(function (button) {
		button.addEventListener('click', function () {
			var target = document.getElementById(button.getAttribute('data-maa-copy-target'));
			var text = target ? (target.value || target.textContent || '') : '';
			if (!text || !window.navigator.clipboard) {
				return;
			}

			window.navigator.clipboard.writeText(text).then(function () {
				var oldText = button.textContent;
				button.textContent = copiedLabel;
				window.setTimeout(function () {
					button.textContent = oldText;
				}, 1500);
			});
		});
	});
})();
