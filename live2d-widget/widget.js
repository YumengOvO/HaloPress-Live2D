/*
 * HaloPress-Live2D browser widget.
 *
 * Copyright (C) 2026 YumengOvO and contributors.
 * Licensed under GPL-3.0. This file does not contain Live2D Cubism Core,
 * Cubism SDK components, models, textures, motions, or other model assets.
 */
(function () {
	'use strict';

	var config = window.HaloPressLive2DConfig;
	if (!config || !config.coreUrl || !config.modelUrl) {
		return;
	}

	var prefix = config.storagePrefix || 'halopress-live2d-';
	var storage = createSafeStorage();
	var widget = null;
	var toggle = null;
	var tips = null;
	var canvas = null;
	var model = null;
	var gl = null;
	var animationFrame = 0;
	var messageTimer = 0;
	var idleTimer = 0;
	var bootPromise = null;
	var pointer = { x: 0, y: 0 };
	var labels = config.labels || {};

	function createSafeStorage() {
		return {
			get: function (key) {
				try {
					return window.localStorage.getItem(prefix + key);
				} catch (error) {
					return null;
				}
			},
			set: function (key, value) {
				try {
					window.localStorage.setItem(prefix + key, String(value));
				} catch (error) {
					// Storage is optional; the widget still works without it.
				}
			},
			remove: function (key) {
				try {
					window.localStorage.removeItem(prefix + key);
				} catch (error) {
					// Storage is optional; the widget still works without it.
				}
			}
		};
	}

	function isEnabledForViewport() {
		var breakpoint = Math.max(320, Number(config.mobileBreakpoint) || 768);
		var mobile = window.matchMedia('(max-width: ' + breakpoint + 'px)').matches;
		return mobile ? Boolean(config.mobileEnabled) : Boolean(config.desktopEnabled);
	}

	function ready(callback) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', callback, { once: true });
		} else {
			callback();
		}
	}

	function createToggle() {
		if (toggle) {
			return toggle;
		}

		toggle = document.createElement('button');
		toggle.id = 'halopress-live2d-toggle';
		toggle.className = 'halopress-live2d-side-' + (config.position === 'right' ? 'right' : 'left');
		toggle.type = 'button';
		toggle.textContent = '2D';
		toggle.setAttribute('aria-label', labels.toggle || 'Show Live2D widget');
		toggle.addEventListener('click', function () {
			storage.remove('hidden-at');
			storage.remove('disabled');
			toggle.classList.remove('is-visible');

			if (widget) {
				widget.classList.remove('is-hidden');
				widget.setAttribute('aria-hidden', 'false');
				return;
			}

			boot();
		});
		document.body.appendChild(toggle);
		return toggle;
	}

	function createWidget() {
		widget = document.createElement('aside');
		widget.id = 'halopress-live2d';
		widget.className = 'halopress-live2d-side-' + (config.position === 'right' ? 'right' : 'left');
		widget.setAttribute('aria-label', 'Live2D');

		tips = document.createElement('div');
		tips.id = 'halopress-live2d-tips';
		tips.setAttribute('role', 'status');
		tips.setAttribute('aria-live', 'polite');

		canvas = document.createElement('canvas');
		canvas.id = 'halopress-live2d-canvas';
		canvas.setAttribute('aria-label', 'Live2D model');
		resizeCanvas();

		var toolbar = document.createElement('div');
		toolbar.id = 'halopress-live2d-toolbar';
		toolbar.setAttribute('role', 'toolbar');

		if (config.hitokotoEnabled && config.hitokotoApiUrl) {
			toolbar.appendChild(createToolButton('💬', labels.hitokoto || '一言', requestHitokoto));
		}
		toolbar.appendChild(createToolButton('▣', labels.photo || '拍照', takePhoto));
		toolbar.appendChild(createToolButton('i', labels.info || '项目信息', openProject));
		toolbar.appendChild(createToolButton('×', labels.quit || '关闭', quit));

		widget.appendChild(tips);
		widget.appendChild(canvas);
		widget.appendChild(toolbar);
		document.body.appendChild(widget);

		restoreDraggedPosition();
		if (config.draggable) {
			registerDrag();
			canvas.classList.add('is-draggable');
		}

		window.addEventListener('resize', resizeCanvas, { passive: true });
		document.addEventListener('pointermove', updateLookTarget, { passive: true });
		document.addEventListener('pointerleave', resetLookTarget, { passive: true });
		canvas.addEventListener('click', function () {
			showRandomIdleMessage();
		});
	}

	function createToolButton(symbol, label, callback) {
		var button = document.createElement('button');
		button.type = 'button';
		button.className = 'halopress-live2d-tool';
		button.textContent = symbol;
		button.title = label;
		button.setAttribute('aria-label', label);
		button.addEventListener('click', callback);
		return button;
	}

	function resizeCanvas() {
		if (!canvas) {
			return;
		}

		var size = Math.max(120, Number(config.size) || 300);
		var ratio = Math.min(2, Math.max(1, window.devicePixelRatio || 1));
		var pixels = Math.round(size * ratio);
		if (canvas.width !== pixels || canvas.height !== pixels) {
			canvas.width = pixels;
			canvas.height = pixels;
		}
	}

	function showMessage(text, duration) {
		if (!tips || !text) {
			return;
		}

		window.clearTimeout(messageTimer);
		tips.textContent = String(text);
		tips.classList.add('is-visible');
		messageTimer = window.setTimeout(function () {
			tips.classList.remove('is-visible');
		}, duration || 5000);
	}

	function randomItem(items) {
		if (!Array.isArray(items) || !items.length) {
			return '';
		}
		return items[Math.floor(Math.random() * items.length)];
	}

	function showRandomIdleMessage() {
		showMessage(randomItem(config.idleMessages), 5000);
	}

	function startIdleMessages() {
		var delay = Math.max(10, Number(config.idleInterval) || 20) * 1000;
		var lastActivity = Date.now();
		var activity = function () {
			lastActivity = Date.now();
		};

		['pointermove', 'keydown', 'scroll', 'touchstart'].forEach(function (eventName) {
			window.addEventListener(eventName, activity, { passive: true });
		});

		window.clearInterval(idleTimer);
		idleTimer = window.setInterval(function () {
			if (Date.now() - lastActivity >= delay) {
				showRandomIdleMessage();
				lastActivity = Date.now();
			}
		}, Math.min(delay, 5000));
	}

	function loadScript(url) {
		if (window.Live2D && window.Live2DModelWebGL) {
			return Promise.resolve();
		}

		return new Promise(function (resolve, reject) {
			var script = document.createElement('script');
			script.src = url;
			script.async = true;
			script.dataset.halopressLive2dCore = 'true';
			script.onload = function () {
				if (window.Live2D && window.Live2DModelWebGL) {
					resolve();
				} else {
					reject(new Error('The configured script does not expose the Cubism 2 Web Core API.'));
				}
			};
			script.onerror = function () {
				reject(new Error('Unable to load Cubism 2 Core.'));
			};
			document.head.appendChild(script);
		});
	}

	function fetchChecked(url, responseType) {
		return window.fetch(url, { credentials: 'omit', mode: 'cors' }).then(function (response) {
			if (!response.ok) {
				throw new Error('HTTP ' + response.status + ' for ' + url);
			}
			return responseType === 'arrayBuffer' ? response.arrayBuffer() : response.json();
		});
	}

	function resolveResource(resource, baseUrl) {
		return new URL(resource, baseUrl).href;
	}

	function loadImage(url) {
		return new Promise(function (resolve, reject) {
			var image = new Image();
			image.crossOrigin = 'anonymous';
			image.onload = function () { resolve(image); };
			image.onerror = function () { reject(new Error('Unable to load texture: ' + url)); };
			image.src = url;
		});
	}

	function attachTexture(image, index) {
		var texture = gl.createTexture();
		if (!texture) {
			throw new Error('Unable to create a WebGL texture.');
		}

		if (typeof model.isPremultipliedAlpha === 'function' && !model.isPremultipliedAlpha()) {
			gl.pixelStorei(gl.UNPACK_PREMULTIPLY_ALPHA_WEBGL, 1);
		}
		gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL, 1);
		gl.activeTexture(gl.TEXTURE0);
		gl.bindTexture(gl.TEXTURE_2D, texture);
		gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, image);
		gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
		gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
		gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
		gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
		model.setTexture(index, texture);
	}

	function buildModelMatrix(live2dModel) {
		var modelWidth = typeof live2dModel.getCanvasWidth === 'function'
			? Number(live2dModel.getCanvasWidth())
			: 2;
		var modelHeight = typeof live2dModel.getCanvasHeight === 'function'
			? Number(live2dModel.getCanvasHeight())
			: modelWidth;
		if (!Number.isFinite(modelWidth) || modelWidth <= 0) {
			modelWidth = 2;
		}
		if (!Number.isFinite(modelHeight) || modelHeight <= 0) {
			modelHeight = modelWidth;
		}

		var scale = 2 / modelWidth;
		return [
			scale, 0, 0, 0,
			0, -scale, 0, 0,
			0, 0, 1, 0,
			-1, (modelHeight * scale) / 2, 0, 1
		];
	}

	function applyParameter(id, value) {
		try {
			model.setParamFloat(id, value);
		} catch (error) {
			// Cubism 2 models are not required to expose every standard parameter.
		}
	}

	function drawFrame(timestamp) {
		if (!model || !gl || !canvas || !document.documentElement.contains(canvas)) {
			return;
		}

		gl.viewport(0, 0, canvas.width, canvas.height);
		gl.clearColor(0, 0, 0, 0);
		gl.clear(gl.COLOR_BUFFER_BIT);

		applyParameter('PARAM_ANGLE_X', pointer.x * 30);
		applyParameter('PARAM_ANGLE_Y', pointer.y * 30);
		applyParameter('PARAM_BODY_ANGLE_X', pointer.x * 10);
		applyParameter('PARAM_EYE_BALL_X', pointer.x);
		applyParameter('PARAM_EYE_BALL_Y', pointer.y);
		applyParameter('PARAM_BREATH', (Math.sin(timestamp / 1000) + 1) / 2);

		model.update();
		model.draw();
		animationFrame = window.requestAnimationFrame(drawFrame);
	}

	function loadModel() {
		return Promise.all([
			loadScript(config.coreUrl),
			fetchChecked(config.modelUrl, 'json')
		]).then(function (results) {
			var modelSettings = results[1];
			if (!modelSettings || typeof modelSettings.model !== 'string' || !Array.isArray(modelSettings.textures)) {
				throw new Error('The model JSON is not a supported Cubism 2 model definition.');
			}

			gl = canvas.getContext('webgl', {
				alpha: true,
				premultipliedAlpha: true,
				preserveDrawingBuffer: true
			}) || canvas.getContext('experimental-webgl');
			if (!gl) {
				throw new Error('WebGL is unavailable.');
			}

			window.Live2D.init();
			window.Live2D.setGL(gl);

			var mocUrl = resolveResource(modelSettings.model, config.modelUrl);
			return fetchChecked(mocUrl, 'arrayBuffer').then(function (mocBuffer) {
				model = window.Live2DModelWebGL.loadModel(mocBuffer);
				if (!model) {
					throw new Error('Cubism 2 Core rejected the model file.');
				}
				model.setMatrix(buildModelMatrix(model));

				return Promise.all(modelSettings.textures.map(function (texturePath, index) {
					return loadImage(resolveResource(texturePath, config.modelUrl)).then(function (image) {
						attachTexture(image, index);
					});
				}));
			});
		}).then(function () {
			animationFrame = window.requestAnimationFrame(drawFrame);
		});
	}

	function updateLookTarget(event) {
		if (!canvas) {
			return;
		}
		var rect = canvas.getBoundingClientRect();
		var centerX = rect.left + rect.width / 2;
		var centerY = rect.top + rect.height / 2;
		pointer.x = Math.max(-1, Math.min(1, (event.clientX - centerX) / Math.max(1, window.innerWidth / 2)));
		pointer.y = Math.max(-1, Math.min(1, (centerY - event.clientY) / Math.max(1, window.innerHeight / 2)));
	}

	function resetLookTarget() {
		pointer.x = 0;
		pointer.y = 0;
	}

	function requestHitokoto() {
		var controller = typeof AbortController === 'function' ? new AbortController() : null;
		var timeout = controller ? window.setTimeout(function () { controller.abort(); }, 8000) : 0;
		var options = { credentials: 'omit', mode: 'cors', headers: { Accept: 'application/json' } };
		if (controller) {
			options.signal = controller.signal;
		}

		window.fetch(config.hitokotoApiUrl, options)
			.then(function (response) {
				if (!response.ok) {
					throw new Error('HTTP ' + response.status);
				}
				return response.json();
			})
			.then(function (result) {
				var text = result && (result.hitokoto || result.text || result.message);
				if (!text) {
					throw new Error('The API response does not contain text.');
				}
				if (result.from) {
					text += ' —— ' + result.from;
				}
				showMessage(text, 7000);
			})
			.catch(function (error) {
				console.warn('[HaloPress-Live2D] Hitokoto request failed.', error);
				showMessage(labels.hitokotoError || '一言获取失败，请稍后再试。', 5000);
			})
			.finally(function () {
				window.clearTimeout(timeout);
			});
	}

	function takePhoto() {
		try {
			var link = document.createElement('a');
			link.download = 'halopress-live2d.png';
			link.href = canvas.toDataURL('image/png');
			link.click();
		} catch (error) {
			console.warn('[HaloPress-Live2D] Canvas export failed.', error);
			showMessage(labels.photoError || '无法保存图片，请检查跨域配置。', 6000);
		}
	}

	function openProject() {
		var newWindow = window.open(config.infoUrl, '_blank', 'noopener,noreferrer');
		if (newWindow) {
			newWindow.opener = null;
		}
	}

	function quit() {
		if (!widget) {
			return;
		}

		widget.classList.add('is-hidden');
		widget.setAttribute('aria-hidden', 'true');
		if (config.showToggleAfterQuit) {
			storage.set('hidden-at', Date.now());
			createToggle().classList.add('is-visible');
		} else {
			storage.set('disabled', '1');
			window.setTimeout(function () {
				if (widget) {
					widget.remove();
					widget = null;
				}
				if (toggle) {
					toggle.remove();
					toggle = null;
				}
				window.cancelAnimationFrame(animationFrame);
				window.clearInterval(idleTimer);
			}, 350);
		}
	}

	function registerDrag() {
		var dragging = false;
		var pointerId = null;
		var startX = 0;
		var startY = 0;
		var originLeft = 0;
		var originTop = 0;

		canvas.addEventListener('pointerdown', function (event) {
			if (event.button !== 0) {
				return;
			}
			var rect = widget.getBoundingClientRect();
			dragging = true;
			pointerId = event.pointerId;
			startX = event.clientX;
			startY = event.clientY;
			originLeft = rect.left;
			originTop = rect.top;
			widget.style.left = rect.left + 'px';
			widget.style.top = rect.top + 'px';
			widget.style.right = 'auto';
			widget.style.bottom = 'auto';
			canvas.setPointerCapture(pointerId);
			event.preventDefault();
		});

		canvas.addEventListener('pointermove', function (event) {
			if (!dragging || event.pointerId !== pointerId) {
				return;
			}
			var maxLeft = Math.max(0, window.innerWidth - widget.offsetWidth);
			var maxTop = Math.max(0, window.innerHeight - widget.offsetHeight);
			var left = Math.max(0, Math.min(maxLeft, originLeft + event.clientX - startX));
			var top = Math.max(0, Math.min(maxTop, originTop + event.clientY - startY));
			widget.style.left = left + 'px';
			widget.style.top = top + 'px';
		});

		var stop = function (event) {
			if (!dragging || event.pointerId !== pointerId) {
				return;
			}
			dragging = false;
			storage.set('position', JSON.stringify({
				left: Math.round(parseFloat(widget.style.left) || 0),
				top: Math.round(parseFloat(widget.style.top) || 0)
			}));
		};
		canvas.addEventListener('pointerup', stop);
		canvas.addEventListener('pointercancel', stop);
	}

	function restoreDraggedPosition() {
		if (!config.draggable) {
			return;
		}
		try {
			var saved = JSON.parse(storage.get('position'));
			if (!saved || !Number.isFinite(saved.left) || !Number.isFinite(saved.top)) {
				return;
			}
			var maxLeft = Math.max(0, window.innerWidth - Number(config.size));
			var maxTop = Math.max(0, window.innerHeight - Number(config.size));
			widget.style.left = Math.max(0, Math.min(maxLeft, saved.left)) + 'px';
			widget.style.top = Math.max(0, Math.min(maxTop, saved.top)) + 'px';
			widget.style.right = 'auto';
			widget.style.bottom = 'auto';
		} catch (error) {
			storage.remove('position');
		}
	}

	function boot() {
		if (bootPromise) {
			return bootPromise;
		}

		createWidget();
		bootPromise = loadModel()
			.then(function () {
				widget.classList.add('is-active');
				showMessage(config.welcomeMessage, 7000);
				startIdleMessages();
			})
			.catch(function (error) {
				console.error('[HaloPress-Live2D] Model initialization failed.', error);
				widget.classList.add('is-active', 'has-error');
				showMessage(labels.loadError || '模型加载失败，请检查配置。', 8000);
			});
		return bootPromise;
	}

	ready(function () {
		if (!isEnabledForViewport() || storage.get('disabled') === '1') {
			return;
		}

		var hiddenAt = Number(storage.get('hidden-at'));
		if (config.showToggleAfterQuit && hiddenAt && Date.now() - hiddenAt < 86400000) {
			createToggle().classList.add('is-visible');
			return;
		}

		storage.remove('hidden-at');
		boot();
	});
})();

