(function () {
	var root = document.querySelector('.npcink-openclaw-adapter-connection');
	if (!root) {
		return;
	}

	var copiedLabel = root.getAttribute('data-maa-copied-label') || 'Copied';
	var failedLabel = root.getAttribute('data-maa-copy-failed-label') || 'Copy failed';

	function copyText(text) {
		if (!text) {
			return Promise.reject(new Error('empty'));
		}

		if (window.navigator.clipboard && window.navigator.clipboard.writeText) {
			return window.navigator.clipboard.writeText(text);
		}

		return new Promise(function (resolve, reject) {
			var textarea = document.createElement('textarea');
			textarea.value = text;
			textarea.setAttribute('readonly', 'readonly');
			textarea.style.position = 'fixed';
			textarea.style.top = '-1000px';
			document.body.appendChild(textarea);
			textarea.select();

			try {
				if (document.execCommand('copy')) {
					resolve();
				} else {
					reject(new Error('execCommand'));
				}
			} catch (error) {
				reject(error);
			} finally {
				document.body.removeChild(textarea);
			}
		});
	}

	root.querySelectorAll('[data-maa-copy-target]').forEach(function (button) {
		button.addEventListener('click', function () {
			var target = document.getElementById(button.getAttribute('data-maa-copy-target'));
			var text = target ? (target.value || target.textContent || '') : '';
			var oldText = button.textContent;

			copyText(text).then(function () {
				button.textContent = copiedLabel;
				window.setTimeout(function () {
					button.textContent = oldText;
				}, 1500);
			}).catch(function () {
				button.textContent = failedLabel;
				window.setTimeout(function () {
					button.textContent = oldText;
				}, 2000);
			});
		});
	});
})();
