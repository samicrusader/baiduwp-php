const colorMode = localStorage.getItem('colorMode'); // 获取色彩模式配置
{ // 限制变量作用域
	function DarkMod() { // 更改为深色模式
		document.querySelector('html').setAttribute('data-bs-theme', 'dark');

		document.querySelector('#Swal2-Light').disabled = true;
		document.querySelector('#Swal2-Dark').disabled = false;
	}
	function LightMod() { // 更改为浅色模式
		document.querySelector('html').setAttribute('data-bs-theme', 'light');

		document.querySelector('#Swal2-Dark').disabled = true;
		document.querySelector('#Swal2-Light').disabled = false;
	}
	function followBrowser() {
		const dark = window.matchMedia('(prefers-color-scheme: dark)'),
			light = window.matchMedia('(prefers-color-scheme: light)');
		function change() { // 更改配色
			if (dark.matches) { // 深色模式
				DarkMod();
			} else if (light.matches) { // 浅色模式
				LightMod();
			} else { // 对于不支持选择的远古浏览器，启用浅色模式
				LightMod();
			}
		}
		dark.addEventListener('change', change); // 配色更改事件
		light.addEventListener('change', change);
		change(); // 加载页面时初始化
	}

	if (colorMode === null) { // 若没有配置
		followBrowser(); // 跟随浏览器
	} else if (colorMode === 'dark') { // 深色模式
		DarkMod();
	} else if (colorMode === 'light') { // 浅色模式
		LightMod();
	} else { // 配置错误时的自动纠正
		localStorage.removeItem('colorMode'); // 删除配置
		followBrowser(); // 跟随浏览器
		document.addEventListener('DOMContentLoaded', function () { // 弹出提示
			Swal.fire({
				title: 'Wrong color configuration',
				html: 'There was an error in the color mode configuration and the configuration has been reset! <br/>Press OK to refresh the page.',
				icon: 'warning',
				footer: '<a href="./#/usersettings" target="_blank">Click here to go to the color mode modification page</a>'
			}).then(function () {
				location.reload(); // 重载页面
			});
		});
	}
}