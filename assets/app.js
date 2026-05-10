import './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */

import './styles/app.css';

// Face Scan Modal Logic (moved from signup.html.twig)
window.faceScanStep = 0;
window.faceImages = [];
const steps = [
	'Look straight ahead',
	'Look to your left',
	'Look to your right',
	'Look up',
	'Look down'
];
window.openFaceScanModal = function() {
	const modal = document.getElementById('faceScanModal');
	if (!modal) return;
	modal.classList.remove('hidden');
	window.faceScanStep = 0;
	window.faceImages = [];
	updateFaceScanStep();
	startFaceModalCamera();
};
window.closeFaceScanModal = function() {
	const modal = document.getElementById('faceScanModal');
	if (!modal) return;
	modal.classList.add('hidden');
	stopFaceModalCamera();
};
function updateFaceScanStep() {
	for (let i = 0; i < steps.length; i++) {
		const stepEl = document.getElementById('face-step-' + i);
		if (stepEl) stepEl.style.fontWeight = (i === window.faceScanStep) ? 'bold' : 'normal';
	}
	const status = document.getElementById('face-modal-status');
	if (status) status.textContent = steps[window.faceScanStep];
}
let faceModalStream = null;
function startFaceModalCamera() {
	const video = document.getElementById('face-modal-video');
	if (video && navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
		navigator.mediaDevices.getUserMedia({ video: true })
			.then(function(stream) {
				faceModalStream = stream;
				video.srcObject = stream;
				video.play();
			});
	}
}
function stopFaceModalCamera() {
	if (faceModalStream) {
		faceModalStream.getTracks().forEach(track => track.stop());
		faceModalStream = null;
	}
}
window.addEventListener('DOMContentLoaded', function() {
	const captureBtn = document.getElementById('face-modal-capture-btn');
	if (captureBtn) {
		captureBtn.onclick = function() {
			const video = document.getElementById('face-modal-video');
			const canvas = document.getElementById('face-modal-canvas');
			if (!video || !canvas) return;
			canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
			const dataUrl = canvas.toDataURL('image/png');
			window.faceImages.push(dataUrl);
			window.faceScanStep++;
			if (window.faceScanStep < steps.length) {
				updateFaceScanStep();
			} else {
				window.closeFaceScanModal();
				alert('Face scan complete! Images will be saved with your account.');
				// TODO: send faceImages to backend for saving in user folder
			}
		};
	}
});
