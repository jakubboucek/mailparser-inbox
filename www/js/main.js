(function(){
	function init() {
		hideLoader();
	}

	function hideLoader() {
		$('#loader').hide();
		$('#select-command-panel').removeClass('hidden');
	}
	function showLoader() {
		$('#loader').show();
		$('#select-command-panel').addClass('hidden');
	}
	function startWork() {
		showLoader();
		$('.menu-panel .btn, .content-panel .disableable').prop('disabled', true);
		$('.content-panel').addClass('in-progress');
	}
	function stopWork() {
		hideLoader();
		$('.menu-panel .btn, .content-panel .disableable').prop('disabled', false);
		$('.content-panel').removeClass('in-progress');
	}

	function errorMessage(message) {
		$('#alert').show().text(message);
		$('.menu-panel .btn').prop('disabled', true);
		$('.content-panel').addClass('in-progress');
	}

	function errorHandler(error) {
		console.log(error);
		errorMessage("Došlo k neočekávané chybě (" + error.message + ")");
	}

	window.addEventListener('error', function (e) {
		console.log(error);
		var error = e.error;
		errorHandler(error);
	});

	$( window ).load(init);
})()