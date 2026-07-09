/*!
 * mod_vertical_menu - Vertical Menu
 *
 * @author    Balint Polgarfi
 * @copyright 2014-2019 Offlajn.com All Rights Reserved
 * @license   https://gnu.org/licenses/gpl-2.0.html
 * @link      https://offlajn.com
 */

;(function($, undefined) {

var Hlpr = {
	width: $.fn.width,
	hackWidth: function(reduce) {
		return function(arg) {
			var value = Hlpr.width.apply(this, arguments);
			if (this[0] == window && !$.isNumeric(arg) && value > 767) value -= reduce;
			return value;
		}
	},
	preventDefault: function(e) {
		e.preventDefault();
	},
	stopPropagation: function(e) {
		e.stopPropagation();
	},
	cancelTransitionEnd: function(e) {
		if(e.target.tagName != "NAV") e.stopPropagation();
	},
	loadImage: function(src) {
		new Image().src = src;
	},
	calcOrigin: function(origin, w, dir) {
		var o = origin.split(/\s+/);
		if (o[2] && o[2].indexOf("%") > 0) // convert originZ to px
			o[2] = parseFloat(o[2]) / 100 * w + "px";
		if (dir < 0) { // invert
			if (o[0].indexOf("%") < 0)
				o[0] = (parseInt(o[0]) / w * 100).toPrecision(3);
			o[0] = (parseInt(o[0]) - 50) * dir + 50 + "%";
		}
		return o.join(" ");
	}
};

var Target = {
	hide: function() {
		this.target.style.display = "none";
	},
	remove: function() {
		this.target.parentNode.removeChild(this.target);
	},
	scrollUp: function() {
		$(this.target.children[0]).scrollTop(0);
		this.target.style.display = "none";
	},
	autoMaxWidth: function() {
		this.target.attributes["class"].value = "sm-title";
		this.target.style.maxWidth = "";
	},
	autoHeight: function() {
		this.target.style.height = "";
	}
};

// calc scrollWidth
$(function() {
	var outer = document.createElement("div");
	outer.style.visibility = "hidden";
	outer.style.width = "100px";
	document.body.appendChild(outer);
	var widthNoScroll = outer.offsetWidth;
	outer.style.overflow = "scroll";
	var inner = document.createElement("div");
	inner.style.width = "100%";
	outer.appendChild(inner);
	var widthWithScroll = inner.offsetWidth;
	outer.parentNode.removeChild(outer);

	Hlpr.scrollWidth = widthNoScroll - widthWithScroll;
});

smTransform = (function() {
	var tr, trs = {transform: 0, WebkitTransform: 0, MozTransform: 0, msTransform: 0, OTransform: 0};
	for (tr in trs) if (tr in document.documentElement.style) return tr;
})();

smTransition = (function() {
	var tr, trs = {transition: 0, WebkitTransition: 0, MozTransition: 0, OTransition: 0};
	for (tr in trs) if (tr in document.documentElement.style) return tr;
})();

smTransitionEnd = smTransition && smTransition + (smTransition[0] == 't' ? 'end' : 'End');

var $body,
		$win = $(window),
		$html = $(document.documentElement),
		oldIE = navigator.userAgent.match(/MSIE ([6-9])/),
		backScale = 0.75;
if (smMobile) $html.addClass('sm-mobile');

$.fn._center = function(bi) {
	var x, w, prev;
	if (bi)
		prev = this[0].parentNode.children[1] == this[0] ? 0 : $(this[0].parentNode.children[0]).outerWidth();
	else
		prev = this.prev().outerWidth() * backScale || 0;
	w = this.css("maxWidth", "none").width() + 30;
	this[0].style.maxWidth = "";
	x = Math.floor((this.parent().outerWidth() - w) / 2);
	return prev > x ? prev : x;
};

function deleteMediaRules(match) {
	var css = '';
	for (var j = 0; j < document.styleSheets.length; j++) {
		var style = document.styleSheets[j];
		if (!style.href || style.href.indexOf('mod_vertical_menu') < 0) try {
			for (var i = 0; i < style.cssRules.length; i++) {
				var rule = style.cssRules[i];
				if (rule.media && rule.media.mediaText.match(match)) {
					if (style.href)
						css += rule.cssText.replace(/(url\(['"]?)([^)]+)/i, function(m, url, href) {
							return href.match(/^\/|^https?:|^data:/i) ? m : url + style.href.replace(/[^/]+$/, '') + href;
						}) + '\n';
					else css += rule.cssText + '\n';
					style.deleteRule(i--);
				}
			}
		} catch(ex) {}
	}
	return css;
}
function hackMediaWidths(width) {
	width = width || 0;
	if (!hackMediaWidths.hasOwnProperty('css')) {
		hackMediaWidths.css = [deleteMediaRules('width')];
		hackMediaWidths.$css = $('<style id="hack-media">').appendTo(document.head);
	}
	if (!hackMediaWidths.css.hasOwnProperty(width)) {
		hackMediaWidths.css[width] = hackMediaWidths.css[0].replace(/\((\w+-width:\s*)(\d+)px\)/g, function() {
			return '('+ arguments[1] + (parseInt(arguments[2]) + width) +'px)';
		});
	}
	hackMediaWidths.$css.html(hackMediaWidths.css[width]);
};

$.fn.VerticalMenu = function(args) { return args ? new VerticalSlideMenu(args) : this.eq(0).data("VerticalMenu") };

( VerticalSlideMenu = function(o) {this.init(o)} ).prototype = {
	$drop: $(),
	$overlay: $(),
	minSwipe: 2,
	scrollMinDur: 0.4,
	scrollDistDiv: 5000,
	titleWidth: "66%",
	fullClass: "sm-full-",
	openClass: "sm-open-",
	closeClass: "sm-close",
	effect: 1, // [1 - 14]
	effectEx: {'-1':1, '1':1, '6':1, '9':1, '11':1, '12':1},
	popupDur: 0.6,
	baseCSS: {
		x: 0, y: 0,
		opacity: 1,
		rotationX: 0,
		rotationY: 0,
		rotationZ: 0,
		skewX: 0, scaleX: 1,
		skewY: 0, scaleY: 1
	},

	init: function(obj) {
		$.extend(this, obj);
		$body = $body || $(document.body);
		this.$node = $('#off-menu_'+this.id).data("VerticalMenu", this);
		this.$parent = this.$node.parent().addClass("sm-parent");
		this.$head = this.$node.find(".sm-head");
		this.$back = this.$head.find(".sm-back");
		this.$title = this.$head.children(":last");
		this.$lvls = this.$node.find(".sm-levels");
		this.$filter = this.$node.find(".sm-filter").attr("value", "");
		this.$dt = this.$lvls.find("dt.parent, dt.notparent");
		this.fullClass += this.id;
		this.openClass += this.id;
		this.menuPadding = parseInt(this.$lvls.children(':first').css('paddingLeft'));
		this.bindUpdateScrollbar = smMobile ? undefined : this.updateScrollbar.bind(this, 0);
		this.$lvls.find('a').map(function(i, a) { // scrollspy
			var hash = a.href.match(/#([\w-]+)$/);
			return hash ? document.getElementById(hash[1]) : undefined;
		}).menuspy().length && $body.on('scrollEnter.menuspy', function(e) {
			this.$lvls.find('a[href$="#'+ e.target.id +'"]').closest('dt').addClass('active').siblings('.active').removeClass('active');
		}.bind(this)).on('scrollLeave.menuspy', function(e) {
			this.$lvls.find('a[href$="#'+ e.target.id +'"]').closest('dt').removeClass('active');
		}.bind(this));

		this.$node.find('.sm-x').on('click.sm touchend.sm', function() {
			this.closeMenu();
		}.bind(this));

		this.dropmenu = this.navtype == 'drop';
		this.accordion = this.navtype == 'accordion';
		this.treemenu = this.navtype == 'tree' || this.accordion;
		this.expand = this.navtype == 'expand' || this.treemenu;

		if (this.overlay && !this.expand) { // override menu type
			this.dropmenu = 0;
			this.expand = 1;
			this.treemenu = this.navtype != 'expand';
		}
		if ((this.treemenu || this.expand) && !this.$filter.length) this.backItem = '';
		if (this.popup || this.overlay) this.sidebarUnder = 0;
		if (this.treemenu)
			this.$node.addClass("sm-tree").on("click.sm touchend.sm", "dt.parent", $.proxy(this, "onClickTree"));
		this.build();
		this.onResize();
		if (!this.sidebar && this.winWidth >= this.sidebarUnder) this.$lvls.height(''); // height fix for webkit
		if (this.filterMinChar < 1) this.filterMinChar = 1;

		this.bg = this[this.overlay ? "$overlay" : "$node"].css("backgroundImage").match(/url\(['"]?(.+?)['"]?\)/i);

		if (this.popup || this.sidebar || this.overlay || this.winWidth < this.sidebarUnder) {
			// only make the autoopen work if the menu is not in module position
			var w = $(window).width(), visible = true;
			if (!+this.visibility[4]) { // basic
				if (w < 768 && !+this.visibility[0]) visible = false;
				if (w >= 768 && w < 992 && !+this.visibility[1]) visible = false;
				if (w >= 992 && w < 1200 && !+this.visibility[2]) visible = false;
				if (w >= 1200 && !+this.visibility[3]) visible = false;
			} else { // advanced
				if (this.visibility[5][0] > w || this.visibility[6][0] < w) visible = false;
			}
			if (visible) switch (this.autoOpen) {
				case 1: // Only once
					if (!window.sessionStorage || sessionStorage.smAutoOpen) break;
					sessionStorage.smAutoOpen = 1;
				case 2: // Always
					if (w < 768 && this.autoOpen == 2) break; // disable always auto open on mobile
					if (this.autoOpenAnim) {
						// animate auto open
						$win.one('load.sm', $.proxy(this, "openMenu"));
					} else {
						// don't animate auto open
						if (this.popup) {
							this.$button.eq(1).hide();
							this.$noTrans = function() { this.$button.eq(1).show(), delete this.$noTrans }.bind(this);
						} else {
							this.$noTrans = this.$node
								.add(this.$pusher)
								.add(this.$content)
								.add(this.$overlay.parent())
								.add(this.$button.hide())
								.css(smTransition+'Duration', '1ms')
							;
						}
						$(this.openMenu.bind(this));
					}
			}
		}

		// add events
		if (this.parentHref) // don't slide to submenu
			this.$node.on("click.sm touchend.sm", "dt.parent a", $.proxy(this, "onRedirect"));
		else // disable redirection when pointer-events:none isn't supported
			this.$node.on("click.sm", "dl:not(.sm-result) dt.parent a", Hlpr.preventDefault);
		this.$node
			.on("click.sm touchend.sm", this.expand && !this.treemenu ? "dt" : ".notparent", $.proxy(this, "onRedirect"))
			.on("click.sm touchend.sm", ".sm-back, .sm-back-item", $.proxy(this, "onBack"))
			.on("click.sm touchend.sm", ".sm-reset", $.proxy(this, "onResetFilter"))
			.on("click.sm", Hlpr.stopPropagation);
		if (!this.expand) this.$node.on("click.sm touchend.sm", "dt.parent", $.proxy(this, "onOpen"));
		this.$filter
			.on("keydown.sm", function disableTab(e) {if (e.keyCode == 9) e.currentTarget.blur()})
			.on("keyup.sm", $.proxy(this, "onFilter"));
		this.$button.on("click.sm", $.proxy(this, "onClickButton")); // touch event causes problems on scroll
		this.$lvls
			.on("touchstart.sm", $.proxy(this, "onTouchStart"))
			.on("touchmove.sm", $.proxy(this, "onTouchMove"))
			.on("touchend.sm touchcancel.sm", $.proxy(this, "onTouchEnd"))
			.on("mousedown.sm", $.proxy(this, "onMouseDown"));
		$win
			.on("resize.sm", $.proxy(this, "onResize"))
			.on("keyup.sm", $.proxy(this, "onEsc"));
		if (this.bg) $win.on("load.sm", Hlpr.loadImage.bind(0, this.bg[1]));
		$body.one("touchend.sm", $.proxy(this, "touchOptimize"));
	},

	touchOptimize: function() {
		this.$node
			.off("click.sm", "dt.parent")
			.off("click.sm", "dt")
			.off("click.sm", ".notparent")
			.off("click.sm", ".sm-back, .sm-back-item");
		if (this.dropmenu)
			this.$drop.off("click.sm", "dt");
	},

	build: function() {
		// build menu icon
		this.$button = $('<div class="menu-icon-cont sm-btn-'+this.id+'">')
			.html('<div class="menu-icon3"><span></span><span></span><span></span></div>');

		if (this.dropmenu) {
			this.anim.inRotationY = this.anim.inCSS.rotationY;
			this.anim.outRotationY = this.anim.outCSS.rotationY;
			this.miRotationY = this.miCSS.rotationY;
			this.$drop = $('<nav>').css({
				position: 'absolute',
				left: 0, right: 0,
				top: 0, zIndex: 999
			}).addClass("sm-drop off-menu_"+this.id)
				.appendTo($body)
				.on("click.sm touchend.sm", "dt", $.proxy(this, "onRedirect"))
		}

		// plus svg animation hack
		$(this.$lvls[0].querySelectorAll('.sm-arrow use[*|href*=plus]')).each(function() {
			$(this).clone().insertAfter(this);
		});

		//add logo link
		var link = this.logoUrl;
		if (link) $("nav.off-menu_" + this.id +" .sm-logo img").css("cursor", "pointer").click(function(e) {
			document.location.href = link;
		});

		if (this.popup) { // POPUP
			this.initPopup();
		} else if (this.sidebar || this.sidebarUnder) { // SIDEBAR
			this.initContainer();
			this.initSidebar();
		} else if (this.overlay) { // OVERLAY
			this.initContainer();
			this.initOverlay();
		}

		// build submenus
		var $div = this.$lvls.find(".sm-level");
		$div.push( // add level for search results
			$('<div class="sm-level level2"><dl class="sm-result level2">')
				.css('display', 'none').appendTo(this.$lvls)[0]);
		this.opened = $div[0];
		this.mainmenu = this.opened;
		if (this.treemenu) this.$lvls.find('dd.opened').css('display', 'block'); // init tree/accordion animation
		if (this.backItem) {
			var $backItem = $("dt:first", $div[0]).clone().removeClass("parent notparent active").addClass("sm-back-item");
			$backItem.find("a").removeAttr("href").html(this.backItem);
			$backItem.find(".desc, .sm-icon, .productnum").remove();
		}
		if (!this.expand) {
			var level, div, i;
			for (i = 1; i < $div.length; i++) {
				if (this.backItem && (!this.dropmenu || i == $div.length - 1)) // if drop, add only for search results
					$backItem.clone().prependTo($div[i].children[0]); // add back menu-item to sublevels
				div = $div[i];
				div.style.display = "none";
				div.parent = $(div.parentNode).closest(".sm-level")[0];
				div.parentItem = $(div.parentNode).prev()[0];
				if (div.parentNode.tagName == "DD")
					$(div.parentNode).prev()[0].menu = div;
			}
		}
		this.$result = this.$lvls.children(":last");
		if (this.expand && this.backItem) $backItem.clone().prependTo(this.$result[0].children[0]); // add back to search result
	},

	initPopup: function() {
		var $clone = this.$button.clone()
			.css(smTransform, "translateX(-100%)")
			.css("zIndex", -1).appendTo(this.$node);
		this.$node.css({
			display: "block", position: "absolute",
			visibility: "hidden", opacity: 0,
			left: 0, top: 0,
			width: this.width,
			margin: 0, zIndex: 99999,
			WebkitTransition: "none", MozTransition: "none", msTransition: "none", transition: "none"
		}).addClass("sm-popup");
		if (this.menuIconCorner) { // button in corner
			this.$node.appendTo(this.$button);
			this.$button.appendTo($body);
		} else { // button in module position
			this.$node.appendTo($body);
			this.$button.appendTo(this.$parent);
		}
		this.$button.addClass('sm-popup-burger').push($clone[0]);
		this.$cont = $body;
		this.effect = 0;
	},

	initContainer: function() {
		this.htmlCSS = {
			background: $html.css("backgroundColor")+" "+$html.css("backgroundImage")+" "+$html.css("backgroundRepeat")+" "+
				$html.css("backgroundAttachment")+" "+$html.css("backgroundPosition")+" / "+$html.css("backgroundSize")
		};
		this.bodyCSS = {
			overflowY : $body.css("overflowY"),
			borderTop: $body.css("borderTopWidth")+" "+$body.css("borderTopStyle")+" "+$body.css("borderTopColor"),
			borderBottom: $body.css("borderBottomWidth")+" "+$body.css("borderBottomStyle")+" "+$body.css("borderBottomColor"),
			borderLeft: $body.css("borderLeftWidth")+" "+$body.css("borderLeftStyle")+" "+$body.css("borderLeftColor"),
			borderRight: $body.css("borderRightWidth")+" "+$body.css("borderRightStyle")+" "+$body.css("borderRightColor"),
			padding: $body.css("paddingTop")+" "+$body.css("paddingRight")+" "+$body.css("paddingBottom")+" "+$body.css("paddingLeft"),
			background: $body.css("backgroundColor")+" "+$body.css("backgroundImage")+" "+$body.css("backgroundRepeat")+" "+
				$body.css("backgroundAttachment")+" "+$body.css("backgroundPosition")+" / "+$body.css("backgroundSize")
		};

		this.$cont = $body.addClass("sm-container");
		this.$pusher = $(".sm-pusher");
		if (!this.$pusher.length) {
			var $cnt = $body.children(':not(nav.sm-menu)');
			this.$pusher = $('<div class="sm-pusher"><div class="sm-content"><div class="sm-content-inner">').appendTo($body);
		}
		this.$content = this.$pusher.children();
		this.$inner = this.$content.children(".sm-content-inner").append($cnt);

		$html.css('overflow-y', 'auto');
	},

	initSidebar: function() {
		if (this.sidebar) {
			this.$button.css("display", "");
			this.$node.appendTo(this.$cont);
		}
		this.$cont.on("click.sm touchstart.sm", function(e) { this.effect < 0 || this.closeMenu(e) }.bind(this));
		// disable page scrolling when sidebar is open
		this.$lvls.on("touchstart.sm", function(e) { this.effect < 0 || this.scrollFixStart(e) }.bind(this));
		this.$cont.on("touchmove.sm", function(e) { this.effect < 0 || this.scrollFixMove(e) }.bind(this));

		// TransitionEnd fix
		this.$node.on(smTransitionEnd, Hlpr.cancelTransitionEnd);
		this.$inner.on(smTransitionEnd, Hlpr.cancelTransitionEnd);

		if (this.menuIconCorner) {
			this.$button.appendTo(this.$inner);
		} else {
			this.$button.appendTo(this.$parent);
			this.$button.push(this.$button.clone().css("opacity", 0).appendTo(this.$node)[0]);
		}
		if (!this.sidebar) this.$button.css("display", "none");
	},

	initOverlay: function() {
		var $overlay = $('<div>').addClass("sm-overlay-"+this.id).appendTo($body);
		this.$overlay = $('<div>').addClass("sm-overlay-win").appendTo($overlay)
			.on("click.sm "+smTransitionEnd, Hlpr.stopPropagation) // don't close overlay on click & TransitionEnd fix
		this.$node.appendTo(this.$overlay);
		this.$cont.on("click.sm touchstart.sm", $.proxy(this, "closeMenu"));
		// disable touch-scrolling when sidebar is open
		this.$lvls.on("touchstart.sm", $.proxy(this, "scrollFixStart"));
		this.$cont.on("touchmove.sm", $.proxy(this, "scrollFixMove"));

		if (this.menuIconCorner) {
			this.$button.appendTo($body);
		} else {
			this.$button.appendTo(this.$parent);
			this.$button.push(this.$button.clone().css("opacity", 0).insertBefore(this.$node)[0]);
		}
	},

	scrollFixStart: function(e) {
		this.touchstartY = e.originalEvent.touches[0].clientY;
	},

	scrollFixMove: function(e) {
		if (!this.$cont.hasClass(this.openClass)) return;
		else if (!$(e.target).closest('.sm-levels').length) return e.preventDefault();

		var node = this.opened.children[0];
		if (this.touchstartY < e.originalEvent.touches[0].clientY) {  // on scroll up
			if (node.scrollTop == 0) e.preventDefault();
		} else { // on scroll down
			if (node.scrollTop == node.scrollHeight - node.offsetHeight) e.preventDefault();
		}
	},

	onMouseDown: function(e) {
		if (e.button == 0) {
			if (e.target.tagName == "A") e.preventDefault();
			this.onTouchStart(e);
			$body
				.on("mousemove.sm", $.proxy(this, "onTouchMove"))
				.on("mouseup.sm", $.proxy(this, "onMouseUp"));
		}
	},

	onMouseUp: function(e) {
		if (e.button == 0) {
			$body.off("mousemove.sm mouseup.sm");
			this.onTouchEnd(e);
		}
	},

	onTouchStart: function(e) {
		var touch = e.originalEvent.touches && e.originalEvent.touches[0] || e;
		this.touchStartX = this.touchPrevX = touch.pageX;
		this.touchStartY = this.touchPrevY = touch.pageY;
		this.touchTarget = e.target;
		delete this.touchEvent;
	},

	onTouchMove: function(e) {
		if (this.touchEvent == "scroll") return;
		if (smMobile && smMobile[0] == "Windows Phone") e.preventDefault(); // disable scrolling or swipe back
		var touch = e.originalEvent.touches && e.originalEvent.touches[0] || e,
				x = touch.pageX,
				y = touch.pageY;
				diffX = x - this.touchStartX,
				diffY = y - this.touchStartY;
		if (this.touchEvent === undefined) {
			var dx = Math.abs(diffX),
					dy = Math.abs(diffY);
			if (dy > this.minSwipe && dy >= dx) { // vertical swipe
				this.touchEvent = "scroll";
				$body.addClass("sm-scroll");
			} else if (!this.opened.parent) {
				// don't check back swipe on main level
			} else if (diffX > this.minSwipe && dx > dy) { // back swipe
				if (this.openTL && this.openTL.isActive()) {
					if (this.openTL.time() < this.dur) return;
					else this.openTL.pause();
				}
				this.touchEvent = "back";
				if (!smMobile) $(this.opened.children).perfectScrollbar("destroy");
				this.opened.parent.style.display = "block";
				this.initBackTimeline();
				TweenLite.set(this.$lvls[0], {perspective: this.perspective}); // blur fix for retina
				this.nodeW = this.$lvls.outerWidth();
			}
		}
		if (this.touchEvent == "back") {
			e.preventDefault(); // disable scrolling
			diffX -= this.minSwipe;

			if (diffX <= 1) diffX = 0.1;
			if (diffX >= this.nodeW) diffX = this.nodeW - 0.1;
			this.backTL.seek(this.dur * diffX / this.nodeW);

			this.touchDirLeft = this.touchPrevX < x;
			this.touchDirTop = this.touchPrevY < y;
			if (this.touchPrevX != x) this.touchPrevX = x;
			if (this.touchPrevY != y) this.touchPrevY = y;
		}
	},

	onTouchEnd: function(e) {
		var touch = e.originalEvent.changedTouches && e.originalEvent.changedTouches[0] || e,
				dx = Math.abs(this.touchStartX - touch.pageX),
				dy = Math.abs(this.touchStartY - touch.pageY);

		if (!this.touchEvent && dx <= this.minSwipe && dy <= this.minSwipe) return;
		e.preventDefault();
		e.stopPropagation();

		if (this.touchEvent == "scroll") {
			$body.removeClass("sm-scroll");
		} else if (this.touchEvent == "back") {
			// disable click when swipe finished on the same menu-item where it started
			if (this.touchTarget == e.target) this.disableClick = true;
			if (this.touchDirLeft) {
				this.back();
			} else {
				if (!smMobile) $(this.opened.children).perfectScrollbar();
				this.backTL.reverse();
			}
		}
	},

	onEsc: function(e) {
		if (e.keyCode == 27 && !this.$button.eq(1).hasClass("sm-hide") && this.$button.css('display') != 'none') {
			e.stopPropagation();
			this.closeMenu();
		}
	},

	onClickButton: function(e) {
		e.stopPropagation();
		e.preventDefault();
		if (this.$button.hasClass("sm-hide")) {
			this.$button[0]._gsTransform.x = this.menuIconX;
			this.$button.css(smTransform, '').removeClass("sm-hide");
			return;
		}
		if (this.popup) { // POPUP
			if (this.$node.css("visibility") == "hidden") {
				this.openMenu();
			} else {
				if (e.currentTarget == this.$button[0]) return; // don't close menu for any click
				this.closeMenu();
			}
		} else { // SIDEBAR || OVERLAY
			this.$cont.hasClass(this.openClass) ? this.closeMenu() : this.openMenu();
		}
	},

	onOpenPopup: function() {
		var bw = this.$button.width(),
				bh = this.$button.height(),
				w = this.$node.width(),
				h = this.$node.height();
		this.$node.css("visibility", "visible");
		if (this.menuIconCorner) {  // fixed position
			TweenLite.set(this.$node[0], {transformOrigin: "0% 0% 0"});
		} else {  // module position
			this.onResize();
		}
		var tl = new TimelineLite({ paused: true, autoRemoveChildren: true, onComplete: this.$noTrans });
		tl.add(TweenLite.fromTo(this.$node[0], 1, {scaleX: bw/w, scaleY: bh/h}, {css: {scaleX: 1, scaleY: 1}, ease: Back.easeOut}), 0);
		tl.add(TweenLite.to(this.$node[0], 1, {css: {opacity: 1}, onComplete: $.proxy(this.$button.eq(1), "addClass", this.closeClass)}), 0);
		tl.add(TweenLite.fromTo(this.$button[1], 0.75, {x: -bw, opacity: 1}, {css: {x: 0}}), -0.5);
		tl.duration(this.$noTrans ? 0.001 : this.popupDur);
		tl.play();
		this.$cont.addClass(this.openClass);
		this.$node.trigger("openMenu", this);
	},

	onClosePopup: function() {
		var bw = this.$button.width(),
				bh = this.$button.height(),
				w = this.$node.width(),
				h = this.$node.height();
		this.$button.removeClass(this.closeClass);
		var tl = new TimelineLite({paused: true, autoRemoveChildren: true});
		tl.add(TweenLite.to(this.$button[1], 0.3, {css: {x: -bw}}), -0.15);
		tl.add(TweenLite.to(this.$node[0], 0.3, {css: {opacity: 0}, ease: Quad.easeIn}), 0.3);
		tl.add(TweenLite.to(this.$node[0], 0.3, {css: {scaleX: bw/w, scaleY: bh/h}, onComplete: $.proxy(this.$node, "css", "visibility", "hidden")}), 0.3);
		tl.play();
		this.$cont.removeClass(this.openClass);
		this.$node.trigger("closeMenu", this);
	},

	openMenu: function(e, effect) {
		if (this.$cont.hasClass(this.openClass)) return;
		var otherOpen = this.$cont[0].className.match(/\bsm-open-(\d+)\b/);
		if (otherOpen) { // if another menu is already open
			window['sm'+otherOpen[1]].closeMenu();
			return setTimeout($.proxy(this, 'openMenu'), 550);
		}
		if (e && e.stopPropagation) e.stopPropagation();
		if (this.popup) return this.onOpenPopup();

		$body.off(smTransitionEnd+".tr").one(smTransitionEnd+".tr", ".sm-pusher", $.proxy(this, "onEndOpenMenu"));

		this.effect = effect || this.effect;
		if (this.effect < 0) {
			$html.addClass('sm-reduce-width');
			hackMediaWidths(this.width);
			$.fn.width = Hlpr.hackWidth(this.width);
		}
		this.$cont.addClass("sm-effect-" + Math.abs(this.effect));

		var btn = this.$button.length && (!this.menuIconCorner || this.overlay || this.effectEx[this.effect]);
		if (btn && this.menuIconCorner) this.$button.appendTo(this.overlay ? this.$overlay : this.$node);

		this.scroll = $win.scrollTop();
		if (smMobile) {
			this.effect < 0 ? $win.off("resize.sm") : $win.off("resize.sm").scrollTop(0); // don't resize during animation on mobile
		}

		this.$cont.css({ position: 'fixed', top: 0, bottom: 0 });
		$html.addClass(this.fullClass);
		this.$inner.css(this.bodyCSS);
		this.$content.css(this.htmlCSS);

		if (!smMobile) {
			if (btn && !this.menuIconCorner) {
				if (!this.overlay) this.$button.eq(1).appendTo(this.effectEx[this.effect] ? this.$node : this.$inner);
				this.$button[1].style.display = "inline-block";
			}
		} else { // mobile
			if (this.$button[1]) this.$button[1].style.display = "block";
		}

		if (this.overlay) {
			this.$content.css('maxWidth', '100vw'); // Firefox bugfix (#18)
			this.$lvls.css('maxHeight', '');
		} else if (this.sidebar || this.winWidth < this.sidebarUnder) {
			if (this.effect > 0) this.$content.css('maxWidth', '100vw'); // Firefox bugfix (#18)
			this.$lvls.css('maxHeight', this.effect == 8 ? window.innerHeight : window.innerHeight - this.$lvls.position().top); // fix for sidebar effect 8
		}

		this.$content.scrollTop(this.scroll);
		this.$cont.css("background", this.siteBg).addClass(this.openClass);
		this.$node.trigger("openMenu", this);
		if (oldIE) this.onEndOpenMenu();
	},

	onEndOpenMenu: function(e) {
		if (smMobile) {
			$win.on("resize.sm", $.proxy(this, "onResize")); // allow resize after animation on mobile
			if (this.sidebar || this.winWidth < this.sidebarUnder) // fix for sidebar bottom can't be see
				this.$lvls.css('maxHeight', window.innerHeight - this.$lvls.position().top);
		}
		if (this.effect < 0 && this.winWidth > 767) $win.resize();
		if (this.menuIconCorner)
			setTimeout($.proxy(this.$button, "addClass", this.closeClass), 1);
		else {
			this.$button[0].style.opacity = 0;
			this.$button[1].style.opacity = 1;
			this.$button.eq(1).addClass(this.closeClass);
		}
		if (this.overlay) { // calculate overlay max-height after transition
			var topHeight = 0;
			this.$lvls.prevAll().each(function() { topHeight += $(this).outerHeight() });
			this.$lvls.css("maxHeight", this.$overlay.outerHeight() - topHeight);
		} else if (this.effect == 8) { // fix for sidebar effect 8
			this.$lvls.css('maxHeight', this.$cont.outerHeight() - this.$lvls.position().top);
		}
		this.$node.css("zIndex", 99);
		if (this.$noTrans) {
			this.$button.addClass(this.closeClass).show();
			this.$noTrans.css(smTransition+'Duration', '');
			delete this.$noTrans;
		}
		this.updateScrollbar();
	},

	closeMenu: function(e) {
		if (e && $(e.target).closest(".sm-menu").length) return;
		if (!this.$cont || !this.$cont.hasClass(this.openClass)) return;
		if (this.popup) return this.onClosePopup();
		$body.off(smTransitionEnd+".tr").one(smTransitionEnd+".tr", this.overlay ? ".sm-overlay-"+this.id : "", $.proxy(this, "onEndCloseMenu"));
		if (!this.menuIconCorner) {
			this.$button[1].style.display = "none";
			this.$button[1].style.opacity = 0;
		}
		if (smMobile) this.$filter.blur();
		this.$cont.removeClass(this.openClass);
		this.$node.css("zIndex", "").trigger("closeMenu", this);
		if (oldIE) this.onEndCloseMenu();
	},

	onEndCloseMenu: function(e) {
		if (this.effect < 0) {
			$.fn.width = Hlpr.width;
			hackMediaWidths(0);
			this.scroll = $win.scrollTop();
			$html.removeClass('sm-reduce-width');
			if (this.winWidth >= 768) $win.resize();
		}
		var s = this.$inner[0].style;
		s.position = s.background = s.border = s.padding = s.margin = "";
		$body[0].style.background = $body[0].style.position = $body[0].style.top = $body[0].style.bottom = "";
		this.$content.css({height: '', background: ''});
		$html.removeClass(this.fullClass);
		//$html.css("overflowY", "");
		if (smMobile) this.$node.css({top: "", position: ""});
		$win.scrollTop(this.scroll);
		this.$cont.removeClass("sm-effect-" + Math.abs(this.effect));

		this.$content.css('maxWidth', ''); // Firefox bugfix (#18)
		if (this.$button.length) {
			if (!this.menuIconCorner || this.overlay || this.effectEx[this.effect]) {
				if (this.menuIconCorner) this.$button.appendTo(this.$inner);
				else this.$button[0].style.opacity = 1;
				setTimeout($.proxy(this.$button, "removeClass", this.closeClass), 1);
			}
			else this.$button.removeClass(this.closeClass);
		}
	},

	onResize: function(e) {
		var isInCont = this.$node[0].parentNode != this.$parent[0],
				$title = this.$title.last();
		this.prevWinWidth = this.winWidth || $win.outerWidth();
		this.winWidth = $win.outerWidth();
		this.winHeight = $win.outerHeight();
		// update button
		if (!this.sidebar && this.winWidth < this.sidebarUnder && !isInCont) {
			this.$button.css("display", "");
			this.$node
				.appendTo(this.$cont)
				.trigger("moveToSidebar", this);
		}

		// update title position (don't update on init)
		if (e && $title[0]) TweenLite.set($title[0], {x: $title._center(this.$back.length)});

		// update dropmenu
		var drop = this.drop;
		this.drop = this.dropmenu && !smMobile && this.winWidth >= this.sidebarUnder;
		if (drop != this.drop) {
			if (this.drop) {
				if (this.$head.length) {
					this.$title.filter(":not(:first)").remove();
					this.$title.length = 1;
					TweenLite.set(this.$title[0], {css: {scale:1, x:this.$title.attr("class", "sm-title")._center(this.$back.length)}});
				}
				this.$lvls.children().attr("style", "display: none;");
				this.opened = this.$lvls.children(":first").attr("style", "position: static;")[0];
				this.prev = undefined;
				this.$node
					.on(this.dropEvent+".dm", "dt.parent", $.proxy(this, "onOpenDrop"))
					.on(this.dropEvent+".dm", ">.sm-level", $.proxy(this, "onMouseEnterDrop"));
				this.$drop
					.on(this.dropEvent+".dm", "dt.parent", $.proxy(this, "onOpenDrop"))
					.on(this.dropEvent+".dm", ".sm-level", $.proxy(this, "onMouseEnterDrop"));
				if (this.dropEvent == "click") {
					$body.on("click.dm", $.proxy(this, "closeAllDrops"));
				} else {
					this.$node
						.on("mouseleave.dm", "dt.parent", $.proxy(this, "onCloseDrop"))
						.on("mouseleave.dm", ">.sm-level", $.proxy(this, "onMouseLeaveDrop"));
					this.$drop
						.on("mouseleave.dm", "dt.parent", $.proxy(this, "onCloseDrop"))
						.on("mouseleave.dm", ".sm-level", $.proxy(this, "onMouseLeaveDrop"));
				}
			} else {
				this.$node.off(".dm");
				this.$drop.off(".dm");
				$body.off("click.dm");
			}
		}
		// update background img pos
		if (this.bg && this.bgX) {
			var level = this.opened.children[0].attributes['class'].value.match(/\blevel(\d)\b/)[1];
			this.$node[0].backgroundPosition = this.bgX * (level - 1) + "% 0";
		}

		if (this.popup) {
			var maxH, mb = navigator.userAgent.match(/iPad/i) ? 25 : 5;
			if (this.menuIconCorner) {  // fixed position
				var off = this.$lvls.offset();
				maxH = (this.winHeight - (off.top - $win.scrollTop()) - mb);
				this.opened.children[0].style.maxHeight = maxH - 2 * this.menuPadding + "px";
				this.$lvls.css("maxHeight", maxH);
				this.$node.css("maxWidth", this.winWidth - off.left - this.$button.width());
			} else if (this.$node.css("visibility") != "hidden") { // module position
				var btn = this.$button.offset(),
						top = $win.scrollTop(),
						originX = "0%";
				if (this.winWidth/2 > btn.left || this.popup == 2) { // left
					this.$node.css({
						maxWidth: this.winWidth - btn.left - this.$button.width(),
						left: btn.left,
						right: ""
					});
				} else {  // right
					originX = "100%";
					this.$node.css({
						maxWidth: btn.left,
						left: "",
						right: this.winWidth - btn.left - this.$button.width()
					});
				}
				if (top + this.winHeight/2 > btn.top) { // top
					TweenLite.set(this.$node[0], {transformOrigin: originX + " 0% 0", scaleX: 1, scaleY: 1});
					maxH = this.winHeight - (btn.top - top) - this.$lvls.position().top - mb;
					this.$node.css({top: btn.top, bottom: ""});
				} else { // bottom
					TweenLite.set(this.$node[0], {transformOrigin: originX + " 100% 0", scaleX: 1, scaleY: 1});
					maxH = btn.top - top + this.$button.height() - this.$lvls.position().top;
					this.$node.css({top: "", bottom: this.winHeight - btn.top - this.$button.height()});
				}
				this.opened.children[0].style.maxHeight = maxH - 2 * this.menuPadding + "px";
				this.$lvls.css({maxHeight: maxH, height: $(this.opened.children).height() + 2 * this.menuPadding});
			}
		} else if (this.sidebar || this.sidebarUnder) {
			// update button & sidebar
			if (!this.sidebar && this.winWidth >= this.sidebarUnder && isInCont) {
				this.$button.css("display", "none");
				this.$node
					.css({top: "", position: ""}) // mobile fix
					.appendTo(this.$parent);
				if (this.$cont.hasClass(this.openClass)) {
					this.closeMenu();
					this.onEndCloseMenu();
				}
				this.$node.trigger("moveToModulepos", this);
			}
			if (this.$node.css("display") == "block") { // if sidebar is open
				this.$pusher.addClass("no-trans"); // disable transitions during resize
				if (this.$pusher[0]) this.$pusher[0].offsetHeight; // trigger a reflow, flushing the CSS changes
				this.$pusher.removeClass("no-trans");
				// update menu height
				if (this.sidebar || this.winWidth < this.sidebarUnder)
					this.$lvls.css('maxHeight', window.innerHeight - this.$lvls.position().top);
				else if (!this.treemenu)
					this.$lvls.height($(this.opened.children).outerHeight() + 2 * this.menuPadding);
				else
					this.$lvls[0].style.height = "";
			} else { // if sidebar is close
				if (this.hideBurger && this.autoOpen == 2 && this.prevWinWidth < 768 && this.winWidth >= 768) this.openMenu();
			}
		} else if (this.overlay && $body.hasClass(this.openClass)) { // OVERLAY
			var topHeight = 0;
			this.$lvls.prevAll().each(function() { topHeight += $(this).outerHeight() });
			this.$lvls.css('maxHeight', this.$overlay.outerHeight() - topHeight);
		}

		this.updateScrollbar();
	},

	onFilter: function(e) {
		this.$filter.attr('value', this.$filter.val());
		if (!e.target.value.length && $(this.opened.children).hasClass("sm-result")) this.onBack();
		if (this.filterTimeout) this.filterTimeout = clearTimeout(this.filterTimeout);
		if (e.target.value.length < this.filterMinChar) return;
		this.filterTimeout = setTimeout($.proxy(this, "filter", e.target.value), this.filterDelay);
	},

	filter: function(keyword) {
		keyword = keyword.trim().replace(/\s+/g, " ").replace(/([.?*+^$|{}()\\[\]])/g, "\\$1");
		if (this.keyword == keyword) return;
		if (!this.keyword) this.filterParent = this.opened;
		this.keyword = keyword;
		this.$filter.trigger("filter", [this, keyword]);
		var i, $dt,
				$result = this.$result.clone().appendTo(this.$lvls).children(),
				submenu = $result[0].parentNode;
		for (i = 0; i < this.$dt.length; i++) {
			$dt = $(this.$dt[i]);
			if ($dt.text().match( new RegExp(this.keyword, "i") )) {
				var $clone = $dt.clone().removeClass("parent").addClass("notparent");
				$clone[0].innerHTML = $clone[0].innerHTML.replace(new RegExp(">([^<]*)("+this.keyword+")([^>]*)<", "ig"), ">$1<strong>$2</strong>$3<");
				$clone.appendTo($result);
			}
		}
		var res = $result[0].children.length;
		if (this.backItem && res == 1 || !res) {
			var $no = $(this.opened).find("dt:first").clone()
				.removeClass("parent opened").addClass("notparent").css("pointerEvents", "none");
			$no.find("a").removeAttr("href").html(this.noResult);
			$no.find(".desc, .sm-icon, .productnum").remove();
			$no.appendTo($result);
		}
		submenu.parent = this.filterParent;
		this.open(this.result, submenu);
	},

	onRedirect: function(e) {
		e.stopPropagation();
		if (this.disableClick) return e.preventDefault(); // disable click during swipe
		var $a = e.currentTarget.tagName == "A" ? $(e.currentTarget) : $("a", e.currentTarget),
				a = $a[0], event = $.Event("openMenuItem");
		$a.trigger(event, this);
		if (event.isDefaultPrevented()) return e.preventDefault();
		var $scrollTo = $(), hash = a.href && a.href.split('#')[1];
		if (hash && location.href.split('#')[0] == a.href.split('#')[0])
			$scrollTo = hash == 'top' ? $body : $('#'+hash+', a[name="'+hash+'"]');
		if ($scrollTo.length) { // scroll
			e.preventDefault();
			var $cont, cont, t, b;
			if (this.$cont && this.$cont.hasClass(this.openClass) && this.effect > 0) { // sidebar
				$cont = this.$content;
				cont = {scroll: $cont.scrollTop()};
				// t = $scrollTo.offset().top + cont.scroll - $cont.offset().top;
				t = $.fn.menuspy.$elems.filter($scrollTo).data('menuspy').min;
				b = this.$inner.outerHeight() - this.$content.height();
			} else { // not sidebar
				$cont = $win;
				cont = {scroll: $cont.scrollTop()};
				t = $scrollTo.offset().top;
				b = $body.outerHeight() - this.winHeight;
			}
			t = Math.max(0, t - this.scrollOffset);
			this.scroll = Math.round(t < b ? t : b);
			var dur = this.scrollMinDur + Math.abs(cont.scroll - this.scroll) / this.scrollDistDiv;
			TweenLite.to(cont, dur, {
				scroll: this.scroll,
				onUpdate: function onUpdateScroll() { $cont.scrollTop(cont.scroll) },
				onComplete: this.popup && this.menuIconCorner && this.winWidth < this.hidePopupUnder ? function onEndScroll() {
					if (!this.$button.eq(1).hasClass(this.closeClass)) return;
					TweenLite.set(this.$button[0], {
						x: this.menuIconX - this.$node.width(),
						y: this.menuIconY
					});
					this.$button.eq(1).addClass("sm-hide");
					if (!smMobile) this.$node.one("mouseenter", $.proxy(this, "onClickButton"));
				}.bind(this) : function() { $a.closest('dt').addClass('active').siblings('.active').removeClass('active') }
			});
		} else {  // open url
			if (!(smMobile && this.popup)) // fix for mobile popup menu-item opening
				if (a == e.target.parentNode || a == e.target) return;
			if (!a.click) {
				var event = document.createEvent("HTMLEvents");
				event.initEvent("click", false, true);
				a.dispatchEvent(event);
			} else a.click();
		}
	},

	onOpenDrop: function(e) {
		if (this.sidebar && !$body.hasClass(this.openClass)) return;
		var item = e.currentTarget,
				openTween = item.parentNode.parentNode.openTween,
				closeTween = item.parentNode.parentNode.closeTween,
				items = item.parentNode.children, i;
		for (i = 0; i < items.length; i++)
			if (items[i] != item && items[i].menu && items[i].menu.style.display == "block")
				{ this.closeDrop(items[i].menu); break; }
		item.menu.closeTimeout = clearTimeout(item.menu.closeTimeout);
		if (openTween && openTween.isActive())
			openTween.vars.onComplete = $.proxy(this, "openDrop", item);
		else if (closeTween && closeTween.isActive())
			closeTween.vars.onReverseComplete = $.proxy(this, "openDrop", item);
		else
			this.openDrop(item);
	},

	onCloseDrop: function(e) {
		var item = e.currentTarget,
				openTween = item.parentNode.parentNode.openTween,
				closeTween = item.parentNode.parentNode.closeTween;
		if (openTween && openTween.vars.onComplete)
			delete openTween.vars.onComplete;
		else if (closeTween && closeTween.vars.onReverseComplete)
			delete closeTween.vars.onReverseComplete;
		else
			item.menu.closeTimeout = setTimeout($.proxy(this, "closeDrop", item.menu), this.anim.outDur * 500 || 200);
	},

	onMouseEnterDrop: function(e) {
		if (this.sidebar && !$body.hasClass(this.openClass)) return;
		var menu = e.currentTarget,
				item = menu.parentItem;
		menu.style.zIndex = 99;
		do {
			if (menu.closeTween && menu.closeTween.isActive()) menu.closeTween.reverse();
			menu.closeTimeout = clearTimeout(menu.closeTimeout);
			menu = menu.parent;
			$(item).addClass("hover");
			item = menu.parentItem;
		} while ("closeTimeout" in menu);
	},

	onMouseLeaveDrop: function(e) {
		var menu = e.currentTarget,
				item = menu.parentItem,
				items = menu.children[0].children,
				i = items.length, t = 1;
		while (i--)
			if (items[i].menu && items[i].menu.closeTimeout) {t++; break}
		do {
			menu.closeTimeout = setTimeout($.proxy(this, "closeDrop", menu), (this.anim.outDur * 500 || 200) * t++);
			menu = menu.parent;
			$(item).removeClass("hover");
			item = menu.parentItem;
		} while ("closeTimeout" in menu);
	},

	closeAllDrops: function() {
		var $menu = this[this.sidebar ? "$node" : "$drop"].children(".sm-level[style*=block]:last");
		if ($menu.length) this.onMouseLeaveDrop({currentTarget: $menu[0]});
	},

	openDrop: function(item) {
		var submenu = item.menu,
				openTween = item.parentNode.parentNode.openTween,
				closeTween = item.parentNode.parentNode.closeTween;
		if (openTween) delete openTween.vars.onComplete;
		if (closeTween) delete closeTween.vars.onReverseComplete;
		// replace nodes to be on top (zIndex)
		if (this.sidebar && submenu.parentNode != this.$node[0])
			$(submenu).appendTo(this.$node);
		if (!this.sidebar && submenu.parentNode != this.$drop[0])
			$(submenu).appendTo(this.$drop);

		var p = $(item).offset(),
				w = $(item).outerWidth(),
				h = $(submenu).outerHeight(),
				t = $win.scrollTop(),
				m = t + this.winHeight - p.top - h;
		if (submenu.parent.parentNode == this.$lvls[0])
			this.dropdir = p.left + w/2 - this.winWidth/2 < 0 ? 1 : -1;

		if (this.dropdir < 0) {
			p.right = $win.outerWidth() - p.left; // don't use this.winWidth
			if (this.sidebar && p.right < this.width) p.right = this.width - this.menuPadding;
			submenu.style.right = p.right + this.menuPadding + this.dropspace + "px";
			submenu.style.left = "";
		} else {
			if (p.left < 0) p.left = 0;
			submenu.style.left = w + p.left + this.menuPadding + this.dropspace + "px";
			submenu.style.right = "";
		}

		if (this.dropFullHeight) {
			p.top = t;
			submenu.style.height = "100vh";
		} else if (m < 0) { // out of screen
			var ih = $(item).outerHeight(), y = p.top;
			p.top += Math.floor(m / ih) * ih;
			if (p.top + h <= y) p.top += ih;
			if (p.top < t) p.top = t;
			submenu.children[0].style.height = h >= this.winHeight ? "100vh" : "";
		}
		if (this.effect < 0) p.top -= $win.scrollTop(); // reduce_width bugfix (#21)
		submenu.style.top = p.top - (this.dropFullHeight ? 0 : this.menuPadding) + "px";
		submenu.style.zIndex = 99;

		this.anim.inCSS.x = this.anim.inUnitX == "%" ? this.anim.inX * this.dropwidth / 100 : this.anim.inX;
		this.anim.inCSS.x *= this.dropdir;
		this.anim.inCSS.rotationY = this.dropdir * this.anim.inRotationY;
		TweenLite.set(submenu, {
			transformPerspective: this.anim.perspective,
			transformOrigin: Hlpr.calcOrigin(this.anim.inOrigin, this.dropwidth, this.dropdir)
		});
		var animTo = {
			overwrite: 1,
			css: this.baseCSS,
			ease: this.anim.inEase
		};
		if (submenu.style.display == "none") {
			submenu.openTween = TweenLite.fromTo(submenu, this.anim.inDur, {css: this.anim.inCSS}, animTo);
			// menu-item anim
			if (this.miAnim) {
				this.miCSS.x = this.miUnitX == "%" ? this.miX * w / 100 : this.miX;
				this.miCSS.x *= this.dropdir;
				this.miCSS.rotationY = this.miRotationY * this.dropdir;
				if (this.miShift)
					TweenMax.staggerFromTo($(">dl>dt", submenu), this.miDur, {css: this.miCSS}, {overwrite: 1, css: this.baseCSS, ease: this.miEase}, this.miShift);
				else
					TweenLite.fromTo(submenu.children[0], this.miDur, {css: this.miCSS}, {overwrite: 1, css: this.baseCSS, ease: this.miEase});
			}
			// icons anim
			if (this.iconAnim && !smMobile)
				TweenLite.fromTo($(".sm-icon img", submenu), 0.5, {scale: 0, opacity: 0}, {css: {scale: 1, opacity: 1}, ease: Back.easeOut, delay: 0.66*this.dur});
		} else {
			submenu.openTween = TweenLite.to(submenu, this.anim.inDur, animTo);
		}

		submenu.style.display = "block";
		this.updateScrollbar(submenu);
		$(item).trigger("openParentItem", this);
	},

	closeDrop: function(submenu) {
		if (submenu.style.display == 'none') return;
		submenu.style.zIndex = "";
		this.anim.outCSS.x = this.anim.outUnitX == "%" ? this.anim.outX * this.dropwidth / 100 : this.anim.outX;
		this.anim.outCSS.rotationY = this.dropdir * this.anim.outRotationY;
		TweenLite.set(submenu, {transformOrigin: Hlpr.calcOrigin(this.anim.outOrigin, this.dropwidth, this.dropdir)});
		submenu.closeTween = TweenLite.to(submenu, this.anim.outDur, {
			overwrite: 1,
			css: this.anim.outCSS,
			ease: this.anim.outEase,
			onComplete: Target.hide
		});
	},

	onClickTree: function(e) {
		e.stopPropagation();
		e.preventDefault();
		//if (smMobile && e.type == 'click') return;
		var $item = $(e.currentTarget);
		this[$item.hasClass("opened") ? "closeTree" : "openTree"]($item);
	},

	openTree: function($item) {
		var dur = this.dur;
		var $dd = $item.addClass("opened").next();
		if (this.popup) this.$lvls.height("auto");
		if (this.accordion) {
			$item.siblings("dt.opened").removeClass("opened");
			$dd.siblings("dd.opened").removeClass("opened").slideUp({ easing: this.outEase, duration: dur * 1000 });
		}
		$dd.slideDown({
			queue: false,
			easing: this.inEase,
			duration: dur * 1000,
			progress: this.bindUpdateScrollbar,
			complete: function() { $dd.attr('style', 'display: block;') }
		}).addClass('opened');

		if (this.miAnim) {
			this.miCSS.x = this.miUnitX == "%" ? this.miX * this.$lvls.outerWidth() / 100 : this.miX;
			if (this.miShift)
				dur = new TimelineLite()
					.staggerFromTo($dd.find("dt").not("dd:not(.opened) dt"), this.miDur, {css: this.miCSS}, {overwrite: 1, css: this.baseCSS, ease: this.miEase}, this.miShift)
					.totalDuration();
			else
				TweenLite.fromTo($dd.find("dl").not("dd:not(.opened) dl"), this.miDur, {css: this.miCSS}, {overwrite: 1, css: this.baseCSS, ease: this.miEase});
		}

		// icons anim
		if (this.iconAnim && !smMobile)
			TweenLite.fromTo($dd.find(".sm-icon img"), 0.5, {scale: 0, opacity: 0}, {css: {scale: 1, opacity: 1}, ease: Back.easeOut, delay: 0.66*this.dur});

		$item.trigger("openParentItem", this);
	},

	closeTree: function($item) {
		var $dd = $item.removeClass("opened").next();
		$dd.removeClass('opened').slideUp({
			queue: false,
			easing: this.outEase,
			duration: this.dur * 1000,
			progress: this.bindUpdateScrollbar,
			complete: function() { $dd.attr('style', '') }
		});
	},

	onOpen: function(e) {
		if (this.drop) return;
		if (e.type == "touchend") e.preventDefault();
		if (e.currentTarget.parentNode.parentNode == this.prev) return;
		if (this.disableClick) return; // disable click during swipe
		var title = $(e.currentTarget).find("a").html().replace(/<.*>/, "");
		this.open(title, e.currentTarget.menu);
	},

	open: function(title, submenu) {
		if (submenu.parentNode != this.$lvls[0])
			$(submenu).css({top:'', left:'', right:''}).appendTo(this.$lvls);
		var w = this.$lvls.outerWidth(),
				tl = new TimelineLite({paused: true, autoRemoveChildren: true, onComplete: $.proxy(this, "onEndSlide")});
		TweenLite.set(this.opened, {transformOrigin: Hlpr.calcOrigin(this.outOrigin, w)});
		TweenLite.set(submenu, {transformOrigin: Hlpr.calcOrigin(this.inOrigin, w)});

		this.inCSS.x = this.inUnitX == "%" ? this.inX * w / 100 : this.inX;
		this.outCSS.x = this.outUnitX == "%" ? this.outX * w / 100 : this.outX;

		// submenu anims
		tl.add(TweenLite.to(this.opened, this.dur, {css: this.outCSS, ease: this.outEase, onComplete: Target.scrollUp}), 0);
		tl.add(TweenLite.fromTo(submenu, this.dur, {css: this.inCSS}, {css: this.baseCSS, ease: this.inEase}), 0);

		this.opened.style.zIndex = "";
		submenu.style.zIndex = 99;
		submenu.style.display = "block";
		if (!this.sidebar && this.winWidth >= this.sidebarUnder) {
			if (this.popup) submenu.children[0].style.maxHeight = this.$lvls[0].style.maxHeight;
			var h = $(submenu.children[0]).outerHeight() + 2 * this.menuPadding;
			tl.add(TweenLite.to(this.$lvls[0], this.dur, {css: {height: h}}), 0);
		}

		if (!$(this.opened.children).hasClass("sm-result")) { // if filtering was started at search result, don't run it
			// header anims
			if (this.$head.length) {
				var $title = $('<span class="sm-title" title="'+title+'">'+title+'</span>').appendTo(this.$head),
						i = this.$title.length;
				if (!this.$title[0].style.position) // init title position
					TweenLite.set(this.$title[0], {css: {position: "absolute", x: this.$title._center(this.$back.length)}});
				if (this.$back.length) { // clean theme
					if (i == 1)
						tl.add(TweenLite.to(this.$back[0], this.dur, {css: {opacity: 1, display: 'block'}}), 0);
					if (this.$title[--i]) this.$title[i].style.position = "absolute",
						tl.add(TweenLite.to(this.$title[i], 2*this.dur, {css: {x: -w*0.6, opacity: -1, scale: 0}}), 0);
				} else { // flat theme
					this.$title.attr("class", "sm-back");
					if (this.$title[--i]) this.$title[i].style.position = "absolute",
						tl.add(TweenLite.to(this.$title[i], this.dur, {css: {x: 0, scale: backScale}}), 0);
					if (this.$title[--i])
						tl.add(TweenLite.to(this.$title[i], this.dur, {css: {x: -w*0.6}, ease: Linear.easeNone}), 0);
				}
				tl.add(TweenLite.fromTo($title, this.dur, {x: w}, {css: {x: $title._center(this.$back.length)}}), 0);
				this.$title.push($title[0]);
			}

			// bg anim
			if (this.bg && this.bgX) {
				var level = this.opened.children[0].attributes['class'].value.match(/\blevel(\d)\b/)[1];
				tl.add(TweenLite.to(this.$node[0], this.dur, {css: {backgroundPosition: this.bgX * level + "% 0"}}), 0);
			}
		}
		// menu-item anim
		if (this.miAnim) {
			this.miCSS.x = this.miUnitX == "%" ? this.miX * w / 100 : this.miX;
			if (this.miShift)
				tl.staggerFromTo($(">dl>dt", submenu), this.miDur, {css: this.miCSS}, {css: this.baseCSS, ease: this.miEase}, this.miShift, 0);
			else
				tl.fromTo(submenu.children[0], this.miDur, {css: this.miCSS}, {css: this.baseCSS, ease: this.miEase}, 0);
		}
		// icons anim
		if (this.iconAnim && !smMobile)
			tl.add(TweenLite.fromTo($(".sm-icon img", submenu), 0.5, {scale: 0, opacity: 0}, {css: {scale: 1, opacity: 1}, ease: Back.easeOut}), 0.66*this.dur);
		this.openTL = tl;

		if (this.backTL && this.backTL._first) delete this.backTL._first.vars.onComplete; // don't run Target.scrollUp
		TweenLite.set(this.$lvls[0], {perspective: this.perspective}); // blur fix for retina
		this.openTL.play();
		this.prev = this.opened;
		this.opened = submenu;
		this.updateScrollbar();

		// don't trigger event when submenu is search result
		if (!$(submenu.children[0]).hasClass("sm-result")) $(submenu.parentItem).trigger("openParentItem", [this, title]);
	},

	onResetFilter: function() {
		this.$filter.attr("value", "");
		if ($(this.opened.children).hasClass("sm-result")) this.onBack();
		this.$filter[0].focus();
	},

	onBack: function(e) {
		if (e && e.type == "touchend") e.preventDefault();
		if (!this.opened.parent) return;
		this.initBackTimeline();
		this.back();
	},

	back: function() {
		if (this.openTL && this.openTL._first) delete this.openTL._first.vars.onComplete; // don't run Target.scrollUp
		TweenLite.set(this.$lvls[0], {perspective: this.perspective}); // blur fix for retina
		this.backTL.resume();
		this.prev = this.opened;
		this.opened = this.opened.parent;
		this.updateScrollbar();

		this.$title.length--;
		this.$filter.attr("value", "");
		this.keyword = "";
		$(this.opened).trigger("back", this);
	},

	initBackTimeline: function() {
		var w = this.$lvls.addClass("sm-swipe").outerWidth(),
				tl = new TimelineLite({
					paused: true,
					autoRemoveChildren: true,
					onComplete: $.proxy(this, "onEndSlide"),
					onReverseComplete: function() { this.onEndSlide(), this.opened.parent.style.display = "none" }.bind(this)
				});

		this.inCSS.x = this.inUnitX == "%" ? this.inX * w / 100 : this.inX;

		TweenLite.set(this.opened, {transformOrigin: Hlpr.calcOrigin(this.inOrigin, w)});
		TweenLite.set(this.opened.parent, {transformOrigin: Hlpr.calcOrigin(this.outOrigin, w)});

		// submenu anims
		tl.add(TweenLite.to(this.opened, this.dur, {css: this.inCSS, onComplete: Target.scrollUp}), 0);
		tl.add(TweenLite.to(this.opened.parent, this.dur, {css: this.baseCSS, ease: this.inEase}), 0);

		this.opened.parent.style.display = "block";
		if (!this.sidebar && this.winWidth >= this.sidebarUnder) {
			if (this.popup) this.opened.parent.children[0].style.maxHeight = this.$lvls[0].style.maxHeight;
			tl.add(TweenLite.to(this.$lvls[0], this.dur, {
				css: {height: $(this.opened.parent.children[0]).outerHeight() + 2 * this.menuPadding},
				onComplete: this.prev.parentNode.children[0] == this.prev ? Target.autoHeight : undefined // auto height first level
			}), 0);
		}

		// header anims
		if (this.$head.length) {
			var i = this.$title.length;
			tl.add(TweenLite.to(this.$title[--i], this.dur, {css: {x: w, opacity: 0}, onComplete: Target.remove}), 0);
			if (this.$back.length) { // clean theme
				if (i == 1) tl.add(TweenLite.to(this.$back[0], this.dur, {css: {opacity: 0, display: 'none'}}), 0);
				tl.add(TweenLite.to(this.$title[--i], this.dur, {css: {x: $(this.$title[i])._center(true), opacity: 1, scale: 1}}), 0);
			} else { // flat theme
				tl.add(TweenLite.to(this.$title[--i], this.dur, {
					css: {x: $(this.$title[i])._center(), scale: 1, maxWidth: i ? this.titleWidth : "100%"},
					onComplete: Target.autoMaxWidth
				}), 0);
				if (this.$title[--i]) tl.add(TweenLite.to(this.$title[i], this.dur, {css: {x: 0}}), 0);
			}
		}

		// bg anim
		if (this.bg && this.bgX) {
			var level = this.opened.children[0].attributes['class'].value.match(/\blevel(\d)\b/);
			level = level ? level[1] - 2 : 0;
			tl.add(TweenLite.to(this.$node[0], this.dur, {css: {backgroundPosition: this.bgX * level + "% 0"}}), 0);
		}

		this.backTL = tl;
	},

	onEndSlide: function() {
		// blur fix for retina
		$(this.opened).css({WebkitTransform: "", MozTransform: "", msTransform: "", transform: ""});
		this.$lvls.css({WebkitPerspective: "", MozPerspective: "", perspective: ""}).removeClass("sm-swipe");
		this.disableClick = false; // enable click after swipe
	},

	updateScrollbar: function(menu) {
		if (smMobile) return;
		var $dl = $( (menu || this.opened).children[0] );
		$dl.perfectScrollbar($dl.data("perfectScrollbar") ? "update" : undefined);
	}

};

// depreciated functions
VerticalSlideMenu.prototype.openPopup = VerticalSlideMenu.prototype.openMenu;
VerticalSlideMenu.prototype.closePopup = VerticalSlideMenu.prototype.closeMenu;
VerticalSlideMenu.prototype.openSidebar = VerticalSlideMenu.prototype.openMenu;
VerticalSlideMenu.prototype.closeSidebar = VerticalSlideMenu.prototype.closeMenu;

$html.on('click.sm', '.sm-toggle', function(e) {
	var id = this.className.match(/\bsm-(\d+)\b/);
	id = id ? id[1] : ($('.sm-menu:first').attr('id') || '').split('_')[1];
	var sm = window['sm'+id];
	if (sm) {
		e.preventDefault();
		sm.$cont.hasClass(sm.openClass) ? sm.closeMenu(e) : sm.openMenu(e);
	}
});

(function initMenuSpy() {
	var $elems = $();
	$win
		.on("load.menuspy resize.menuspy", onResize)
		.on("scroll.menuspy", onScroll)
	;

	function onScroll() {
		if (!$elems.length) return;
		var y = $win.scrollTop();
		$elems.each(function(i) {
			var $elem = $(this);
			var o = $elem.data('menuspy');
			if (y >= o.min && y < o.max) {
				if (!o.inside) {
					o.inside = true;
					$elem.trigger("scrollEnter.menuspy", {scrollTop: y, enters: ++o.enters, leaves: o.leaves});
				}
			} else if (o.inside) {
				o.inside = false;
				$elem.trigger("scrollLeave.menuspy", {scrollTop: y, enters: o.enters, leaves: ++o.leaves});
			}
		});
	}

	function onResize() {
		$elems.each(function() {
			var $elem = $(this);
			var o = $elem.data("menuspy");
			o.min = $elem.offset().top;
			o.max = $elem.outerHeight() + o.min;
		});
		onScroll();
	}

	$.fn.menuspy = function(options) {
		var defaults = {
			offset: 0,
			enters: 0,
			leaves: 0,
			inside: false
		};
		return this.each(function() {
			var $elem = $(this);
			var top = $elem.offset().top;
			$elem.data("menuspy", $.extend({
				min: top,
				max: top + $elem.outerHeight(),
			}, defaults, options));
			$elems.filter(this).length || $elems.push(this);
		});
	};

	$.fn.menuspy.$elems = $elems;
})();

})(window.jq183||jQuery);