/**
 * Moving & Storage Volume Calculator: admin screens.
 *
 * Settings tabs, colour pickers, copy buttons, and the editors for rooms,
 * items, presets and capacity tables. Editors post their data back as one
 * JSON field that the server sanitizes value by value.
 *
 * @package MSVCalc
 */
( function () {
	'use strict';

	const data = window.msvcalcAdmin || {};
	const t = data.i18n || {};
	const FT3 = 35.3147;

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
			} else if ( 'value' === key ) {
				el.value = value;
			} else if ( 'checked' === key ) {
				el.checked = !! value;
			} else if ( 0 === key.indexOf( 'on' ) && 'function' === typeof value ) {
				el.addEventListener( key.slice( 2 ).toLowerCase(), value );
			} else if ( true === value ) {
				el.setAttribute( key, '' );
			} else {
				el.setAttribute( key, String( value ) );
			}
		} );
		( kids || [] ).forEach( function ( kid ) {
			if ( kid ) {
				el.appendChild( 'string' === typeof kid ? document.createTextNode( kid ) : kid );
			}
		} );
		return el;
	}

	function icon( name, size ) {
		const shapes = window.msvcalcIcons || {};
		const svg = document.createElementNS( 'http://www.w3.org/2000/svg', 'svg' );
		svg.setAttribute( 'viewBox', '0 0 24 24' );
		svg.setAttribute( 'width', String( size || 20 ) );
		svg.setAttribute( 'height', String( size || 20 ) );
		svg.setAttribute( 'fill', 'none' );
		svg.setAttribute( 'stroke', 'currentColor' );
		svg.setAttribute( 'stroke-width', '1.75' );
		svg.setAttribute( 'stroke-linecap', 'round' );
		svg.setAttribute( 'stroke-linejoin', 'round' );
		svg.setAttribute( 'aria-hidden', 'true' );
		svg.setAttribute( 'class', 'msvcalc-icon' );
		// Fixed shapes from icons.js only.
		svg.innerHTML = shapes[ name ] || shapes.box || '';
		return svg;
	}

	function slug( text, taken ) {
		let base = String( text || 'item' )
			.toLowerCase()
			.normalize( 'NFD' )
			.replace( /[̀-ͯ]/g, '' )
			.replace( /[^a-z0-9]+/g, '-' )
			.replace( /^-+|-+$/g, '' )
			.slice( 0, 48 ) || 'item';
		let id = base;
		let n = 2;
		while ( taken( id ) ) {
			id = base + '-' + n++;
		}
		return id;
	}

	function sprintf( text, value ) {
		return String( text ).replace( /%(\d\$)?[sd]/, String( value ) );
	}

	function round( value, decimals ) {
		const p = Math.pow( 10, decimals );
		return Math.round( value * p ) / p;
	}

	/* ------------------------------------------------------------------ */
	/* Small helpers on every plugin screen                                 */
	/* ------------------------------------------------------------------ */

	function initCommon() {
		document.querySelectorAll( '[data-msvcalc-copy]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				const source = document.querySelector( button.getAttribute( 'data-msvcalc-copy' ) );
				if ( ! source || ! navigator.clipboard ) {
					return;
				}
				navigator.clipboard.writeText( source.textContent.trim() ).then( function () {
					const label = button.textContent;
					button.textContent = t.copied || 'Copied';
					button.classList.add( 'is-copied' );
					window.setTimeout( function () {
						button.textContent = label;
						button.classList.remove( 'is-copied' );
					}, 1600 );
				} );
			} );
		} );

		document.querySelectorAll( 'form[data-msvcalc-confirm]' ).forEach( function ( form ) {
			form.addEventListener( 'submit', function ( event ) {
				if ( ! window.confirm( form.getAttribute( 'data-msvcalc-confirm' ) ) ) {
					event.preventDefault();
				}
			} );
		} );

		// Settings tabs.
		const tabs = document.querySelectorAll( '[data-msvcalc-tab]' );
		const show = function ( name, push ) {
			let found = false;
			tabs.forEach( function ( tab ) {
				const active = tab.getAttribute( 'data-msvcalc-tab' ) === name;
				found = found || active;
				tab.classList.toggle( 'nav-tab-active', active );
				tab.setAttribute( 'aria-selected', active ? 'true' : 'false' );
				const panel = document.getElementById( 'msvcalc-tab-' + tab.getAttribute( 'data-msvcalc-tab' ) );
				if ( panel ) {
					panel.hidden = ! active;
				}
			} );
			if ( found && push && window.history.replaceState ) {
				window.history.replaceState( null, '', '#' + name );
			}
			return found;
		};
		tabs.forEach( function ( tab ) {
			tab.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				show( tab.getAttribute( 'data-msvcalc-tab' ), true );
			} );
		} );
		if ( tabs.length && window.location.hash ) {
			show( window.location.hash.slice( 1 ).replace( 'msvcalc-tab-', '' ), false );
		}

		if ( window.jQuery && window.jQuery.fn.wpColorPicker ) {
			window.jQuery( '.msvcalc-color' ).wpColorPicker();
		}
	}

	/* ------------------------------------------------------------------ */
	/* Dirty tracking                                                       */
	/* ------------------------------------------------------------------ */

	function trackDirty( form ) {
		let dirty = false;
		const note = form.querySelector( '.msvcalc-dirty' );
		const mark = function () {
			if ( ! dirty ) {
				dirty = true;
				if ( note ) {
					note.hidden = false;
				}
			}
		};
		form.addEventListener( 'input', mark );
		form.addEventListener( 'change', mark );
		form.addEventListener( 'msvcalc:change', mark );
		window.addEventListener( 'beforeunload', function ( event ) {
			// A form replaced by a background update no longer counts.
			if ( dirty && document.body.contains( form ) ) {
				event.preventDefault();
				event.returnValue = '';
			}
		} );
		form.addEventListener( 'submit', function () {
			dirty = false;
		} );
		return mark;
	}

	let openPicker = null;

	function closePicker( focusTrigger ) {
		if ( ! openPicker ) {
			return;
		}
		const picker = openPicker;
		openPicker = null;
		picker.pop.remove();
		picker.trigger.setAttribute( 'aria-expanded', 'false' );
		if ( focusTrigger ) {
			picker.trigger.focus();
		}
	}

	document.addEventListener( 'mousedown', function ( event ) {
		if ( openPicker && ! openPicker.field.contains( event.target ) ) {
			closePicker( false );
		}
	} );

	/**
	 * A visual icon picker: a button showing the current icon that opens a
	 * searchable grid, sorted into categories.
	 *
	 * @param {string}   value    Current icon.
	 * @param {string[]} choices  Allowed icon names.
	 * @param {Function} onChange Called with the new name.
	 * @return {HTMLElement} Field.
	 */
	function iconSelect( value, choices, onChange ) {
		let current = value;
		const allowed = {};
		choices.forEach( function ( name ) {
			allowed[ name ] = true;
		} );
		let groups = ( data.iconGroups || [] )
			.map( function ( g ) {
				return {
					name: g.name,
					icons: g.icons.filter( function ( name ) {
						return allowed[ name ];
					} ),
				};
			} )
			.filter( function ( g ) {
				return g.icons.length;
			} );
		if ( ! groups.length ) {
			groups = [ { name: '', icons: choices.slice() } ];
		}

		const label = function ( name ) {
			return name.replace( /-/g, ' ' );
		};
		const trigger = h( 'button.button.msvcalc-icon-trigger', {
			type: 'button',
			'aria-haspopup': 'dialog',
			'aria-expanded': 'false',
			title: t.chooseIcon,
			onClick: function () {
				if ( openPicker && openPicker.trigger === trigger ) {
					closePicker( true );
				} else {
					open();
				}
			},
		} );
		function paintTrigger() {
			trigger.textContent = '';
			trigger.appendChild( icon( current, 22 ) );
			trigger.appendChild( h( 'span.screen-reader-text', { text: t.chooseIcon + ': ' + label( current ) } ) );
		}
		paintTrigger();
		const field = h( 'span.msvcalc-icon-field', {}, [ trigger ] );

		function open() {
			closePicker( false );
			const search = h( 'input.msvcalc-picker-search', {
				type: 'search',
				placeholder: t.findIcon,
				'aria-label': t.findIcon,
			} );
			const body = h( 'div.msvcalc-picker-body' );
			const pop = h( 'div.msvcalc-picker', { role: 'dialog', 'aria-label': t.chooseIcon }, [ search, body ] );

			function draw() {
				const q = search.value.trim().toLowerCase();
				body.textContent = '';
				let shown = 0;
				groups.forEach( function ( g ) {
					const list = g.icons.filter( function ( name ) {
						return ! q || -1 !== label( name ).indexOf( q ) || -1 !== g.name.toLowerCase().indexOf( q );
					} );
					if ( ! list.length ) {
						return;
					}
					shown += list.length;
					if ( g.name ) {
						body.appendChild( h( 'p.msvcalc-picker-group', { text: g.name } ) );
					}
					const grid = h( 'div.msvcalc-picker-grid' );
					list.forEach( function ( name ) {
						grid.appendChild(
							h(
								'button.msvcalc-picker-icon' + ( name === current ? '.is-current' : '' ),
								{
									type: 'button',
									title: label( name ),
									'aria-label': label( name ),
									'aria-pressed': name === current ? 'true' : 'false',
									onClick: function () {
										current = name;
										paintTrigger();
										onChange( name );
										closePicker( true );
									},
								},
								[ icon( name, 22 ) ]
							)
						);
					} );
					body.appendChild( grid );
				} );
				if ( ! shown ) {
					body.appendChild( h( 'p.msvcalc-picker-none', { text: t.noIcons } ) );
				}
			}

			search.addEventListener( 'input', draw );
			pop.addEventListener( 'keydown', function ( event ) {
				if ( 'Escape' === event.key ) {
					event.preventDefault();
					event.stopPropagation();
					closePicker( true );
				}
			} );
			draw();
			field.appendChild( pop );
			trigger.setAttribute( 'aria-expanded', 'true' );
			openPicker = { trigger: trigger, pop: pop, field: field };
			search.focus();
			const active = pop.querySelector( '.is-current' );
			if ( active && active.scrollIntoView ) {
				active.scrollIntoView( { block: 'nearest' } );
			}
		}

		return field;
	}

	/* ------------------------------------------------------------------ */
	/* Rooms & items editor                                                 */
	/* ------------------------------------------------------------------ */

	function ItemsEditor( root, form ) {
		const cat = JSON.parse( JSON.stringify( data.catalogue ) );
		const feet = 'ft3' === data.units;
		const unitLabel = feet ? 'ft³' : 'm³';
		const icons = data.icons || [];
		let room = cat.rooms.length ? cat.rooms[ 0 ].id : '';
		let filter = '';
		let preset = cat.presets.length ? cat.presets[ 0 ].id : '';
		const changed = function () {
			form.dispatchEvent( new CustomEvent( 'msvcalc:change' ) );
		};
		const toUnit = function ( m3 ) {
			return feet ? round( m3 * FT3, 2 ) : round( m3, 3 );
		};
		const fromUnit = function ( value ) {
			const n = parseFloat( value );
			if ( isNaN( n ) || n <= 0 ) {
				return 0;
			}
			return feet ? n / FT3 : n;
		};

		const roomsBox = h( 'div.msvcalc-ed-rooms' );
		const itemsBox = h( 'div.msvcalc-ed-items' );
		const presetsBox = h( 'div.msvcalc-ed-presets' );

		function move( list, index, step ) {
			const target = index + step;
			if ( target < 0 || target >= list.length ) {
				return;
			}
			const tmp = list[ index ];
			list[ index ] = list[ target ];
			list[ target ] = tmp;
			changed();
		}

		function renderRooms() {
			roomsBox.textContent = '';
			roomsBox.appendChild( h( 'h2', { text: t.rooms } ) );
			const list = h( 'ul.msvcalc-ed-roomlist' );
			cat.rooms.forEach( function ( r, index ) {
				const count = cat.items.filter( function ( item ) {
					return item.room === r.id;
				} ).length;
				const li = h(
					'li.msvcalc-ed-room' + ( r.id === room ? '.is-current' : '' ),
					{},
					[
						h(
							'button.msvcalc-ed-room-pick',
							{
								type: 'button',
								'aria-pressed': r.id === room ? 'true' : 'false',
								onClick: function () {
									room = r.id;
									filter = '';
									renderRooms();
									renderItems();
								},
							},
							[ icon( r.icon, 20 ), h( 'span', { text: r.name } ), h( 'small', { text: sprintf( t.itemCount, count ) } ) ]
						),
						h( 'span.msvcalc-ed-room-tools', {}, [
							h( 'button.button-link', { type: 'button', 'aria-label': t.moveUp, title: t.moveUp, text: '↑', onClick: function () {
								move( cat.rooms, index, -1 );
								renderRooms();
							} } ),
							h( 'button.button-link', { type: 'button', 'aria-label': t.moveDown, title: t.moveDown, text: '↓', onClick: function () {
								move( cat.rooms, index, 1 );
								renderRooms();
							} } ),
						] ),
					]
				);
				list.appendChild( li );
			} );
			roomsBox.appendChild( list );
			roomsBox.appendChild(
				h(
					'button.button',
					{
						type: 'button',
						onClick: function () {
							const id = slug( t.newRoom, function ( candidate ) {
								return cat.rooms.some( function ( r ) {
									return r.id === candidate;
								} );
							} );
							const current = cat.rooms.find( function ( x ) {
								return x.id === room;
							} );
							cat.rooms.push( { id: id, name: t.newRoom, icon: 'boxes', group: current ? current.group : 'home' } );
							room = id;
							changed();
							renderRooms();
							renderItems();
						},
					},
					[ icon( 'plus', 16 ), h( 'span', { text: t.addRoom } ) ]
				)
			);
		}

		function renderItems() {
			itemsBox.textContent = '';
			const r = cat.rooms.find( function ( x ) {
				return x.id === room;
			} );
			if ( ! r ) {
				itemsBox.appendChild( h( 'p', { text: t.none } ) );
				return;
			}

			const head = h( 'div.msvcalc-ed-roomhead', {}, [
				iconSelect( r.icon, icons, function ( value ) {
					r.icon = value;
					changed();
					renderRooms();
				} ),
				h( 'label.screen-reader-text', { for: 'msvcalc-ed-roomname', text: t.roomName } ),
				h( 'input.regular-text', {
					id: 'msvcalc-ed-roomname',
					type: 'text',
					value: r.name,
					maxlength: '80',
					onInput: function ( event ) {
						r.name = event.target.value;
					},
					onChange: function () {
						renderRooms();
					},
				} ),
				h( 'label.screen-reader-text', { for: 'msvcalc-ed-roomgroup', text: t.roomGroup } ),
				h(
					'select',
					{
						id: 'msvcalc-ed-roomgroup',
						onChange: function ( event ) {
							r.group = event.target.value;
							changed();
						},
					},
					( data.groups || [] ).map( function ( g ) {
						const option = h( 'option', { value: g.id, text: g.name } );
						option.selected = g.id === ( r.group || 'home' );
						return option;
					} )
				),
				h( 'button.button.button-link-delete', {
					type: 'button',
					text: t.deleteRoom,
					onClick: function () {
						if ( ! window.confirm( t.deleteRoomQ ) ) {
							return;
						}
						cat.rooms = cat.rooms.filter( function ( x ) {
							return x.id !== r.id;
						} );
						const gone = {};
						cat.items = cat.items.filter( function ( item ) {
							if ( item.room === r.id ) {
								gone[ item.id ] = true;
								return false;
							}
							return true;
						} );
						cat.presets.forEach( function ( p ) {
							Object.keys( gone ).forEach( function ( id ) {
								delete p.items[ id ];
							} );
						} );
						room = cat.rooms.length ? cat.rooms[ 0 ].id : '';
						changed();
						renderRooms();
						renderItems();
						renderPresets();
					},
				} ),
			] );
			itemsBox.appendChild( head );

			const search = h( 'input.msvcalc-ed-filter', {
				type: 'search',
				placeholder: t.filter,
				'aria-label': t.filter,
				value: filter,
				onInput: function ( event ) {
					filter = event.target.value.toLowerCase();
					renderRows();
				},
			} );
			itemsBox.appendChild( search );

			const table = h( 'table.widefat.striped.msvcalc-ed-table' );
			table.appendChild(
				h( 'thead', {}, [
					h( 'tr', {}, [
						h( 'th', { scope: 'col', text: t.icon } ),
						h( 'th', { scope: 'col', text: t.item } ),
						h( 'th', { scope: 'col', text: t.volume + ' (' + unitLabel + ')' } ),
						h( 'th', { scope: 'col', text: t.weight } ),
						h( 'th', { scope: 'col', text: t.aliases, title: t.aliasesHelp } ),
						h( 'th', { scope: 'col', text: t.popular, title: t.popularHelp } ),
						h( 'th', { scope: 'col', text: t.box, title: t.boxHelp } ),
						h( 'th', { scope: 'col' }, [ h( 'span.screen-reader-text', { text: t.delete } ) ] ),
					] ),
				] )
			);
			const body = h( 'tbody' );
			table.appendChild( body );
			itemsBox.appendChild( h( 'div.msvcalc-ed-tablewrap', {}, [ table ] ) );

			function renderRows() {
				body.textContent = '';
				const rows = cat.items.filter( function ( item ) {
					return item.room === r.id && ( ! filter || -1 !== item.name.toLowerCase().indexOf( filter ) );
				} );
				rows.forEach( function ( item ) {
					const volume = h( 'input.small-text', {
						type: 'number',
						min: '0.001',
						step: 'any',
						value: String( toUnit( item.m3 ) ),
						'aria-label': t.volume + ': ' + item.name,
						onChange: function ( event ) {
							const m3 = fromUnit( event.target.value );
							if ( m3 > 0 ) {
								item.m3 = round( m3, 3 );
								event.target.setCustomValidity( '' );
							} else {
								event.target.setCustomValidity( t.invalidVolume );
								event.target.reportValidity();
							}
							renderPresetTotal();
						},
					} );
					body.appendChild(
						h( 'tr', {}, [
							h( 'td', {}, [
								iconSelect( item.icon, icons, function ( value ) {
									item.icon = value;
									changed();
								} ),
							] ),
							h( 'td', {}, [
								h( 'input.regular-text', {
									type: 'text',
									value: item.name,
									maxlength: '80',
									'aria-label': t.name,
									onInput: function ( event ) {
										item.name = event.target.value;
									},
								} ),
							] ),
							h( 'td', {}, [ volume ] ),
							h( 'td', {}, [
								h( 'input.small-text', {
									type: 'number',
									min: '0',
									step: 'any',
									value: null === item.kg || undefined === item.kg ? '' : String( item.kg ),
									'aria-label': t.weight + ': ' + item.name,
									onChange: function ( event ) {
										const kg = parseFloat( event.target.value );
										item.kg = isNaN( kg ) ? null : kg;
									},
								} ),
							] ),
							h( 'td', {}, [
								h( 'input', {
									type: 'text',
									value: ( item.aliases || [] ).join( ', ' ),
									placeholder: t.aliasesHelp,
									'aria-label': t.aliases + ': ' + item.name,
									onChange: function ( event ) {
										item.aliases = event.target.value
											.split( ',' )
											.map( function ( a ) {
												return a.trim();
											} )
											.filter( Boolean );
									},
								} ),
							] ),
							h( 'td', {}, [
								h( 'input', {
									type: 'checkbox',
									checked: !! item.popular,
									'aria-label': t.popular + ': ' + item.name,
									onChange: function ( event ) {
										item.popular = event.target.checked;
									},
								} ),
							] ),
							h( 'td', {}, [
								h( 'input', {
									type: 'checkbox',
									checked: !! item.box,
									'aria-label': t.box + ': ' + item.name,
									onChange: function ( event ) {
										item.box = event.target.checked;
									},
								} ),
							] ),
							h( 'td', {}, [
								h( 'button.button-link.button-link-delete', {
									type: 'button',
									'aria-label': t.delete + ': ' + item.name,
									text: '×',
									onClick: function () {
										cat.items = cat.items.filter( function ( x ) {
											return x !== item;
										} );
										cat.presets.forEach( function ( p ) {
											delete p.items[ item.id ];
										} );
										changed();
										renderRows();
										renderRooms();
										renderPresets();
									},
								} ),
							] ),
						] )
					);
				} );
			}
			renderRows();

			itemsBox.appendChild(
				h(
					'button.button',
					{
						type: 'button',
						onClick: function () {
							const id = slug( t.newItem, function ( candidate ) {
								return cat.items.some( function ( x ) {
									return x.id === candidate;
								} );
							} );
							cat.items.push( { id: id, room: r.id, name: t.newItem, aliases: [], m3: 0.5, kg: null, icon: r.icon, popular: true, box: false } );
							filter = '';
							search.value = '';
							changed();
							renderRows();
							renderRooms();
							const inputs = body.querySelectorAll( 'input.regular-text' );
							if ( inputs.length ) {
								inputs[ inputs.length - 1 ].focus();
								inputs[ inputs.length - 1 ].select();
							}
						},
					},
					[ icon( 'plus', 16 ), h( 'span', { text: t.addItem } ) ]
				)
			);
		}

		let presetTotal = null;
		function renderPresetTotal() {
			if ( ! presetTotal ) {
				return;
			}
			const p = cat.presets.find( function ( x ) {
				return x.id === preset;
			} );
			let total = 0;
			if ( p ) {
				Object.keys( p.items ).forEach( function ( id ) {
					const item = cat.items.find( function ( x ) {
						return x.id === id;
					} );
					if ( item ) {
						total += item.m3 * p.items[ id ];
					}
				} );
			}
			presetTotal.textContent = t.presetTotal + ': ' + toUnit( total ) + ' ' + unitLabel;
		}

		function renderPresets() {
			presetsBox.textContent = '';
			presetsBox.appendChild( h( 'h2', { text: t.presets } ) );
			presetsBox.appendChild( h( 'p.description', { text: t.presetsHelp } ) );

			const tabs = h( 'div.msvcalc-ed-presettabs' );
			cat.presets.forEach( function ( p ) {
				tabs.appendChild(
					h( 'button.button' + ( p.id === preset ? '.button-primary' : '' ), {
						type: 'button',
						text: p.label,
						'aria-pressed': p.id === preset ? 'true' : 'false',
						onClick: function () {
							preset = p.id;
							renderPresets();
						},
					} )
				);
			} );
			tabs.appendChild(
				h(
					'button.button',
					{
						type: 'button',
						onClick: function () {
							const id = slug( t.newPreset, function ( candidate ) {
								return cat.presets.some( function ( x ) {
									return x.id === candidate;
								} );
							} );
							cat.presets.push( { id: id, label: t.newPreset, icon: 'home', items: {} } );
							preset = id;
							changed();
							renderPresets();
						},
					},
					[ icon( 'plus', 16 ), h( 'span', { text: t.addPreset } ) ]
				)
			);
			presetsBox.appendChild( tabs );

			const p = cat.presets.find( function ( x ) {
				return x.id === preset;
			} );
			if ( ! p ) {
				return;
			}
			presetTotal = h( 'strong.msvcalc-ed-presettotal' );
			presetsBox.appendChild(
				h( 'div.msvcalc-ed-presethead', {}, [
					iconSelect( p.icon || 'home', icons, function ( value ) {
						p.icon = value;
						changed();
					} ),
					h( 'input.regular-text', {
						type: 'text',
						value: p.label,
						maxlength: '80',
						'aria-label': t.preset,
						onInput: function ( event ) {
							p.label = event.target.value;
						},
						onChange: function () {
							renderPresets();
						},
					} ),
					presetTotal,
					h( 'button.button.button-link-delete', {
						type: 'button',
						text: t.deletePreset,
						onClick: function () {
							cat.presets = cat.presets.filter( function ( x ) {
								return x !== p;
							} );
							preset = cat.presets.length ? cat.presets[ 0 ].id : '';
							changed();
							renderPresets();
						},
					} ),
				] )
			);

			const list = h( 'ul.msvcalc-ed-presetlist' );
			Object.keys( p.items ).forEach( function ( id ) {
				const item = cat.items.find( function ( x ) {
					return x.id === id;
				} );
				if ( ! item ) {
					return;
				}
				list.appendChild(
					h( 'li', {}, [
						h( 'input.small-text', {
							type: 'number',
							min: '0',
							max: '999',
							value: String( p.items[ id ] ),
							'aria-label': t.qty + ': ' + item.name,
							onChange: function ( event ) {
								const qty = parseInt( event.target.value, 10 );
								if ( qty > 0 ) {
									p.items[ id ] = Math.min( 999, qty );
								} else {
									delete p.items[ id ];
									renderPresets();
									return;
								}
								renderPresetTotal();
							},
						} ),
						icon( item.icon, 18 ),
						h( 'span', { text: item.name } ),
					] )
				);
			} );
			presetsBox.appendChild( list );

			const add = h( 'select.msvcalc-ed-presetadd', {
				'aria-label': t.addToPreset,
				onChange: function () {
					if ( add.value ) {
						p.items[ add.value ] = ( p.items[ add.value ] || 0 ) + 1;
						changed();
						renderPresets();
					}
				},
			} );
			add.appendChild( h( 'option', { value: '', text: t.addToPreset } ) );
			cat.rooms.forEach( function ( r ) {
				const group = h( 'optgroup', { label: r.name } );
				cat.items
					.filter( function ( item ) {
						return item.room === r.id && ! p.items[ item.id ];
					} )
					.forEach( function ( item ) {
						group.appendChild( h( 'option', { value: item.id, text: item.name } ) );
					} );
				add.appendChild( group );
			} );
			presetsBox.appendChild( add );
			renderPresetTotal();
		}

		root.textContent = '';
		root.appendChild( h( 'div.msvcalc-ed-grid', {}, [ roomsBox, itemsBox ] ) );
		root.appendChild( presetsBox );
		renderRooms();
		renderItems();
		renderPresets();

		form.addEventListener( 'submit', function ( event ) {
			const bad = form.querySelector( '.msvcalc-ed-table input:invalid' );
			if ( bad ) {
				event.preventDefault();
				bad.reportValidity();
				return;
			}
			document.getElementById( 'msvcalc-catalogue-json' ).value = JSON.stringify( cat );
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Vehicles & storage editor                                            */
	/* ------------------------------------------------------------------ */

	function TablesEditor( root, form ) {
		const tables = JSON.parse( JSON.stringify( data.tables ) );
		const regions = data.regions || {};
		const feet = 'ft3' === data.units;
		const unitLabel = feet ? 'ft³' : 'm³';
		let region = Object.keys( regions )[ 0 ] || 'uk';
		const changed = function () {
			form.dispatchEvent( new CustomEvent( 'msvcalc:change' ) );
		};
		const toUnit = function ( m3 ) {
			return feet ? round( m3 * FT3, 0 ) : round( m3, 2 );
		};
		const fromUnit = function ( value ) {
			const n = parseFloat( value );
			return isNaN( n ) || n <= 0 ? 0 : ( feet ? n / FT3 : n );
		};

		function section( title, rows, icons, help ) {
			const box = h( 'div.msvcalc-card.msvcalc-ed-section' );
			box.appendChild( h( 'h2', { text: title } ) );
			if ( help ) {
				box.appendChild( h( 'p.description', { text: help } ) );
			}
			const table = h( 'table.widefat.striped.msvcalc-ed-table' );
			table.appendChild(
				h( 'thead', {}, [
					h( 'tr', {}, [
						h( 'th', { scope: 'col', text: t.icon } ),
						h( 'th', { scope: 'col', text: t.name } ),
						h( 'th', { scope: 'col', text: t.note } ),
						h( 'th', { scope: 'col', text: t.capacity + ' (' + unitLabel + ')' } ),
						h( 'th', { scope: 'col' }, [ h( 'span.screen-reader-text', { text: t.delete } ) ] ),
					] ),
				] )
			);
			const body = h( 'tbody' );
			table.appendChild( body );

			function draw() {
				body.textContent = '';
				rows.forEach( function ( row ) {
					body.appendChild(
						h( 'tr', {}, [
							h( 'td', {}, [
								icons.length > 1
									? iconSelect( row.icon, icons, function ( value ) {
										row.icon = value;
										changed();
									} )
									: h( 'span.msvcalc-icon-preview', {}, [ icon( icons[ 0 ], 20 ) ] ),
							] ),
							h( 'td', {}, [
								h( 'input.regular-text', {
									type: 'text',
									value: row.name,
									maxlength: '80',
									'aria-label': t.name,
									onInput: function ( event ) {
										row.name = event.target.value;
									},
								} ),
							] ),
							h( 'td', {}, [
								h( 'input', {
									type: 'text',
									value: row.note || '',
									maxlength: '120',
									'aria-label': t.note,
									onInput: function ( event ) {
										row.note = event.target.value;
									},
								} ),
							] ),
							h( 'td', {}, [
								h( 'input.small-text', {
									type: 'number',
									min: '0.1',
									step: 'any',
									value: String( toUnit( row.m3 ) ),
									'aria-label': t.capacity + ': ' + row.name,
									onChange: function ( event ) {
										const m3 = fromUnit( event.target.value );
										if ( m3 > 0 ) {
											row.m3 = round( m3, 2 );
										}
									},
								} ),
							] ),
							h( 'td', {}, [
								h( 'button.button-link.button-link-delete', {
									type: 'button',
									text: '×',
									'aria-label': t.delete + ': ' + row.name,
									onClick: function () {
										rows.splice( rows.indexOf( row ), 1 );
										changed();
										draw();
									},
								} ),
							] ),
						] )
					);
				} );
			}
			draw();
			box.appendChild( h( 'div.msvcalc-ed-tablewrap', {}, [ table ] ) );
			box.appendChild(
				h(
					'button.button',
					{
						type: 'button',
						onClick: function () {
							rows.push( { id: 'row-' + Date.now().toString( 36 ), name: '', note: '', m3: feet ? 10 / FT3 : 10, icon: icons[ 0 ] } );
							changed();
							draw();
						},
					},
					[ icon( 'plus', 16 ), h( 'span', { text: t.addRow } ) ]
				)
			);
			return box;
		}

		function render() {
			root.textContent = '';
			const picker = h( 'div.msvcalc-ed-regions', { role: 'group', 'aria-label': t.region } );
			Object.keys( regions ).forEach( function ( key ) {
				picker.appendChild(
					h( 'button.button' + ( key === region ? '.button-primary' : '' ), {
						type: 'button',
						text: regions[ key ],
						'aria-pressed': key === region ? 'true' : 'false',
						onClick: function () {
							region = key;
							render();
						},
					} )
				);
			} );
			root.appendChild( picker );
			tables.vehicles[ region ] = tables.vehicles[ region ] || [];
			tables.storage[ region ] = tables.storage[ region ] || [];
			root.appendChild( h( 'div.msvcalc-ed-stack', {}, [
				section( t.vehicles + ' – ' + regions[ region ], tables.vehicles[ region ], [ 'van', 'luton', 'truck', 'lorry' ] ),
				section( t.storage + ' – ' + regions[ region ], tables.storage[ region ], [ 'storage' ] ),
			] ) );
			root.appendChild( section( t.containers, tables.containers, [ 'container' ] ) );

			const density = h( 'input.small-text', {
				type: 'number',
				min: '20',
				max: '600',
				step: '1',
				value: String( tables.density ),
				id: 'msvcalc-ed-density',
				onChange: function ( event ) {
					tables.density = parseInt( event.target.value, 10 ) || 160;
				},
			} );
			root.appendChild(
				h( 'div.msvcalc-card.msvcalc-ed-section', {}, [
					h( 'p', {}, [ h( 'label', { for: 'msvcalc-ed-density', text: t.density + ' ' } ), density, ' kg/m³' ] ),
					h( 'p.description', { text: t.densityHelp } ),
				] )
			);
		}
		render();

		form.addEventListener( 'submit', function () {
			document.getElementById( 'msvcalc-tables-json' ).value = JSON.stringify( tables );
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Background requests with the branded loader                          */
	/* ------------------------------------------------------------------ */

	/**
	 * The plugin's one branded loader (the same ring and cube as the
	 * calculator), laid over the admin content while a request runs.
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

	const Busy = ( function () {
		let layer = null;
		let shownAt = 0;
		return {
			show: function ( text ) {
				if ( ! layer ) {
					layer = h( 'div.msvcalc-busy', { role: 'status', 'aria-live': 'polite' }, [
						loaderMark( 'span.msvcalc-loader' ),
						h( 'span.msvcalc-busy__text' ),
					] );
					document.body.appendChild( layer );
				}
				const content = document.getElementById( 'wpcontent' );
				layer.style.left = ( content ? Math.max( 0, content.getBoundingClientRect().left ) : 0 ) + 'px';
				layer.querySelector( '.msvcalc-busy__text' ).textContent = text || t.working || '';
				layer.classList.add( 'is-on' );
				document.body.classList.add( 'msvcalc-is-busy' );
				shownAt = Date.now();
			},
			hide: function () {
				if ( ! layer ) {
					return Promise.resolve();
				}
				// Stay up long enough to be seen, never a flicker.
				const wait = Math.max( 0, 350 - ( Date.now() - shownAt ) );
				return new Promise( function ( resolve ) {
					window.setTimeout( function () {
						layer.classList.remove( 'is-on' );
						document.body.classList.remove( 'msvcalc-is-busy' );
						resolve();
					}, wait );
				} );
			},
		};
	}() );

	function param( url, key ) {
		try {
			return new URL( url, window.location.href ).searchParams.get( key ) || '';
		} catch ( e ) {
			return '';
		}
	}

	/**
	 * Whether two addresses show the same view of a screen (the same lead,
	 * the same settings), so the scroll position can stay.
	 *
	 * @param {string} a Address.
	 * @param {string} b Address.
	 * @return {boolean} Same view.
	 */
	function sameView( a, b ) {
		return [ 'page', 'lead', 'tab' ].every( function ( key ) {
			return param( a, key ) === param( b, key );
		} );
	}

	const NOTICES = '.notice, div.updated, div.error';

	function readSettings( doc ) {
		const script = doc.getElementById( 'msvcalc-admin-js-before' );
		if ( ! script ) {
			return;
		}
		const text = script.textContent;
		try {
			const next = JSON.parse( text.slice( text.indexOf( '{' ), text.lastIndexOf( '}' ) + 1 ) );
			Object.keys( next ).forEach( function ( key ) {
				if ( 'i18n' !== key ) {
					data[ key ] = next[ key ];
				}
			} );
		} catch ( e ) {}
	}

	let shownPath = window.location.pathname + window.location.search;

	/**
	 * Puts a fetched copy of this screen in place: content (or only its
	 * notices), the menu badge, the title and the address.
	 *
	 * @param {Document} doc  Fetched page.
	 * @param {string}   url  Address it came from.
	 * @param {Object}   opts { push, keepScroll, noticesOnly }.
	 */
	function swapIn( doc, url, opts ) {
		const fresh = doc.getElementById( 'wpbody-content' );
		const here = document.getElementById( 'wpbody-content' );
		if ( ! fresh || ! here ) {
			window.location.assign( url );
			return;
		}
		readSettings( doc );
		const scroll = window.scrollY;
		if ( opts.noticesOnly ) {
			here.querySelectorAll( NOTICES ).forEach( function ( n ) {
				n.remove();
			} );
			const end = here.querySelector( '.wp-header-end' );
			Array.from( fresh.querySelectorAll( NOTICES ) ).reverse().forEach( function ( n ) {
				if ( end ) {
					end.after( document.importNode( n, true ) );
				}
			} );
		} else {
			here.innerHTML = fresh.innerHTML;
			// WordPress moves notices under the header; do the same.
			const end = here.querySelector( '.wp-header-end' );
			if ( end ) {
				Array.from( here.querySelectorAll( NOTICES ) )
					.filter( function ( n ) {
						return ! n.closest( '.msvcalc-wrap' ) || n.parentNode === end.parentNode;
					} )
					.reverse()
					.forEach( function ( n ) {
						if ( n.compareDocumentPosition( end ) & Node.DOCUMENT_POSITION_FOLLOWING ) {
							end.after( n );
						}
					} );
			}
		}
		const menu = document.getElementById( 'toplevel_page_msvcalc' );
		const freshMenu = doc.getElementById( 'toplevel_page_msvcalc' );
		if ( menu && freshMenu ) {
			menu.innerHTML = freshMenu.innerHTML;
		}
		if ( doc.title ) {
			document.title = doc.title;
		}
		const canonical = doc.getElementById( 'wp-admin-canonical' );
		const clean = canonical && canonical.getAttribute( 'href' ) ? canonical.getAttribute( 'href' ) + window.location.hash : url;
		window.history[ opts.push ? 'pushState' : 'replaceState' ]( { msvcalc: true }, '', clean );
		shownPath = window.location.pathname + window.location.search;
		if ( ! opts.noticesOnly ) {
			init();
		}
		if ( window.jQuery ) {
			window.jQuery( document ).trigger( 'wp-updates-notice-added' );
		}
		window.scrollTo( 0, opts.keepScroll ? scroll : 0 );
		// Bring the result notice into view, gently, when it is above.
		const notice = here.querySelector( '.wp-header-end ~ .notice' );
		if ( opts.keepScroll && notice && notice.getBoundingClientRect().top < 40 ) {
			window.scrollTo( { top: Math.max( 0, notice.getBoundingClientRect().top + window.scrollY - 60 ), behavior: 'smooth' } );
		}
	}

	function failed() {
		Busy.hide();
		window.alert( t.failed || 'Error' );
	}

	/**
	 * Fetches a page and swaps it in when it is this screen; any other
	 * screen loads normally, as it has its own scripts.
	 *
	 * @param {string} url  Address.
	 * @param {Object} req  fetch() options.
	 * @param {Object} opts { text, push, keepScroll, noticesOnly }.
	 */
	function go( url, req, opts ) {
		const from = window.location.href;
		Busy.show( opts.text || t.loading );
		return window
			.fetch( url, Object.assign( { credentials: 'same-origin' }, req || {} ) )
			.then( function ( response ) {
				const type = response.headers.get( 'Content-Type' ) || '';
				if ( ! response.ok || -1 === type.indexOf( 'text/html' ) ) {
					throw new Error( String( response.status ) );
				}
				if ( param( response.url, 'page' ) !== param( from, 'page' ) || -1 === response.url.indexOf( '/wp-admin/admin.php' ) ) {
					window.location.assign( response.url );
					return null;
				}
				return response.text().then( function ( html ) {
					const doc = new window.DOMParser().parseFromString( html, 'text/html' );
					swapIn( doc, response.url, {
						push: !! opts.push,
						keepScroll: !! opts.keepScroll && sameView( response.url, from ),
						noticesOnly: !! opts.noticesOnly && sameView( response.url, from ),
					} );
					return Busy.hide();
				} );
			} )
			.catch( failed );
	}

	/**
	 * Downloads a file (lead CSV, catalogue JSON) in the background.
	 *
	 * @param {string} url Address.
	 * @param {Object} req fetch() options.
	 */
	function download( url, req ) {
		Busy.show( t.preparingCsv );
		return window
			.fetch( url, Object.assign( { credentials: 'same-origin' }, req || {} ) )
			.then( function ( response ) {
				const disposition = response.headers.get( 'Content-Disposition' ) || '';
				if ( ! response.ok || -1 === disposition.indexOf( 'attachment' ) ) {
					throw new Error( String( response.status ) );
				}
				const match = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec( disposition );
				return response.blob().then( function ( blob ) {
					const link = h( 'a', {
						href: URL.createObjectURL( blob ),
						download: match ? decodeURIComponent( match[ 1 ] ) : 'export',
						hidden: true,
					} );
					document.body.appendChild( link );
					link.click();
					window.setTimeout( function () {
						URL.revokeObjectURL( link.href );
						link.remove();
					}, 1000 );
					return Busy.hide();
				} );
			} )
			.catch( failed );
	}

	/**
	 * Opens the print dialog for a lead report without leaving the screen:
	 * the report loads in a hidden frame and prints itself from there.
	 *
	 * @param {string} url Report address.
	 */
	function printReport( url ) {
		Busy.show( t.preparingPdf );
		const old = document.getElementById( 'msvcalc-report-frame' );
		if ( old ) {
			old.remove();
		}
		const frame = h( 'iframe.msvcalc-report-frame', {
			id: 'msvcalc-report-frame',
			title: t.preparingPdf,
			'aria-hidden': 'true',
			tabindex: '-1',
		} );
		frame.addEventListener( 'load', function () {
			Busy.hide();
		} );
		frame.src = url;
		document.body.appendChild( frame );
	}

	let backgroundReady = false;

	/**
	 * Runs the plugin screens' links and forms in the background. Bound
	 * once on the document, so it keeps working after each update.
	 */
	function initBackground() {
		if ( backgroundReady || ! window.fetch || ! window.DOMParser || ! window.URL || ! document.querySelector( '.msvcalc-wrap' ) ) {
			return;
		}
		backgroundReady = true;

		document.addEventListener( 'click', function ( event ) {
			if ( event.defaultPrevented || 0 !== event.button || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey ) {
				return;
			}
			const link = event.target.closest ? event.target.closest( '.msvcalc-wrap a[href]' ) : null;
			if ( ! link || link.hasAttribute( 'download' ) || 0 !== link.href.indexOf( window.location.origin + '/' ) ) {
				return;
			}
			const href = link.href;
			const action = param( href, 'action' );
			if ( -1 !== href.indexOf( '/wp-admin/admin-post.php' ) && 'msvcalc_export_leads' === action ) {
				event.preventDefault();
				download( href );
				return;
			}
			if ( -1 !== href.indexOf( '/wp-admin/admin-post.php' ) && 'msvcalc_lead_report' === action ) {
				event.preventDefault();
				printReport( href );
				return;
			}
			if ( link.target || -1 === href.indexOf( '/wp-admin/admin.php' ) || 0 !== param( href, 'page' ).indexOf( 'msvcalc' ) ) {
				return;
			}
			if ( -1 !== href.indexOf( '#' ) && href.split( '#' )[ 0 ] === window.location.href.split( '#' )[ 0 ] ) {
				return;
			}
			if ( param( href, 'page' ) !== param( window.location.href, 'page' ) ) {
				// Another plugin screen: it loads normally, with the loader up.
				Busy.show( t.loading );
				return;
			}
			event.preventDefault();
			go( href, null, { push: true } );
		} );

		document.addEventListener( 'submit', function ( event ) {
			const form = event.target;
			if ( event.defaultPrevented || ! form.closest || ! form.closest( '.msvcalc-wrap' ) || form.target ) {
				return;
			}
			const body = new window.FormData( form, event.submitter || undefined );
			const target = new URL( form.getAttribute( 'action' ) || window.location.href, window.location.href );
			if ( 'msvcalc_export_catalogue' === body.get( 'action' ) ) {
				event.preventDefault();
				download( target.href, { method: 'POST', body: body } );
				return;
			}
			if ( 'get' === ( form.getAttribute( 'method' ) || 'get' ).toLowerCase() ) {
				target.search = new URLSearchParams( body ).toString();
				if ( param( target.href, 'page' ) !== param( window.location.href, 'page' ) ) {
					return;
				}
				event.preventDefault();
				go( target.href, null, { push: true } );
				return;
			}
			event.preventDefault();
			// The rooms and vehicle editors already show what was saved, so
			// only the notice changes and the editor keeps its place.
			const editor = 'msvcalc-items-form' === form.id || 'msvcalc-tables-form' === form.id;
			go( target.href, { method: 'POST', body: body }, { text: t.saving, keepScroll: true, noticesOnly: editor } );
		} );

		// Select-all boxes in a list table that came in the background.
		document.addEventListener( 'change', function ( event ) {
			const box = event.target;
			if ( ! box.matches || ! box.matches( '.msvcalc-wrap thead .check-column input, .msvcalc-wrap tfoot .check-column input' ) ) {
				return;
			}
			box.closest( 'table' ).querySelectorAll( '.check-column input[type="checkbox"]' ).forEach( function ( other ) {
				other.checked = box.checked;
			} );
		} );

		window.addEventListener( 'popstate', function () {
			const path = window.location.pathname + window.location.search;
			if ( path !== shownPath && 0 === param( window.location.href, 'page' ).indexOf( 'msvcalc' ) ) {
				go( window.location.href, null, { push: false } );
			}
		} );

		// Coming back from another screen through the browser's cache.
		window.addEventListener( 'pageshow', function ( event ) {
			if ( event.persisted ) {
				Busy.hide();
			}
		} );
	}


	function init() {
		initCommon();
		initBackground();
		const itemsRoot = document.getElementById( 'msvcalc-items-editor' );
		const itemsForm = document.getElementById( 'msvcalc-items-form' );
		if ( itemsRoot && itemsForm && data.catalogue ) {
			trackDirty( itemsForm );
			ItemsEditor( itemsRoot, itemsForm );
		}
		const tablesRoot = document.getElementById( 'msvcalc-tables-editor' );
		const tablesForm = document.getElementById( 'msvcalc-tables-form' );
		if ( tablesRoot && tablesForm && data.tables ) {
			trackDirty( tablesForm );
			TablesEditor( tablesRoot, tablesForm );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
