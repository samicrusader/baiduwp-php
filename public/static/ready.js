// add qrcode
$('[data-qrcode-attr]').each(function (i) {
	this.outerHTML = this.outerHTML + `<span class="qrcode" id="TEMP_QRCODE_ATTR_ID${i}"></span>`;
	const codeDiv = document.querySelector(`#TEMP_QRCODE_ATTR_ID${i}`),
		size = (this.getAttribute('data-qrcode-size') !== null) ? this.dataset.qrcodeSize : 512;
	let level = QRCode.CorrectLevel.M;
	if (this.getAttribute('data-qrcode-level') !== null) {
		switch (this.dataset.qrcodeLevel) {
			case 'L':
				level = QRCode.CorrectLevel.L;
				break;
			case 'M':
				break;
			case 'H':
				level = QRCode.CorrectLevel.H;
				break;
			case 'Q':
				level = QRCode.CorrectLevel.Q;
				break;
			default:
		}
	}
	makeQRCode(codeDiv, this.getAttribute(this.dataset.qrcodeAttr), size, level);
	codeDiv.removeAttribute('id');
});

$('[data-qrcode-text]').each(function (i) {
	this.outerHTML = this.outerHTML + `<span class="qrcode" id="TEMP_QRCODE_TEXT_ID${i}"></span>`;
	const codeDiv = document.querySelector(`#TEMP_QRCODE_TEXT_ID${i}`),
		size = (this.getAttribute('data-qrcode-size') !== null) ? this.dataset.qrcodeSize : 512;
	let level = QRCode.CorrectLevel.M;
	if (this.getAttribute('data-qrcode-level') !== null) {
		switch (this.dataset.qrcodeLevel) {
			case 'L':
				level = QRCode.CorrectLevel.L;
				break;
			case 'M':
				break;
			case 'H':
				level = QRCode.CorrectLevel.H;
				break;
			case 'Q':
				level = QRCode.CorrectLevel.Q;
				break;
			default:
		}
	}
	makeQRCode(codeDiv, this.dataset.qrcodeText, size, level);
	codeDiv.removeAttribute('id');
});

let CheckUpdate = localStorage.getItem('UpdateTip') || "true";
if (CheckUpdate === "true")
	getAPI('/system/update').then(function (response) {
		if (!response.success) {
			console.log('Update check failed：');
			console.log(response);
		}
		console.log('New update: ');
		console.log(response);
		const data = response.data;
		if (data.code === 0) {
			if (data.have_update) {
				const div = document.createElement('div');
				div.id = 'CheckUpdate';
				div.style.margin = '0.3rem 1rem';
				div.style.display = 'none';
				div.innerHTML = `There is a new version of the Baiduwp-PHP project: ${data.version}（${data.isPreRelease ? '(pre-release)' : ''} Current version is ${data.now_version}! Contact the site operator to apply the update.
					&nbsp; <a href="${data.page_url}" target="_blank">Release/Download page</a><div style="float: right;"><a href="javascript:SetUpdateTip(false);">Don't prompt again</a></div>`;
				document.body.insertAdjacentElement('beforeBegin', div);
				if (localStorage.getItem('UpdateTip') !== "false")
					$('#CheckUpdate').show(1500);
			}
		} else if (data.code === 2) {
			console.log('The current version is pre-release and does not prompt for updates.');
		} else if (data.code === 1) {
			console.log('Server failed to get updates! Details:');
		} else {
			console.log('The server failed to fetch an update and the error code is not in the supported list! Details:');
		}
	});

function SetUpdateTip(value) {
	localStorage.setItem('UpdateTip', `${value}`); // 不知为啥，只能用string类型
	if (value) $('#CheckUpdate').show(2000);
	else $('#CheckUpdate').hide(2000);
}