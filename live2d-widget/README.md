# HaloPress Live2D Widget

This directory contains the GPL-3.0 browser-facing code used by the WordPress
plugin. It was rewritten for the HaloPress integration and calls a Cubism 2
Core URL configured by the site administrator at runtime.

It intentionally does **not** include:

- Live2D Cubism Core or SDK source/object code;
- models, textures, motions, audio, or sample materials;
- third-party model presets;
- AI/chat features.

The configured model must use the Cubism 2 `model.json` format and all remote
resources must allow cross-origin browser requests. Users are responsible for
obtaining and complying with the licenses for the runtime and model resources
they configure.

