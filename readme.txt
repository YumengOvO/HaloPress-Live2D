=== HaloPress-Live2D ===
Contributors: yumengovo
Tags: live2d, cubism2, widget
Requires at least: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Add a configurable Cubism 2 character widget without bundling models or the Live2D runtime.

== Description ==

HaloPress-Live2D provides a native WordPress settings page and a lightweight
front-end widget. Site administrators supply their own licensed Cubism 2 Core
and model URLs.

The plugin does not include models, textures, motions, Cubism Core, Cubism SDK,
AI chat, model upload, model switching, or costume switching.

== Installation ==

1. Upload and activate the plugin.
2. Open Settings > HaloPress-Live2D.
3. Enter a trusted Cubism 2 Core URL and a Cubism 2 model JSON URL.
4. Save settings.

== Frequently Asked Questions ==

= Does this plugin include a Live2D model? =

No. You must provide a model URL and comply with that model's license.

= Does this plugin include Cubism Core or SDK files? =

No. You must provide a Cubism 2 Core URL and comply with Live2D's terms.

= Why does a model fail to load? =

Confirm that the model uses the Cubism 2 JSON format and that its JSON, MOC,
and texture server responses allow cross-origin browser access.

== Privacy ==

Configured runtime and model resources are requested directly by the visitor's
browser. The optional Hitokoto API is contacted only when a visitor clicks its
button. Widget state is stored locally in the visitor's browser.

== Changelog ==

= 1.0.0 =

* Initial WordPress plugin release.
