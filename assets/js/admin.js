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
				resetForm( form );
				if ( opener.getAttribute( 'data-json' ) ) {
					var data = JSON.parse( opener.getAttribute( 'data-json' ) );
					fillForm( form, data );
					if ( data.members ) {
						populateMembers( modal, data.members );
					}
				} else {
					clearMembers( modal );
				}
				var kind = opener.getAttribute( 'data-kind' );
				if ( kind ) {
					var kindField = form.querySelector( '[data-field="kind"]' );
					if ( kindField ) { kindField.value = kind; }
				}
				openModal( modal );
				return;
			}
			if ( e.target.closest( '.mmr-close-modal' ) ) {
				e.preventDefault();
				var box = e.target.closest( '.mmr-modal' );
				closeModal( box );
			}
			// Click on backdrop closes.
			if ( e.target.classList && e.target.classList.contains( 'mmr-modal' ) ) {
				closeModal( e.target );
			}
		} );
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' ) {
				document.querySelectorAll( '.mmr-modal:not([hidden])' ).forEach( closeModal );
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
				addMemberRow( e.target.closest( '.mmr-modal' ), null );
			}
			if ( e.target.classList && e.target.classList.contains( 'mmr-remove-member' ) ) {
				e.preventDefault();
				var row = e.target.closest( '.mmr-member-row' );
				if ( row ) { row.remove(); }
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
		var oldWeek = parseFloat( cal.getAttribute( 'data-week-total' ) || '0' );
		var weekTotal = 0;
		cal.querySelectorAll( '.mmr-col' ).forEach( function ( col ) {
			var dayTotal = 0;
			col.querySelectorAll( '.mmr-slot.is-filled' ).forEach( function ( slot ) {
				dayTotal += parseFloat( slot.getAttribute( 'data-fee' ) || '0' );
			} );
			var dayEl = col.querySelector( '.mmr-day-total' );
			if ( dayEl ) { dayEl.textContent = formatMoney( dayTotal ); }
			weekTotal += dayTotal;
		} );
		var weekEl = document.getElementById( 'mmr-week-total' );
		if ( weekEl ) { weekEl.textContent = formatMoney( weekTotal ); }

		var monthEl = document.getElementById( 'mmr-month-total' );
		if ( monthEl ) {
			var monthBase = parseFloat( cal.getAttribute( 'data-month-total' ) || '0' );
			monthBase += ( weekTotal - oldWeek );
			cal.setAttribute( 'data-month-total', String( monthBase ) );
			monthEl.textContent = formatMoney( monthBase );
		}
		cal.setAttribute( 'data-week-total', String( weekTotal ) );
	}

	function fillSlot( slot, lesson ) {
		slot.classList.remove( 'is-empty' );
		slot.classList.add( 'is-filled' );
		slot.setAttribute( 'data-lesson-id', lesson.id );
		slot.setAttribute( 'data-class-id', lesson.class_id );
		slot.setAttribute( 'data-fee', lesson.fee );
		slot.setAttribute( 'data-note', lesson.note || '' );
		slot.innerHTML = '';
		var name = el( 'span', { 'class': 'mmr-slot-name' }, lesson.class_name );
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

		var feeLabel = el( 'label', null, 'Fee override (zł)' );
		var feeInput = el( 'input', { 'type': 'text', 'value': slot.getAttribute( 'data-fee' ) || '' } );
		feeLabel.appendChild( feeInput );
		pop.appendChild( feeLabel );

		var noteLabel = el( 'label', null, 'Note' );
		var noteInput = el( 'input', { 'type': 'text', 'value': slot.getAttribute( 'data-note' ) || '' } );
		noteLabel.appendChild( noteInput );
		pop.appendChild( noteLabel );

		var moveLabel = el( 'label', null, 'Move to date / slot' );
		var moveDate = el( 'input', { 'type': 'date', 'value': slot.getAttribute( 'data-date' ) } );
		moveLabel.appendChild( moveDate );
		pop.appendChild( moveLabel );
		var moveSlot = el( 'input', { 'type': 'number', 'min': '1', 'max': String( cfg.slots || 12 ), 'value': slot.getAttribute( 'data-slot' ) } );
		pop.appendChild( moveSlot );

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

		// Seed month base so incremental deltas work.
		var monthEl = document.getElementById( 'mmr-month-total' );
		var weekEl = document.getElementById( 'mmr-week-total' );
		if ( weekEl ) {
			cal.setAttribute( 'data-week-total', String( parseMoney( weekEl.textContent ) ) );
		}
		if ( monthEl ) {
			cal.setAttribute( 'data-month-total', String( parseMoney( monthEl.textContent ) ) );
		}

		cal.addEventListener( 'click', function ( e ) {
			var slot = e.target.closest( '.mmr-slot' );
			if ( ! slot ) { return; }
			if ( slot.classList.contains( 'is-empty' ) ) {
				openPicker( slot, cal );
			} else {
				openPopover( slot, cal );
			}
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

	function parseMoney( text ) {
		return parseFloat( String( text ).replace( /[^\d,-]/g, '' ).replace( ',', '.' ) ) || 0;
	}

	function nextWeek( monday ) {
		var d = new Date( monday + 'T00:00:00' );
		d.setDate( d.getDate() + 7 );
		return d.toISOString().slice( 0, 10 );
	}

	/* ---------------------------------------------------------------
	 * Settlement
	 * ------------------------------------------------------------- */

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
				return;
			}

			var recBtn = e.target.closest( '.mmr-record-settlement' );
			if ( recBtn ) {
				e.preventDefault();
				openSettlementModal( JSON.parse( recBtn.getAttribute( 'data-json' ) ) );
			}
		} );

		var perMember = document.getElementById( 'mmr-per-member' );
		if ( perMember ) {
			perMember.addEventListener( 'change', function () {
				toggleSettlementMode( perMember.checked );
			} );
		}
	}

	function toggleSettlementMode( isPerMember ) {
		var single = document.getElementById( 'mmr-settle-single' );
		var pm = document.getElementById( 'mmr-settle-permember' );
		if ( single ) {
			single.hidden = isPerMember;
			single.querySelectorAll( 'input, textarea, select' ).forEach( function ( f ) { f.disabled = isPerMember; } );
		}
		if ( pm ) {
			pm.hidden = ! isPerMember;
			pm.querySelectorAll( 'input, textarea, select' ).forEach( function ( f ) { f.disabled = ! isPerMember; } );
		}
	}

	function openSettlementModal( data ) {
		var modal = document.getElementById( 'mmr-settlement-modal' );
		if ( ! modal ) { return; }
		var form = modal.querySelector( 'form' );
		resetForm( form );
		form.querySelector( '[data-field="class_id"]' ).value = data.class_id;
		var payer = form.querySelector( '[data-field="payer_label"]' );
		if ( payer ) { payer.value = data.class_name; }
		var amount = form.querySelector( '[data-field="amount"]' );
		if ( amount ) { amount.value = Number( data.amount ).toFixed( 2 ); }
		document.getElementById( 'mmr-settle-class' ).textContent = data.class_name;

		// Per-member rows.
		var pmRows = document.getElementById( 'mmr-settle-members-rows' );
		var toggleWrap = modal.querySelector( '.mmr-per-member-toggle' );
		pmRows.innerHTML = '';
		if ( data.is_group && data.members && data.members.length ) {
			toggleWrap.hidden = false;
			data.members.forEach( function ( m ) {
				var tr = el( 'tr' );
				var td1 = el( 'td' );
				td1.appendChild( el( 'span', null, m.student_name ) );
				td1.appendChild( el( 'input', { 'type': 'hidden', 'name': 'member_name[]', 'value': m.student_name } ) );
				var td2 = el( 'td' );
				var amt = el( 'input', { 'type': 'text', 'name': 'member_amount[]', 'value': Number( m.total ).toFixed( 2 ) } );
				td2.appendChild( amt );
				tr.appendChild( td1 );
				tr.appendChild( td2 );
				pmRows.appendChild( tr );
			} );
		} else {
			toggleWrap.hidden = true;
		}
		var perMember = document.getElementById( 'mmr-per-member' );
		if ( perMember ) { perMember.checked = false; }
		toggleSettlementMode( false );

		openModal( modal );
	}

	/* ---------------------------------------------------------------
	 * Receivables inline toggles
	 * ------------------------------------------------------------- */

	function initReceivables() {
		var table = document.querySelector( '.mmr-receivables-table' );
		if ( ! table ) { return; }
		table.addEventListener( 'change', function ( e ) {
			var toggle = e.target.closest( '.mmr-toggle' );
			if ( ! toggle ) { return; }
			var row = toggle.closest( 'tr' );
			var id = row.getAttribute( 'data-id' );
			var field = toggle.getAttribute( 'data-field' );
			ajax( 'mmr_toggle_settlement', { id: id, field: field, value: toggle.checked ? 1 : 0 } ).then( function ( res ) {
				if ( res && res.success ) {
					var totalEl = document.getElementById( 'mmr-unpaid-total' );
					if ( totalEl ) { totalEl.textContent = res.data.unpaid_total; }
					if ( field === 'paid' ) {
						var cell = row.querySelector( '.mmr-paid-on' );
						if ( cell ) { cell.textContent = res.data.paid_on ? formatDate( res.data.paid_on ) : '—'; }
						row.classList.toggle( 'mmr-paid', toggle.checked );
						row.classList.toggle( 'mmr-unpaid', ! toggle.checked );
					}
				} else {
					toggle.checked = ! toggle.checked;
					alert( i18n.error || 'Error' );
				}
			} );
		} );
	}

	function formatDate( ymd ) {
		var p = String( ymd ).split( '-' );
		if ( p.length === 3 ) { return parseInt( p[ 2 ], 10 ) + '.' + parseInt( p[ 1 ], 10 ) + '.' + p[ 0 ]; }
		return ymd;
	}

	/* ---------------------------------------------------------------
	 * Boot
	 * ------------------------------------------------------------- */

	document.addEventListener( 'DOMContentLoaded', function () {
		initModals();
		initMembersRepeater();
		initCalendar();
		initSettlement();
		initReceivables();
	} );

} )();
