/**
 * MM Register — admin JS. Vanilla ES6, no jQuery, no frameworks.
 */
( function () {
	'use strict';

	var cfg = window.MMR || {};
	var i18n = cfg.i18n || {};

	/* ---------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------- */

	function ajax( action, params ) {
		var body = new URLSearchParams();
		body.append( 'action', action );
		body.append( 'nonce', cfg.nonce );
		Object.keys( params || {} ).forEach( function ( key ) {
			body.append( key, params[ key ] );
		} );
		return fetch( cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		} ).then( function ( r ) {
			return r.json();
		} );
	}

	function formatMoney( amount ) {
		var n = Number( amount ) || 0;
		var parts = n.toFixed( 2 ).split( '.' );
		var intPart = parts[ 0 ].replace( /\B(?=(\d{3})+(?!\d))/g, ' ' );
		return intPart + ',' + parts[ 1 ] + ' zł';
	}

	function el( tag, attrs, text ) {
		var node = document.createElement( tag );
		if ( attrs ) {
			Object.keys( attrs ).forEach( function ( k ) {
				node.setAttribute( k, attrs[ k ] );
			} );
		}
		if ( text != null ) {
			node.textContent = text;
		}
		return node;
	}

	/* ---------------------------------------------------------------
	 * Modals
	 * ------------------------------------------------------------- */

	function fillForm( form, data ) {
		if ( ! form ) { return; }
		Object.keys( data ).forEach( function ( key ) {
			var field = form.querySelector( '[data-field="' + key + '"]' );
			if ( ! field ) { return; }
			if ( field.type === 'checkbox' ) {
				field.checked = !! Number( data[ key ] ) || data[ key ] === true;
			} else {
				field.value = ( data[ key ] === null || data[ key ] === undefined ) ? '' : data[ key ];
			}
		} );
	}

	function resetForm( form ) {
		if ( ! form ) { return; }
		form.reset();
		var idField = form.querySelector( '[data-field="id"]' );
		if ( idField ) { idField.value = '0'; }
	}

	function openModal( modal ) {
		if ( modal ) { modal.hidden = false; }
	}
	function closeModal( modal ) {
		if ( modal ) { modal.hidden = true; }
	}

	function initModals() {
		document.addEventListener( 'click', function ( e ) {
			var opener = e.target.closest( '.mmr-open-modal' );
			if ( opener ) {
				e.preventDefault();
				var modal = document.getElementById( opener.getAttribute( 'data-modal' ) );
				if ( ! modal ) { return; }
				var form = modal.querySelector( 'form' );
				var data = opener.getAttribute( 'data-json' ) ? JSON.parse( opener.getAttribute( 'data-json' ) ) : null;
				resetForm( form );
				if ( data ) {
					fillForm( form, data );
					if ( data.members ) {
						populateMembers( modal, data.members );
					}
				} else {
					clearMembers( modal );
				}
				// Fields that form.reset() and fillForm() cannot restore on
				// their own — chips, derived displays — rebuild from here, so
				// they always run after the form has been reset and filled.
				if ( form ) {
					form.dispatchEvent( new CustomEvent( 'mmr:fill', { detail: data || {} } ) );
				}
				openModal( modal );
				return;
			}
			if ( e.target.closest( '.mmr-close-modal' ) ) {
				e.preventDefault();
				closeModal( e.target.closest( '.mmr-modal-overlay' ) );
			}
			// Click on backdrop closes.
			if ( e.target.classList && e.target.classList.contains( 'mmr-modal-overlay' ) ) {
				closeModal( e.target );
			}
		} );
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' ) {
				document.querySelectorAll( '.mmr-modal-overlay:not([hidden])' ).forEach( closeModal );
				closePicker();
				closePopover();
			}
		} );
	}

	/* ---------------------------------------------------------------
	 * Class members repeater
	 * ------------------------------------------------------------- */

	function clearMembers( modal ) {
		var rows = modal.querySelector( '#mmr-members-rows' );
		if ( rows ) { rows.innerHTML = ''; }
	}

	function addMemberRow( modal, member ) {
		var tpl = modal.querySelector( '#mmr-member-row-template' );
		var rows = modal.querySelector( '#mmr-members-rows' );
		if ( ! tpl || ! rows ) { return; }
		var frag = tpl.content.cloneNode( true );
		if ( member ) {
			var select = frag.querySelector( '.mmr-member-select' );
			var share = frag.querySelector( '.mmr-member-share' );
			if ( select ) { select.value = member.student_id; }
			if ( share ) { share.value = ( member.share === null || member.share === '' ) ? '' : member.share; }
		}
		rows.appendChild( frag );
	}

	function populateMembers( modal, members ) {
		clearMembers( modal );
		members.forEach( function ( m ) {
			addMemberRow( modal, m );
		} );
	}

	function initMembersRepeater() {
		document.addEventListener( 'click', function ( e ) {
			if ( e.target.id === 'mmr-add-member' ) {
				e.preventDefault();
				addMemberRow( e.target.closest( '.mmr-modal-overlay' ), null );
			}
			if ( e.target.classList && e.target.classList.contains( 'mmr-remove-member' ) ) {
				e.preventDefault();
				var row = e.target.closest( '.mmr-member-row' );
				if ( row ) { row.remove(); }
			}
		} );
	}

	/* ---------------------------------------------------------------
	 * Class modal — company field: keep hidden company_id in step with the
	 * typed name. Server-side resolution stays authoritative.
	 * ------------------------------------------------------------- */

	function initCompanyField() {
		var input = document.getElementById( 'mmr-class-company' );
		if ( ! input ) { return; }
		var mapNode = document.getElementById( 'mmr-company-map' );
		var map = {};
		if ( mapNode ) {
			try { map = JSON.parse( mapNode.textContent ) || {}; } catch ( err ) { map = {}; }
		}
		var hidden = input.closest( 'form' ).querySelector( '[data-field="company_id"]' );
		input.addEventListener( 'input', function () {
			if ( ! hidden ) { return; }
			var key = input.value.trim().toLowerCase();
			hidden.value = Object.prototype.hasOwnProperty.call( map, key ) ? map[ key ] : '';
		} );
	}

	/* ---------------------------------------------------------------
	 * Forms that need a confirm() before submitting (e.g. archiving a
	 * company still linked to classes).
	 * ------------------------------------------------------------- */

	function initConfirmForms() {
		document.addEventListener( 'submit', function ( e ) {
			var form = e.target.closest( 'form[data-confirm]' );
			if ( ! form ) { return; }
			if ( ! window.confirm( form.getAttribute( 'data-confirm' ) ) ) {
				e.preventDefault();
			}
		} );
	}

	/* ---------------------------------------------------------------
	 * Calendar
	 * ------------------------------------------------------------- */

	var activePicker = null;
	var activePopover = null;

	function closePicker() {
		if ( activePicker ) { activePicker.remove(); activePicker = null; }
	}
	function closePopover() {
		if ( activePopover ) { activePopover.remove(); activePopover = null; }
	}

	function recomputeTotals( cal ) {
		var weekTotal = 0;
		var lessonCount = 0;
		// Each day column's money also lands in the bucket for its own month,
		// so a week straddling a boundary keeps the two months apart.
		var monthSlices = {};
		cal.querySelectorAll( '.mmr-col' ).forEach( function ( col ) {
			var dayTotal = 0;
			col.querySelectorAll( '.mmr-slot.is-filled' ).forEach( function ( slot ) {
				dayTotal += parseFloat( slot.getAttribute( 'data-fee' ) || '0' );
				lessonCount += 1;
			} );
			var dayEl = col.querySelector( '.mmr-day-total' );
			if ( dayEl ) { dayEl.textContent = formatMoney( dayTotal ); }
			var period = ( col.getAttribute( 'data-date' ) || '' ).slice( 0, 7 );
			monthSlices[ period ] = ( monthSlices[ period ] || 0 ) + dayTotal;
			weekTotal += dayTotal;
		} );
		var weekEl = document.getElementById( 'mmr-week-total' );
		if ( weekEl ) { weekEl.textContent = formatMoney( weekTotal ); }

		var countEl = document.getElementById( 'mmr-lesson-count' );
		if ( countEl ) { countEl.textContent = String( lessonCount ); }

		// Month badge = the rest of that month (fixed server-side at render
		// time) + this week's live slice of it. Rebuilt from the base rather
		// than nudged by a delta, so a lesson booked on 1.09 moves September
		// and never August.
		cal.querySelectorAll( '.mmr-month-total' ).forEach( function ( monthEl ) {
			var period = monthEl.getAttribute( 'data-period' ) || '';
			var base = parseFloat( monthEl.getAttribute( 'data-month-base' ) || '0' );
			monthEl.textContent = formatMoney( base + ( monthSlices[ period ] || 0 ) );
		} );

		updatePillStatus( weekTotal, lessonCount );
	}

	/* Keep the conditional pills (week money, lesson count) in step with the
	 * live totals. JS only toggles the semantic modifier class and rewrites the
	 * aria-label; every colour still comes from CSS tokens. */

	function pillStatus( value, th ) {
		if ( ! th ) { return null; }
		if ( value < th.warn ) { return 'danger'; }
		if ( value <= th.good ) { return 'warn'; }
		return 'good';
	}

	function fmtAria( tpl, a, b ) {
		return String( tpl || '' ).replace( /%1\$s/g, a ).replace( /%2\$s/g, b );
	}

	function setPill( pill, status, aria ) {
		if ( ! pill || ! status ) { return; }
		[ 'danger', 'warn', 'good' ].forEach( function ( s ) {
			pill.classList.toggle( 'mmr-pill--' + s, s === status );
		} );
		if ( aria ) { pill.setAttribute( 'aria-label', aria ); }
	}

	function updatePillStatus( weekTotal, lessonCount ) {
		var stats = cfg.stats || {};
		var words = stats.words || {};
		var wStatus = pillStatus( weekTotal, stats.money );
		var cStatus = pillStatus( lessonCount, stats.lessons );
		setPill( document.getElementById( 'mmr-week-pill' ), wStatus, fmtAria( stats.weekAria, formatMoney( weekTotal ), words[ wStatus ] ) );
		setPill( document.getElementById( 'mmr-lesson-pill' ), cStatus, fmtAria( stats.lessonAria, String( lessonCount ), words[ cStatus ] ) );
	}

	function fillSlot( slot, lesson ) {
		slot.classList.remove( 'is-empty' );
		slot.classList.add( 'is-filled' );
		slot.setAttribute( 'data-lesson-id', lesson.id );
		slot.setAttribute( 'data-class-id', lesson.class_id );
		slot.setAttribute( 'data-fee', lesson.fee );
		slot.setAttribute( 'data-note', lesson.note || '' );
		slot.innerHTML = '';
		var name = el( 'span', { 'class': 'mmr-slot-name', 'title': lesson.class_name }, lesson.class_name );
		if ( lesson.note ) {
			var star = el( 'span', { 'class': 'mmr-slot-note', 'title': lesson.note }, '*' );
			name.appendChild( star );
		}
		slot.appendChild( name );
		slot.appendChild( el( 'span', { 'class': 'mmr-slot-fee' }, lesson.fee_display ) );
	}

	function emptySlot( slot ) {
		slot.classList.remove( 'is-filled' );
		slot.classList.add( 'is-empty' );
		slot.removeAttribute( 'data-lesson-id' );
		slot.removeAttribute( 'data-class-id' );
		slot.removeAttribute( 'data-fee' );
		slot.removeAttribute( 'data-note' );
		slot.innerHTML = '';
		slot.appendChild( el( 'span', { 'class': 'mmr-slot-plus' }, '+' ) );
	}

	function openPicker( slot, cal ) {
		closePicker();
		closePopover();
		var picker = el( 'div', { 'class': 'mmr-picker' } );
		var input = el( 'input', { 'type': 'text', 'placeholder': i18n.searchClass || 'Search…' } );
		var list = el( 'div', { 'class': 'mmr-picker-list' } );
		picker.appendChild( input );
		picker.appendChild( list );
		document.body.appendChild( picker );
		positionFloating( picker, slot );
		activePicker = picker;

		var results = [];
		var activeIndex = -1;
		var timer = null;

		function render() {
			list.innerHTML = '';
			if ( ! results.length ) {
				list.appendChild( el( 'div', { 'class': 'mmr-picker-empty' }, i18n.noResults || 'No results' ) );
				return;
			}
			results.forEach( function ( cls, idx ) {
				var item = el( 'div', { 'class': 'mmr-picker-item' + ( idx === activeIndex ? ' is-active' : '' ) } );
				item.appendChild( el( 'span', { 'class': 'mmr-pi-name' }, cls.name ) );
				item.appendChild( el( 'span', { 'class': 'mmr-pi-fee' }, cls.fee_display ) );
				item.addEventListener( 'mousedown', function ( e ) {
					e.preventDefault();
					choose( cls );
				} );
				list.appendChild( item );
			} );
		}

		function search() {
			ajax( 'mmr_search_classes', { term: input.value } ).then( function ( res ) {
				if ( res && res.success ) {
					results = res.data.classes;
					activeIndex = results.length ? 0 : -1;
					render();
				}
			} );
		}

		function choose( cls ) {
			ajax( 'mmr_create_lesson', {
				date: slot.getAttribute( 'data-date' ),
				slot: slot.getAttribute( 'data-slot' ),
				class_id: cls.id
			} ).then( function ( res ) {
				if ( res && res.success ) {
					fillSlot( slot, res.data.lesson );
					recomputeTotals( cal );
					closePicker();
				} else {
					alert( i18n.error || 'Error' );
				}
			} );
		}

		input.addEventListener( 'input', function () {
			clearTimeout( timer );
			timer = setTimeout( search, 150 );
		} );
		input.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'ArrowDown' ) {
				e.preventDefault();
				activeIndex = Math.min( activeIndex + 1, results.length - 1 );
				render();
			} else if ( e.key === 'ArrowUp' ) {
				e.preventDefault();
				activeIndex = Math.max( activeIndex - 1, 0 );
				render();
			} else if ( e.key === 'Enter' ) {
				e.preventDefault();
				if ( activeIndex >= 0 && results[ activeIndex ] ) {
					choose( results[ activeIndex ] );
				}
			} else if ( e.key === 'Escape' ) {
				closePicker();
			}
		} );

		search();
		input.focus();
	}

	function openPopover( slot, cal ) {
		closePicker();
		closePopover();
		var pop = el( 'div', { 'class': 'mmr-popover' } );
		var lessonId = slot.getAttribute( 'data-lesson-id' );
		var name = slot.querySelector( '.mmr-slot-name' );
		pop.appendChild( el( 'h4', null, name ? name.textContent.replace( '*', '' ) : '' ) );

		// Same field grid as the modals (single column at popover width).
		function field( labelText, input ) {
			var wrap = el( 'div', { 'class': 'mmr-field' } );
			wrap.appendChild( el( 'label', null, labelText ) );
			wrap.appendChild( input );
			return wrap;
		}
		var grid = el( 'div', { 'class': 'mmr-form-grid' } );
		var feeInput = el( 'input', { 'type': 'text', 'value': slot.getAttribute( 'data-fee' ) || '' } );
		grid.appendChild( field( 'Fee override (zł)', feeInput ) );
		var noteInput = el( 'input', { 'type': 'text', 'value': slot.getAttribute( 'data-note' ) || '' } );
		grid.appendChild( field( 'Note', noteInput ) );
		var moveDate = el( 'input', { 'type': 'date', 'value': slot.getAttribute( 'data-date' ) } );
		grid.appendChild( field( 'Move to date', moveDate ) );
		var moveSlot = el( 'input', { 'type': 'number', 'min': '1', 'max': String( cfg.slots || 12 ), 'value': slot.getAttribute( 'data-slot' ) } );
		grid.appendChild( field( 'Move to slot', moveSlot ) );
		pop.appendChild( grid );

		var actions = el( 'div', { 'class': 'mmr-popover-actions' } );
		var saveBtn = el( 'button', { 'class': 'button button-primary', 'type': 'button' }, 'Save' );
		var delBtn = el( 'button', { 'class': 'button mmr-delete-btn', 'type': 'button' }, 'Delete' );
		actions.appendChild( saveBtn );
		actions.appendChild( delBtn );
		pop.appendChild( actions );

		document.body.appendChild( pop );
		positionFloating( pop, slot );
		activePopover = pop;

		saveBtn.addEventListener( 'click', function () {
			var params = { id: lessonId, fee: feeInput.value, note: noteInput.value };
			var newDate = moveDate.value;
			var newSlot = moveSlot.value;
			if ( newDate !== slot.getAttribute( 'data-date' ) || newSlot !== slot.getAttribute( 'data-slot' ) ) {
				params.move_date = newDate;
				params.move_slot = newSlot;
			}
			ajax( 'mmr_update_lesson', params ).then( function ( res ) {
				if ( res && res.success ) {
					if ( params.move_date ) {
						// Moved: reload to reflect new position reliably.
						window.location.reload();
						return;
					}
					fillSlot( slot, res.data.lesson );
					recomputeTotals( cal );
					closePopover();
				} else {
					alert( ( res && res.data && res.data.message === 'slot_taken' ) ? 'Target slot is occupied.' : ( i18n.error || 'Error' ) );
				}
			} );
		} );

		delBtn.addEventListener( 'click', function () {
			if ( ! window.confirm( i18n.confirmDelete || 'Delete?' ) ) { return; }
			ajax( 'mmr_delete_lesson', { id: lessonId } ).then( function ( res ) {
				if ( res && res.success ) {
					emptySlot( slot );
					recomputeTotals( cal );
					closePopover();
				}
			} );
		} );

		feeInput.focus();
	}

	function positionFloating( node, anchor ) {
		var rect = anchor.getBoundingClientRect();
		var top = rect.bottom + window.scrollY + 2;
		var left = rect.left + window.scrollX;
		var maxLeft = window.scrollX + document.documentElement.clientWidth - node.offsetWidth - 8;
		if ( left > maxLeft ) { left = maxLeft; }
		node.style.top = top + 'px';
		node.style.left = Math.max( 8, left ) + 'px';
	}

	function initCalendar() {
		var cal = document.getElementById( 'mmr-calendar' );
		if ( ! cal ) { return; }

		function openSlot( slot ) {
			if ( slot.classList.contains( 'is-empty' ) ) {
				openPicker( slot, cal );
			} else {
				openPopover( slot, cal );
			}
		}

		cal.addEventListener( 'click', function ( e ) {
			var slot = e.target.closest( '.mmr-slot' );
			if ( ! slot ) { return; }
			openSlot( slot );
		} );

		// Keyboard: every slot is tabbable; Enter / Space opens it.
		cal.addEventListener( 'keydown', function ( e ) {
			if ( e.key !== 'Enter' && e.key !== ' ' && e.key !== 'Spacebar' ) { return; }
			var slot = e.target.closest( '.mmr-slot' );
			if ( ! slot || ! cal.contains( slot ) ) { return; }
			e.preventDefault();
			openSlot( slot );
		} );

		document.addEventListener( 'click', function ( e ) {
			if ( activePicker && ! activePicker.contains( e.target ) && ! e.target.closest( '.mmr-slot' ) ) {
				closePicker();
			}
			if ( activePopover && ! activePopover.contains( e.target ) && ! e.target.closest( '.mmr-slot' ) ) {
				closePopover();
			}
		} );

		// Date jumper.
		var jump = document.getElementById( 'mmr-jump' );
		if ( jump ) {
			jump.addEventListener( 'change', function () {
				var url = new URL( window.location.href );
				url.searchParams.set( 'week', jump.value );
				window.location.href = url.toString();
			} );
		}

		// Copy week.
		var copyBtn = document.getElementById( 'mmr-copy-week' );
		if ( copyBtn ) {
			copyBtn.addEventListener( 'click', function () {
				var source = cal.getAttribute( 'data-monday' );
				var target = window.prompt( 'Copy this week into which week? Enter any date (YYYY-MM-DD) in the target week:', nextWeek( source ) );
				if ( ! target ) { return; }
				ajax( 'mmr_copy_week', { source_monday: source, target_monday: target } ).then( function ( res ) {
					if ( res && res.success ) {
						alert( 'Copied ' + res.data.copied + ' lessons, skipped ' + res.data.skipped + '.' );
						var url = new URL( window.location.href );
						url.searchParams.set( 'week', target );
						window.location.href = url.toString();
					} else {
						alert( i18n.error || 'Error' );
					}
				} );
			} );
		}

		// Mobile day tabs.
		var tabs = cal.querySelectorAll( '.mmr-day-tab' );
		var cols = cal.querySelectorAll( '.mmr-col' );
		function activateDay( day ) {
			tabs.forEach( function ( t ) { t.classList.toggle( 'is-active', t.getAttribute( 'data-day' ) === String( day ) ); } );
			cols.forEach( function ( c ) { c.classList.toggle( 'is-active', c.getAttribute( 'data-day' ) === String( day ) ); } );
		}
		if ( tabs.length ) {
			tabs.forEach( function ( tab ) {
				tab.addEventListener( 'click', function () { activateDay( tab.getAttribute( 'data-day' ) ); } );
			} );
			var todayTab = cal.querySelector( '.mmr-day-tab.is-today' );
			activateDay( todayTab ? todayTab.getAttribute( 'data-day' ) : '0' );
		}
	}

	function nextWeek( monday ) {
		var d = new Date( monday + 'T00:00:00' );
		d.setDate( d.getDate() + 7 );
		return d.toISOString().slice( 0, 10 );
	}

	/* ---------------------------------------------------------------
	 * Settlement
	 * ------------------------------------------------------------- */

	var MMR_SORT_STORAGE = 'mmrSettlementSort';

	var mmrCollator = new Intl.Collator( 'pl', { sensitivity: 'base', numeric: true } );

	function readStoredSort() {
		try {
			var raw = window.localStorage.getItem( MMR_SORT_STORAGE );
			if ( ! raw ) { return null; }
			var parsed = JSON.parse( raw );
			if ( ! parsed || ! parsed.key ) { return null; }
			return { key: parsed.key, dir: 'desc' === parsed.dir ? 'desc' : 'asc' };
		} catch ( err ) {
			return null;
		}
	}

	function writeStoredSort( state ) {
		try {
			window.localStorage.setItem( MMR_SORT_STORAGE, JSON.stringify( state ) );
		} catch ( err ) {
			/* Private mode or storage disabled — sorting still works, it just
			   will not survive the next page load. Not worth surfacing. */
		}
	}

	/* A class row plus its optional split row move as one unit. */
	function settlementGroups( tbody ) {
		var groups = [];
		var rows = tbody.querySelectorAll( 'tr.mmr-settle-row' );
		Array.prototype.forEach.call( rows, function ( row, index ) {
			var nodes = [ row ];
			var next = row.nextElementSibling;
			if ( next && next.classList.contains( 'mmr-split-row' ) ) {
				nodes.push( next );
			}
			groups.push( { row: row, nodes: nodes, index: index } );
		} );
		return groups;
	}

	function sortSettlementTable( table, key, dir ) {
		var tbody = table.tBodies[ 0 ];
		if ( ! tbody ) { return; }

		var attr = 'sort' + key.charAt( 0 ).toUpperCase() + key.slice( 1 ); // sortClass | sortCompany | sortMethod
		var groups = settlementGroups( tbody );
		var factor = 'desc' === dir ? -1 : 1;

		groups.sort( function ( a, b ) {
			var va = ( a.row.dataset[ attr ] || '' ).trim();
			var vb = ( b.row.dataset[ attr ] || '' ).trim();

			/* Blanks last in both directions. */
			if ( '' === va && '' === vb ) { return a.index - b.index; }
			if ( '' === va ) { return 1; }
			if ( '' === vb ) { return -1; }

			var cmp = mmrCollator.compare( va, vb );
			if ( 0 === cmp ) { return a.index - b.index; } // stable tie-break
			return cmp * factor;
		} );

		var frag = document.createDocumentFragment();
		groups.forEach( function ( group ) {
			group.nodes.forEach( function ( node ) { frag.appendChild( node ); } );
		} );
		tbody.appendChild( frag );

		/* Header state. */
		var heads = table.querySelectorAll( 'th.mmr-sortable' );
		Array.prototype.forEach.call( heads, function ( th ) {
			if ( th.getAttribute( 'data-mmr-sort' ) === key ) {
				th.setAttribute( 'aria-sort', 'desc' === dir ? 'descending' : 'ascending' );
			} else {
				th.setAttribute( 'aria-sort', 'none' );
			}
		} );
	}

	function initSettlement() {
		document.addEventListener( 'click', function ( e ) {
			var toggle = e.target.closest( '.mmr-toggle-split' );
			if ( toggle ) {
				e.preventDefault();
				var row = toggle.closest( 'tr' );
				var splitRow = row.nextElementSibling;
				if ( splitRow && splitRow.classList.contains( 'mmr-split-row' ) ) {
					splitRow.hidden = ! splitRow.hidden;
					toggle.setAttribute( 'aria-expanded', String( ! splitRow.hidden ) );
				}
			}
		} );

		initSettlementSort();
		initSettlementFilter();
		initSummaryExport();
	}

	/* Client-side filter over the same data-* attributes the sort reads. The
	   filter is deliberately not persisted: a hidden stored filter would make
	   the table look like it had lost rows on the next visit. */
	function initSettlementFilter() {
		var table = document.querySelector( '.mmr-settlement-table' );
		var input = document.getElementById( 'mmr-class-filter' );
		if ( ! table || ! input ) { return; }

		var clearBtn = document.getElementById( 'mmr-class-filter-clear' );
		var countOut = document.getElementById( 'mmr-class-filter-count' );
		var footTotal = document.getElementById( 'mmr-class-foot-total' );
		var footLabel = document.getElementById( 'mmr-class-foot-label' );
		/* The server-rendered total is the source of truth when unfiltered —
		   restoring the string avoids re-deriving it from rounded row values. */
		var fullTotal = footTotal ? footTotal.textContent : '';
		var fullLabel = footLabel ? footLabel.textContent : '';

		function applyFilter() {
			var tbody = table.tBodies[ 0 ];
			if ( ! tbody ) { return; }

			var query = input.value.trim().toLowerCase();
			var groups = settlementGroups( tbody );
			var visible = 0;
			var total = 0;

			groups.forEach( function ( group ) {
				var row = group.row;
				var haystack = [
					row.dataset.sortClass || '',
					row.dataset.sortCompany || '',
					row.dataset.sortMethod || ''
				].join( ' ' ).toLowerCase();
				var match = '' === query || -1 !== haystack.indexOf( query );

				group.nodes.forEach( function ( node ) {
					node.classList.toggle( 'mmr-filtered-out', ! match );
				} );

				if ( match ) {
					visible++;
					total += parseFloat( row.dataset.classTotal ) || 0;
				}
			} );

			if ( clearBtn ) { clearBtn.hidden = '' === query; }
			if ( countOut ) {
				countOut.textContent = '' === query
					? ''
					: ( i18n.classesShown || 'Showing %1$s of %2$s classes' )
						.replace( '%1$s', visible )
						.replace( '%2$s', groups.length );
			}
			/* Only the table's own footer follows the filter. The Month total
			   and To bill chips stay full-month so the headline figures are
			   never a filtered half-truth. */
			if ( footTotal ) {
				footTotal.textContent = '' === query ? fullTotal : formatMoney( total );
			}
			if ( footLabel ) {
				footLabel.textContent = '' === query ? fullLabel : ( i18n.filteredTotal || 'Filtered total' );
			}
		}

		input.addEventListener( 'input', applyFilter );
		if ( clearBtn ) {
			clearBtn.addEventListener( 'click', function () {
				input.value = '';
				applyFilter();
				input.focus();
			} );
		}

		/* Browsers restore search-input values on back/refresh, so reconcile
		   the table with whatever is in the box at load. */
		applyFilter();
	}

	function initSettlementSort() {
		var table = document.querySelector( '.mmr-settlement-table' );
		if ( ! table ) { return; }

		var state = readStoredSort();
		if ( state ) {
			/* Ignore a stored key that no longer exists in the markup. */
			if ( table.querySelector( 'th.mmr-sortable[data-mmr-sort="' + state.key + '"]' ) ) {
				sortSettlementTable( table, state.key, state.dir );
			} else {
				state = null;
			}
		}

		table.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.mmr-sort-btn' );
			if ( ! btn ) { return; }
			var th = btn.closest( 'th.mmr-sortable' );
			if ( ! th ) { return; }

			var key = th.getAttribute( 'data-mmr-sort' );
			var dir = 'asc';
			if ( state && state.key === key && 'asc' === state.dir ) {
				dir = 'desc';
			}
			state = { key: key, dir: dir };
			sortSettlementTable( table, key, dir );
			writeStoredSort( state );
		} );
	}

	/* Copy / CSV export of the per-student summary. */

	function summaryData() {
		var node = document.getElementById( 'mmr-settlement-summary-data' );
		if ( ! node ) { return null; }
		try {
			return JSON.parse( node.textContent );
		} catch ( err ) {
			return null;
		}
	}

	function summaryFeedback( message ) {
		var out = document.getElementById( 'mmr-copy-feedback' );
		if ( ! out ) { return; }
		out.textContent = message;
		setTimeout( function () { out.textContent = ''; }, 3000 );
	}

	function initSummaryExport() {
		var copyBtn = document.getElementById( 'mmr-copy-summary' );
		var csvBtn = document.getElementById( 'mmr-download-csv' );
		if ( ! copyBtn && ! csvBtn ) { return; }

		if ( copyBtn ) {
			copyBtn.addEventListener( 'click', function () {
				var data = summaryData();
				if ( ! data ) { return; }
				// Tab-separated with display formatting — pastes cleanly into a sheet.
				var lines = [ data.headers.join( '\t' ) ];
				data.rows.forEach( function ( r ) {
					lines.push( [ r.student, r.company, r.lessons, formatMoney( r.lesson_total ), formatMoney( r.tbt ), formatMoney( r.to_bill ) ].join( '\t' ) );
				} );
				lines.push( [ 'TOTAL', '', '', '', '', formatMoney( data.total ) ].join( '\t' ) );
				var text = lines.join( '\n' );
				if ( navigator.clipboard && navigator.clipboard.writeText ) {
					navigator.clipboard.writeText( text ).then( function () {
						summaryFeedback( 'Copied.' );
					}, function () {
						summaryFeedback( i18n.error || 'Error' );
					} );
				} else {
					var ta = document.createElement( 'textarea' );
					ta.value = text;
					document.body.appendChild( ta );
					ta.select();
					document.execCommand( 'copy' );
					ta.remove();
					summaryFeedback( 'Copied.' );
				}
			} );
		}

		if ( csvBtn ) {
			csvBtn.addEventListener( 'click', function () {
				var data = summaryData();
				if ( ! data ) { return; }
				function esc( value ) {
					var s = String( value );
					return /[",\n]/.test( s ) ? '"' + s.replace( /"/g, '""' ) + '"' : s;
				}
				var lines = [ data.headers.map( esc ).join( ',' ) ];
				data.rows.forEach( function ( r ) {
					lines.push( [ esc( r.student ), esc( r.company ), r.lessons, Number( r.lesson_total ).toFixed( 2 ), Number( r.tbt ).toFixed( 2 ), Number( r.to_bill ).toFixed( 2 ) ].join( ',' ) );
				} );
				lines.push( [ 'TOTAL', '', '', '', '', Number( data.total ).toFixed( 2 ) ].join( ',' ) );
				// BOM so Excel opens the UTF-8 file with Polish characters intact.
				var blob = new Blob( [ '\uFEFF' + lines.join( '\r\n' ) ], { type: 'text/csv;charset=utf-8' } );
				var a = document.createElement( 'a' );
				a.href = URL.createObjectURL( blob );
				a.download = 'settlement-' + data.period + '.csv';
				document.body.appendChild( a );
				a.click();
				a.remove();
				URL.revokeObjectURL( a.href );
			} );
		}
	}

	/* ---------------------------------------------------------------
	 * Events (receivables register)
	 * ------------------------------------------------------------- */

	function eventFilterArgs( page ) {
		return {
			year: page.getAttribute( 'data-year' ) || '',
			type: page.getAttribute( 'data-type' ) || '',
			state: page.getAttribute( 'data-state' ) || '',
			search: page.getAttribute( 'data-search' ) || ''
		};
	}

	/* KSEF records a fact that only exists for invoices. The server enforces
	   this on every write; the control is disabled here so the UI never offers
	   a tick that would be silently discarded. */
	function syncKsefField( form ) {
		if ( ! form ) { return; }
		var typeField = form.querySelector( '[data-field="type"]' );
		var ksef = form.querySelector( '[data-field="ksef"]' );
		if ( ! typeField || ! ksef ) { return; }
		var isInvoice = typeField.value === 'invoice';
		ksef.disabled = ! isInvoice;
		if ( ! isInvoice ) { ksef.checked = false; }
		var label = ksef.closest( 'label' );
		if ( label ) { label.classList.toggle( 'is-disabled', ! isInvoice ); }
	}

	/* Who — the entry point of the event form. Options are the register's own
	   classes (archived included, so a historical document can still be
	   recorded); the settle method on each one is what decides the document's
	   Type, so it rides along on every chip and every result row. */
	function initEventWho( form ) {
		var chipBox = document.getElementById( 'mmr-event-chips' );
		var search = document.getElementById( 'mmr-event-who-search' );
		var resultBox = document.getElementById( 'mmr-event-who-results' );
		var whoText = document.getElementById( 'mmr-event-who-text' );
		var typeSelect = form.querySelector( '[data-field="type"]' );
		var typeOut = document.getElementById( 'mmr-event-type-derived' );
		var typeNote = document.getElementById( 'mmr-event-type-note' );
		var root = document.getElementById( 'mmr-event-who' );
		if ( ! root || ! chipBox || ! search || ! typeSelect ) { return null; }

		var selected = [];
		var results = [];
		var timer = null;

		function names( list ) {
			return list.map( function ( c ) { return c.name; } ).join( ', ' );
		}

		/* The same rules as MMR_Data::derive_event_type(), which re-runs them
		   on save — this copy only decides what the form shows. */
		function derive() {
			if ( ! selected.length ) {
				return { resolved: false, reason: 'none', type: '', classes: [] };
			}
			var unmapped = selected.filter( function ( c ) { return ! c.type_hint; } );
			if ( unmapped.length ) {
				return { resolved: false, reason: 'unmapped', type: '', classes: unmapped };
			}
			var hints = [];
			selected.forEach( function ( c ) {
				if ( hints.indexOf( c.type_hint ) < 0 ) { hints.push( c.type_hint ); }
			} );
			if ( hints.length > 1 ) {
				return { resolved: false, reason: 'mixed', type: '', classes: selected };
			}
			return { resolved: true, reason: 'ok', type: hints[ 0 ], classes: [] };
		}

		function renderType() {
			var d = derive();
			if ( d.resolved ) {
				typeSelect.value = d.type;
				typeSelect.hidden = true;
				typeNote.hidden = true;
				typeOut.hidden = false;
				var methods = [];
				selected.forEach( function ( c ) {
					if ( c.settle_method && methods.indexOf( c.settle_method ) < 0 ) { methods.push( c.settle_method ); }
				} );
				typeOut.textContent = ( ( cfg.eventTypes || {} )[ d.type ] || d.type )
					+ ( methods.length ? ' · ' + ( i18n.typeFrom || 'from settle method %s' ).replace( '%s', methods.join( ', ' ) ) : '' );
			} else {
				// Unresolved: the user makes one explicit choice, and the note
				// says which classes made the answer ambiguous.
				typeOut.hidden = true;
				typeSelect.hidden = false;
				var message = '';
				if ( 'mixed' === d.reason ) {
					message = ( i18n.typeMixed || 'These classes settle differently: %s. Pick the type for this event.' ).replace( '%s', names( d.classes ) );
				} else if ( 'unmapped' === d.reason ) {
					message = ( i18n.typeUnmapped || 'No settle method on: %s. Pick the type for this event.' ).replace( '%s', names( d.classes ) );
				} else {
					message = i18n.typeFreeText || 'No class selected — pick the type for this event.';
				}
				typeNote.textContent = message;
				typeNote.hidden = false;
			}
			syncKsefField( form );
		}

		function renderChips() {
			chipBox.textContent = '';
			selected.forEach( function ( entry ) {
				var chip = el( 'span', { 'class': 'mmr-chip' } );
				chip.appendChild( el( 'span', { 'class': 'mmr-chip-name' }, entry.name ) );
				if ( entry.company ) {
					chip.appendChild( el( 'span', { 'class': 'mmr-chip-meta' }, entry.company ) );
				}
				chip.appendChild( el(
					'span',
					{ 'class': 'mmr-chip-badge' + ( entry.type_hint ? '' : ' mmr-chip-badge--none' ) },
					entry.settle_method || ( i18n.noMethod || 'no settle method' )
				) );
				var remove = el( 'button', {
					'type': 'button',
					'class': 'mmr-chip-remove',
					'aria-label': ( i18n.removeClass || 'Remove %s' ).replace( '%s', entry.name )
				}, '×' );
				remove.addEventListener( 'click', function () { drop( entry.id ); } );
				chip.appendChild( remove );
				// The chip *is* the field: this is what gets posted.
				chip.appendChild( el( 'input', { 'type': 'hidden', 'name': 'class_ids[]', 'value': String( entry.id ) } ) );
				chipBox.appendChild( chip );
			} );
			// With no class picked, the free-text box carries the requirement,
			// so the browser blocks an empty Who without custom validation.
			if ( whoText ) { whoText.required = ! selected.length; }
			renderType();
		}

		function add( entry ) {
			var exists = selected.some( function ( c ) { return c.id === entry.id; } );
			if ( ! exists ) { selected.push( entry ); }
			search.value = '';
			hideResults();
			renderChips();
		}

		function drop( id ) {
			selected = selected.filter( function ( c ) { return c.id !== id; } );
			renderChips();
		}

		function hideResults() {
			resultBox.hidden = true;
			resultBox.textContent = '';
		}

		function renderResults() {
			resultBox.textContent = '';
			var open = results.filter( function ( c ) {
				return ! selected.some( function ( s ) { return s.id === c.id; } );
			} );
			if ( ! open.length ) {
				resultBox.appendChild( el( 'div', { 'class': 'mmr-picker-empty' }, i18n.noResults || 'No results' ) );
				resultBox.hidden = false;
				return;
			}
			open.forEach( function ( entry ) {
				var item = el( 'div', { 'class': 'mmr-picker-item' } );
				var text = el( 'span', { 'class': 'mmr-pi-text' } );
				text.appendChild( el( 'span', { 'class': 'mmr-pi-name' }, entry.name ) );
				// Enough context to tell two similarly named classes apart.
				var meta = [ entry.company, 'archived' === entry.status ? ( i18n.archived || 'archived' ) : '' ]
					.filter( function ( part ) { return !! part; } ).join( ' · ' );
				if ( meta ) { text.appendChild( el( 'span', { 'class': 'mmr-pi-meta' }, meta ) ); }
				item.appendChild( text );
				item.appendChild( el(
					'span',
					{ 'class': 'mmr-chip-badge' + ( entry.type_hint ? '' : ' mmr-chip-badge--none' ) },
					entry.settle_method || ( i18n.noMethod || 'no settle method' )
				) );
				item.addEventListener( 'mousedown', function ( e ) {
					e.preventDefault();
					add( entry );
				} );
				resultBox.appendChild( item );
			} );
			resultBox.hidden = false;
		}

		function load() {
			ajax( 'mmr_search_classes', { term: search.value, all_statuses: 1 } ).then( function ( res ) {
				if ( ! res || ! res.success ) { return; }
				results = res.data.classes;
				renderResults();
			} );
		}

		search.addEventListener( 'input', function () {
			clearTimeout( timer );
			timer = setTimeout( load, 150 );
		} );
		// Focus alone opens the list: the options are the field, not a
		// separate action to reach for.
		search.addEventListener( 'focus', load );
		search.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key ) { hideResults(); }
		} );
		document.addEventListener( 'click', function ( e ) {
			if ( ! root.contains( e.target ) ) { hideResults(); }
		} );

		return {
			add: add,
			set: function ( list ) {
				selected = ( list || [] ).map( function ( c ) {
					return {
						id: Number( c.id ),
						name: c.name,
						company: c.company || '',
						settle_method: c.settle_method || '',
						type_hint: c.type_hint || ''
					};
				} );
				results = [];
				hideResults();
				search.value = '';
				renderChips();
			}
		};
	}

	function initEventFlags( page ) {
		var totals = {
			issued: document.getElementById( 'mmr-events-issued' ),
			received: document.getElementById( 'mmr-events-received' ),
			outstanding: document.getElementById( 'mmr-events-outstanding' )
		};

		page.addEventListener( 'change', function ( e ) {
			var box = e.target.closest( '.mmr-event-flag' );
			if ( ! box ) { return; }
			var row = box.closest( '.mmr-event-row' );
			if ( ! row ) { return; }

			var params = eventFilterArgs( page );
			params.id = row.getAttribute( 'data-id' );
			params.flag = box.getAttribute( 'data-flag' );
			params.value = box.checked ? 1 : 0;
			box.disabled = true;

			function rollback() {
				box.disabled = false;
				box.checked = ! box.checked; // Put the control back where it was.
				alert( i18n.error || 'Error' );
			}

			// The failure path is the second argument, not a trailing .catch:
			// a throw while re-rendering must not pretend the write failed.
			ajax( 'mmr_toggle_event_flag', params ).then( function ( res ) {
				box.disabled = false;
				if ( ! res || ! res.success ) {
					rollback();
					return;
				}
				var ev = res.data.event;
				row.querySelectorAll( '.mmr-event-flag' ).forEach( function ( input ) {
					var flag = input.getAttribute( 'data-flag' );
					if ( ev[ flag ] !== undefined ) { input.checked = !! Number( ev[ flag ] ); }
				} );
				var dateCell = row.querySelector( '.mmr-event-paid-date' );
				if ( dateCell ) { dateCell.textContent = ev.paid_date_display || ''; }
				row.classList.toggle( 'mmr-event--unpaid', ! Number( ev.paid ) );

				Object.keys( totals ).forEach( function ( key ) {
					if ( totals[ key ] ) { totals[ key ].textContent = res.data.totals[ key + '_display' ]; }
				} );
			}, rollback );
		} );
	}

	/* The amount picker: a panel inside the event modal that reads the
	   computed settlement, so no figure is ever retyped between pages. Who and
	   Type are no longer its business — the Who field owns both. */
	function initEventSuggestions( page, who ) {
		var panel = document.getElementById( 'mmr-suggest-panel' );
		var body = document.getElementById( 'mmr-suggest-body' );
		var labelOut = document.getElementById( 'mmr-suggest-label' );
		var openBtn = document.getElementById( 'mmr-take-settlement' );
		if ( ! panel || ! body || ! openBtn ) { return; }

		var form = panel.closest( 'form' );
		var period = '';

		function issuePeriod() {
			var field = form.querySelector( '[data-field="issue_date"]' );
			var value = field && field.value ? field.value : '';
			return /^\d{4}-\d{2}/.test( value ) ? value.slice( 0, 7 ) : '';
		}

		function shiftPeriod( value, months ) {
			var parts = value.split( '-' );
			var d = new Date( Number( parts[ 0 ] ), Number( parts[ 1 ] ) - 1 + months, 1 );
			var month = String( d.getMonth() + 1 );
			return d.getFullYear() + '-' + ( month.length < 2 ? '0' + month : month );
		}

		function useEntry( entry ) {
			fillForm( form, {
				amount: entry.amount,
				source_kind: entry.kind,
				source_id: entry.id,
				source_period: period
			} );
			// A class suggestion also names a payer, so it fills Who as well —
			// and Type then derives from that class's settle method, exactly
			// as it would have done had the class been picked by hand.
			if ( 'class' === entry.kind && who ) {
				who.add( {
					id: Number( entry.id ),
					name: entry.label,
					company: '',
					settle_method: entry.settle_method || '',
					type_hint: entry.type_hint || ''
				} );
			}
			panel.hidden = true;
		}

		function renderGroup( title, entries ) {
			var section = el( 'div', { 'class': 'mmr-suggest-group' } );
			section.appendChild( el( 'h4', null, title ) );
			entries.forEach( function ( entry ) {
				var row = el( 'div', { 'class': 'mmr-suggest-row' } );
				var text = el( 'div', { 'class': 'mmr-suggest-text' } );
				text.appendChild( el( 'span', { 'class': 'mmr-suggest-label' }, entry.label ) );
				text.appendChild( el( 'span', { 'class': 'mmr-suggest-detail' }, entry.detail ) );
				if ( entry.billed && entry.billed_note ) {
					text.appendChild( el( 'span', { 'class': 'mmr-suggest-billed' }, entry.billed_note ) );
				}
				row.appendChild( text );
				row.appendChild( el( 'span', { 'class': 'mmr-suggest-amount' }, entry.amount_display ) );
				var use = el( 'button', { 'type': 'button', 'class': 'button button-small' }, i18n.use || 'Use' );
				use.addEventListener( 'click', function () { useEntry( entry ); } );
				row.appendChild( use );
				section.appendChild( row );
			} );
			return section;
		}

		function load() {
			body.textContent = i18n.loading || 'Loading…';
			ajax( 'mmr_event_suggestions', { period: period } ).then( function ( res ) {
				if ( ! res || ! res.success ) {
					body.textContent = i18n.error || 'Error';
					return;
				}
				if ( labelOut ) { labelOut.textContent = res.data.label; }
				var groups = res.data.groups;
				var titles = {
					classes: i18n.suggestClasses || 'Classes',
					students: i18n.suggestStudents || 'Students',
					companies: i18n.suggestCompanies || 'Companies'
				};
				body.textContent = '';
				var any = false;
				[ 'classes', 'students', 'companies' ].forEach( function ( key ) {
					if ( groups[ key ] && groups[ key ].length ) {
						any = true;
						body.appendChild( renderGroup( titles[ key ], groups[ key ] ) );
					}
				} );
				if ( ! any ) {
					// Name the month, so it is obvious the period — not the
					// data — may be the thing that is wrong.
					body.appendChild( el( 'p', { 'class': 'mmr-muted' },
						( i18n.noSuggestions || 'No lessons booked in %s.' ).replace( '%s', res.data.label ) ) );
				}
			} ).catch( function () {
				body.textContent = i18n.error || 'Error';
			} );
		}

		function step( months ) {
			period = shiftPeriod( period, months );
			if ( labelOut ) { labelOut.textContent = ''; }
			load();
		}

		openBtn.addEventListener( 'click', function () {
			if ( ! panel.hidden ) {
				panel.hidden = true;
				return;
			}
			// The month of the Issue date in the form, which itself defaults
			// to today; the arrows step from there.
			period = issuePeriod() || period || new Date().toISOString().slice( 0, 7 );
			panel.hidden = false;
			load();
		} );
		document.getElementById( 'mmr-suggest-prev' ).addEventListener( 'click', function () { step( -1 ); } );
		document.getElementById( 'mmr-suggest-next' ).addEventListener( 'click', function () { step( 1 ); } );
		document.getElementById( 'mmr-suggest-close' ).addEventListener( 'click', function () { panel.hidden = true; } );

		// A fresh open of the modal starts from a closed panel.
		document.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '.mmr-open-modal' ) ) { panel.hidden = true; }
		} );
	}

	function initEvents() {
		var page = document.querySelector( '.mmr-events' );
		if ( ! page ) { return; }

		initEventFlags( page );

		var modal = document.getElementById( 'mmr-event-modal' );
		var form = modal ? modal.querySelector( 'form' ) : null;
		if ( ! form ) { return; }

		var who = initEventWho( form );
		initEventSuggestions( page, who );

		// Opening the modal — new or edit — rebuilds the chips and re-derives
		// Type from them. Fired by initModals once the form is reset/filled.
		form.addEventListener( 'mmr:fill', function ( e ) {
			var data = e.detail || {};
			if ( who ) {
				who.set( data.classes || [] );
			} else {
				syncKsefField( form );
			}
		} );

		form.addEventListener( 'change', function ( e ) {
			if ( e.target.matches( '[data-field="type"]' ) ) { syncKsefField( form ); }
			// Ticking PAID in the form suggests today, matching the list's
			// inline toggle; the date stays editable.
			if ( e.target.matches( '[data-field="paid"]' ) ) {
				var paidDate = form.querySelector( '[data-field="paid_date"]' );
				if ( paidDate && e.target.checked && ! paidDate.value ) {
					paidDate.value = new Date().toISOString().slice( 0, 10 );
				}
			}
		} );

	}

	/* ---------------------------------------------------------------
	 * Boot
	 * ------------------------------------------------------------- */

	document.addEventListener( 'DOMContentLoaded', function () {
		initModals();
		initMembersRepeater();
		initCompanyField();
		initConfirmForms();
		initCalendar();
		initSettlement();
		initEvents();
	} );

} )();
