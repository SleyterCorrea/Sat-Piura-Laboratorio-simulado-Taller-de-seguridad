// Copyright (c) 2012, 2014 Hyunje Alex Jun and other contributors
// Licensed under the MIT License

smMobile = navigator.userAgent.match(/(Android|webOS|BlackBerry|iPhone|iPad|iPod|Windows Phone)/i);
if (!smMobile) (function (factory) {
	factory(window.jq183 || jQuery);
})(function ($) {
	'use strict';

	function int(x) {
		if (typeof x === 'string') {
			return parseInt(x, 10);
		} else {
			return ~~x;
		}
	}

	var defaultSettings = {
		wheelSpeed: 1,
		wheelPropagation: false,
		minScrollbarLength: null,
		maxScrollbarLength: null,
		useBothWheelAxes: false,
		useKeyboard: true,
		suppressScrollX: true,
		suppressScrollY: false,
		scrollXMarginOffset: 0,
		scrollYMarginOffset: 0,
		includePadding: true
	};

	var getEventClassName = (function () {
		var incrementingId = 0;
		return function () {
			var id = incrementingId;
			incrementingId += 1;
			return '.perfect-scrollbar-' + id;
		};
	})();

	$.fn.perfectScrollbar = function (suppliedSettings, option) {

		return this.each(function () {
			var settings = $.extend(true, {}, defaultSettings);
			var $this = $(this);

			if (typeof suppliedSettings === "object") {
				// Override default settings with any supplied
				$.extend(true, settings, suppliedSettings);
			} else {
				// If no setting was supplied, then the first param must be the option
				option = suppliedSettings;
			}

			// Catch options
			if (option === 'update') {
				if ($this.data('perfect-scrollbar-update')) {
					$this.data('perfect-scrollbar-update')();
				}
				return $this;
			}
			else if (option === 'destroy') {
				if ($this.data('perfect-scrollbar-destroy')) {
					$this.data('perfect-scrollbar-destroy')();
				}
				return $this;
			}

			if ($this.data('perfect-scrollbar')) {
				// if there's already perfect-scrollbar
				return $this.data('perfect-scrollbar');
			}


			// Or generate new perfectScrollbar

			$this.addClass('ps-container');

			var containerWidth;
			var containerHeight;
			var contentWidth;
			var contentHeight;

			var isRtl = $this.css('direction') === "rtl";
			var eventClassName = getEventClassName();
			var ownerDocument = this.ownerDocument || document;

			var $scrollbarYRail = $("<div class='ps-scrollbar-y-rail'>").appendTo($this);
			var $scrollbarY = $("<div class='ps-scrollbar-y'>").appendTo($scrollbarYRail);
			var scrollbarYActive;
			var scrollbarYHeight;
			var scrollbarYTop;
			var scrollbarYRight = int($scrollbarYRail.css('right'));
			var isScrollbarYUsingRight = scrollbarYRight === scrollbarYRight; // !isNaN
			var scrollbarYLeft = isScrollbarYUsingRight ? null : int($scrollbarYRail.css('left'));
			var railBorderYWidth = 0;

			function updateScrollTop(currentTop, deltaY) {
				var newTop = currentTop + deltaY;
				var maxTop = containerHeight - scrollbarYHeight;

				if (newTop < 0) {
					scrollbarYTop = 0;
				} else if (newTop > maxTop) {
					scrollbarYTop = maxTop;
				} else {
					scrollbarYTop = newTop;
				}

				var scrollTop = int(scrollbarYTop * (contentHeight - containerHeight) / (containerHeight - scrollbarYHeight));
				$this.scrollTop(scrollTop);
			}

			function getThumbSize(thumbSize) {
				if (settings.minScrollbarLength) {
					thumbSize = Math.max(thumbSize, settings.minScrollbarLength);
				}
				if (settings.maxScrollbarLength) {
					thumbSize = Math.min(thumbSize, settings.maxScrollbarLength);
				}
				return thumbSize;
			}

			function updateCss() {
				var railYOffset = {top: $this.scrollTop(), height: containerHeight, display: scrollbarYActive ? "inherit" : "none"};

				if (isScrollbarYUsingRight) {
					if (isRtl) {
						railYOffset.right = contentWidth - $this.scrollLeft() - scrollbarYRight - $scrollbarY.outerWidth();
					} else {
						railYOffset.right = scrollbarYRight - $this.scrollLeft();
					}
				} else {
					if (isRtl) {
						railYOffset.left = $this.scrollLeft() + containerWidth * 2 - contentWidth - scrollbarYLeft - $scrollbarY.outerWidth();
					} else {
						railYOffset.left = scrollbarYLeft + $this.scrollLeft();
					}
				}
				$scrollbarYRail.css(railYOffset);

				$scrollbarY.css({top: scrollbarYTop, height: scrollbarYHeight - railBorderYWidth});
				$scrollbarY.parent().css("visibility", scrollbarYHeight - railBorderYWidth > 0 ? "visible" : "hidden");
			}

			function updateGeometry() {
				// Hide scrollbars not to affect scrollWidth and scrollHeight
				$scrollbarYRail.hide();

				containerWidth = settings.includePadding ? $this.innerWidth() : $this.width();
				containerHeight = settings.includePadding ? $this.innerHeight() : $this.height();
				contentWidth = $this.prop('scrollWidth');
				contentHeight = $this.prop('scrollHeight');

				if (!settings.suppressScrollY && containerHeight + settings.scrollYMarginOffset < contentHeight - 1) {
					scrollbarYActive = true;
					scrollbarYHeight = getThumbSize(int(containerHeight * containerHeight / contentHeight));
					scrollbarYTop = int($this.scrollTop() * (containerHeight - scrollbarYHeight) / (contentHeight - containerHeight));
				} else {
					scrollbarYActive = false;
					scrollbarYHeight = 0;
					scrollbarYTop = 0;
					$this.scrollTop(0);
				}

				if (scrollbarYTop >= containerHeight - scrollbarYHeight) {
					scrollbarYTop = containerHeight - scrollbarYHeight;
				}

				updateCss();

				if (scrollbarYActive) {
					$this.addClass('ps-active-y');
				} else {
					$this.removeClass('ps-active-y');
				}

				// Show scrollbars if needed after updated
				if (!settings.suppressScrollY) {
					$scrollbarYRail.show();
				}
			}

			function bindMouseScrollYHandler() {
				var currentTop;
				var currentPageY;

				$scrollbarY.on('mousedown' + eventClassName, function (e) {
					currentPageY = e.pageY;
					currentTop = $scrollbarY.position().top;
					$scrollbarYRail.addClass('in-scrolling');
					e.stopPropagation();
					e.preventDefault();
				});

				$(ownerDocument).on('mousemove' + eventClassName, function (e) {
					if ($scrollbarYRail.hasClass('in-scrolling')) {
						updateScrollTop(currentTop, e.pageY - currentPageY);
						updateGeometry();
						e.stopPropagation();
						e.preventDefault();
					}
				});

				$(ownerDocument).on('mouseup' + eventClassName, function (e) {
					if ($scrollbarYRail.hasClass('in-scrolling')) {
						$scrollbarYRail.removeClass('in-scrolling');
					}
				});

				currentTop =
				currentPageY = null;
			}

			// check if the default scrolling should be prevented.
			function shouldPreventDefault(deltaX, deltaY) {
				var scrollTop = $this.scrollTop();
				if (deltaX === 0) {
					if (!scrollbarYActive) {
						return false;
					}
					if ((scrollTop === 0 && deltaY > 0) || (scrollTop >= contentHeight - containerHeight && deltaY < 0)) {
						return !settings.wheelPropagation;
					}
				}

				var scrollLeft = $this.scrollLeft();
				if (deltaY === 0) {
					if (!scrollbarXActive) {
						return false;
					}
					if ((scrollLeft === 0 && deltaX < 0) || (scrollLeft >= contentWidth - containerWidth && deltaX > 0)) {
						return !settings.wheelPropagation;
					}
				}
				return true;
			}

			function bindMouseWheelHandler() {
				var shouldPrevent = false;

				function getDeltaFromEvent(e) {
					var deltaX = e.originalEvent.deltaX;
					var deltaY = -1 * e.originalEvent.deltaY;

					if (typeof deltaX === "undefined" || typeof deltaY === "undefined") {
						// OS X Safari
						deltaX = -1 * e.originalEvent.wheelDeltaX / 6;
						deltaY = e.originalEvent.wheelDeltaY / 6;
					}

					if (e.originalEvent.deltaMode && e.originalEvent.deltaMode === 1) {
						// Firefox in deltaMode 1: Line scrolling
						deltaX *= 10;
						deltaY *= 10;
					}

					if (deltaX !== deltaX && deltaY !== deltaY/* NaN checks */) {
						// IE in some mouse drivers
						deltaX = 0;
						deltaY = e.originalEvent.wheelDelta;
					}

					return [deltaX, deltaY];
				}

				function mousewheelHandler(e) {
					var delta = getDeltaFromEvent(e);

					var deltaX = delta[0];
					var deltaY = delta[1];

					shouldPrevent = false;
					if (!settings.useBothWheelAxes) {
						// deltaX will only be used for horizontal scrolling and deltaY will
						// only be used for vertical scrolling - this is the default
						$this.scrollTop($this.scrollTop() - (deltaY * settings.wheelSpeed));
						$this.scrollLeft($this.scrollLeft() + (deltaX * settings.wheelSpeed));
					} else if (scrollbarYActive && !scrollbarXActive) {
						// only vertical scrollbar is active and useBothWheelAxes option is
						// active, so let's scroll vertical bar using both mouse wheel axes
						if (deltaY) {
							$this.scrollTop($this.scrollTop() - (deltaY * settings.wheelSpeed));
						} else {
							$this.scrollTop($this.scrollTop() + (deltaX * settings.wheelSpeed));
						}
						shouldPrevent = true;
					} else if (scrollbarXActive && !scrollbarYActive) {
						// useBothWheelAxes and only horizontal bar is active, so use both
						// wheel axes for horizontal bar
						if (deltaX) {
							$this.scrollLeft($this.scrollLeft() + (deltaX * settings.wheelSpeed));
						} else {
							$this.scrollLeft($this.scrollLeft() - (deltaY * settings.wheelSpeed));
						}
						shouldPrevent = true;
					}

					updateGeometry();

					shouldPrevent = (shouldPrevent || shouldPreventDefault(deltaX, deltaY));
					if (shouldPrevent) {
						e.stopPropagation();
						e.preventDefault();
					}
				}

				if (typeof window.onwheel !== "undefined") {
					$this.on('wheel' + eventClassName, mousewheelHandler);
				} else if (typeof window.onmousewheel !== "undefined") {
					$this.on('mousewheel' + eventClassName, mousewheelHandler);
				}
			}

			function bindKeyboardHandler() {
				var hovered = false;
				$this.on('mouseenter' + eventClassName, function (e) {
					hovered = true;
				});
				$this.on('mouseleave' + eventClassName, function (e) {
					hovered = false;
				});

				var shouldPrevent = false;
				$(ownerDocument).on('keydown' + eventClassName, function (e) {
					if (e.isDefaultPrevented && e.isDefaultPrevented()) {
						return;
					}

					if (!hovered) {
						return;
					}

					var activeElement = document.activeElement ? document.activeElement : ownerDocument.activeElement;
					// go deeper if element is a webcomponent
					while (activeElement.shadowRoot) {
						activeElement = activeElement.shadowRoot.activeElement;
					}
					if ($(activeElement).is(":input,[contenteditable]")) {
						return;
					}

					var deltaX = 0;
					var deltaY = 0;

					switch (e.which) {
					case 38: // up
						deltaY = 30;
						break;
					case 40: // down
						deltaY = -30;
						break;
					case 33: // page up
						deltaY = 90;
						break;
					case 32: // space bar
					case 34: // page down
						deltaY = -90;
						break;
					case 35: // end
						if (e.ctrlKey) {
							deltaY = -contentHeight;
						} else {
							deltaY = -containerHeight;
						}
						break;
					case 36: // home
						if (e.ctrlKey) {
							deltaY = $this.scrollTop();
						} else {
							deltaY = containerHeight;
						}
						break;
					default:
						return;
					}

					$this.scrollTop($this.scrollTop() - deltaY);
					$this.scrollLeft($this.scrollLeft() + deltaX);

					shouldPrevent = shouldPreventDefault(deltaX, deltaY);
					if (shouldPrevent) {
						e.preventDefault();
					}
				});
			}

			function bindRailClickHandler() {
				function stopPropagation(e) { e.stopPropagation(); }

				$scrollbarY.on('click' + eventClassName, stopPropagation);
				$scrollbarYRail.on('click' + eventClassName, function (e) {
					var halfOfScrollbarLength = int(scrollbarYHeight / 2);
					var positionTop = e.pageY - $scrollbarYRail.offset().top - halfOfScrollbarLength;
					var maxPositionTop = containerHeight - scrollbarYHeight;
					var positionRatio = positionTop / maxPositionTop;

					if (positionRatio < 0) {
						positionRatio = 0;
					} else if (positionRatio > 1) {
						positionRatio = 1;
					}

					$this.scrollTop((contentHeight - containerHeight) * positionRatio);
				});
			}

			function bindScrollHandler() {
				$this.on('scroll' + eventClassName, function (e) {
					updateGeometry();
				});
			}

			function destroy() {
				$this.unbind(eventClassName);
				$(window).unbind(eventClassName);
				$(ownerDocument).unbind(eventClassName);
				$this.data('perfect-scrollbar', null);
				$this.data('perfect-scrollbar-update', null);
				$this.data('perfect-scrollbar-destroy', null);
				$scrollbarY.remove();
				$scrollbarYRail.remove();

				// clean all variables
				$scrollbarYRail =
				$scrollbarY =
				scrollbarYActive =
				containerWidth =
				containerHeight =
				contentWidth =
				contentHeight =
				scrollbarYHeight =
				scrollbarYTop =
				scrollbarYRight =
				isScrollbarYUsingRight =
				scrollbarYLeft =
				isRtl =
				eventClassName = null;
			}

			var supportsTouch = (('ontouchstart' in window) || window.DocumentTouch && document instanceof window.DocumentTouch);
			var supportsIePointer = window.navigator.msMaxTouchPoints !== null;

			function initialize() {
				updateGeometry();
				bindScrollHandler();
				bindMouseScrollYHandler();
				bindRailClickHandler();
				bindMouseWheelHandler();

				if (settings.useKeyboard) {
					bindKeyboardHandler();
				}
				$this.data('perfect-scrollbar', $this);
				$this.data('perfect-scrollbar-update', updateGeometry);
				$this.data('perfect-scrollbar-destroy', destroy);
			}

			initialize();

			return $this;
		});
	};
});

(function($) {

/*! jQuery UI - v1.11.4 - 2015-03-11
* http://jqueryui.com
* Includes: effect.js
* Copyright 2015 jQuery Foundation and other contributors; Licensed MIT */

var baseEasings = {};
$.each( [ "Quad", "Cubic", "Quart", "Quint", "Expo" ], function( i, name ) {
	baseEasings[ name ] = function( p ) { return Math.pow( p, i + 2 ) };
});
$.extend( baseEasings, {
	Sine: function( p ) { return 1 - Math.cos( p * Math.PI / 2 ) },
	Circ: function( p ) { return 1 - Math.sqrt( 1 - p * p ) },
	Elastic: function( p ) { return p === 0 || p === 1 ? p : -Math.pow( 2, 8 * (p - 1) ) * Math.sin( ( (p - 1) * 80 - 7.5 ) * Math.PI / 15 ) },
	Back: function( p ) { return p * p * ( 3 * p - 2 ) },
	Bounce: function( p ) {
		var pow2, bounce = 4;
		while ( p < ( ( pow2 = Math.pow( 2, --bounce ) ) - 1 ) / 11 );
		return 1 / Math.pow( 4, 3 - bounce ) - 7.5625 * Math.pow( ( pow2 * 3 - 2 ) / 22 - p, 2 );
	}
});
$.each( baseEasings, function( name, easeIn ) {
	$.easing[ "easeIn" + name ] = easeIn;
	$.easing[ "easeOut" + name ] = function( p ) { return 1 - easeIn( 1 - p ) };
	$.easing[ "easeInOut" + name ] = function( p ) { return p < 0.5 ? easeIn( p * 2 ) / 2 : 1 - easeIn( p * -2 + 2 ) / 2 };
});

})(window.jq183 || jQuery);