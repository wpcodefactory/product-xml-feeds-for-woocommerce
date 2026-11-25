/**
 * Product Feeds admin (v2): enhances the server-rendered screens. Vanilla JS, REST through wp.apiFetch.
 *
 * @version 4.0.0
 * @since   4.0.0
 */
( function () {
	'use strict';
	var cfg = window.algWcPxfAdmin || { ns: 'alg-pxf/v1', i18n: {} };
	var t = cfg.i18n;
	var app = document.querySelector( '.pxf-app' );
	if ( ! app || ! window.wp || ! wp.apiFetch ) {
		return;
	}
	function api( method, path, data ) {
		var o = { path: '/' + cfg.ns + path, method: method };
		if ( data ) {
			o.data = data;
		}
		return wp.apiFetch( o );
	}
	function fmt( s ) {
		var a = Array.prototype.slice.call( arguments, 1 );
		return String( s ).replace( /%(\d+)\$s|%s/g, function ( m, n ) {
			return n ? a[ n - 1 ] : a.shift();
		} );
	}
	function toast( msg, isError ) {
		var r = document.getElementById( 'pxf-toasts' );
		if ( ! r ) {
			return;
		}
		var el = document.createElement( 'div' );
		el.className = 'toast' + ( isError ? ' is-error' : '' );
		el.textContent = msg;
		r.appendChild( el );
		setTimeout( function () { el.remove(); }, 4000 );
	}
	function fail( e ) {
		toast( ( e && e.message ) || t.error, true );
	}
	function q( sel, root ) { return ( root || document ).querySelector( sel ); }
	function qa( sel, root ) { return Array.prototype.slice.call( ( root || document ).querySelectorAll( sel ) ); }

	// Toggles: keep the On/Off label in step.
	app.addEventListener( 'change', function ( e ) {
		var i = e.target;
		if ( i.matches( '.toggle input[type=checkbox]' ) ) {
			var s = i.parentNode.querySelector( '.state' );
			if ( s ) {
				s.textContent = i.checked ? t.on : t.off;
			}
		}
		if ( i.matches( '[data-pxf-autosubmit]' ) ) {
			i.form.submit();
		}
	} );

	// Copy feed links.
	app.addEventListener( 'click', function ( e ) {
		var b = e.target.closest( '[data-pxf-copy]' );
		if ( ! b ) {
			return;
		}
		var input = document.getElementById( b.dataset.pxfCopy );
		var done = function () { toast( t.copied ); };
		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( input.value ).then( done, function () { input.select(); toast( t.copyFailed ); } );
		} else {
			input.select();
			try {
				document.execCommand( 'copy' );
				done();
			} catch ( err ) {
				toast( t.copyFailed );
			}
		}
	} );

	// Run state: chip, meta line, progress bar, button.
	var STATUS = { running: [ 'info', t.updating ], queued: [ 'info', t.waiting ], failed: [ 'bad', t.notUpdated ] };
	function paint( box, st ) {
		var s = STATUS[ st.status ] || ( st.last_good ? [ 'ok', t.upToDate ] : [ 'neutral', t.never ] );
		var busy = 'running' === st.status || 'queued' === st.status;
		var chip = q( '[data-pxf-chip]', box ) || q( '[data-pxf-chip]' );
		if ( chip ) {
			chip.className = 'chip chip-' + s[ 0 ];
			chip.textContent = s[ 1 ];
		}
		var bar = q( '[data-pxf-progress]', box );
		if ( bar ) {
			bar.hidden = ! busy;
			bar.firstElementChild.style.width = ( st.total ? Math.round( 100 * ( st.done || 0 ) / st.total ) : 5 ) + '%';
		}
		var meta = q( '[data-pxf-meta]', box );
		if ( meta && busy && st.total ) {
			meta.textContent = fmt( t.progress, st.done || 0, st.total );
		}
		var btn = q( '[data-pxf-run="' + box.dataset.pxfFeed + '"]' );
		if ( btn ) {
			btn.disabled = busy;
			btn.textContent = busy ? s[ 1 ] : t.updateNow;
		}
		box.dataset.status = st.status;
		return busy;
	}
	function poll( box ) {
		var id = box.dataset.pxfFeed;
		api( 'GET', '/feeds/' + id + '/status' ).then( function ( st ) {
			if ( paint( box, st ) ) {
				setTimeout( function () { poll( box ); }, 2500 );
			} else if ( 'done' === st.status || st.last_good ) {
				var msg = fmt( t.doneMsg, st.written || 0, st.left_out || 0 ), meta = q( '[data-pxf-meta]', box );
				if ( meta ) {
					meta.textContent = msg;
				}
				toast( msg );
			}
		} ).catch( fail );
	}
	qa( '[data-pxf-feed]' ).forEach( function ( box ) {
		if ( 'running' === box.dataset.status || 'queued' === box.dataset.status ) {
			poll( box );
		}
	} );
	app.addEventListener( 'click', function ( e ) {
		var b = e.target.closest( '[data-pxf-run]' );
		if ( ! b ) {
			return;
		}
		e.preventDefault();
		var id = b.dataset.pxfRun;
		var box = q( '[data-pxf-feed="' + id + '"]' );
		b.disabled = true;
		api( 'POST', '/feeds/' + id + '/run' ).then( function ( st ) {
			toast( t.started );
			paint( box, st && st.status ? st : { status: 'queued' } );
			poll( box );
		} ).catch( function ( err ) { b.disabled = false; fail( err ); } );
	} );

	// New feed: name follows the channel until the merchant types one; create through REST.
	var nf = document.getElementById( 'pxf-new' );
	if ( nf ) {
		var name = nf.elements.name, country = nf.elements.country, typed = false;
		name.addEventListener( 'input', function () { typed = true; } );
		var rename = function () {
			var ch = q( 'input[name=channel]:checked', nf );
			if ( ch && ! typed ) {
				name.value = ch.dataset.label + ' (' + country.options[ country.selectedIndex ].text + ')';
			}
		};
		nf.addEventListener( 'change', function ( e ) {
			if ( 'channel' === e.target.name || 'country' === e.target.name ) {
				rename();
			}
		} );
		nf.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var btn = q( '[type=submit]', nf );
			btn.disabled = true;
			api( 'POST', '/feeds', {
				name: name.value,
				channel: ( q( 'input[name=channel]:checked', nf ) || {} ).value,
				country: country.value,
				language: nf.elements.language.value,
				currency: nf.elements.currency.value
			} ).then( function ( feed ) {
				var go = function () { location.href = cfg.editUrl.replace( '__ID__', feed.id ) + '&pxf_msg=created'; };
				// Build the first file right away; the edit screen follows the run.
				api( 'POST', '/feeds/' + feed.id + '/run' ).then( go, go );
			} ).catch( function ( err ) { btn.disabled = false; fail( err ); } );
		} );
	}

	// Form to nested object: "config[filters][stock][]" style names; later values win, hidden "0" before checkboxes.
	function formData( form, prefix ) {
		var out = {};
		qa( 'input, select, textarea', form ).forEach( function ( el ) {
			if ( ! el.name || el.name.indexOf( prefix + '[' ) !== 0 || el.disabled ) {
				return;
			}
			if ( ( 'checkbox' === el.type || 'radio' === el.type ) && ! el.checked ) {
				return;
			}
			var keys = el.name.slice( prefix.length ).match( /\[([^\]]*)\]/g ).map( function ( k ) { return k.slice( 1, -1 ); } );
			var vals = 'SELECT' === el.tagName && el.multiple ? qa( 'option', el ).filter( function ( o ) { return o.selected; } ).map( function ( o ) { return o.value; } ) : [ el.value ];
			var node = out;
			for ( var i = 0; i < keys.length - 1; i++ ) {
				if ( '' === keys[ i + 1 ] ) {
					if ( ! Array.isArray( node[ keys[ i ] ] ) ) {
						node[ keys[ i ] ] = [];
					}
					Array.prototype.push.apply( node[ keys[ i ] ], vals );
					return;
				}
				if ( 'object' !== typeof node[ keys[ i ] ] || null === node[ keys[ i ] ] ) {
					node[ keys[ i ] ] = {};
				}
				node = node[ keys[ i ] ];
			}
			node[ keys[ keys.length - 1 ] ] = vals.length > 1 || ( 'SELECT' === el.tagName && el.multiple ) ? vals : vals[ 0 ];
		} );
		return out;
	}
	function toConfig( raw ) {
		var c = raw || {};
		[ 'gzip', 'use_ai_titles', 'use_ai_descriptions', 'rebuild_on_change', 'leave_out_errors' ].forEach( function ( k ) {
			if ( k in c ) {
				c[ k ] = '1' === c[ k ];
			}
		} );
		if ( c.filters ) {
			[ 'stock', 'include_cats', 'exclude_cats' ].forEach( function ( k ) {
				if ( k in c.filters && ! Array.isArray( c.filters[ k ] ) ) {
					c.filters[ k ] = [];
				}
			} );
			[ 'include_cats', 'exclude_cats' ].forEach( function ( k ) {
				if ( c.filters[ k ] ) {
					c.filters[ k ] = c.filters[ k ].map( Number ).filter( Boolean );
				}
			} );
			if ( 'exclude_ids' in c.filters ) {
				c.filters.exclude_ids = String( c.filters.exclude_ids ).split( /[\s,]+/ ).map( Number ).filter( Boolean );
			}
		}
		if ( c.mapping ) {
			var m = {};
			Object.keys( c.mapping ).forEach( function ( f ) {
				var r = c.mapping[ f ], src = r.source || '';
				if ( ! src ) {
					return;
				}
				var p = src.split( ':' ), type = p[ 0 ], key = p.slice( 1 ).join( ':' );
				m[ f ] = { type: type, key: 'meta' === type ? ( r.key || '' ) : key, value: r.value || '', prefix: '', suffix: '' };
			} );
			c.mapping = m;
		}
		return c;
	}

	// Edit feed.
	var ef = document.getElementById( 'pxf-edit' );
	if ( ef ) {
		var feedId = ef.dataset.feed;
		qa( '[data-pxf-tab]' ).forEach( function ( a ) {
			a.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var tab = a.dataset.pxfTab;
				qa( '[data-pxf-tab]' ).forEach( function ( x ) {
					var on = x === a;
					x.classList.toggle( 'on', on );
					if ( on ) {
						x.setAttribute( 'aria-current', 'page' );
					} else {
						x.removeAttribute( 'aria-current' );
					}
				} );
				qa( '[data-pxf-pane]', ef ).forEach( function ( p ) { p.hidden = p.dataset.pxfPane !== tab; } );
				ef.elements.tab.value = tab;
				history.replaceState( null, '', a.href );
			} );
		} );
		var meter = function () {
			var rows = qa( '.map[data-req="1"]', ef ), filled = 0;
			rows.forEach( function ( r ) {
				var s = q( '[data-pxf-source]', r );
				if ( ( s && s.value ) || '1' === r.dataset.default ) {
					filled++;
				}
			} );
			var num = q( '[data-pxf-meter-num]' ), bar = q( '[data-pxf-meter]' );
			if ( num ) {
				num.textContent = fmt( t.meter, filled, rows.length );
				bar.classList.toggle( 'warn', filled < rows.length );
				bar.firstElementChild.style.width = ( rows.length ? Math.round( 100 * filled / rows.length ) : 100 ) + '%';
				bar.setAttribute( 'aria-label', num.textContent );
			}
		};
		ef.addEventListener( 'change', function ( e ) {
			var s = e.target;
			if ( s.matches( '[data-pxf-source]' ) ) {
				var type = s.value.split( ':' )[ 0 ];
				qa( '[data-pxf-for]', s.parentNode ).forEach( function ( i ) { i.hidden = i.dataset.pxfFor !== type; } );
				meter();
			}
			if ( 'pxf_show' === s.name ) {
				qa( '.map', ef ).forEach( function ( r ) { r.hidden = 'req' === s.value && '1' !== r.dataset.req; } );
			}
			if ( s.matches( '[data-pxf-preview]' ) ) {
				preview( s.value );
			}
			var st = q( '[data-pxf-savestate]' );
			if ( st ) {
				st.textContent = '';
			}
		} );
		var preview = function ( pid ) {
			api( 'GET', '/feeds/' + feedId + '/preview?product=' + encodeURIComponent( pid ) ).then( function ( r ) {
				var item = ( r && r.item ) || {}, lines = [];
				Object.keys( item ).forEach( function ( k ) {
					var v = item[ k ];
					lines.push( '<' + k + '>' + ( 'object' === typeof v ? JSON.stringify( v ) : v ) + '</' + k + '>' );
				} );
				q( '[data-pxf-xml]' ).textContent = lines.join( '\n' );
				qa( '.map', ef ).forEach( function ( row ) {
					var v = item[ row.dataset.field ], cell = q( '[data-pxf-val]', row );
					v = undefined === v || null === v ? '' : ( 'object' === typeof v ? JSON.stringify( v ) : String( v ) );
					cell.textContent = v || t.empty;
					cell.title = v;
					cell.classList.toggle( 'missing', ! v && '1' === row.dataset.req );
				} );
				var ul = q( '[data-pxf-issues]' );
				ul.textContent = '';
				( r.issues || [] ).forEach( function ( i ) {
					var li = document.createElement( 'li' ), c = document.createElement( 'span' ), m = document.createElement( 'span' );
					c.className = 'chip chip-' + ( 'error' === i.severity ? 'bad' : 'warn' );
					c.textContent = 'error' === i.severity ? t.error_ : t.warning;
					m.textContent = i.message;
					li.appendChild( c );
					li.appendChild( m );
					ul.appendChild( li );
				} );
				if ( ! ( r.issues || [] ).length ) {
					var li = document.createElement( 'li' );
					li.className = 'muted';
					li.textContent = t.noIssues;
					ul.appendChild( li );
				}
			} ).catch( fail );
		};
		ef.addEventListener( 'submit', function ( e ) {
			if ( e.submitter && e.submitter.hasAttribute( 'data-pxf-delete' ) ) {
				e.preventDefault();
				if ( window.confirm( t.confirmDel ) ) {
					api( 'DELETE', '/feeds/' + feedId ).then( function () { location.href = cfg.editUrl.replace( /&view=edit.*$/, '&view=feeds&pxf_msg=deleted' ); } ).catch( fail );
				}
				return;
			}
			e.preventDefault();
			var btn = e.submitter || q( '.btn-primary', ef );
			btn.disabled = true;
			api( 'PUT', '/feeds/' + feedId, { config: toConfig( formData( ef, 'config' ) ) } ).then( function () {
				btn.disabled = false;
				toast( t.saved );
				var st = q( '[data-pxf-savestate]' );
				if ( st ) {
					st.textContent = t.saved;
				}
			} ).catch( function ( err ) { btn.disabled = false; fail( err ); } );
		} );
	}

	// Categories: Google category typeahead, save through REST.
	var timer;
	qa( '[data-pxf-typeahead]' ).forEach( function ( box ) {
		var input = q( '[data-pxf-ta-input]', box ), hid = q( '[data-pxf-ta-id]', box ), list = q( 'ul', box ), active = -1;
		var close = function () { list.hidden = true; input.setAttribute( 'aria-expanded', 'false' ); active = -1; };
		var pick = function ( li ) {
			input.value = li.textContent;
			hid.value = li.dataset.id;
			close();
		};
		input.addEventListener( 'input', function () {
			clearTimeout( timer );
			if ( ! input.value.trim() ) {
				hid.value = 0;
				close();
				return;
			}
			timer = setTimeout( function () {
				api( 'GET', '/taxonomy?q=' + encodeURIComponent( input.value.trim() ) ).then( function ( rows ) {
					list.textContent = '';
					( rows || [] ).slice( 0, 12 ).forEach( function ( r, n ) {
						var li = document.createElement( 'li' );
						li.setAttribute( 'role', 'option' );
						li.id = input.id + '-o' + n;
						li.dataset.id = r.id;
						li.textContent = r.path || r.name || r.label;
						li.addEventListener( 'mousedown', function ( ev ) { ev.preventDefault(); pick( li ); } );
						list.appendChild( li );
					} );
					if ( ! list.children.length ) {
						var li = document.createElement( 'li' );
						li.textContent = t.noMatch;
						list.appendChild( li );
					}
					list.hidden = false;
					input.setAttribute( 'aria-expanded', 'true' );
				} ).catch( fail );
			}, 250 );
		} );
		input.addEventListener( 'keydown', function ( e ) {
			var opts = qa( 'li[role=option]', list );
			if ( list.hidden || ! opts.length ) {
				return;
			}
			if ( 'ArrowDown' === e.key || 'ArrowUp' === e.key ) {
				e.preventDefault();
				active = ( active + ( 'ArrowDown' === e.key ? 1 : -1 ) + opts.length ) % opts.length;
				opts.forEach( function ( o, n ) { o.setAttribute( 'aria-selected', n === active ? 'true' : 'false' ); } );
				input.setAttribute( 'aria-activedescendant', opts[ active ].id );
			} else if ( 'Enter' === e.key && active > -1 ) {
				e.preventDefault();
				pick( opts[ active ] );
			} else if ( 'Escape' === e.key ) {
				close();
			}
		} );
		input.addEventListener( 'blur', function () { setTimeout( close, 150 ); } );
	} );
	var cf = document.getElementById( 'pxf-cats' );
	if ( cf ) {
		cf.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var map = {};
			qa( '[data-pxf-ta-id]', cf ).forEach( function ( h ) {
				map[ h.name.replace( /\D/g, '' ) ] = parseInt( h.value, 10 ) || 0;
			} );
			api( 'PUT', '/category-map', { map: map } ).then( function () { toast( t.saved ); } ).catch( fail );
		} );
	}

	// AI review: estimate and run on the ids the screen lists (data-ids), progress from GET /ai/run, approve and undo.
	var run = document.getElementById( 'pxf-ai-run' );
	var results = document.getElementById( 'pxf-ai-results' );
	if ( run ) {
		var jobSel = q( '[data-pxf-ai-job]', run ), feed = parseInt( run.dataset.feed, 10 ) || 0, start = q( '[data-pxf-ai-start]', run );
		var ids = [];
		try {
			ids = JSON.parse( run.dataset.ids || '[]' ).map( Number ).filter( Boolean );
		} catch ( err ) {
			ids = [];
		}
		var line = q( '[data-pxf-estimate]', run ), meta = q( '[data-pxf-estimate-meta]', run );
		var showBalance = function ( balance ) {
			var pill = q( '[data-pxf-balance]' );
			if ( pill && null !== balance && undefined !== balance ) {
				pill.textContent = fmt( t.credits, balance );
				pill.hidden = false;
			}
		};
		var estimate = function () {
			if ( ! ids.length ) {
				line.textContent = t.aiNothing;
				start.disabled = true;
				return;
			}
			api( 'POST', '/ai/estimate', { job: jobSel.value, ids: ids } ).then( function ( r ) {
				r = r || {};
				line.textContent = r.message || '';
				showBalance( r.balance );
				if ( ! r.ready ) {
					meta.textContent = t.notConnected;
				}
				start.disabled = '1' === run.dataset.running || ! r.ready || ! r.items || false === r.enough;
			} ).catch( fail );
		};
		var follow = function () {
			api( 'GET', '/ai/run' ).then( function ( r ) {
				var st = ( r && r.run ) || null;
				if ( st && ( 'queued' === st.status || 'running' === st.status ) ) {
					meta.textContent = st.status_text || t.aiStarted;
					setTimeout( follow, 2500 );
					return;
				}
				toast( ( st && st.status_text ) || t.aiDone );
				setTimeout( function () { location.reload(); }, 800 );
			} ).catch( fail );
		};
		jobSel.addEventListener( 'change', function () {
			location.href = cfg.aiUrl + '&job=' + encodeURIComponent( jobSel.value ) + ( feed ? '&feed=' + feed : '' );
		} );
		estimate();
		if ( '1' === run.dataset.running ) {
			follow();
		}
		start.addEventListener( 'click', function () {
			start.disabled = true;
			api( 'POST', '/ai/run', { job: jobSel.value, ids: ids } ).then( function () {
				toast( t.aiStarted );
				run.dataset.running = '1';
				follow();
			} ).catch( function ( err ) { start.disabled = false; fail( err ); } );
		} );
	}
	if ( results ) {
		var job = results.dataset.job;
		var setApproved = function ( row ) {
			q( '[data-pxf-ai-approve]', row ).hidden = true;
			var edit = q( '[data-pxf-ai-edit]', row );
			if ( edit ) {
				edit.hidden = true;
			}
			q( '[data-pxf-ai-undo]', row ).hidden = false;
			var box = q( '[data-pxf-edit-box]', row ), after = q( '[data-pxf-after]', row );
			if ( box && ! box.hidden ) {
				after.textContent = box.value;
				box.hidden = true;
				after.hidden = false;
			}
			if ( ! q( '[data-pxf-ai-chip]', row ) ) {
				var chip = document.createElement( 'span' );
				chip.className = 'chip chip-ok';
				chip.setAttribute( 'data-pxf-ai-chip', '' );
				chip.textContent = t.approved;
				after.parentNode.appendChild( chip );
			}
		};
		results.addEventListener( 'click', function ( e ) {
			var b = e.target.closest( 'button' );
			if ( ! b ) {
				return;
			}
			var row = b.closest( '[data-pxf-ai-item]' ), id = row ? parseInt( row.dataset.pxfAiItem, 10 ) : 0;
			if ( b.hasAttribute( 'data-pxf-ai-approve-all' ) ) {
				b.disabled = true;
				api( 'POST', '/ai/approve', { job: job, all: true } ).then( function ( r ) {
					qa( '[data-pxf-ai-item]', results ).forEach( setApproved );
					toast( ( r && r.message ) || t.approved );
					b.disabled = false;
				} ).catch( function ( err ) { b.disabled = false; fail( err ); } );
			} else if ( b.hasAttribute( 'data-pxf-ai-edit' ) ) {
				var box = q( '[data-pxf-edit-box]', row );
				box.hidden = false;
				q( '[data-pxf-after]', row ).hidden = true;
				box.focus();
			} else if ( b.hasAttribute( 'data-pxf-ai-approve' ) ) {
				var data = { job: job, ids: [ id ] }, eb = q( '[data-pxf-edit-box]', row );
				if ( eb && ! eb.hidden ) {
					data.values = {};
					data.values[ id ] = eb.value;
				}
				api( 'POST', '/ai/approve', data ).then( function ( r ) {
					setApproved( row );
					toast( ( r && r.message ) || t.approved );
				} ).catch( fail );
			} else if ( b.hasAttribute( 'data-pxf-ai-undo' ) ) {
				// Undo removes the AI value (product data never changes), so the row goes away.
				api( 'POST', '/ai/undo', { job: job, ids: [ id ] } ).then( function ( r ) {
					row.remove();
					toast( ( r && r.message ) || t.undone );
				} ).catch( fail );
			}
		} );
	}
}() );
