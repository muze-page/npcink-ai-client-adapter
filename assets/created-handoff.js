(function () {
	var copiedLabel = document.body.getAttribute('data-maa-copied-label') || 'Copied';

	document.querySelectorAll('[data-maa-created-copy-target]').forEach(function (button) {
		button.addEventListener('click', function () {
			var target = document.getElementById(button.getAttribute('data-maa-created-copy-target'));
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
