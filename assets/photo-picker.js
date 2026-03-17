/*!
 * photo-picker.js
 * Shared inline photo input widget for Lost & Found system.
 * Provides a consistent Camera + Upload interface across all forms.
 *
 * Usage:
 *   var pp = PhotoPicker.init({ el: 'myWrapperId', onChange: fn });
 *   pp.clear();            // reset to idle
 *   pp.setPhoto(dataUrl);  // programmatically set a photo
 */
(function (global) {
  'use strict';

  /* ── Singleton camera state ──────────────────────────────────────────── */
  var _stream      = null;
  var _pendingCb   = null;   /* callback(dataUrl) after "Use This Photo" */
  var _capturedUrl = null;
  var CAM_ID       = 'ppSharedCamOverlay';

  /* ── Build camera overlay HTML ───────────────────────────────────────── */
  function buildCamHtml() {
    return '<div id="' + CAM_ID + '" class="pp-cam-overlay" style="display:none" role="dialog" aria-modal="true">'
      + '<div class="pp-cam-modal" onclick="event.stopPropagation()">'
      +   '<div class="pp-cam-header">'
      +     '<h3 class="pp-cam-title">Take Photo</h3>'
      +     '<button type="button" class="pp-cam-close-btn" id="ppCamClose" aria-label="Close">'
      +       '<i class="fa-solid fa-xmark"></i>'
      +     '</button>'
      +   '</div>'
      +   '<div class="pp-cam-body">'
      +     '<div class="pp-cam-frame">'
      +       '<video id="ppCamVideo" autoplay playsinline muted></video>'
      +       '<img id="ppCamShot" class="pp-cam-shot" src="" alt="">'
      +       '<div class="pp-cam-placeholder" id="ppCamPlaceholder">'
      +         '<i class="fa-solid fa-camera"></i><span>Ready to start</span>'
      +       '</div>'
      +     '</div>'
      +     '<canvas id="ppCamCanvas" style="display:none"></canvas>'
      +     '<p class="pp-cam-hint" id="ppCamHint">Start your camera, then take a photo.</p>'
      +     '<div class="pp-cam-actions" id="ppCamIdleBtns">'
      +       '<button type="button" class="pp-cam-btn pp-cam-btn--start" id="ppCamStart">'
      +         '<i class="fa-solid fa-camera"></i> Start Camera'
      +       '</button>'
      +     '</div>'
      +     '<div class="pp-cam-actions" id="ppCamLiveBtns" style="display:none">'
      +       '<button type="button" class="pp-cam-btn pp-cam-btn--capture" id="ppCamCapture">'
      +         '<i class="fa-solid fa-circle-dot"></i> Take Photo'
      +       '</button>'
      +       '<button type="button" class="pp-cam-btn pp-cam-btn--stop" id="ppCamStop">Stop</button>'
      +     '</div>'
      +     '<div class="pp-cam-actions" id="ppCamCapturedBtns" style="display:none">'
      +       '<button type="button" class="pp-cam-btn pp-cam-btn--retake" id="ppCamRetake">'
      +         '<i class="fa-solid fa-rotate-left"></i> Retake'
      +       '</button>'
      +       '<button type="button" class="pp-cam-btn pp-cam-btn--use" id="ppCamUse">'
      +         '<i class="fa-solid fa-check"></i> Use This Photo'
      +       '</button>'
      +     '</div>'
      +   '</div>'
      + '</div>'
      + '</div>';
  }

  /* ── Inject & bind camera overlay (once per page) ────────────────────── */
  function ensureCamOverlay() {
    if (document.getElementById(CAM_ID)) return;
    var div = document.createElement('div');
    div.innerHTML = buildCamHtml();
    document.body.appendChild(div.firstChild);

    var overlay = document.getElementById(CAM_ID);
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) closeCam();
    });
    document.getElementById('ppCamClose').addEventListener('click', closeCam);
    document.getElementById('ppCamStart').addEventListener('click', startCam);
    document.getElementById('ppCamStop').addEventListener('click', stopCam);
    document.getElementById('ppCamCapture').addEventListener('click', captureCam);
    document.getElementById('ppCamRetake').addEventListener('click', retakeCam);
    document.getElementById('ppCamUse').addEventListener('click', useCam);

    /* ESC closes camera overlay (capture phase so it fires before any modal ESC handler) */
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        var ov = document.getElementById(CAM_ID);
        if (ov && ov.style.display !== 'none') {
          e.stopPropagation();
          closeCam();
        }
      }
    }, true);
  }

  /* ── Internal camera helpers ─────────────────────────────────────────── */
  function g(id) { return document.getElementById(id); }

  function resetCamUI() {
    g('ppCamVideo').style.display         = 'none';
    g('ppCamShot').style.display          = 'none';
    g('ppCamShot').src                    = '';
    g('ppCamPlaceholder').style.display   = 'flex';
    g('ppCamIdleBtns').style.display      = 'flex';
    g('ppCamLiveBtns').style.display      = 'none';
    g('ppCamCapturedBtns').style.display  = 'none';
    g('ppCamHint').textContent = 'Start your camera, then take a photo.';
  }

  function openCam(callback) {
    ensureCamOverlay();
    stopStream();
    _pendingCb   = callback;
    _capturedUrl = null;
    resetCamUI();
    g(CAM_ID).style.display = 'flex';
  }

  function closeCam() {
    stopStream();
    var ov = g(CAM_ID);
    if (ov) ov.style.display = 'none';
  }

  function startCam() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      alert('Camera access is not supported in this browser.');
      return;
    }
    stopStream();
    navigator.mediaDevices.getUserMedia({
      video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } }
    }).then(function (stream) {
      _stream = stream;
      var video = g('ppCamVideo');
      video.srcObject = stream;
      video.play();
      video.style.display               = 'block';
      g('ppCamPlaceholder').style.display = 'none';
      g('ppCamIdleBtns').style.display    = 'none';
      g('ppCamLiveBtns').style.display    = 'flex';
      g('ppCamHint').textContent = 'Position the item, then tap Take Photo.';
    }).catch(function () {
      alert('Could not access the camera. Check browser permissions, or use Upload instead.');
    });
  }

  function stopCam() {
    stopStream();
    if (!g('ppCamPlaceholder')) return;
    g('ppCamPlaceholder').style.display = 'flex';
    g('ppCamVideo').style.display       = 'none';
    g('ppCamLiveBtns').style.display    = 'none';
    g('ppCamIdleBtns').style.display    = 'flex';
    g('ppCamHint').textContent = 'Start your camera, then take a photo.';
  }

  function captureCam() {
    var video  = g('ppCamVideo');
    var canvas = g('ppCamCanvas');
    if (!video || !canvas) return;
    canvas.width  = video.videoWidth  || 640;
    canvas.height = video.videoHeight || 480;
    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
    _capturedUrl = canvas.toDataURL('image/jpeg', 0.88);
    stopStream();
    var shot = g('ppCamShot');
    shot.src = _capturedUrl;
    shot.style.display                   = 'block';
    g('ppCamVideo').style.display        = 'none';
    g('ppCamLiveBtns').style.display     = 'none';
    g('ppCamCapturedBtns').style.display = 'flex';
    g('ppCamHint').textContent = 'Photo captured! Use it or retake.';
  }

  function retakeCam() {
    _capturedUrl = null;
    g('ppCamShot').style.display          = 'none';
    g('ppCamShot').src                    = '';
    g('ppCamCapturedBtns').style.display  = 'none';
    startCam();
  }

  function useCam() {
    var dataUrl = _capturedUrl;
    closeCam();
    if (dataUrl && _pendingCb) _pendingCb(dataUrl);
  }

  function stopStream() {
    if (_stream) {
      _stream.getTracks().forEach(function (t) { t.stop(); });
      _stream = null;
    }
    var video = g('ppCamVideo');
    if (video) { video.srcObject = null; }
  }

  /* ── PhotoPicker.init ────────────────────────────────────────────────── */
  /**
   * Wire up a .pp-wrap element.
   * @param  {object} opts
   *   opts.el       {string|HTMLElement}  The .pp-wrap element or its id
   *   opts.onChange {function(dataUrl)}   Called with dataUrl or null on change
   * @return {{ clear, setPhoto }}
   */
  function init(opts) {
    var wrap = typeof opts.el === 'string'
      ? document.getElementById(opts.el)
      : opts.el;
    if (!wrap) return { clear: noop, setPhoto: noop };

    var idle      = wrap.querySelector('.pp-idle');
    var preview   = wrap.querySelector('.pp-preview');
    var imgEl     = wrap.querySelector('.pp-preview-img');
    var fileInput = wrap.querySelector('.pp-file');

    function showPhoto(dataUrl) {
      if (imgEl)   imgEl.src = dataUrl;
      if (idle)    idle.style.display    = 'none';
      if (preview) preview.style.display = 'block';
      if (opts.onChange) opts.onChange(dataUrl);
    }

    function clearPhoto() {
      if (imgEl)     imgEl.src = '';
      if (idle)      idle.style.display    = 'block';
      if (preview)   preview.style.display = 'none';
      if (fileInput) fileInput.value = '';
      if (opts.onChange) opts.onChange(null);
    }

    /* Delegate all button clicks within the widget */
    wrap.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-pp]');
      if (!btn) return;
      var action = btn.getAttribute('data-pp');
      if (action === 'camera') {
        openCam(showPhoto);
      } else if (action === 'upload') {
        if (fileInput) fileInput.click();
      } else if (action === 'remove') {
        clearPhoto();
      }
    });

    if (fileInput) {
      fileInput.addEventListener('change', function () {
        var file = this.files && this.files[0];
        if (!file) return;
        if (file.size > 10 * 1024 * 1024) {
          alert('Image must be under 10 MB.');
          this.value = '';
          return;
        }
        var reader = new FileReader();
        reader.onload = function (ev) { showPhoto(ev.target.result); };
        reader.readAsDataURL(file);
      });
    }

    return { clear: clearPhoto, setPhoto: showPhoto };
  }

  function noop() {}

  /* ── Export ──────────────────────────────────────────────────────────── */
  global.PhotoPicker = { init: init, openCamera: openCam };

}(window));
