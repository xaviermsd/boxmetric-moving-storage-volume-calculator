/**
 * Moving & Storage Volume Calculator: front-end app.
 *
 * Plain modern JavaScript, no dependencies. Every calculator on the page is
 * an independent instance that reads the shared window.msvcalcConfig.
 * All text from the server is written with textContent, never as HTML.
 *
 * @package MSVCalc
 */
( function () {
	'use strict';

	const config = window.msvcalcConfig;
	if ( ! config || ! config.catalogue ) {
		return;
	}

	const FT3 = 35.3147;
	const LB = 2.20462;
	const STORE_DAYS = 30;
	const MAX_QTY = 999;
	const t = config.i18n;
	const s = config.settings;
	const reduceMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	/* ------------------------------------------------------------------ */
	/* Helpers                                                              */
	/* ------------------------------------------------------------------ */

	/**
	 * Creates an element. Text is always set with textContent.
	 *
	 * @param {string} tag   Tag name, with optional .class names.
	 * @param {Object} attrs Attributes; "text" sets textContent, "on*" adds listeners.
	 * @param {Array}  kids  Children (nodes or strings).
	 * @return {HTMLElement} Element.
	 */
	function h( tag, attrs, kids ) {
		const parts = tag.split( '.' );
		const el = document.createElement( parts[ 0 ] || 'div' );
		if ( parts.length > 1 ) {
			el.className = parts.slice( 1 ).join( ' ' );
		}
		Object.keys( attrs || {} ).forEach( function ( key ) {
			const value = attrs[ key ];
			if ( null === value || undefined === value || false === value ) {
				return;
			}
			if ( 'text' === key ) {
				el.textContent = value;
			} else if ( 'class' === key ) {
				el.className += ( el.className ? ' ' : '' ) + value;
			} else if ( 0 === key.indexOf( 'on' ) && 'function' === typeof value ) {
				el.addEventListener( key.slice( 2 ).toLowerCase(), value );
			} else if ( true === value ) {
				el.setAttribute( key, '' );
			} else {
				el.setAttribute( key, String( value ) );
			}
		} );
		( kids || [] ).forEach( function ( kid ) {
			if ( null === kid || undefined === kid || false === kid ) {
				return;
			}
			el.appendChild( 'string' === typeof kid ? document.createTextNode( kid ) : kid );
		} );
		return el;
	}

	/**
	 * An icon from the plugin's own set.
	 *
	 * @param {string} name   Icon name.
	 * @param {number} size   Pixel size.
	 * @param {string} stroke Optional stroke (e.g. a gradient URL).
	 * @return {SVGElement} Icon.
	 */
	function icon( name, size, stroke ) {
		const shapes = window.msvcalcIcons || {};
		const ns = 'http://www.w3.org/2000/svg';
		const svg = document.createElementNS( ns, 'svg' );
		svg.setAttribute( 'viewBox', '0 0 24 24' );
		svg.setAttribute( 'width', String( size || 20 ) );
		svg.setAttribute( 'height', String( size || 20 ) );
		svg.setAttribute( 'fill', 'none' );
		svg.setAttribute( 'stroke', stroke || 'currentColor' );
		svg.setAttribute( 'stroke-width', '1.75' );
		svg.setAttribute( 'stroke-linecap', 'round' );
		svg.setAttribute( 'stroke-linejoin', 'round' );
		svg.setAttribute( 'aria-hidden', 'true' );
		svg.setAttribute( 'focusable', 'false' );
		svg.setAttribute( 'class', 'msvcalc-icon' );
		// Shapes are fixed strings shipped in icons.js, never user input.
		svg.innerHTML = shapes[ name ] || shapes.box || '';
		return svg;
	}

	const BOX_IDS = [ 'box-small', 'box-medium', 'box-large', 'box-wardrobe' ];
	const FORGOT_ROOMS = [ 'kitchen', 'garage', 'garden', 'boxes', 'bathroom', 'laundry', 'hallway' ];
	// Areas people tend to forget, by the service picked in the quote form.
	const FORGOT_BY_SERVICE = {
		office: [ 'business', 'boxes', 'warehouse' ],
		storage: [ 'boxes', 'garage', 'garden', 'leisure' ],
	};
	// A shop, café or other business list: only the usual extras.
	const FORGOT_BUSINESS = [ 'boxes', 'warehouse' ];
	const SHARE_KEY = 'msvcalc';

	/**
	 * Suggested box counts from the estimator answers.
	 *
	 * @param {Object} a Answers: home 0-5, cupboards 0-2, books 0-2, kitchen 0-2, loft 0-2.
	 * @return {Object} Item ID => count.
	 */
	function boxCounts( a ) {
		const beds = [ 0.5, 1, 2, 3, 4, 5 ][ a.home ];
		const base = ( 12 + 14 * beds ) * [ 0.75, 1, 1.35 ][ a.cupboards ];
		const counts = {};
		counts[ 'box-small' ] = Math.round( base * 0.15 + [ 2 + beds, 4 + 3 * beds, 8 + 5 * beds ][ a.books ] );
		counts[ 'box-medium' ] = Math.round( base * 0.6 + [ 4, 8, 14 ][ a.kitchen ] );
		counts[ 'box-large' ] = Math.round( base * 0.25 + [ 0, 6 + 2 * beds, 15 + 4 * beds ][ a.loft ] );
		counts[ 'box-wardrobe' ] = Math.max( 1, Math.round( beds * 1.5 ) );
		return counts;
	}

	/**
	 * A known, pickable icon name, or "box".
	 *
	 * @param {*} name Candidate.
	 * @return {string} Icon name.
	 */
	function validIcon( name ) {
		return 'string' === typeof name && ( window.msvcalcIcons || {} )[ name ] && ! /^(plus|minus|search|close|check|chevron|arrow|reset|print|mail|logo|share|link|chat)$/.test( name ) ? name : 'box';
	}

	function groupsAvailable() {
		return ( config.iconGroups || [] ).some( function ( g ) {
			return g.icons && g.icons.length;
		} );
	}

	/**
	 * A searchable icon picker in categories. The grid opens as a popover
	 * inside `host`.
	 *
	 * @param {HTMLElement} host     Positioned container for the popover.
	 * @param {string}      value    Current icon.
	 * @param {Function}    onChange Called with the picked name.
	 * @return {Object} The trigger button (el), a setter (set) and close().
	 */
	function iconPicker( host, value, onChange ) {
		let current = value;
		let pop = null;
		const groups = ( config.iconGroups || [] ).filter( function ( g ) {
			return g.icons && g.icons.length;
		} );
		const label = function ( name ) {
			return name.replace( /-/g, ' ' );
		};
		const trigger = h( 'button.msvcalc__icon-pick', {
			type: 'button',
			'aria-haspopup': 'dialog',
			'aria-expanded': 'false',
			onClick: function () {
				if ( pop ) {
					close( true );
				} else {
					open();
				}
			},
		} );
		function paint() {
			trigger.textContent = '';
			trigger.appendChild( h( 'span.msvcalc__icon-pick-glyph', {}, [ icon( current, 22 ) ] ) );
			trigger.appendChild( icon( 'chevron', 14 ) );
			trigger.appendChild( h( 'span.msvcalc__sr', { text: t.chooseIcon + ': ' + label( current ) } ) );
		}
		function outside( event ) {
			if ( pop && ! pop.contains( event.target ) && ! trigger.contains( event.target ) ) {
				close( false );
			}
		}
		function close( focus ) {
			if ( ! pop ) {
				return;
			}
			pop.remove();
			pop = null;
			trigger.setAttribute( 'aria-expanded', 'false' );
			document.removeEventListener( 'mousedown', outside );
			if ( focus ) {
				trigger.focus();
			}
		}
		function open() {
			let active = '';
			const search = h( 'input.msvcalc__input.msvcalc__picker-search', { type: 'search', placeholder: t.findIcon, 'aria-label': t.findIcon } );
			const chips = h( 'div.msvcalc__picker-chips', { role: 'group', 'aria-label': t.groups } );
			// Small phones get a compact dropdown instead of the chips.
			const groupSelect = h( 'select.msvcalc__input.msvcalc__picker-select', { 'aria-label': t.groups }, [ h( 'option', { value: '', text: t.allIcons } ) ] );
			const grid = h( 'div.msvcalc__picker-grid' );
			function draw() {
				const q = normalise( search.value );
				grid.textContent = '';
				let shown = 0;
				groups.forEach( function ( g ) {
					if ( active && g.id !== active ) {
						return;
					}
					g.icons.forEach( function ( name ) {
						if ( q && -1 === label( name ).indexOf( q ) ) {
							return;
						}
						shown++;
						grid.appendChild(
							h(
								'button.msvcalc__picker-icon' + ( name === current ? '.is-current' : '' ),
								{
									type: 'button',
									title: label( name ),
									'aria-label': label( name ),
									'aria-pressed': name === current ? 'true' : 'false',
									onClick: function () {
										current = name;
										paint();
										onChange( name );
										close( true );
									},
								},
								[ icon( name, 24 ) ]
							)
						);
					} );
				} );
				if ( ! shown ) {
					grid.appendChild( h( 'p.msvcalc__muted', { text: t.noIcons } ) );
				}
			}
			function chip( id, text ) {
				return h(
					'button.msvcalc__chip',
					{
						type: 'button',
						'aria-pressed': id === active ? 'true' : 'false',
						'data-id': id,
						onClick: function () {
							active = id;
							chips.querySelectorAll( '.msvcalc__chip' ).forEach( function ( el ) {
								el.setAttribute( 'aria-pressed', el.getAttribute( 'data-id' ) === active ? 'true' : 'false' );
							} );
							groupSelect.value = active;
							draw();
						},
					},
					[ h( 'span', { text: text } ) ]
				);
			}
			chips.appendChild( chip( '', t.allIcons ) );
			groups.forEach( function ( g ) {
				chips.appendChild( chip( g.id, g.name ) );
				groupSelect.appendChild( h( 'option', { value: g.id, text: g.name } ) );
			} );
			groupSelect.addEventListener( 'change', function () {
				active = groupSelect.value;
				chips.querySelectorAll( '.msvcalc__chip' ).forEach( function ( el ) {
					el.setAttribute( 'aria-pressed', el.getAttribute( 'data-id' ) === active ? 'true' : 'false' );
				} );
				draw();
			} );
			search.addEventListener( 'input', draw );
			pop = h( 'div.msvcalc__picker', { role: 'dialog', 'aria-label': t.chooseIcon }, [ search, groupSelect, chips, grid ] );
			pop.addEventListener( 'keydown', function ( event ) {
				if ( 'Escape' === event.key ) {
					event.preventDefault();
					event.stopPropagation();
					close( true );
				}
			} );
			draw();
			host.appendChild( pop );
			trigger.setAttribute( 'aria-expanded', 'true' );
			document.addEventListener( 'mousedown', outside );
			search.focus();
		}
		paint();
		return {
			el: trigger,
			set: function ( name ) {
				current = name;
				paint();
			},
			close: close,
		};
	}

	/**
	 * The plugin's one branded loader: a gradient ring around the cube mark,
	 * the same as the start screen. Used wherever the visitor has to wait.
	 *
	 * @param {string} text Message under the loader.
	 * @return {HTMLElement} Loader block.
	 */
	let loaderCount = 0;

	/**
	 * The loader mark, shared with PHP through icons.js.
	 *
	 * @param {string} cls Wrapper class.
	 * @return {Element} Wrapper with the animated mark.
	 */
	function loaderMark( cls ) {
		const wrap = h( cls, { 'aria-hidden': 'true' } );
		loaderCount += 1;
		wrap.innerHTML = String( window.msvcalcLoader || '' ).replace( /__ID__/g, 'msvcalc-loader-js-' + loaderCount );
		return wrap;
	}

	function loader( text ) {
		return h( 'div.msvcalc__loading', { role: 'status' }, [
			loaderMark( 'span.msvcalc__loader' ),
			h( 'span.msvcalc__loading-text', { text: text } ),
		] );
	}

	/**
	 * Wraps a horizontal strip with edge fades and previous/next buttons that
	 * appear only while there is more to scroll. A mouse can also drag it.
	 *
	 * @param {HTMLElement} list      The scrolling strip.
	 * @param {string}      className Wrapper class.
	 * @return {HTMLElement} Wrapper with an update() method.
	 */
	function scroller( list, className ) {
		const behavior = reduceMotion ? 'auto' : 'smooth';
		const step = function ( dir ) {
			list.scrollBy( { left: dir * Math.max( 120, list.clientWidth * 0.7 ), behavior: behavior } );
		};
		const button = function ( dir ) {
			return h(
				'button.msvcalc__scroll-btn.msvcalc__scroll-btn--' + ( dir < 0 ? 'prev' : 'next' ),
				{
					type: 'button',
					tabindex: '-1',
					'aria-hidden': 'true',
					onClick: function () {
						step( dir );
					},
				},
				[ icon( 'chevron', 18 ) ]
			);
		};
		list.classList.add( 'msvcalc__strip' );
		const wrap = h( 'div.msvcalc__scroller.' + className, {}, [ button( -1 ), list, button( 1 ) ] );
		let frame = 0;
		const update = function () {
			cancelAnimationFrame( frame );
			frame = requestAnimationFrame( function () {
				const max = list.scrollWidth - list.clientWidth;
				wrap.classList.toggle( 'can-prev', list.scrollLeft > 2 );
				wrap.classList.toggle( 'can-next', list.scrollLeft < max - 2 );
			} );
		};
		list.addEventListener( 'scroll', update, { passive: true } );
		if ( window.ResizeObserver ) {
			new window.ResizeObserver( update ).observe( list );
		} else {
			window.addEventListener( 'resize', update );
		}
		// Drag to scroll with a mouse; touch already scrolls natively.
		let drag = null;
		list.addEventListener( 'pointerdown', function ( event ) {
			if ( 'mouse' !== event.pointerType || 0 !== event.button || list.scrollWidth <= list.clientWidth ) {
				return;
			}
			drag = { x: event.clientX, left: list.scrollLeft, moved: false };
		} );
		window.addEventListener( 'pointermove', function ( event ) {
			if ( ! drag ) {
				return;
			}
			const dx = event.clientX - drag.x;
			if ( ! drag.moved && Math.abs( dx ) > 6 ) {
				drag.moved = true;
				list.classList.add( 'is-dragging' );
			}
			if ( drag.moved ) {
				list.scrollLeft = drag.left - dx;
			}
		} );
		window.addEventListener( 'pointerup', function () {
			if ( drag && drag.moved ) {
				// Swallow the click that ends a drag.
				list.addEventListener(
					'click',
					function ( event ) {
						event.preventDefault();
						event.stopPropagation();
					},
					{ capture: true, once: true }
				);
				setTimeout( function () {
					list.classList.remove( 'is-dragging' );
				}, 0 );
			}
			drag = null;
		} );
		wrap.update = update;
		update();
		return wrap;
	}

	/**
	 * Scrolls a strip so `el` sits in the middle, without moving the page.
	 *
	 * @param {HTMLElement} list Strip.
	 * @param {HTMLElement} el   Child to centre.
	 * @param {boolean}     now  Jump instead of animating.
	 */
	function centerIn( list, el, now ) {
		if ( ! list || ! el || list.scrollWidth <= list.clientWidth + 1 ) {
			return;
		}
		const lr = list.getBoundingClientRect();
		const er = el.getBoundingClientRect();
		const left = list.scrollLeft + ( er.left - lr.left ) - ( lr.width - er.width ) / 2;
		list.scrollTo( { left: Math.max( 0, left ), behavior: now || reduceMotion ? 'auto' : 'smooth' } );
	}

	// Quick Starts shown before "+ more" on phones and tablets.
	const PRIMARY_PRESETS = [ 'few-items', 'studio', '1-bed', '2-bed', '3-bed', '4-bed' ];

	// Icons for Quick Starts saved before presets had their own icon.
	const PRESET_ICONS = {
		'boxes-only': 'boxes',
		'few-items': 'sofa',
		student: 'laptop',
		garage: 'garage',
		'storage-unit': 'storage',
		office: 'office',
		shop: 'register',
		cafe: 'coffee-machine',
	};

	/**
	 * A Quick Start's chip icon: its own, else the old default for its ID.
	 *
	 * @param {Object} preset Preset.
	 * @return {string} Icon name.
	 */
	function presetIcon( preset ) {
		if ( preset.icon && 'home' !== preset.icon && validIcon( preset.icon ) === preset.icon ) {
			return preset.icon;
		}
		return PRESET_ICONS[ preset.id ] || 'home';
	}

	function sprintf( text, value ) {
		return String( text ).replace( /%(\d\$)?[sd]/, String( value ) ).replace( '%%', '%' );
	}

	function clampQty( value ) {
		const n = parseInt( value, 10 );
		if ( isNaN( n ) || n < 0 ) {
			return 0;
		}
		return Math.min( MAX_QTY, n );
	}

	function normalise( text ) {
		return String( text || '' )
			.toLowerCase()
			.normalize( 'NFD' )
			.replace( /[̀-ͯ]/g, '' )
			.replace( /[^a-z0-9\s]/g, ' ' )
			.replace( /\s+/g, ' ' )
			.trim();
	}

	const numberFormats = {};
	function fmt( value, decimals ) {
		const key = String( decimals );
		if ( ! numberFormats[ key ] ) {
			try {
				numberFormats[ key ] = new Intl.NumberFormat( s.locale, {
					minimumFractionDigits: decimals,
					maximumFractionDigits: decimals,
				} );
			} catch ( e ) {
				numberFormats[ key ] = new Intl.NumberFormat( undefined, {
					minimumFractionDigits: decimals,
					maximumFractionDigits: decimals,
				} );
			}
		}
		return numberFormats[ key ].format( value );
	}

	/**
	 * Picks the smallest row that holds the load; otherwise as many of the
	 * largest as needed plus the smallest row for the rest. Mirrors
	 * MSVCalc_Calculator::fit() in PHP.
	 *
	 * @param {number} load Load in m³.
	 * @param {Array}  rows Rows sorted by m³.
	 * @return {Object|null} Fit.
	 */
	function fit( load, rows ) {
		if ( load <= 0 || ! rows || ! rows.length ) {
			return null;
		}
		for ( let i = 0; i < rows.length; i++ ) {
			if ( rows[ i ].m3 >= load ) {
				return {
					row: rows[ i ],
					label: rows[ i ].name,
					note: rows[ i ].note,
					count: 1,
					fill: Math.round( ( load / rows[ i ].m3 ) * 100 ),
				};
			}
		}
		const largest = rows[ rows.length - 1 ];
		const full = Math.floor( load / largest.m3 );
		const rest = load - full * largest.m3;
		let extra = null;
		const parts = [];
		if ( full > 0 ) {
			parts.push( full + ' × ' + largest.name );
		}
		if ( rest > 0.01 ) {
			extra = rows.find( function ( row ) {
				return row.m3 >= rest;
			} ) || largest;
			parts.push( '1 × ' + extra.name );
		}
		const capacity = full * largest.m3 + ( extra ? extra.m3 : 0 );
		return {
			row: largest,
			label: parts.join( ' + ' ),
			note: t.tooBig,
			count: full + ( extra ? 1 : 0 ),
			fill: capacity ? Math.round( ( load / capacity ) * 100 ) : 100,
		};
	}

	/* ------------------------------------------------------------------ */
	/* Data                                                                 */
	/* ------------------------------------------------------------------ */

	const catalogue = config.catalogue;
	const tables = config.tables;
	const itemsById = {};
	const itemsByRoom = {};
	catalogue.rooms.forEach( function ( room ) {
		itemsByRoom[ room.id ] = [];
	} );
	catalogue.items.forEach( function ( item ) {
		item.search = normalise( [ item.name ].concat( item.aliases || [] ).join( ' ' ) );
		itemsById[ item.id ] = item;
		if ( itemsByRoom[ item.room ] ) {
			itemsByRoom[ item.room ].push( item );
		}
	} );
	const roomsById = {};
	catalogue.rooms.forEach( function ( room ) {
		roomsById[ room.id ] = room;
	} );
	// Room groups that have at least one room, in display order.
	const groups = ( config.groups || [] ).filter( function ( group ) {
		return catalogue.rooms.some( function ( room ) {
			return room.group === group.id;
		} );
	} );
	const grouped = groups.length > 1;
	function roomsInGroup( groupId ) {
		return catalogue.rooms.filter( function ( room ) {
			return ! grouped || room.group === groupId;
		} );
	}

	let instanceCount = 0;

	/* ------------------------------------------------------------------ */
	/* Calculator instance                                                  */
	/* ------------------------------------------------------------------ */

	function Calculator( root ) {
		let options = {};
		try {
			options = JSON.parse( root.getAttribute( 'data-msvcalc' ) || '{}' );
		} catch ( e ) {
			options = {};
		}

		instanceCount++;
		this.uid = 'msvcalc-' + instanceCount;
		this.root = root;
		this.app = root.querySelector( '.msvcalc__app' );
		this.quoteEnabled = !! options.quote;
		this.state = {
			units: 'ft3' === options.units ? 'ft3' : 'm3',
			qty: {},
			custom: [],
			room: catalogue.rooms.length ? catalogue.rooms[ 0 ].id : '',
			showAll: false,
			query: '',
			view: 'items',
		};
		this.displayed = 0;
		this.restore();
		this.readShared();
		this.build();
		this.update( true );
		if ( this.sharedLoaded ) {
			this.save();
			this.toast( t.sharedLoaded );
		}
	}

	Calculator.prototype.storageKey = function () {
		return s.storageKey;
	};

	Calculator.prototype.restore = function () {
		if ( ! s.remember ) {
			return;
		}
		try {
			const saved = JSON.parse( window.localStorage.getItem( this.storageKey() ) || 'null' );
			if ( ! saved || 1 !== saved.v || Date.now() - saved.t > STORE_DAYS * 864e5 ) {
				return;
			}
			const qty = {};
			Object.keys( saved.qty || {} ).forEach( function ( id ) {
				const n = clampQty( saved.qty[ id ] );
				if ( n && itemsById[ id ] ) {
					qty[ id ] = n;
				}
			} );
			this.state.qty = qty;
			this.state.custom = ( Array.isArray( saved.custom ) ? saved.custom : [] )
				.filter( function ( row ) {
					return row && 'string' === typeof row.name && row.m3 > 0 && row.m3 <= 100 && clampQty( row.qty ) > 0;
				} )
				.slice( 0, 30 )
				.map( function ( row, index ) {
					return { id: 'c' + index + Date.now(), name: row.name.slice( 0, 60 ), m3: Number( row.m3 ), qty: clampQty( row.qty ), icon: validIcon( row.icon ), dims: 'string' === typeof row.dims ? row.dims.slice( 0, 40 ) : '' };
				} );
			if ( s.unitToggle && ( 'm3' === saved.units || 'ft3' === saved.units ) ) {
				this.state.units = saved.units;
			}
		} catch ( e ) {
			// Storage blocked or data unreadable: start fresh.
		}
	};

	Calculator.prototype.save = function () {
		if ( ! s.remember ) {
			return;
		}
		try {
			const custom = this.state.custom.map( function ( row ) {
				return { name: row.name, m3: row.m3, qty: row.qty, icon: row.icon, dims: row.dims || '' };
			} );
			window.localStorage.setItem(
				this.storageKey(),
				JSON.stringify( { v: 1, t: Date.now(), qty: this.state.qty, custom: custom, units: this.state.units } )
			);
		} catch ( e ) {
			// Private mode or storage full: the calculator still works.
		}
	};

	/* ---------------------------- Totals ------------------------------ */

	Calculator.prototype.totals = function () {
		const self = this;
		let net = 0;
		let kg = 0;
		let count = 0;
		let boxes = 0;
		const rooms = {};
		Object.keys( this.state.qty ).forEach( function ( id ) {
			const item = itemsById[ id ];
			const qty = self.state.qty[ id ];
			if ( ! item || ! qty ) {
				return;
			}
			const volume = item.m3 * qty;
			net += volume;
			kg += ( null !== item.kg && undefined !== item.kg ? item.kg : item.m3 * tables.density ) * qty;
			count += qty;
			if ( item.box ) {
				boxes += qty;
			}
			rooms[ item.room ] = ( rooms[ item.room ] || 0 ) + volume;
		} );
		this.state.custom.forEach( function ( row ) {
			net += row.m3 * row.qty;
			kg += row.m3 * tables.density * row.qty;
			count += row.qty;
			rooms._custom = ( rooms._custom || 0 ) + row.m3 * row.qty;
		} );
		const load = net * ( 1 + s.margin / 100 );
		return {
			net: net,
			load: load,
			kg: kg,
			count: count,
			boxes: boxes,
			rooms: rooms,
			vehicle: fit( load, tables.vehicles ),
			container: fit( load, tables.containers ),
			storage: fit( load, tables.storage ),
		};
	};

	Calculator.prototype.volumeText = function ( m3, withUnit ) {
		const ft = 'ft3' === this.state.units;
		let text = ft ? fmt( m3 * FT3, m3 * FT3 < 10 ? 1 : 0 ) : fmt( m3, 2 );
		// A small item never reads as nothing.
		if ( m3 > 0 && ( ft ? m3 * FT3 < 0.05 : m3 < 0.005 ) ) {
			text = '< ' + ( ft ? fmt( 0.1, 1 ) : fmt( 0.01, 2 ) );
		}
		return withUnit ? text + ' ' + t[ this.state.units ] : text;
	};

	/* ---------------------------- Build ------------------------------- */

	Calculator.prototype.build = function () {
		const self = this;
		this.app.textContent = '';
		this.app.removeAttribute( 'aria-live' );

		// Gradient shared by the icons and illustrations of this instance.
		const ns = 'http://www.w3.org/2000/svg';
		const defs = document.createElementNS( ns, 'svg' );
		defs.setAttribute( 'class', 'msvcalc__defs' );
		defs.setAttribute( 'aria-hidden', 'true' );
		defs.setAttribute( 'width', '0' );
		defs.setAttribute( 'height', '0' );
		const grad = document.createElementNS( ns, 'linearGradient' );
		grad.setAttribute( 'id', this.uid + '-grad' );
		grad.setAttribute( 'x1', '0' );
		grad.setAttribute( 'y1', '0' );
		grad.setAttribute( 'x2', '1' );
		grad.setAttribute( 'y2', '1' );
		[ [ '0', 'var(--msvcalc-accent)' ], [ '1', 'var(--msvcalc-accent-2)' ] ].forEach( function ( stop ) {
			const el = document.createElementNS( ns, 'stop' );
			el.setAttribute( 'offset', stop[ 0 ] );
			el.setAttribute( 'style', 'stop-color:' + stop[ 1 ] );
			grad.appendChild( el );
		} );
		defs.appendChild( grad );
		this.gradUrl = 'url(#' + this.uid + '-grad)';

		// Icons use user-space coordinates so straight strokes keep their colour.
		const iconGrad = grad.cloneNode( true );
		iconGrad.setAttribute( 'id', this.uid + '-igrad' );
		iconGrad.setAttribute( 'gradientUnits', 'userSpaceOnUse' );
		iconGrad.setAttribute( 'x2', '24' );
		iconGrad.setAttribute( 'y2', '24' );
		defs.appendChild( iconGrad );
		this.iconGradUrl = 'url(#' + this.uid + '-igrad)';
		this.app.appendChild( defs );

		this.live = h( 'div.msvcalc__sr', { role: 'status', 'aria-live': 'polite' } );
		this.app.appendChild( this.live );

		this.app.appendChild( this.buildToolbar() );

		this.main = h( 'div.msvcalc__main' );
		this.itemsView = h( 'div.msvcalc__view' );
		this.quoteView = h( 'div.msvcalc__view', { hidden: true } );

		if ( s.search ) {
			this.itemsView.appendChild( this.buildSearch() );
		}
		this.itemsView.appendChild( this.buildTabs() );
		this.panel = h( 'div.msvcalc__panel', { role: 'tabpanel', id: this.uid + '-panel', tabindex: '-1' } );
		this.itemsView.appendChild( this.panel );
		if ( s.custom ) {
			this.itemsView.appendChild( this.buildCustom() );
		}
		this.main.appendChild( this.itemsView );
		if ( this.quoteEnabled ) {
			this.main.appendChild( this.quoteView );
		}

		// The optional credit sits under the list, centred on that column.
		const credit = this.root.querySelector( '.msvcalc__credit' );
		if ( credit ) {
			this.main.appendChild( credit );
		}

		this.summary = this.buildSummary();
		this.app.appendChild( h( 'div.msvcalc__body', {}, [ this.main, this.summary ] ) );
		this.app.appendChild( this.buildDock() );

		if ( ! roomsById[ this.state.room ] && catalogue.rooms.length ) {
			this.state.room = catalogue.rooms[ 0 ].id;
		}
		this.syncGroup();
		this.renderPanel();

		// A soft ripple where buttons, chips and tabs are pressed.
		if ( ! reduceMotion && s.animations ) {
			this.root.addEventListener( 'pointerdown', function ( event ) {
				const host = event.target.closest && event.target.closest( '.msvcalc__button, .msvcalc__chip, .msvcalc__tab, .msvcalc__group, .msvcalc__segment, .msvcalc__unit, .msvcalc__ghost, .msvcalc__more, .msvcalc__step' );
				if ( ! host || ! self.root.contains( host ) || host.disabled ) {
					return;
				}
				const rect = host.getBoundingClientRect();
				const size = Math.max( rect.width, rect.height ) * 2.2;
				const ripple = h( 'span.msvcalc__ripple', { 'aria-hidden': 'true' } );
				ripple.style.width = size + 'px';
				ripple.style.height = size + 'px';
				ripple.style.left = event.clientX - rect.left - size / 2 + 'px';
				ripple.style.top = event.clientY - rect.top - size / 2 + 'px';
				host.appendChild( ripple );
				window.setTimeout( function () {
					ripple.remove();
				}, 650 );
			} );
		}

		// Keep a tall summary in view: when it is taller than the window it
		// sticks by its bottom edge, otherwise by its top.
		const stick = function () {
			const panel = self.summary;
			if ( ! panel ) {
				return;
			}
			panel.style.removeProperty( 'top' );
			if ( 'sticky' !== window.getComputedStyle( panel ).position ) {
				return;
			}
			const bar = document.getElementById( 'wpadminbar' );
			const base = 24 + ( bar && 'fixed' === window.getComputedStyle( bar ).position ? bar.offsetHeight : 0 );
			panel.style.top = Math.min( base, window.innerHeight - panel.offsetHeight - 16 ) + 'px';
		};
		window.addEventListener( 'resize', stick );
		if ( window.ResizeObserver ) {
			new window.ResizeObserver( stick ).observe( this.summary );
		}
		stick();

		// Close the mobile summary sheet with Escape.
		this.root.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && self.root.classList.contains( 'msvcalc--sheet-open' ) ) {
				self.toggleSheet( false );
			}
		} );

		requestAnimationFrame( function () {
			self.root.classList.add( 'msvcalc--ready' );
			if ( self.tabs && self.tabs[ self.state.room ] ) {
				centerIn( self.tabList, self.tabs[ self.state.room ].el, true );
			}
			if ( self.groupButtons && roomsById[ self.state.room ] && self.groupButtons[ roomsById[ self.state.room ].group ] ) {
				centerIn( self.groupList, self.groupButtons[ roomsById[ self.state.room ].group ].el, true );
			}
		} );
	};

	Calculator.prototype.buildToolbar = function () {
		const self = this;
		const bar = h( 'div.msvcalc__toolbar' );

		if ( s.presets && catalogue.presets.length ) {
			const chips = h( 'div.msvcalc__presets', { role: 'group', 'aria-label': t.quickStart } );
			// On phones and tablets only the most common choices show until
			// the visitor asks for more.
			const known = catalogue.presets.filter( function ( preset ) {
				return -1 !== PRIMARY_PRESETS.indexOf( preset.id );
			} ).length;
			let extras = 0;
			catalogue.presets.forEach( function ( preset, index ) {
				const extra = known ? -1 === PRIMARY_PRESETS.indexOf( preset.id ) : index >= PRIMARY_PRESETS.length;
				extras += extra ? 1 : 0;
				chips.appendChild(
					h(
						'button.msvcalc__chip' + ( extra ? '.msvcalc__chip--extra' : '' ),
						{
							type: 'button',
							title: t.quickStartHelp,
							onClick: function () {
								self.applyPreset( preset );
							},
						},
						[ icon( presetIcon( preset ), 16 ), h( 'span', { text: preset.label } ) ]
					)
				);
			} );
			if ( extras > 1 ) {
				chips.classList.add( 'is-collapsed' );
				const more = h( 'button.msvcalc__chip.msvcalc__chip--more', {
					type: 'button',
					'aria-expanded': 'false',
					text: sprintf( t.morePresets, extras ),
					onClick: function () {
						const open = chips.classList.toggle( 'is-collapsed' );
						more.textContent = open ? sprintf( t.morePresets, extras ) : t.fewerPresets;
						more.setAttribute( 'aria-expanded', open ? 'false' : 'true' );
					},
				} );
				chips.appendChild( more );
			}
			bar.appendChild( h( 'div.msvcalc__quick', {}, [ h( 'span.msvcalc__label', { text: t.quickStart, 'aria-hidden': 'true' } ), scroller( chips, 'msvcalc__presets-wrap' ) ] ) );
		}

		const tools = h( 'div.msvcalc__tools' );
		if ( s.unitToggle ) {
			const toggle = h( 'div.msvcalc__units', { role: 'radiogroup', 'aria-label': t.units } );
			[ 'm3', 'ft3' ].forEach( function ( unit ) {
				const button = h( 'button.msvcalc__unit', {
					type: 'button',
					role: 'radio',
					'aria-checked': self.state.units === unit ? 'true' : 'false',
					title: 'm3' === unit ? t.cubicMetres : t.cubicFeet,
					text: t[ unit ],
					onClick: function () {
						self.setUnits( unit );
					},
				} );
				button.dataset.unit = unit;
				toggle.appendChild( button );
			} );
			toggle.appendChild( h( 'span.msvcalc__units-glider', { 'aria-hidden': 'true' } ) );
			toggle.dataset.active = this.state.units;
			this.unitToggle = toggle;
			tools.appendChild( toggle );
		}
		tools.appendChild(
			h(
				'button.msvcalc__ghost',
				{
					type: 'button',
					onClick: function () {
						self.reset();
					},
				},
				[ icon( 'reset', 16 ), h( 'span', { text: t.reset } ) ]
			)
		);
		bar.appendChild( tools );
		return bar;
	};

	Calculator.prototype.buildSearch = function () {
		const self = this;
		const id = this.uid + '-search';
		this.searchInput = h( 'input.msvcalc__search-input', {
			type: 'search',
			id: id,
			placeholder: t.search,
			autocomplete: 'off',
			'aria-controls': this.uid + '-results',
			onInput: function ( event ) {
				self.state.query = event.target.value;
				self.renderSearch();
			},
		} );
		this.searchResults = h( 'div.msvcalc__results', { id: this.uid + '-results', hidden: true } );
		return h( 'div.msvcalc__search', {}, [
			h( 'label.msvcalc__sr', { for: id, text: t.searchLabel } ),
			icon( 'search', 18 ),
			this.searchInput,
			h(
				'button.msvcalc__search-clear',
				{
					type: 'button',
					'aria-label': t.close,
					onClick: function () {
						self.searchInput.value = '';
						self.state.query = '';
						self.renderSearch();
						self.searchInput.focus();
					},
				},
				[ icon( 'close', 16 ) ]
			),
			this.searchResults,
		] );
	};

	Calculator.prototype.buildTabs = function () {
		const self = this;
		this.tabs = {};
		const list = h( 'div.msvcalc__tabs', { role: 'tablist', 'aria-label': t.rooms } );
		const rooms = catalogue.rooms.slice();
		rooms.forEach( function ( room ) {
			const badge = h( 'span.msvcalc__badge', { 'aria-hidden': 'true' } );
			const tab = h(
				'button.msvcalc__tab',
				{
					type: 'button',
					role: 'tab',
					id: self.uid + '-tab-' + room.id,
					'aria-controls': self.uid + '-panel',
					'aria-selected': room.id === self.state.room ? 'true' : 'false',
					onClick: function () {
						self.selectRoom( room.id, false );
					},
				},
				[ h( 'span.msvcalc__tab-icon', {}, [ icon( room.icon, 20 ) ] ), h( 'span.msvcalc__tab-name', { text: room.name } ), badge ]
			);
			self.tabs[ room.id ] = { el: tab, badge: badge, iconWrap: tab.firstChild };
			list.appendChild( tab );
		} );
		this.tabList = list;
		const wrap = scroller( list, 'msvcalc__tabs-wrap' );
		this.tabScroller = wrap;
		if ( ! grouped ) {
			return wrap;
		}
		return h( 'div.msvcalc__nav', {}, [ this.buildGroups(), wrap ] );
	};

	/**
	 * The category bar: Home, Garage & garden, Business and so on. Picking one
	 * shows only that category's rooms.
	 *
	 * @return {HTMLElement} Bar.
	 */
	Calculator.prototype.buildGroups = function () {
		const self = this;
		this.groupButtons = {};
		const bar = h( 'div.msvcalc__groups', { role: 'group', 'aria-label': t.groups, style: '--n:' + groups.length } );
		groups.forEach( function ( group, index ) {
			let total = 0;
			roomsInGroup( group.id ).forEach( function ( room ) {
				total += ( itemsByRoom[ room.id ] || [] ).length;
			} );
			const badge = h( 'span.msvcalc__badge', { 'aria-hidden': 'true' } );
			const button = h(
				'button.msvcalc__group',
				{
					type: 'button',
					'aria-pressed': 'false',
					style: '--i:' + index,
					onClick: function () {
						self.selectGroup( group.id );
					},
				},
				[
					h( 'span.msvcalc__group-icon', {}, [ icon( group.icon, 22 ) ] ),
					h( 'span.msvcalc__group-text', {}, [
						h( 'span.msvcalc__group-name', { text: group.name } ),
						h( 'span.msvcalc__group-meta', { text: sprintf( t.groupItems, total ) } ),
					] ),
					badge,
				]
			);
			self.groupButtons[ group.id ] = { el: button, badge: badge };
			bar.appendChild( button );
		} );
		this.groupList = bar;
		this.groupScroller = scroller( bar, 'msvcalc__groups-wrap' );
		return this.groupScroller;
	};

	/**
	 * Shows a category's rooms and opens the first one (or the first that
	 * already has items).
	 *
	 * @param {string} groupId Group id.
	 */
	Calculator.prototype.selectGroup = function ( groupId ) {
		const self = this;
		const rooms = roomsInGroup( groupId );
		if ( ! rooms.length ) {
			return;
		}
		const current = roomsById[ this.state.room ];
		if ( current && current.group === groupId ) {
			return;
		}
		const withItems = rooms.find( function ( room ) {
			return ( itemsByRoom[ room.id ] || [] ).some( function ( item ) {
				return self.state.qty[ item.id ];
			} );
		} );
		this.selectRoom( ( withItems || rooms[ 0 ] ).id, false );
	};

	/**
	 * Marks the active category and hides the tabs of the others.
	 */
	Calculator.prototype.syncGroup = function () {
		if ( ! grouped ) {
			return;
		}
		const self = this;
		const room = roomsById[ this.state.room ];
		const active = room ? room.group : '';
		Object.keys( this.groupButtons ).forEach( function ( id ) {
			self.groupButtons[ id ].el.setAttribute( 'aria-pressed', id === active ? 'true' : 'false' );
		} );
		Object.keys( this.tabs ).forEach( function ( id ) {
			self.tabs[ id ].el.hidden = roomsById[ id ].group !== active;
		} );
		this.tabList.classList.toggle( 'is-single', 1 === roomsInGroup( active ).length );
		if ( active !== this.shownGroup ) {
			this.shownGroup = active;
			this.tabList.scrollLeft = 0;
			if ( this.groupButtons[ active ] ) {
				centerIn( this.groupList, this.groupButtons[ active ].el );
			}
		}
		if ( this.tabScroller ) {
			this.tabScroller.update();
		}
	};

	Calculator.prototype.selectRoom = function ( roomId, focus ) {
		const self = this;
		if ( ! roomsById[ roomId ] ) {
			return;
		}
		this.state.room = roomId;
		this.state.showAll = false;
		this.syncGroup();
		Object.keys( this.tabs ).forEach( function ( id ) {
			const active = id === roomId;
			self.tabs[ id ].el.setAttribute( 'aria-selected', active ? 'true' : 'false' );
		} );
		const tab = this.tabs[ roomId ].el;
		if ( focus ) {
			tab.focus();
		}
		centerIn( this.tabList, tab );
		this.renderPanel();
	};

	Calculator.prototype.renderPanel = function () {
		const self = this;
		const room = roomsById[ this.state.room ];
		if ( ! room ) {
			return;
		}
		const all = itemsByRoom[ room.id ] || [];
		const popular = all.filter( function ( item ) {
			return item.popular || self.state.qty[ item.id ];
		} );
		const showToggle = popular.length > 0 && popular.length < all.length;
		const visible = this.state.showAll || ! showToggle ? all : popular;

		this.panel.textContent = '';
		this.panel.setAttribute( 'aria-labelledby', this.uid + '-tab-' + room.id );
		this.roomTotal = h( 'span.msvcalc__room-total' );
		this.panel.appendChild(
			h( 'div.msvcalc__panel-head', {}, [
				h( 'h3.msvcalc__panel-title', {}, [ icon( room.icon, 22, this.iconGradUrl ), h( 'span', { text: room.name } ) ] ),
				this.roomTotal,
			] )
		);

		if ( 'boxes' === room.group && BOX_IDS.every( function ( id ) {
			return itemsById[ id ];
		} ) ) {
			this.panel.appendChild( this.buildBoxEstimator() );
		}

		const grid = h( 'ul.msvcalc__grid', { role: 'list' } );
		this.cards = {};
		visible.forEach( function ( item, index ) {
			grid.appendChild( self.card( item, index ) );
		} );
		this.panel.appendChild( grid );

		if ( showToggle ) {
			this.panel.appendChild(
				h(
					'button.msvcalc__more',
					{
						type: 'button',
						'aria-expanded': this.state.showAll ? 'true' : 'false',
						onClick: function () {
							self.state.showAll = ! self.state.showAll;
							self.renderPanel();
						},
					},
					[
						h( 'span', { text: this.state.showAll ? t.showLess : t.showAll + ' (' + all.length + ')' } ),
						icon( 'chevron', 16 ),
					]
				)
			);
		}
		this.updateRoomTotal();
	};

	/**
	 * An item card with a stepper.
	 *
	 * @param {Object} item  Catalogue item.
	 * @param {number} index Position, for the staggered entrance.
	 * @return {HTMLElement} Card.
	 */
	Calculator.prototype.card = function ( item, index ) {
		const self = this;
		const qty = this.state.qty[ item.id ] || 0;
		const volume = h( 'span.msvcalc__card-vol' );
		const input = h( 'input.msvcalc__qty', {
			type: 'number',
			inputmode: 'numeric',
			min: '0',
			max: String( MAX_QTY ),
			step: '1',
			value: String( qty ),
			'aria-label': sprintf( t.quantityOf, item.name ),
			onChange: function ( event ) {
				self.setQty( item.id, clampQty( event.target.value ) );
			},
			onFocus: function ( event ) {
				event.target.select();
			},
		} );
		const card = h(
			'li.msvcalc__card' + ( qty ? '.is-active' : '' ),
			{ style: '--i:' + Math.min( index, 24 ) },
			[
				h( 'span.msvcalc__card-icon', {}, [ icon( item.icon, 26 ) ] ),
				h( 'span.msvcalc__card-text', {}, [ h( 'span.msvcalc__card-name', { text: item.name } ), volume ] ),
				h( 'span.msvcalc__stepper', {}, [
					h(
						'button.msvcalc__step',
						{
							type: 'button',
							'aria-label': sprintf( t.remove, item.name ),
							onClick: function () {
								self.setQty( item.id, ( self.state.qty[ item.id ] || 0 ) - 1 );
							},
						},
						[ icon( 'minus', 16 ) ]
					),
					input,
					h(
						'button.msvcalc__step.msvcalc__step--add',
						{
							type: 'button',
							'aria-label': sprintf( t.add, item.name ),
							onClick: function () {
								self.setQty( item.id, ( self.state.qty[ item.id ] || 0 ) + 1 );
							},
						},
						[ icon( 'plus', 16 ) ]
					),
				] ),
			]
		);
		volume.textContent = this.volumeText( item.m3, true );
		this.cards[ item.id ] = { el: card, input: input, volume: volume };
		return card;
	};

	Calculator.prototype.renderSearch = function () {
		const self = this;
		const query = normalise( this.state.query );
		const box = this.searchResults;
		box.textContent = '';
		if ( query.length < 2 ) {
			box.hidden = true;
			this.searchCards = {};
			return;
		}
		const words = query.split( ' ' );
		const matches = catalogue.items.filter( function ( item ) {
			return words.every( function ( word ) {
				return -1 !== item.search.indexOf( word );
			} );
		} );
		box.hidden = false;
		box.appendChild( h( 'p.msvcalc__results-count', { text: matches.length ? sprintf( t.results, matches.length ) : t.noResults } ) );
		const list = h( 'ul.msvcalc__grid.msvcalc__grid--results', { role: 'list' } );
		const saved = this.cards;
		this.cards = {};
		matches.slice( 0, 40 ).forEach( function ( item, index ) {
			const card = self.card( item, index );
			const room = roomsById[ item.room ];
			if ( room ) {
				card.querySelector( '.msvcalc__card-text' ).appendChild( h( 'span.msvcalc__card-room', { text: room.name } ) );
			}
			list.appendChild( card );
		} );
		this.searchCards = this.cards;
		this.cards = saved;
		box.appendChild( list );
	};

	Calculator.prototype.buildCustom = function () {
		const self = this;
		const metric = 'm3' === this.state.units;
		const fields = {};
		const field = function ( key, label, attrs ) {
			const id = self.uid + '-c-' + key;
			fields[ key ] = h( 'input.msvcalc__input', Object.assign( { id: id, name: key }, attrs ) );
			return h( 'label.msvcalc__field.msvcalc__field--' + key, { for: id }, [ h( 'span', { text: label } ), fields[ key ] ] );
		};
		this.customUnit = h( 'span.msvcalc__dim-unit', { text: metric ? t.cm : t.in } );
		const error = h( 'p.msvcalc__error', { role: 'alert', hidden: true } );
		this.customList = h( 'ul.msvcalc__custom-list', { role: 'list' } );
		const iconHost = h( 'div.msvcalc__field.msvcalc__field--icon' );
		let chosenIcon = 'box';
		let pickedByHand = false;
		const picker = groupsAvailable()
			? iconPicker( iconHost, chosenIcon, function ( name ) {
				chosenIcon = name;
				pickedByHand = true;
			} )
			: null;
		if ( picker ) {
			iconHost.appendChild( h( 'span', { text: t.iconLabel } ) );
			iconHost.appendChild( picker.el );
		}
		// Suggest an icon from the name until the visitor picks one.
		const suggest = function ( text ) {
			if ( ! picker || pickedByHand ) {
				return;
			}
			const words = normalise( text ).split( ' ' ).filter( function ( word ) {
				return word.length > 2;
			} );
			let match = null;
			if ( words.length ) {
				match =
					catalogue.items.find( function ( item ) {
						return words.every( function ( word ) {
							return -1 !== item.search.indexOf( word );
						} );
					} ) ||
					catalogue.items.find( function ( item ) {
						return -1 !== item.search.indexOf( words[ 0 ] );
					} );
			}
			chosenIcon = match ? validIcon( match.icon ) : 'box';
			picker.set( chosenIcon );
		};

		const form = h(
			'form.msvcalc__custom-form',
			{
				novalidate: true,
				onSubmit: function ( event ) {
					event.preventDefault();
					const name = fields.name.value.trim().slice( 0, 60 );
					const l = parseFloat( fields.l.value );
					const w = parseFloat( fields.w.value );
					const hgt = parseFloat( fields.h.value );
					const qty = clampQty( fields.qty.value || 1 ) || 1;
					if ( ! name || ! ( l > 0 ) || ! ( w > 0 ) || ! ( hgt > 0 ) ) {
						error.textContent = t.customInvalid;
						error.hidden = false;
						return;
					}
					error.hidden = true;
					const cubic = l * w * hgt;
					const m3 = 'm3' === self.state.units ? cubic / 1e6 : cubic / 1728 / FT3;
					self.state.custom.push( {
						id: 'c' + Date.now(),
						name: name,
						dims: [ l, w, hgt ].map( function ( n ) {
							return fmt( n, n % 1 ? 1 : 0 );
						} ).join( ' × ' ) + ' ' + ( 'm3' === self.state.units ? t.cm : t.in ),
						m3: Math.min( 100, Math.round( m3 * 1000 ) / 1000 || 0.001 ),
						qty: qty,
						icon: chosenIcon,
					} );
					form.reset();
					fields.qty.value = '1';
					pickedByHand = false;
					chosenIcon = 'box';
					if ( picker ) {
						picker.close( false );
						picker.set( chosenIcon );
					}
					self.renderCustom();
					self.update();
					self.announce( sprintf( t.added, name ) );
					fields.name.focus();
				},
			},
			[
				iconHost,
				field( 'name', t.customName, {
					type: 'text',
					maxlength: '60',
					autocomplete: 'off',
					onInput: function ( event ) {
						suggest( event.target.value );
					},
				} ),
				h( 'div.msvcalc__dims', {}, [
					field( 'l', t.length, { type: 'number', min: '1', step: 'any', inputmode: 'decimal' } ),
					h( 'span.msvcalc__times', { 'aria-hidden': 'true', text: '×' } ),
					field( 'w', t.width, { type: 'number', min: '1', step: 'any', inputmode: 'decimal' } ),
					h( 'span.msvcalc__times', { 'aria-hidden': 'true', text: '×' } ),
					field( 'h', t.height, { type: 'number', min: '1', step: 'any', inputmode: 'decimal' } ),
					this.customUnit,
				] ),
				field( 'qty', t.quantity, { type: 'number', min: '1', max: String( MAX_QTY ), value: '1', inputmode: 'numeric' } ),
				h( 'button.msvcalc__button.msvcalc__button--primary.msvcalc__custom-add', { type: 'submit' }, [ icon( 'plus', 16 ), h( 'span', { text: t.addCustom } ) ] ),
				error,
			]
		);

		const details = h( 'details.msvcalc__custom', {}, [
			h( 'summary.msvcalc__custom-summary', {}, [
				h( 'span.msvcalc__custom-icon', {}, [ icon( 'ruler', 20 ) ] ),
				h( 'span', {}, [ h( 'strong', { text: t.customTitle } ), h( 'small', { text: t.customHelp } ) ] ),
				icon( 'chevron', 18 ),
			] ),
			h( 'div.msvcalc__custom-body', {}, [ form, this.customList ] ),
		] );
		this.renderCustom();
		return details;
	};

	Calculator.prototype.renderCustom = function () {
		const self = this;
		if ( ! this.customList ) {
			return;
		}
		this.customList.textContent = '';
		this.state.custom.forEach( function ( row ) {
			self.customList.appendChild(
				h( 'li.msvcalc__custom-row', {}, [
					h( 'span.msvcalc__custom-row-icon', {}, [ icon( validIcon( row.icon ), 20 ) ] ),
					h( 'span', { text: row.qty + ' × ' + row.name } ),
					h( 'span.msvcalc__muted', { text: self.volumeText( row.m3 * row.qty, true ) } ),
					h(
						'button.msvcalc__icon-button',
						{
							type: 'button',
							'aria-label': sprintf( t.remove, row.name ),
							onClick: function () {
								self.state.custom = self.state.custom.filter( function ( other ) {
									return other.id !== row.id;
								} );
								self.renderCustom();
								self.update();
							},
						},
						[ icon( 'trash', 16 ) ]
					),
				] )
			);
		} );
	};

	/* ---------------------------- Summary ----------------------------- */

	/**
	 * A capacity illustration: a vehicle, container or storage unit whose
	 * load area fills with the gradient.
	 *
	 * @param {string} kind Icon kind of the row.
	 * @return {Object} Parts.
	 */
	Calculator.prototype.illustration = function ( kind ) {
		const ns = 'http://www.w3.org/2000/svg';
		const svg = document.createElementNS( ns, 'svg' );
		svg.setAttribute( 'viewBox', '0 0 160 72' );
		svg.setAttribute( 'class', 'msvcalc__art msvcalc__art--' + kind );
		svg.setAttribute( 'aria-hidden', 'true' );
		svg.setAttribute( 'focusable', 'false' );

		const shapes = {
			van: { box: [ 14, 18, 92, 34, 6 ], cab: 'M106 26h18l14 14v12h-32z', wheels: [ 36, 116 ] },
			luton: { box: [ 8, 8, 104, 44, 4 ], cab: 'M112 24h16l16 16v12h-32z', wheels: [ 30, 124 ] },
			truck: { box: [ 6, 6, 110, 46, 4 ], cab: 'M116 22h18l18 18v12h-36z', wheels: [ 28, 98, 132 ] },
			lorry: { box: [ 4, 4, 118, 48, 3 ], cab: 'M122 20h16l18 18v14h-34z', wheels: [ 22, 44, 100, 138 ] },
			container: { box: [ 6, 10, 148, 46, 3 ], cab: '', wheels: [] },
			storage: { box: [ 30, 14, 100, 46, 4 ], cab: 'M24 16 80 2l56 14', wheels: [] },
		};
		const shape = shapes[ kind ] || shapes.truck;
		const box = shape.box;
		const clipId = this.uid + '-clip-' + Math.random().toString( 36 ).slice( 2, 8 );

		const make = function ( tag, attrs ) {
			const el = document.createElementNS( ns, tag );
			Object.keys( attrs ).forEach( function ( key ) {
				el.setAttribute( key, attrs[ key ] );
			} );
			svg.appendChild( el );
			return el;
		};

		const clip = document.createElementNS( ns, 'clipPath' );
		clip.setAttribute( 'id', clipId );
		const clipRect = document.createElementNS( ns, 'rect' );
		[ [ 'x', box[ 0 ] ], [ 'y', box[ 1 ] ], [ 'width', box[ 2 ] ], [ 'height', box[ 3 ] ], [ 'rx', box[ 4 ] ] ].forEach( function ( pair ) {
			clipRect.setAttribute( pair[ 0 ], String( pair[ 1 ] ) );
		} );
		clip.appendChild( clipRect );
		svg.appendChild( clip );

		make( 'rect', { x: box[ 0 ], y: box[ 1 ], width: box[ 2 ], height: box[ 3 ], rx: box[ 4 ], class: 'msvcalc__art-body' } );

		// The load: a stack of crates, revealed from the front of the load
		// area by a clip that grows with the fill.
		const loadClip = document.createElementNS( ns, 'clipPath' );
		loadClip.setAttribute( 'id', clipId + '-load' );
		const loadRect = document.createElementNS( ns, 'rect' );
		[ [ 'x', box[ 0 ] ], [ 'y', box[ 1 ] - 2 ], [ 'width', box[ 2 ] ], [ 'height', box[ 3 ] + 4 ] ].forEach( function ( pair ) {
			loadRect.setAttribute( pair[ 0 ], String( pair[ 1 ] ) );
		} );
		loadRect.style.transformOrigin = box[ 0 ] + 'px 0px';
		loadRect.style.transform = 'scaleX(0)';
		loadRect.setAttribute( 'class', 'msvcalc__art-fill' );
		loadClip.appendChild( loadRect );
		svg.appendChild( loadClip );

		const body = document.createElementNS( ns, 'g' );
		body.setAttribute( 'clip-path', 'url(#' + clipId + ')' );
		const load = document.createElementNS( ns, 'g' );
		load.setAttribute( 'clip-path', 'url(#' + clipId + '-load)' );
		load.setAttribute( 'class', 'msvcalc__art-load' );
		const fillRect = document.createElementNS( ns, 'rect' );
		[ [ 'x', box[ 0 ] ], [ 'y', box[ 1 ] ], [ 'width', box[ 2 ] ], [ 'height', box[ 3 ] ], [ 'fill', this.gradUrl ] ].forEach( function ( pair ) {
			fillRect.setAttribute( pair[ 0 ], String( pair[ 1 ] ) );
		} );
		load.appendChild( fillRect );
		if ( 'container' !== kind ) {
			// Crate seams: rows of boxes, offset like a real stack.
			const rows = Math.max( 2, Math.round( box[ 3 ] / 14 ) );
			const rowH = box[ 3 ] / rows;
			let d = '';
			for ( let r = 1; r < rows; r++ ) {
				d += 'M' + box[ 0 ] + ' ' + ( box[ 1 ] + r * rowH ).toFixed( 1 ) + 'h' + box[ 2 ];
			}
			for ( let r = 0; r < rows; r++ ) {
				const y = box[ 1 ] + r * rowH;
				for ( let x = box[ 0 ] + ( r % 2 ? 9 : 16 ); x < box[ 0 ] + box[ 2 ]; x += 16 ) {
					d += 'M' + x + ' ' + y.toFixed( 1 ) + 'v' + rowH.toFixed( 1 );
				}
			}
			const seams = document.createElementNS( ns, 'path' );
			seams.setAttribute( 'd', d );
			seams.setAttribute( 'class', 'msvcalc__art-crates' );
			load.appendChild( seams );
		}
		body.appendChild( load );

		// A bright edge that rides along the front of the load.
		const edge = document.createElementNS( ns, 'g' );
		edge.setAttribute( 'class', 'msvcalc__art-edge' );
		edge.style.transform = 'translateX(0px)';
		const edgeLine = document.createElementNS( ns, 'rect' );
		[ [ 'x', box[ 0 ] - 1 ], [ 'y', box[ 1 ] ], [ 'width', 2 ], [ 'height', box[ 3 ] ] ].forEach( function ( pair ) {
			edgeLine.setAttribute( pair[ 0 ], String( pair[ 1 ] ) );
		} );
		edge.appendChild( edgeLine );
		// A crate that drops in when items are added.
		const drop = document.createElementNS( ns, 'rect' );
		[ [ 'x', box[ 0 ] - 9 ], [ 'y', box[ 1 ] + box[ 3 ] - 12 ], [ 'width', 10 ], [ 'height', 10 ], [ 'rx', 1.5 ] ].forEach( function ( pair ) {
			drop.setAttribute( pair[ 0 ], String( pair[ 1 ] ) );
		} );
		drop.setAttribute( 'class', 'msvcalc__art-drop' );
		edge.appendChild( drop );
		body.appendChild( edge );
		svg.appendChild( body );

		// Existing callers set fill.style.transform = scaleX(n); mirror that
		// onto the clip and the edge.
		const fill = {
			style: {},
		};
		Object.defineProperty( fill.style, 'transform', {
			set: function ( value ) {
				const m = /scaleX\(([\d.]+)\)/.exec( String( value ) );
				const share = m ? Math.min( 1, Math.max( 0, parseFloat( m[ 1 ] ) ) ) : 0;
				const previous = parseFloat( loadRect.dataset.share || '0' );
				loadRect.dataset.share = String( share );
				loadRect.style.transform = 'scaleX(' + share.toFixed( 3 ) + ')';
				edge.style.transform = 'translateX(' + ( share * box[ 2 ] ).toFixed( 2 ) + 'px)';
				edge.classList.toggle( 'is-hidden', share <= 0 || share >= 1 );
				if ( share > previous + 0.0005 && ! reduceMotion && s.animations ) {
					svg.classList.remove( 'is-loading' );
					void svg.getBoundingClientRect();
					svg.classList.add( 'is-loading' );
				}
			},
			get: function () {
				return loadRect.style.transform;
			},
		} );

		if ( 'container' === kind ) {
			for ( let x = box[ 0 ] + 18; x < box[ 0 ] + box[ 2 ]; x += 18 ) {
				make( 'path', { d: 'M' + x + ' ' + ( box[ 1 ] + 4 ) + 'v' + ( box[ 3 ] - 8 ), class: 'msvcalc__art-line' } );
			}
		}
		if ( 'storage' === kind ) {
			for ( let y = box[ 1 ] + 9; y < box[ 1 ] + box[ 3 ]; y += 9 ) {
				make( 'path', { d: 'M' + ( box[ 0 ] + 4 ) + ' ' + y + 'h' + ( box[ 2 ] - 8 ), class: 'msvcalc__art-line' } );
			}
		}
		make( 'rect', { x: box[ 0 ], y: box[ 1 ], width: box[ 2 ], height: box[ 3 ], rx: box[ 4 ], class: 'msvcalc__art-outline' } );
		if ( shape.cab ) {
			make( 'path', { d: shape.cab, class: 'storage' === kind ? 'msvcalc__art-roof' : 'msvcalc__art-cab' } );
		}
		shape.wheels.forEach( function ( x ) {
			make( 'circle', { cx: x, cy: 58, r: 8, class: 'msvcalc__art-wheel' } );
			make( 'circle', { cx: x, cy: 58, r: 3, class: 'msvcalc__art-hub' } );
			make( 'path', { d: 'M' + x + ' 52.5v3M' + x + ' 60.5v3', class: 'msvcalc__art-spoke' } );
		} );
		if ( shape.wheels.length ) {
			make( 'path', { d: 'M2 67h156', class: 'msvcalc__art-road' } );
		}
		return { svg: svg, fill: fill, width: box[ 2 ] };
	};

	Calculator.prototype.fitCard = function ( key, label ) {
		const art = this.illustration( 'vehicle' === key ? 'truck' : key );
		const name = h( 'strong.msvcalc__fit-name' );
		const note = h( 'span.msvcalc__fit-note' );
		const bar = h( 'span.msvcalc__bar-fill' );
		const pct = h( 'span.msvcalc__fit-pct' );
		const card = h( 'div.msvcalc__fit.msvcalc__fit--' + key, {}, [
			h( 'div.msvcalc__fit-art', {}, [ art.svg ] ),
			h( 'div.msvcalc__fit-text', {}, [
				h( 'span.msvcalc__fit-label', { text: label } ),
				name,
				note,
				h( 'span.msvcalc__bar', { 'aria-hidden': 'true' }, [ bar ] ),
				pct,
			] ),
		] );
		return { el: card, art: art, artWrap: card.firstChild, name: name, note: note, bar: bar, pct: pct, kind: '' };
	};

	Calculator.prototype.buildSummary = function () {
		const self = this;
		const aside = h( 'aside.msvcalc__summary', { 'aria-label': t.summary, id: this.uid + '-summary' } );
		const sheetClose = h(
			'button.msvcalc__sheet-close',
			{
				type: 'button',
				'aria-label': t.close,
				onClick: function () {
					self.toggleSheet( false );
				},
			},
			[ icon( 'close', 20 ) ]
		);

		this.totalNumber = h( 'span.msvcalc__total-number', { text: '0' } );
		this.totalUnit = h( 'span.msvcalc__total-unit' );
		this.totalAlt = h( 'span.msvcalc__total-alt' );
		this.ring = this.buildRing();
		this.ringWrap = this.ring.svg;

		const hero = h( 'div.msvcalc__hero', {}, [
			this.ring.svg,
			h( 'div.msvcalc__hero-text', {}, [
				h( 'span.msvcalc__eyebrow', { text: t.totalVolume } ),
				h( 'span.msvcalc__total', {}, [ this.totalNumber, this.totalUnit ] ),
				this.totalAlt,
			] ),
		] );

		this.stats = {};
		const stats = h( 'div.msvcalc__stats' );
		const stat = function ( key, iconName, label ) {
			const value = h( 'strong', { text: '0' } );
			self.stats[ key ] = value;
			stats.appendChild( h( 'div.msvcalc__stat', {}, [ icon( iconName, 18 ), h( 'span', { text: label } ), value ] ) );
		};
		stat( 'count', 'list', t.items );
		if ( s.boxes ) {
			stat( 'boxes', 'box', t.boxes );
		}
		if ( s.weight ) {
			stat( 'weight', 'weight', t.weight );
		}

		this.fits = {};
		const fits = h( 'div.msvcalc__fits' );
		if ( s.vehicle && tables.vehicles.length ) {
			this.fits.vehicle = this.fitCard( 'vehicle', t.vehicle );
			fits.appendChild( this.fits.vehicle.el );
		}
		if ( s.container && tables.containers.length ) {
			this.fits.container = this.fitCard( 'container', t.container );
			fits.appendChild( this.fits.container.el );
		}
		if ( s.storage && tables.storage.length ) {
			this.fits.storage = this.fitCard( 'storage', t.storage );
			fits.appendChild( this.fits.storage.el );
		}

		this.listBox = h( 'ul.msvcalc__list', { role: 'list' } );
		this.emptyNote = h( 'p.msvcalc__empty', {}, [ icon( 'sparkle', 18 ), h( 'span', { text: t.empty } ) ] );
		const list = h( 'details.msvcalc__yourlist', {}, [
			h( 'summary', {}, [ h( 'span', { text: t.yourList } ), ( this.listCount = h( 'span.msvcalc__pill', { text: '0' } ) ), icon( 'chevron', 16 ) ] ),
			this.listBox,
		] );

		const actions = h( 'div.msvcalc__actions' );
		if ( this.quoteEnabled ) {
			this.ctaButton = h(
				'button.msvcalc__button.msvcalc__button--primary',
				{
					type: 'button',
					onClick: function () {
						self.showQuote();
					},
				},
				[ h( 'span', { text: s.cta } ), icon( 'arrow', 18 ) ]
			);
			actions.appendChild( this.ctaButton );
		}
		this.shareButton = h(
			'button.msvcalc__button.msvcalc__button--soft',
			{
				type: 'button',
				onClick: function () {
					self.share();
				},
			},
			[ icon( 'share', 18 ), h( 'span', { text: t.share } ) ]
		);
		actions.appendChild( this.shareButton );
		if ( s.print ) {
			actions.appendChild(
				h(
					'button.msvcalc__button.msvcalc__button--primary',
					{
						type: 'button',
						onClick: function () {
							self.print();
						},
					},
					[ icon( 'print', 18 ), h( 'span', { text: t.print } ) ]
				)
			);
		}

		this.marginNote = h( 'p.msvcalc__note' );

		aside.appendChild(
			h( 'div.msvcalc__summary-inner', {}, [
				h( 'div.msvcalc__summary-head', {}, [ h( 'h3', { text: t.summary } ), sheetClose ] ),
				hero,
				stats,
				fits,
				this.emptyNote,
				list,
				actions,
				this.marginNote,
			] )
		);
		return aside;
	};

	Calculator.prototype.buildRing = function () {
		const ns = 'http://www.w3.org/2000/svg';
		const svg = document.createElementNS( ns, 'svg' );
		svg.setAttribute( 'viewBox', '0 0 100 100' );
		svg.setAttribute( 'class', 'msvcalc__ring' );
		svg.setAttribute( 'aria-hidden', 'true' );
		const track = document.createElementNS( ns, 'circle' );
		track.setAttribute( 'cx', '50' );
		track.setAttribute( 'cy', '50' );
		track.setAttribute( 'r', '42' );
		track.setAttribute( 'class', 'msvcalc__ring-track' );
		const bar = document.createElementNS( ns, 'circle' );
		bar.setAttribute( 'cx', '50' );
		bar.setAttribute( 'cy', '50' );
		bar.setAttribute( 'r', '42' );
		bar.setAttribute( 'class', 'msvcalc__ring-bar' );
		bar.setAttribute( 'stroke', this.gradUrl );
		bar.setAttribute( 'pathLength', '100' );
		bar.style.strokeDasharray = '0 100';
		const cube = icon( 'cube', 30, this.iconGradUrl );
		cube.setAttribute( 'x', '35' );
		cube.setAttribute( 'y', '35' );
		svg.appendChild( track );
		svg.appendChild( bar );
		svg.appendChild( cube );
		return { svg: svg, bar: bar };
	};

	Calculator.prototype.buildDock = function () {
		const self = this;
		this.dockTotal = h( 'strong.msvcalc__dock-total', { text: '0' } );
		this.dockCount = h( 'span.msvcalc__dock-count' );
		this.dockButton = h(
			'button.msvcalc__button.msvcalc__button--primary',
			{
				type: 'button',
				'aria-controls': this.uid + '-summary',
				'aria-expanded': 'false',
				onClick: function () {
					self.toggleSheet( true );
				},
			},
			[ h( 'span', { text: t.viewSummary } ), icon( 'arrow', 16 ) ]
		);
		this.backdrop = h( 'div.msvcalc__backdrop', {
			'aria-hidden': 'true',
			onClick: function () {
				self.toggleSheet( false );
			},
		} );
		return h( 'div.msvcalc__dock-wrap', {}, [
			this.backdrop,
			h( 'div.msvcalc__dock', {}, [
				h( 'span.msvcalc__dock-icon', {}, [ icon( 'cube', 22, this.iconGradUrl ) ] ),
				h( 'span.msvcalc__dock-text', {}, [ this.dockTotal, this.dockCount ] ),
				this.dockButton,
			] ),
		] );
	};

	Calculator.prototype.toggleSheet = function ( open ) {
		this.root.classList.toggle( 'msvcalc--sheet-open', open );
		this.dockButton.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		document.documentElement.classList.toggle( 'msvcalc-lock', open );
		if ( open ) {
			const close = this.summary.querySelector( '.msvcalc__sheet-close' );
			if ( close ) {
				close.focus();
			}
		} else {
			this.dockButton.focus();
		}
	};

	/* ---------------------------- Update ------------------------------ */

	Calculator.prototype.setQty = function ( id, qty ) {
		const value = clampQty( qty );
		const before = this.state.qty[ id ] || 0;
		if ( value ) {
			this.state.qty[ id ] = value;
		} else {
			delete this.state.qty[ id ];
		}
		[ this.cards, this.searchCards || {} ].forEach( function ( set ) {
			const card = set[ id ];
			if ( ! card ) {
				return;
			}
			card.input.value = String( value );
			card.el.classList.toggle( 'is-active', value > 0 );
			if ( value !== before && ! reduceMotion ) {
				card.el.dataset.dir = value > before ? 'up' : 'down';
				card.el.classList.remove( 'is-bump' );
				// Restart the bump animation.
				void card.el.offsetWidth;
				card.el.classList.add( 'is-bump' );
			}
		} );
		this.update();
	};

	Calculator.prototype.setUnits = function ( unit ) {
		if ( unit === this.state.units ) {
			return;
		}
		this.state.units = unit;
		if ( this.unitToggle ) {
			Array.prototype.forEach.call( this.unitToggle.querySelectorAll( '.msvcalc__unit' ), function ( button ) {
				button.setAttribute( 'aria-checked', button.dataset.unit === unit ? 'true' : 'false' );
			} );
			this.unitToggle.dataset.active = unit;
		}
		if ( this.customUnit ) {
			this.customUnit.textContent = 'm3' === unit ? t.cm : t.in;
		}
		const self = this;
		[ this.cards, this.searchCards || {} ].forEach( function ( set ) {
			Object.keys( set ).forEach( function ( id ) {
				set[ id ].volume.textContent = self.volumeText( itemsById[ id ].m3, true );
			} );
		} );
		this.renderCustom();
		this.displayed = 0;
		this.update( true );
	};

	Calculator.prototype.applyPreset = function ( preset ) {
		if ( Object.keys( this.state.qty ).length && ! window.confirm( t.presetConfirm ) ) {
			return;
		}
		const qty = {};
		Object.keys( preset.items ).forEach( function ( id ) {
			if ( itemsById[ id ] ) {
				qty[ id ] = clampQty( preset.items[ id ] );
			}
		} );
		this.state.qty = qty;
		this.refreshCards();
		this.update();
		this.announce( t.presetApplied );
		this.flash();
	};

	Calculator.prototype.reset = function () {
		const self = this;
		if ( ! Object.keys( this.state.qty ).length && ! this.state.custom.length ) {
			return;
		}
		const before = { qty: Object.assign( {}, this.state.qty ), custom: this.state.custom.slice() };
		this.toast( t.cleared, t.undo, function () {
			self.state.qty = before.qty;
			self.state.custom = before.custom;
			self.renderCustom();
			self.refreshCards();
			self.update();
			self.announce( t.restored );
		} );
		this.state.qty = {};
		this.state.custom = [];
		this.renderCustom();
		this.refreshCards();
		this.update();
		if ( 'quote' === this.state.view ) {
			this.showItems();
		}
	};

	Calculator.prototype.refreshCards = function () {
		this.renderPanel();
		if ( this.state.query ) {
			this.renderSearch();
		}
	};

	Calculator.prototype.flash = function () {
		if ( reduceMotion || ! s.animations ) {
			return;
		}
		const el = this.summary;
		el.classList.remove( 'is-flash' );
		void el.offsetWidth;
		el.classList.add( 'is-flash' );
	};

	Calculator.prototype.announce = function ( text ) {
		const live = this.live;
		live.textContent = '';
		window.setTimeout( function () {
			live.textContent = text;
		}, 60 );
	};

	Calculator.prototype.updateRoomTotal = function () {
		if ( ! this.roomTotal ) {
			return;
		}
		const totals = this.lastTotals || this.totals();
		const value = totals.rooms[ this.state.room ] || 0;
		this.roomTotal.textContent = value ? this.volumeText( value, true ) : '';
	};

	Calculator.prototype.update = function ( instant ) {
		const self = this;
		const totals = this.totals();
		this.lastTotals = totals;
		const unitLabel = t[ this.state.units ];
		const metric = 'm3' === this.state.units;
		const target = metric ? totals.load : totals.load * FT3;
		const decimals = metric ? 1 : 0;

		this.totalUnit.textContent = unitLabel;
		this.totalAlt.textContent = totals.load
			? ( metric ? fmt( totals.load * FT3, 0 ) + ' ' + t.ft3 : fmt( totals.load, 1 ) + ' ' + t.m3 )
			: '';
		this.tween( this.displayed, target, decimals, instant );
		this.displayed = target;

		// Ring: share of the largest vehicle, capped at 100 %.
		const largest = tables.vehicles.length ? tables.vehicles[ tables.vehicles.length - 1 ].m3 : 60;
		const share = Math.min( 100, ( totals.load / largest ) * 100 );
		this.ring.bar.style.strokeDasharray = share.toFixed( 1 ) + ' 100';

		this.countUp( this.stats.count, totals.count, function ( v ) {
			return fmt( v, 0 );
		} );
		if ( this.stats.boxes ) {
			this.countUp( this.stats.boxes, totals.boxes, function ( v ) {
				return fmt( v, 0 );
			} );
		}
		if ( this.stats.weight ) {
			const weight = metric ? totals.kg : totals.kg * LB;
			const unit = metric ? t.kg : t.lb;
			this.countUp( this.stats.weight, weight, function ( v ) {
				return fmt( v, 0 ) + ' ' + unit;
			}, unit );
		}
		if ( this.ringWrap && ! reduceMotion && s.animations && totals.load !== this.lastPulse ) {
			this.lastPulse = totals.load;
			this.ringWrap.classList.remove( 'is-pulse' );
			void this.ringWrap.offsetWidth;
			this.ringWrap.classList.add( 'is-pulse' );
		}

		Object.keys( this.fits ).forEach( function ( key ) {
			self.renderFit( self.fits[ key ], totals[ key ] );
		} );

		this.marginNote.textContent = ( s.margin ? sprintf( t.margin, s.margin ) + ' ' : '' ) + t.estimate;
		this.emptyNote.hidden = totals.count > 0;
		this.root.classList.toggle( 'msvcalc--has-items', totals.count > 0 );
		if ( this.ctaButton ) {
			this.ctaButton.disabled = totals.count < 1;
		}

		// Tab badges.
		Object.keys( this.tabs ).forEach( function ( id ) {
			let count = 0;
			( itemsByRoom[ id ] || [] ).forEach( function ( item ) {
				count += self.state.qty[ item.id ] || 0;
			} );
			const badge = self.tabs[ id ].badge;
			const text = count ? String( count ) : '';
			if ( badge.textContent !== text ) {
				badge.textContent = text;
				self.tabs[ id ].el.classList.toggle( 'has-items', count > 0 );
			}
		} );

		// Category badges.
		Object.keys( this.groupButtons || {} ).forEach( function ( id ) {
			let count = 0;
			roomsInGroup( id ).forEach( function ( room ) {
				( itemsByRoom[ room.id ] || [] ).forEach( function ( item ) {
					count += self.state.qty[ item.id ] || 0;
				} );
			} );
			const button = self.groupButtons[ id ];
			const text = count ? String( count ) : '';
			if ( button.badge.textContent !== text ) {
				button.badge.textContent = text;
				button.el.classList.toggle( 'has-items', count > 0 );
			}
		} );

		this.renderList( totals );
		this.updateRoomTotal();

		this.dockTotal.textContent = this.volumeText( totals.load, true );
		this.dockCount.textContent = fmt( totals.count, 0 ) + ' ' + t.items.toLowerCase();

		this.save();

		window.clearTimeout( this.announceTimer );
		this.announceTimer = window.setTimeout( function () {
			if ( ! instant ) {
				self.announce( t.totalVolume + ': ' + self.volumeText( totals.load, true ) );
			}
		}, 700 );
	};

	Calculator.prototype.renderFit = function ( card, result ) {
		if ( ! result ) {
			card.el.classList.add( 'is-empty' );
			card.name.textContent = '-';
			card.note.textContent = '';
			card.pct.textContent = '';
			card.bar.style.width = '0%';
			card.art.fill.style.transform = 'scaleX(0)';
			return;
		}
		card.el.classList.remove( 'is-empty' );
		const kind = result.row.icon || 'truck';
		if ( card.kind !== kind ) {
			// Swap the illustration when the vehicle type changes.
			const art = this.illustration( kind );
			card.artWrap.textContent = '';
			card.artWrap.appendChild( art.svg );
			card.art = art;
			card.kind = kind;
			if ( ! reduceMotion ) {
				card.artWrap.classList.remove( 'is-swap' );
				void card.artWrap.offsetWidth;
				card.artWrap.classList.add( 'is-swap' );
			}
		}
		const share = Math.min( 100, Math.max( 0, result.fill ) );
		card.name.textContent = result.label;
		card.note.textContent = result.count > 1 ? result.note : result.row.note || '';
		this.countUp( card.pct, share, function ( v ) {
			return sprintf( t.fill, Math.round( v ) );
		} );
		const art = card.art;
		window.requestAnimationFrame( function () {
			card.bar.style.width = share + '%';
			art.fill.style.transform = 'scaleX(' + ( share / 100 ).toFixed( 3 ) + ')';
		} );
	};

	/**
	 * Animates a number in an element from its last value to `to`.
	 *
	 * @param {HTMLElement} el     Target.
	 * @param {number}      to     New value.
	 * @param {Function}    format Turns a number into the text to show.
	 * @param {string}      key    Restarts from zero when this changes (units).
	 */
	Calculator.prototype.countUp = function ( el, to, format, key ) {
		const from = 'number' === typeof el._msvcalcValue && el._msvcalcKey === ( key || '' ) ? el._msvcalcValue : to;
		el._msvcalcValue = to;
		el._msvcalcKey = key || '';
		window.cancelAnimationFrame( el._msvcalcFrame );
		if ( reduceMotion || ! s.animations || from === to ) {
			el.textContent = format( to );
			return;
		}
		el.classList.remove( 'is-tick' );
		void el.offsetWidth;
		el.classList.add( 'is-tick' );
		const start = performance.now();
		const step = function ( now ) {
			const p = Math.min( 1, ( now - start ) / 560 );
			const eased = 1 - Math.pow( 1 - p, 3 );
			el.textContent = format( from + ( to - from ) * eased );
			if ( p < 1 ) {
				el._msvcalcFrame = window.requestAnimationFrame( step );
			}
		};
		el._msvcalcFrame = window.requestAnimationFrame( step );
	};

	Calculator.prototype.tween = function ( from, to, decimals, instant ) {
		const el = this.totalNumber;
		if ( instant || reduceMotion || ! s.animations || from === to ) {
			el.textContent = fmt( to, decimals );
			return;
		}
		const start = performance.now();
		const duration = 520;
		window.cancelAnimationFrame( this.tweenFrame );
		const self = this;
		const step = function ( now ) {
			const p = Math.min( 1, ( now - start ) / duration );
			const eased = 1 - Math.pow( 1 - p, 3 );
			el.textContent = fmt( from + ( to - from ) * eased, decimals );
			if ( p < 1 ) {
				self.tweenFrame = window.requestAnimationFrame( step );
			}
		};
		this.tweenFrame = window.requestAnimationFrame( step );
	};

	Calculator.prototype.lines = function () {
		const self = this;
		const lines = [];
		catalogue.rooms.forEach( function ( room ) {
			( itemsByRoom[ room.id ] || [] ).forEach( function ( item ) {
				const qty = self.state.qty[ item.id ];
				if ( qty ) {
					lines.push( { id: item.id, room: room.name, name: item.name, qty: qty, m3: item.m3 * qty } );
				}
			} );
		} );
		this.state.custom.forEach( function ( row ) {
			lines.push( { custom: row.id, room: t.customRoom, name: row.name, dims: row.dims || '', qty: row.qty, m3: row.m3 * row.qty } );
		} );
		return lines;
	};

	Calculator.prototype.renderList = function ( totals ) {
		const self = this;
		const lines = this.lines();
		this.listBox.textContent = '';
		this.listCount.textContent = fmt( totals.count, 0 );
		let room = null;
		lines.forEach( function ( line ) {
			if ( line.room !== room ) {
				room = line.room;
				self.listBox.appendChild( h( 'li.msvcalc__list-room', { text: room } ) );
			}
			self.listBox.appendChild(
				h( 'li.msvcalc__list-row', {}, [
					h( 'span.msvcalc__list-qty', { text: line.qty + '×' } ),
					h( 'span.msvcalc__list-name', { text: line.name } ),
					h( 'span.msvcalc__list-vol', { text: self.volumeText( line.m3, false ) } ),
					h(
						'button.msvcalc__icon-button',
						{
							type: 'button',
							'aria-label': sprintf( t.remove, line.name ),
							onClick: function () {
								if ( line.custom ) {
									self.state.custom = self.state.custom
										.map( function ( row ) {
											return row.id === line.custom ? Object.assign( {}, row, { qty: row.qty - 1 } ) : row;
										} )
										.filter( function ( row ) {
											return row.qty > 0;
										} );
									self.renderCustom();
									self.update();
								} else {
									self.setQty( line.id, line.qty - 1 );
								}
							},
						},
						[ icon( 'minus', 16 ) ]
					),
				] )
			);
		} );
	};

	/* ---------------------------- Quote ------------------------------- */

	Calculator.prototype.showQuote = function () {
		if ( ! this.quoteEnabled ) {
			return;
		}
		if ( this.root.classList.contains( 'msvcalc--sheet-open' ) ) {
			this.toggleSheet( false );
		}
		if ( ! this.quoteForm ) {
			this.buildQuote();
		}
		this.renderForgotten();
		this.state.view = 'quote';
		this.itemsView.hidden = true;
		this.quoteView.hidden = false;
		this.root.classList.add( 'msvcalc--quote' );
		this.quoteView.classList.remove( 'is-in' );
		void this.quoteView.offsetWidth;
		this.quoteView.classList.add( 'is-in' );
		const first = this.quoteView.querySelector( 'input, select, textarea' );
		this.root.scrollIntoView( { behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' } );
		if ( first ) {
			window.setTimeout( function () {
				first.focus( { preventScroll: true } );
			}, 250 );
		}
	};

	Calculator.prototype.showItems = function () {
		this.state.view = 'items';
		this.quoteView.hidden = true;
		this.itemsView.hidden = false;
		this.root.classList.remove( 'msvcalc--quote' );
	};

	Calculator.prototype.buildQuote = function () {
		const self = this;
		const f = s.fields;
		const inputs = {};
		const errors = {};

		const row = function ( key, label, control, rule, help ) {
			const id = self.uid + '-q-' + key;
			control.id = id;
			control.name = key;
			if ( 'required' === rule ) {
				control.required = true;
				control.setAttribute( 'aria-required', 'true' );
			}
			const error = h( 'span.msvcalc__field-error', { id: id + '-error', role: 'alert' } );
			control.setAttribute( 'aria-describedby', id + '-error' );
			inputs[ key ] = control;
			errors[ key ] = error;
			return h( 'div.msvcalc__field.msvcalc__field--' + key, {}, [
				h( 'label', { for: id }, [ h( 'span', { text: label } ), 'optional' === rule ? h( 'small', { text: ' ' + t.optional } ) : null ] ),
				// Selects get our own chevron, so a theme's select arrow cannot double up.
				'SELECT' === control.tagName ? h( 'span.msvcalc__select', {}, [ control, icon( 'chevron', 18 ) ] ) : control,
				help ? h( 'small.msvcalc__help', { text: help } ) : null,
				error,
			] );
		};

		const fieldsWrap = h( 'div.msvcalc__form-grid' );
		fieldsWrap.appendChild( row( 'name', t.name, h( 'input.msvcalc__input', { type: 'text', autocomplete: 'name', maxlength: '80' } ), 'required' ) );
		fieldsWrap.appendChild( row( 'email', t.email, h( 'input.msvcalc__input', { type: 'email', autocomplete: 'email', maxlength: '100' } ), 'required' ) );
		if ( 'hidden' !== f.phone ) {
			fieldsWrap.appendChild( row( 'phone', t.phone, h( 'input.msvcalc__input', { type: 'tel', autocomplete: 'tel', maxlength: '30' } ), f.phone ) );
		}
		if ( 'hidden' !== f.service ) {
			const select = h( 'select.msvcalc__input', {}, [ h( 'option', { value: '', text: t.choose } ) ] );
			Object.keys( s.services ).forEach( function ( key ) {
				select.appendChild( h( 'option', { value: key, text: s.services[ key ] } ) );
			} );
			// The "Anything from these areas?" reminder follows the service.
			self.quoteService = select;
			select.addEventListener( 'change', function () {
				self.renderForgotten();
			} );
			fieldsWrap.appendChild( row( 'service', t.service, select, f.service ) );
		}
		if ( 'hidden' !== f.addresses ) {
			fieldsWrap.appendChild( row( 'from', t.from, h( 'input.msvcalc__input', { type: 'text', autocomplete: 'off', maxlength: '200', placeholder: t.fromPlaceholder } ), f.addresses ) );
			fieldsWrap.appendChild( row( 'to', t.to, h( 'input.msvcalc__input', { type: 'text', autocomplete: 'off', maxlength: '200', placeholder: t.fromPlaceholder } ), f.addresses ) );
		}
		let dateRow = null;
		if ( 'hidden' !== f.date ) {
			dateRow = row( 'date', t.date, h( 'input.msvcalc__input', { type: 'date', min: s.today } ), f.date );
		}
		if ( 'hidden' !== f.access ) {
			const floorSelect = function () {
				const select = h( 'select.msvcalc__input', {}, [ h( 'option', { value: '', text: t.choose } ) ] );
				Object.keys( s.floors ).forEach( function ( key ) {
					select.appendChild( h( 'option', { value: key, text: s.floors[ key ] } ) );
				} );
				return select;
			};
			[ [ 'from', t.fromFloor ], [ 'to', t.toFloor ] ].forEach( function ( pair ) {
				const end = pair[ 0 ];
				const field = row( end + '_floor', pair[ 1 ], floorSelect(), 'required' === f.access && 'to' === end ? 'optional' : f.access );
				const liftId = self.uid + '-q-' + end + '-lift';
				inputs[ end + '_lift' ] = h( 'input', { type: 'checkbox', id: liftId, name: end + '_lift' } );
				field.appendChild(
					h( 'label.msvcalc__toggle-check', { for: liftId }, [
						inputs[ end + '_lift' ],
						h( 'span.msvcalc__check', { 'aria-hidden': 'true' }, [ icon( 'check', 14 ) ] ),
						h( 'span', { text: t.lift } ),
					] )
				);
				fieldsWrap.appendChild( field );
			} );
			const parking = h( 'select.msvcalc__input', {}, [ h( 'option', { value: '', text: t.choose } ) ] );
			Object.keys( s.parking ).forEach( function ( key ) {
				parking.appendChild( h( 'option', { value: key, text: s.parking[ key ] } ) );
			} );
			// Floors sit under their addresses; the date pairs with parking.
			if ( dateRow ) {
				fieldsWrap.appendChild( dateRow );
				dateRow = null;
			}
			const parkingRow = row( 'parking', t.parking, parking, 'optional' );
			if ( ! f.date || 'hidden' === f.date ) {
				parkingRow.classList.add( 'msvcalc__field--wide' );
			}
			fieldsWrap.appendChild( parkingRow );
		}
		if ( dateRow ) {
			fieldsWrap.appendChild( dateRow );
		}
		inputs.extras = [];
		if ( 'hidden' !== f.extras && Object.keys( s.extras || {} ).length ) {
			const chips = h( 'div.msvcalc__extras' );
			Object.keys( s.extras ).forEach( function ( key ) {
				const id = self.uid + '-q-extra-' + key;
				const box = h( 'input', { type: 'checkbox', id: id, name: 'extras[]', value: key } );
				inputs.extras.push( box );
				chips.appendChild(
					h( 'label.msvcalc__extra', { for: id }, [
						box,
						h( 'span.msvcalc__check', { 'aria-hidden': 'true' }, [ icon( 'check', 14 ) ] ),
						h( 'span', { text: s.extras[ key ] } ),
					] )
				);
			} );
			fieldsWrap.appendChild(
				h( 'fieldset.msvcalc__field.msvcalc__field--wide.msvcalc__fieldset', {}, [
					h( 'legend', {}, [ h( 'span', { text: t.extras } ), h( 'small', { text: ' ' + t.optional } ) ] ),
					chips,
				] )
			);
		}
		const notes = row( 'notes', t.notes, h( 'textarea.msvcalc__input', { rows: '3', maxlength: '2000', placeholder: t.notesHelp } ), 'optional' );
		notes.classList.add( 'msvcalc__field--wide' );
		fieldsWrap.appendChild( notes );

		// Honeypot: hidden from people and assistive tech.
		const trapId = this.uid + '-q-website';
		inputs.website = h( 'input', { type: 'text', id: trapId, name: 'website', tabindex: '-1', autocomplete: 'off' } );
		const trap = h( 'div.msvcalc__trap', { 'aria-hidden': 'true' }, [ h( 'label', { for: trapId, text: t.websiteTrap } ), inputs.website ] );

		const consentId = this.uid + '-q-consent';
		inputs.consent = h( 'input', { type: 'checkbox', id: consentId, name: 'consent', required: true, 'aria-describedby': consentId + '-error' } );
		errors.consent = h( 'span.msvcalc__field-error', { id: consentId + '-error', role: 'alert' } );
		const consent = h( 'div.msvcalc__consent', {}, [
			inputs.consent,
			h( 'label.msvcalc__check', { for: consentId, 'aria-hidden': 'true' }, [ icon( 'check', 14 ) ] ),
			h( 'p.msvcalc__consent-text', {}, [
				h( 'label', { for: consentId, text: s.consent } ),
				s.privacyUrl ? ' ' : null,
				s.privacyUrl ? h( 'a', { href: s.privacyUrl, target: '_blank', rel: 'noopener', text: t.privacy } ) : null,
			] ),
			errors.consent,
		] );

		const status = h( 'p.msvcalc__form-status', { role: 'alert' } );
		const submit = h( 'button.msvcalc__button.msvcalc__button--primary.msvcalc__button--wide', { type: 'submit' }, [ h( 'span', { text: s.cta } ), icon( 'arrow', 18 ) ] );

		const form = h(
			'form.msvcalc__quote-form',
			{
				novalidate: true,
				onSubmit: function ( event ) {
					event.preventDefault();
					self.submitQuote( inputs, errors, status, submit );
				},
			},
			[ fieldsWrap, trap, consent, status, submit ]
		);

		this.quoteView.appendChild(
			h( 'div.msvcalc__quote', {}, [
				h(
					'button.msvcalc__back',
					{
						type: 'button',
						onClick: function () {
							self.showItems();
						},
					},
					[ h( 'span.msvcalc__back-icon', {}, [ icon( 'arrow', 16 ) ] ), h( 'span', { text: t.edit } ) ]
				),
				h( 'h3.msvcalc__quote-title', { text: t.quoteTitle } ),
				h( 'p.msvcalc__muted', { text: t.quoteHelp } ),
				( this.forgotBox = h( 'div.msvcalc__forgot', { hidden: true } ) ),
				form,
			] )
		);
		this.quoteForm = form;
	};

	Calculator.prototype.submitQuote = function ( inputs, errors, status, submit ) {
		const self = this;
		const f = s.fields;
		let firstBad = null;
		Object.keys( errors ).forEach( function ( key ) {
			errors[ key ].textContent = '';
			if ( inputs[ key ] ) {
				inputs[ key ].removeAttribute( 'aria-invalid' );
			}
		} );
		status.textContent = '';

		const value = function ( key ) {
			return inputs[ key ] ? String( inputs[ key ].value || '' ).trim() : '';
		};
		const fail = function ( key, message ) {
			if ( ! errors[ key ] ) {
				return;
			}
			errors[ key ].textContent = message;
			inputs[ key ].setAttribute( 'aria-invalid', 'true' );
			firstBad = firstBad || inputs[ key ];
		};

		if ( value( 'name' ).length < 2 ) {
			fail( 'name', t.required );
		}
		if ( ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( value( 'email' ) ) ) {
			fail( 'email', t.invalidEmail );
		}
		[ [ 'phone', f.phone ], [ 'service', f.service ], [ 'from', f.addresses ], [ 'to', f.addresses ], [ 'date', f.date ], [ 'from_floor', f.access ] ].forEach( function ( pair ) {
			if ( 'required' === pair[ 1 ] && ! value( pair[ 0 ] ) ) {
				fail( pair[ 0 ], t.required );
			}
		} );
		if ( ! inputs.consent.checked ) {
			fail( 'consent', t.required );
		}
		if ( firstBad ) {
			firstBad.focus();
			return;
		}
		if ( this.lastTotals.count < 1 ) {
			status.textContent = t.emptyList;
			return;
		}

		const custom = this.state.custom.map( function ( row ) {
			return { name: row.name, m3: row.m3, qty: row.qty, icon: row.icon };
		} );
		const payload = {
			nonce: config.nonce,
			website: inputs.website.value,
			name: value( 'name' ),
			email: value( 'email' ),
			phone: value( 'phone' ),
			service: value( 'service' ),
			from: value( 'from' ),
			to: value( 'to' ),
			date: value( 'date' ),
			notes: value( 'notes' ),
			from_floor: value( 'from_floor' ),
			to_floor: value( 'to_floor' ),
			from_lift: !! ( inputs.from_lift && inputs.from_lift.checked ),
			to_lift: !! ( inputs.to_lift && inputs.to_lift.checked ),
			parking: value( 'parking' ),
			extras: inputs.extras
				.filter( function ( box ) {
					return box.checked;
				} )
				.map( function ( box ) {
					return box.value;
				} ),
			consent: inputs.consent.checked,
			source: window.location.href.split( '#' )[ 0 ],
			referrer: document.referrer || '',
			items: this.state.qty,
			custom: custom,
		};

		submit.disabled = true;
		// The same branded loader as the start screen, over the form.
		const busy = loader( t.sending );
		busy.classList.add( 'msvcalc__overlay' );
		this.quoteView.appendChild( busy );
		const idle = function () {
			submit.disabled = false;
			busy.remove();
		};
		status.textContent = t.sending;

		const send = function ( retry ) {
			return window
				.fetch( config.rest + '/quote', {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify( payload ),
				} )
				.then( function ( response ) {
					return response.json().then( function ( data ) {
						if ( ! response.ok && data && 'msvcalc_expired' === data.code && retry ) {
							// The page came from a cache older than the nonce: get a fresh one once.
							return window
								.fetch( config.rest + '/nonce', { credentials: 'same-origin' } )
								.then( function ( r ) {
									return r.json();
								} )
								.then( function ( fresh ) {
									config.nonce = fresh.nonce;
									payload.nonce = fresh.nonce;
									return send( false );
								} );
						}
						return { ok: response.ok, data: data };
					} );
				} );
		};

		send( true )
			.then( function ( result ) {
				idle();
				if ( result.ok && result.data && result.data.ok ) {
					status.textContent = '';
					self.quoteSent( result.data );
					return;
				}
				const data = result.data || {};
				const fields = data.data && data.data.fields ? data.data.fields : {};
				Object.keys( fields ).forEach( function ( key ) {
					fail( key, fields[ key ] );
				} );
				status.textContent = data.message || t.error;
				if ( firstBad ) {
					firstBad.focus();
				}
			} )
			.catch( function () {
				idle();
				status.textContent = t.error;
			} );
	};

	Calculator.prototype.quoteSent = function ( data ) {
		if ( data.redirect ) {
			window.location.assign( data.redirect );
			return;
		}
		const ns = 'http://www.w3.org/2000/svg';
		const tick = document.createElementNS( ns, 'svg' );
		tick.setAttribute( 'viewBox', '0 0 52 52' );
		tick.setAttribute( 'class', 'msvcalc__tick' );
		tick.setAttribute( 'aria-hidden', 'true' );
		const circle = document.createElementNS( ns, 'circle' );
		circle.setAttribute( 'cx', '26' );
		circle.setAttribute( 'cy', '26' );
		circle.setAttribute( 'r', '24' );
		circle.setAttribute( 'stroke', this.gradUrl );
		const path = document.createElementNS( ns, 'path' );
		path.setAttribute( 'd', 'M15 27l7 7 15-16' );
		path.setAttribute( 'stroke', this.gradUrl );
		tick.appendChild( circle );
		tick.appendChild( path );

		this.quoteView.textContent = '';
		const self = this;
		const totals = this.lastTotals || this.totals();
		const recap = [
			[ t.totalVolume, this.volumeText( totals.load, true ) ],
			[ t.items, fmt( totals.count, 0 ) ],
		];
		if ( s.vehicle && totals.vehicle ) {
			recap.push( [ t.vehicle, totals.vehicle.label ] );
		}
		const done = h( 'div.msvcalc__done', { tabindex: '-1' }, [
			tick,
			h( 'h3', { text: t.sent } ),
			h( 'p', { text: data.message } ),
			h(
				'dl.msvcalc__done-recap',
				{},
				recap.map( function ( pair ) {
					return h( 'div', {}, [ h( 'dt', { text: pair[ 0 ] } ), h( 'dd', { text: pair[ 1 ] } ) ] );
				} )
			),
		] );
		const actions = h( 'div.msvcalc__done-actions' );
		if ( s.print ) {
			actions.appendChild(
				h(
					'button.msvcalc__button.msvcalc__button--primary',
					{
						type: 'button',
						onClick: function () {
							self.print();
						},
					},
					[ icon( 'print', 18 ), h( 'span', { text: t.printCopy } ) ]
				)
			);
		}
		// Made a mistake? Go back to the same list, change it and send again.
		actions.appendChild(
			h(
				'button.msvcalc__button.msvcalc__button--soft',
				{
					type: 'button',
					onClick: function () {
						self.afterSent( false );
					},
				},
				[ icon( 'list', 18 ), h( 'span', { text: t.editList } ) ]
			)
		);
		actions.appendChild(
			h(
				'button.msvcalc__ghost',
				{
					type: 'button',
					onClick: function () {
						self.afterSent( true );
					},
				},
				[ icon( 'reset', 16 ), h( 'span', { text: t.startNew } ) ]
			)
		);
		done.appendChild( actions );
		this.quoteView.appendChild( done );
		this.quoteForm = null;
		this.root.classList.add( 'msvcalc--sent' );
		this.root.scrollIntoView( { behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' } );
		done.focus( { preventScroll: true } );
	};

	/**
	 * Leave the "Request sent" screen: back to the same list to change it,
	 * or to an empty calculator for a new one.
	 *
	 * @param {boolean} clear Whether to empty the list.
	 */
	Calculator.prototype.afterSent = function ( clear ) {
		this.root.classList.remove( 'msvcalc--sent' );
		this.quoteView.textContent = '';
		if ( clear ) {
			this.state.qty = {};
			this.state.custom = [];
			this.renderCustom();
			this.refreshCards();
			this.update();
		}
		this.showItems();
		this.root.scrollIntoView( { behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' } );
	};

	/* ---------------------------- Print ------------------------------- */

	/* ------------------------- Box estimator ---------------------------- */

	/**
	 * Five quick questions that suggest how many boxes of each size to add.
	 *
	 * @return {HTMLElement} Card.
	 */
	Calculator.prototype.buildBoxEstimator = function () {
		const self = this;
		const answers = this.boxAnswers || ( this.boxAnswers = { home: 2, cupboards: 1, books: 1, kitchen: 1, loft: 0 } );
		const questions = [
			[ 'home', t.boxHome, t.boxHomeChoices ],
			[ 'cupboards', t.boxCupboards, t.boxCupboardsChoices ],
			[ 'books', t.boxBooks, t.boxBooksChoices ],
			[ 'kitchen', t.boxKitchen, t.boxKitchenChoices ],
			[ 'loft', t.boxLoft, t.boxLoftChoices ],
		];
		const result = h( 'div.msvcalc__box-result' );

		const draw = function () {
			const counts = boxCounts( answers );
			result.textContent = '';
			const list = h( 'ul.msvcalc__box-counts', { role: 'list' } );
			let total = 0;
			BOX_IDS.forEach( function ( id ) {
				total += counts[ id ];
				list.appendChild(
					h( 'li', {}, [
						h( 'span.msvcalc__box-count-icon', {}, [ icon( itemsById[ id ].icon, 20 ) ] ),
						h( 'strong', { text: fmt( counts[ id ], 0 ) } ),
						h( 'span', { text: itemsById[ id ].name } ),
					] )
				);
			} );
			result.appendChild( h( 'p.msvcalc__box-total', { text: sprintf( t.boxTotal, fmt( total, 0 ) ) } ) );
			result.appendChild( list );
			result.appendChild(
				h(
					'button.msvcalc__button.msvcalc__button--primary',
					{
						type: 'button',
						onClick: function () {
							const before = Object.assign( {}, self.state.qty );
							BOX_IDS.forEach( function ( id ) {
								if ( counts[ id ] ) {
									self.state.qty[ id ] = clampQty( counts[ id ] );
								} else {
									delete self.state.qty[ id ];
								}
							} );
							self.refreshCards();
							self.update();
							self.flash();
							self.toast( sprintf( t.boxAdded, fmt( total, 0 ) ), t.undo, function () {
								self.state.qty = before;
								self.refreshCards();
								self.update();
							} );
						},
					},
					[ icon( 'plus', 16 ), h( 'span', { text: t.boxApply } ) ]
				)
			);
		};

		const form = h( 'div.msvcalc__box-questions' );
		questions.forEach( function ( q ) {
			const key = q[ 0 ];
			const group = h( 'div.msvcalc__segmented' + ( q[ 2 ].length > 4 ? '.is-many' : '' ), { role: 'radiogroup', 'aria-label': q[ 1 ], style: '--n:' + q[ 2 ].length } );
			const buttons = [];
			q[ 2 ].forEach( function ( label, index ) {
				const button = h( 'button.msvcalc__segment', {
					type: 'button',
					role: 'radio',
					'aria-checked': index === answers[ key ] ? 'true' : 'false',
					tabindex: index === answers[ key ] ? '0' : '-1',
					text: label,
					onClick: function () {
						answers[ key ] = index;
						buttons.forEach( function ( other, i ) {
							other.setAttribute( 'aria-checked', i === index ? 'true' : 'false' );
							other.setAttribute( 'tabindex', i === index ? '0' : '-1' );
						} );
						draw();
					},
					onKeydown: function ( event ) {
						let next = index;
						if ( 'ArrowRight' === event.key || 'ArrowDown' === event.key ) {
							next = Math.min( q[ 2 ].length - 1, index + 1 );
						} else if ( 'ArrowLeft' === event.key || 'ArrowUp' === event.key ) {
							next = Math.max( 0, index - 1 );
						} else {
							return;
						}
						event.preventDefault();
						buttons[ next ].click();
						buttons[ next ].focus();
					},
				} );
				buttons.push( button );
				group.appendChild( button );
			} );
			form.appendChild( h( 'div.msvcalc__box-q', {}, [ h( 'p.msvcalc__box-label', { text: q[ 1 ] } ), group ] ) );
		} );
		draw();

		return h( 'details.msvcalc__box-estimator', { open: ! BOX_IDS.some( function ( id ) {
			return self.state.qty[ id ];
		} ) }, [
			h( 'summary.msvcalc__box-summary', {}, [
				h( 'span.msvcalc__custom-icon', {}, [ icon( 'sparkle', 20 ) ] ),
				h( 'span', {}, [ h( 'strong', { text: t.boxTitle } ), h( 'small', { text: t.boxHelp } ) ] ),
				icon( 'chevron', 18 ),
			] ),
			h( 'div.msvcalc__box-body', {}, [ form, result ] ),
		] );
	};

	/* ------------------------- Forgotten spaces ------------------------- */

	/**
	 * Before the quote, lists the areas the visitor has not added anything
	 * from yet, with links back to them. The areas follow the service they
	 * pick (a home move, an office move or storage) and what is on the list.
	 */
	Calculator.prototype.renderForgotten = function () {
		const self = this;
		const box = this.forgotBox;
		if ( ! box ) {
			return;
		}
		box.textContent = '';
		const used = {};
		catalogue.items.forEach( function ( item ) {
			if ( self.state.qty[ item.id ] ) {
				used[ item.room ] = true;
			}
		} );
		const business = catalogue.rooms.some( function ( room ) {
			return 'business' === room.group && used[ room.id ];
		} );
		const service = this.quoteService ? this.quoteService.value : '';
		let areas = FORGOT_BY_SERVICE[ service ] || FORGOT_ROOMS;
		if ( business && ! FORGOT_BY_SERVICE[ service ] ) {
			areas = FORGOT_BUSINESS;
		}
		const missing = catalogue.rooms.filter( function ( room ) {
			return -1 !== areas.indexOf( room.id ) && ! used[ room.id ] && ( itemsByRoom[ room.id ] || [] ).length;
		} );
		if ( ! missing.length ) {
			box.hidden = true;
			return;
		}
		const links = h( 'div.msvcalc__forgot-links' );
		missing.forEach( function ( room ) {
			links.appendChild(
				h(
					'button.msvcalc__chip',
					{
						type: 'button',
						onClick: function () {
							self.showItems();
							self.selectRoom( room.id, true );
						},
					},
					[ icon( room.icon, 16 ), h( 'span', { text: room.name } ) ]
				)
			);
		} );
		box.appendChild( h( 'span.msvcalc__forgot-icon', {}, [ icon( 'info', 20 ) ] ) );
		box.appendChild( h( 'div', {}, [ h( 'p.msvcalc__forgot-title', { text: t.forgotTitle } ), h( 'p.msvcalc__muted', { text: t.forgotHelp } ), links ] ) );
		box.hidden = false;
	};

	/* ------------------------------ Toast ------------------------------- */

	/**
	 * A short message with an optional action (e.g. Undo).
	 *
	 * @param {string}   text   Message.
	 * @param {string}   label  Action label.
	 * @param {Function} action Action.
	 */
	Calculator.prototype.toast = function ( text, label, action ) {
		const self = this;
		if ( this.toastEl ) {
			this.toastEl.remove();
		}
		window.clearTimeout( this.toastTimer );
		const close = function () {
			if ( self.toastEl === el ) {
				el.classList.add( 'is-out' );
				window.setTimeout( function () {
					el.remove();
				}, 200 );
				self.toastEl = null;
			}
		};
		const el = h( 'div.msvcalc__toast', { role: 'status' }, [
			h( 'span', { text: text } ),
			action
				? h( 'button.msvcalc__toast-action', {
					type: 'button',
					text: label,
					onClick: function () {
						action();
						close();
					},
				} )
				: null,
		] );
		this.toastEl = el;
		this.app.appendChild( el );
		this.toastTimer = window.setTimeout( close, 8000 );
	};

	/* --------------------------- Share link ---------------------------- */

	/**
	 * The list as a compact, URL-safe string. It lives in the link's #hash,
	 * so it never reaches the server.
	 *
	 * @return {string} Encoded list.
	 */
	Calculator.prototype.encodeList = function () {
		const data = {
			v: 1,
			q: this.state.qty,
			c: this.state.custom.map( function ( row ) {
				return [ row.name, row.m3, row.qty, row.icon || 'box' ];
			} ),
			u: this.state.units,
		};
		const bytes = new TextEncoder().encode( JSON.stringify( data ) );
		let binary = '';
		bytes.forEach( function ( b ) {
			binary += String.fromCharCode( b );
		} );
		return window.btoa( binary ).replace( /\+/g, '-' ).replace( /\//g, '_' ).replace( /=+$/, '' );
	};

	Calculator.prototype.share = function () {
		const self = this;
		if ( this.lastTotals.count < 1 ) {
			this.toast( t.emptyList );
			return;
		}
		const url = window.location.href.split( '#' )[ 0 ] + '#' + SHARE_KEY + '=' + this.encodeList();
		const text = sprintf( t.shareText, this.volumeText( this.lastTotals.load, true ) );
		const opener = document.activeElement;

		if ( this.shareBox ) {
			this.shareBox.remove();
		}
		const field = h( 'input.msvcalc__share-url', { type: 'text', readonly: 'readonly', value: url, id: this.uid + '-share-url' } );
		const copyLabel = h( 'span', { text: t.copy } );
		const copyButton = h(
			'button.msvcalc__button.msvcalc__button--primary.msvcalc__share-copy',
			{ type: 'button' },
			[ icon( 'link', 18 ), copyLabel ]
		);
		const close = function () {
			box.classList.remove( 'is-open' );
			document.removeEventListener( 'keydown', onKey );
			window.setTimeout( function () {
				box.remove();
			}, reduceMotion ? 0 : 220 );
			self.shareBox = null;
			if ( opener && opener.focus ) {
				opener.focus();
			}
		};
		const onKey = function ( event ) {
			if ( 'Escape' === event.key ) {
				close();
			}
		};
		copyButton.addEventListener( 'click', function () {
			const done = function () {
				copyLabel.textContent = t.copied;
				copyButton.classList.add( 'is-done' );
				window.setTimeout( function () {
					copyLabel.textContent = t.copy;
					copyButton.classList.remove( 'is-done' );
				}, 1800 );
			};
			field.select();
			if ( navigator.clipboard && window.isSecureContext ) {
				navigator.clipboard.writeText( url ).then( done, function () {
					document.execCommand( 'copy' );
					done();
				} );
			} else {
				document.execCommand( 'copy' );
				done();
			}
		} );

		const option = function ( name, label, href ) {
			return h( 'a.msvcalc__share-option', { href: href, target: '_blank', rel: 'noopener' }, [
				h( 'span.msvcalc__share-icon', {}, [ icon( name, 20 ) ] ),
				h( 'span', { text: label } ),
			] );
		};
		const options = h( 'div.msvcalc__share-options', {}, [
			option( 'chat', t.shareWhatsApp, 'https://wa.me/?text=' + encodeURIComponent( text + ' ' + url ) ),
			option( 'mail', t.shareEmail, 'mailto:?subject=' + encodeURIComponent( t.printTitle ) + '&body=' + encodeURIComponent( text + '\n\n' + url ) ),
		] );
		if ( window.matchMedia && window.matchMedia( '(pointer: coarse)' ).matches ) {
			options.appendChild( option( 'phone', t.shareSms, 'sms:?&body=' + encodeURIComponent( text + ' ' + url ) ) );
		}
		if ( navigator.share ) {
			const more = h( 'button.msvcalc__share-option', { type: 'button' }, [
				h( 'span.msvcalc__share-icon', {}, [ icon( 'share', 20 ) ] ),
				h( 'span', { text: t.shareMore } ),
			] );
			more.addEventListener( 'click', function () {
				navigator.share( { title: t.printTitle, text: text, url: url } ).catch( function () {} );
			} );
			options.appendChild( more );
		}

		const titleId = this.uid + '-share-title';
		const box = h( 'div.msvcalc__share-layer', {}, [
			h( 'div.msvcalc__share-backdrop', { onClick: close } ),
			h( 'div.msvcalc__share', { role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': titleId }, [
				h( 'div.msvcalc__share-head', {}, [
					h( 'span.msvcalc__share-mark', {}, [ icon( 'share', 20 ) ] ),
					h( 'div', {}, [
						h( 'h3.msvcalc__share-title', { id: titleId, text: t.shareTitle } ),
						h( 'p.msvcalc__share-sub', { text: text } ),
					] ),
					h( 'button.msvcalc__icon-button.msvcalc__share-close', { type: 'button', 'aria-label': t.close, onClick: close }, [ icon( 'close', 18 ) ] ),
				] ),
				h( 'label.msvcalc__share-label', { for: this.uid + '-share-url', text: t.shareLinkLabel } ),
				h( 'div.msvcalc__share-row', {}, [ field, copyButton ] ),
				options,
				h( 'p.msvcalc__share-help', { text: t.shareHelp } ),
			] ),
		] );
		this.root.appendChild( box );
		this.shareBox = box;
		document.addEventListener( 'keydown', onKey );
		// Keep focus inside the dialog.
		box.addEventListener( 'keydown', function ( event ) {
			if ( 'Tab' !== event.key ) {
				return;
			}
			const focusable = box.querySelectorAll( 'button, a[href], input' );
			const first = focusable[ 0 ];
			const last = focusable[ focusable.length - 1 ];
			if ( event.shiftKey && document.activeElement === first ) {
				event.preventDefault();
				last.focus();
			} else if ( ! event.shiftKey && document.activeElement === last ) {
				event.preventDefault();
				first.focus();
			}
		} );
		window.requestAnimationFrame( function () {
			box.classList.add( 'is-open' );
			copyButton.focus();
		} );
	};

	/**
	 * Loads a list from a shared link, then removes it from the address bar.
	 */
	Calculator.prototype.readShared = function () {
		const match = new RegExp( '[#&]' + SHARE_KEY + '=([A-Za-z0-9_-]+)' ).exec( window.location.hash );
		if ( ! match || 1 !== instanceCount ) {
			return;
		}
		try {
			const b64 = match[ 1 ].replace( /-/g, '+' ).replace( /_/g, '/' );
			const binary = window.atob( b64 + '==='.slice( ( b64.length + 3 ) % 4 ) );
			const bytes = Uint8Array.from( binary, function ( ch ) {
				return ch.charCodeAt( 0 );
			} );
			const data = JSON.parse( new TextDecoder().decode( bytes ) );
			if ( ! data || 1 !== data.v ) {
				return;
			}
			const qty = {};
			Object.keys( data.q || {} ).forEach( function ( id ) {
				const n = clampQty( data.q[ id ] );
				if ( n && itemsById[ id ] ) {
					qty[ id ] = n;
				}
			} );
			const custom = ( Array.isArray( data.c ) ? data.c : [] )
				.filter( function ( row ) {
					return Array.isArray( row ) && 'string' === typeof row[ 0 ] && row[ 1 ] > 0 && row[ 1 ] <= 100 && clampQty( row[ 2 ] ) > 0;
				} )
				.slice( 0, 30 )
				.map( function ( row, index ) {
					return { id: 's' + index, name: String( row[ 0 ] ).slice( 0, 60 ), m3: Number( row[ 1 ] ), qty: clampQty( row[ 2 ] ), icon: validIcon( row[ 3 ] ) };
				} );
			const hasOwn = Object.keys( this.state.qty ).length || this.state.custom.length;
			if ( ( Object.keys( qty ).length || custom.length ) && ( ! hasOwn || window.confirm( t.loadShared ) ) ) {
				this.state.qty = qty;
				this.state.custom = custom;
				if ( s.unitToggle && ( 'm3' === data.u || 'ft3' === data.u ) ) {
					this.state.units = data.u;
				}
				this.sharedLoaded = true;
			}
		} catch ( e ) {
			// A damaged link: ignore it.
		}
		if ( window.history && window.history.replaceState ) {
			window.history.replaceState( null, '', window.location.pathname + window.location.search );
		}
	};

	Calculator.prototype.print = function () {
		const self = this;
		const totals = this.lastTotals;
		const metric = 'm3' === this.state.units;
		const ns = 'http://www.w3.org/2000/svg';
		const sheet = h( 'div.msvcalc-print-sheet' );
		const look = window.getComputedStyle( this.root );
		sheet.style.setProperty( '--msvcalc-print-a', look.getPropertyValue( '--msvcalc-accent' ).trim() || '#0fb5ba' );
		sheet.style.setProperty( '--msvcalc-print-b', look.getPropertyValue( '--msvcalc-accent-2' ).trim() || '#84cc16' );
		const brandName = s.companyName || s.siteName || '';
		const pageUrl = window.location.href.split( '#' )[ 0 ];
		let today = '';
		try {
			today = new Date().toLocaleDateString( s.locale || undefined, { year: 'numeric', month: 'long', day: 'numeric' } );
		} catch ( e ) {
			today = new Date().toDateString();
		}

		// Header: mark, business name, title and date.
		const mark = h( 'span.msvcalc-print__mark', {}, [ icon( 'cube', 26 ) ] );
		sheet.appendChild(
			h( 'header.msvcalc-print__head', {}, [
				mark,
				h( 'div.msvcalc-print__brand', {}, [
					h( 'p.msvcalc-print__eyebrow', { text: brandName } ),
					h( 'h1', { text: t.printTitle } ),
				] ),
				h( 'p.msvcalc-print__date', { text: sprintf( t.printDate, today ) } ),
			] )
		);

		// Key figures.
		const stats = [ [ t.totalVolume, this.volumeText( totals.load, true ) ], [ t.items, fmt( totals.count, 0 ) ] ];
		if ( s.boxes ) {
			stats.push( [ t.boxes, fmt( totals.boxes, 0 ) ] );
		}
		if ( s.weight ) {
			stats.push( [ t.weight, metric ? fmt( totals.kg, 0 ) + ' ' + t.kg : fmt( totals.kg * LB, 0 ) + ' ' + t.lb ] );
		}
		sheet.appendChild(
			h(
				'div.msvcalc-print__stats',
				{},
				stats.map( function ( stat, index ) {
					return h( 'div.msvcalc-print__stat' + ( 0 === index ? '.is-main' : '' ), {}, [
						h( 'span', { text: stat[ 0 ] } ),
						h( 'strong', { text: stat[ 1 ] } ),
						0 === index && totals.load ? h( 'small', { text: self.totalAlt.textContent } ) : null,
					] );
				} )
			)
		);

		// What it fits in, with a fill bar.
		const fits = [];
		[ [ 'vehicle', t.vehicle ], [ 'container', t.container ], [ 'storage', t.storage ] ].forEach( function ( pair ) {
			const fit = totals[ pair[ 0 ] ];
			if ( self.fits[ pair[ 0 ] ] && fit ) {
				const bar = document.createElementNS( ns, 'svg' );
				bar.setAttribute( 'viewBox', '0 0 100 6' );
				bar.setAttribute( 'preserveAspectRatio', 'none' );
				bar.setAttribute( 'class', 'msvcalc-print__bar' );
				bar.setAttribute( 'aria-hidden', 'true' );
				const track = document.createElementNS( ns, 'rect' );
				[ [ 'width', '100' ], [ 'height', '6' ], [ 'rx', '3' ], [ 'fill', '#e8f0f2' ] ].forEach( function ( a ) {
					track.setAttribute( a[ 0 ], a[ 1 ] );
				} );
				// Each bar carries its own gradient: the calculator's shared one
				// is hidden while printing.
				const gradId = self.uid + '-print-' + pair[ 0 ];
				const grad = document.createElementNS( ns, 'linearGradient' );
				grad.setAttribute( 'id', gradId );
				[ [ '0', sheet.style.getPropertyValue( '--msvcalc-print-a' ) ], [ '1', sheet.style.getPropertyValue( '--msvcalc-print-b' ) ] ].forEach( function ( stop ) {
					const el = document.createElementNS( ns, 'stop' );
					el.setAttribute( 'offset', stop[ 0 ] );
					el.setAttribute( 'stop-color', stop[ 1 ] );
					grad.appendChild( el );
				} );
				const defs = document.createElementNS( ns, 'defs' );
				defs.appendChild( grad );
				bar.appendChild( defs );
				const level = document.createElementNS( ns, 'rect' );
				[ [ 'width', String( Math.max( 2, Math.min( 100, fit.fill || 0 ) ) ) ], [ 'height', '6' ], [ 'rx', '3' ], [ 'fill', 'url(#' + gradId + ')' ] ].forEach( function ( a ) {
					level.setAttribute( a[ 0 ], a[ 1 ] );
				} );
				bar.appendChild( track );
				bar.appendChild( level );
				fits.push(
					h( 'div.msvcalc-print__fit', {}, [
						h( 'span.msvcalc-print__fit-label', { text: pair[ 1 ] } ),
						h( 'strong', { text: fit.label } ),
						( fit.count > 1 ? fit.note : fit.row && fit.row.note ) ? h( 'span.msvcalc-print__fit-note', { text: fit.count > 1 ? fit.note : fit.row.note } ) : null,
						bar,
						h( 'span.msvcalc-print__fit-pct', { text: sprintf( t.fill, Math.min( 100, fit.fill || 0 ) ) } ),
					] )
				);
			}
		} );
		if ( fits.length ) {
			sheet.appendChild( h( 'h2', { text: t.printFits } ) );
			sheet.appendChild( h( 'div.msvcalc-print__fits', {}, fits ) );
		}

		// The inventory, by room, with subtotals.
		sheet.appendChild( h( 'h2', { text: t.yourList } ) );
		const table = h( 'table.msvcalc-print__table' );
		table.appendChild(
			h( 'thead', {}, [
				h( 'tr', {}, [ h( 'th', { text: t.printQty } ), h( 'th', { text: t.printItem } ), h( 'th', { text: t.printVolume } ) ] ),
			] )
		);
		const groups = [];
		this.lines().forEach( function ( line ) {
			let group = groups[ groups.length - 1 ];
			if ( ! group || group.room !== line.room ) {
				group = { room: line.room, m3: 0, lines: [] };
				groups.push( group );
			}
			group.m3 += line.m3;
			group.lines.push( line );
		} );
		groups.forEach( function ( group ) {
			const body = h( 'tbody' );
			body.appendChild(
				h( 'tr.msvcalc-print__room', {}, [
					h( 'th', { colspan: '2', text: group.room } ),
					h( 'th', { text: self.volumeText( group.m3, true ) } ),
				] )
			);
			group.lines.forEach( function ( line ) {
				body.appendChild(
					h( 'tr', {}, [
						h( 'td', { text: fmt( line.qty, 0 ) + ' ×' } ),
						h( 'td', {}, [ line.name, line.dims ? h( 'span.msvcalc-print__dims', { text: line.dims } ) : null ] ),
						h( 'td', { text: self.volumeText( line.m3, true ) } ),
					] )
				);
			} );
			table.appendChild( body );
		} );
		// A plain row group, not tfoot, so the totals print once at the end
		// instead of repeating at the bottom of every page.
		const foot = h( 'tbody.msvcalc-print__sum' );
		if ( s.margin ) {
			foot.appendChild( h( 'tr', {}, [ h( 'td', { colspan: '2', text: t.printNet } ), h( 'td', { text: this.volumeText( totals.net, true ) } ) ] ) );
			foot.appendChild( h( 'tr', {}, [ h( 'td', { colspan: '2', text: sprintf( t.printMargin, s.margin ) } ), h( 'td', { text: this.volumeText( totals.load - totals.net, true ) } ) ] ) );
		}
		foot.appendChild( h( 'tr.msvcalc-print__total', {}, [ h( 'td', { colspan: '2', text: t.totalVolume } ), h( 'td', { text: this.volumeText( totals.load, true ) } ) ] ) );
		table.appendChild( foot );
		sheet.appendChild( table );

		// Footer: estimate note, source page, optional credit.
		const footer = h( 'footer.msvcalc-print__foot', {}, [
			h( 'p', { text: this.marginNote.textContent } ),
			h( 'p', { text: sprintf( t.printSource, pageUrl ) } ),
		] );
		if ( s.showCredit ) {
			footer.appendChild( h( 'p.msvcalc-print__credit', {}, [ icon( 'cube', 12 ), h( 'span', { text: t.poweredBy } ) ] ) );
		}
		sheet.appendChild( footer );

		document.body.appendChild( sheet );
		document.body.classList.add( 'msvcalc-printing' );
		const cleanup = function () {
			document.body.classList.remove( 'msvcalc-printing' );
			if ( sheet.parentNode ) {
				sheet.parentNode.removeChild( sheet );
			}
			window.removeEventListener( 'afterprint', cleanup );
		};
		window.addEventListener( 'afterprint', cleanup );
		window.print();
		window.setTimeout( cleanup, 1500 );
	};

	/* ------------------------------------------------------------------ */
	/* Boot                                                                 */
	/* ------------------------------------------------------------------ */

	function boot() {
		Array.prototype.forEach.call( document.querySelectorAll( '.msvcalc[data-msvcalc]' ), function ( root ) {
			if ( root.msvcalc ) {
				return;
			}
			root.msvcalc = new Calculator( root );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}() );
