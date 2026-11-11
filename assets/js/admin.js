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

	/* Member picks are set from code, so no native event says the member
	   list changed. Anything that mirrors it (the credit Paid by column)
	   listens for this instead. Fired on the rows box; it bubbles. */
	function membersChanged( node ) {
		if ( node ) {
			node.dispatchEvent( new CustomEvent( 'mmr:members-changed', { bubbles: true } ) );
		}
	}

	function clearMembers( modal ) {
		var rows = modal.querySelector( '#mmr-members-rows' );
		if ( rows ) { rows.innerHTML = ''; }
		membersChanged( rows );
	}

	function addMemberRow( modal, member ) {
		var tpl = modal.querySelector( '#mmr-member-row-template' );
		var rows = modal.querySelector( '#mmr-members-rows' );
		if ( ! tpl || ! rows ) { return; }
		var frag = tpl.content.cloneNode( true );
		var search = frag.querySelector( '.mmr-member-search' );
		if ( member ) {
			var idField = frag.querySelector( '.mmr-member-id' );
			var share = frag.querySelector( '.mmr-member-share' );
			// The name comes with the saved member, so an archived student who
			// is no longer offered in the list still shows and is kept on save.
			if ( idField ) { idField.value = member.student_id; }
			if ( search ) { search.value = member.name || ''; }
			if ( share ) { share.value = ( member.share === null || member.share === '' ) ? '' : member.share; }
		}
		rows.appendChild( frag );
		membersChanged( rows );
		// A row added by hand is for typing into straight away.
		if ( ! member && search ) { search.focus(); }
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
				if ( row ) {
					var rowsBox = row.parentNode;
					row.remove();
					membersChanged( rowsBox );
				}
			}
		} );
		initMemberSearch();
	}

	/* ---------------------------------------------------------------
	 * Class members — type-to-search student field. The visible box only
	 * finds a student; the hidden member_student_id[] input is what posts,
	 * exactly as the old <select> did, so saving is unchanged.
	 * ------------------------------------------------------------- */

	// Lower-case and fold Polish diacritics, so "lukasz" finds "Łukasz".
	function foldText( text ) {
		return String( text || '' )
			.toLowerCase()
			.replace( /ł/g, 'l' )
			.normalize( 'NFD' )
			.replace( /[̀-ͯ]/g, '' );
	}

	function initMemberSearch() {
		var dataNode = document.getElementById( 'mmr-member-students' );
		var rowsBox = document.getElementById( 'mmr-members-rows' );
		if ( ! dataNode || ! rowsBox ) { return; }
		var students = [];
		try { students = JSON.parse( dataNode.textContent ) || []; } catch ( err ) { students = []; }
		students.forEach( function ( s ) {
			s.id = Number( s.id );
			s.key = foldText( s.name );
			s.emailKey = foldText( s.email );
		} );

		var form = rowsBox.closest( 'form' );
		var activeIndex = -1;

		function parts( row ) {
			return {
				search: row.querySelector( '.mmr-member-search' ),
				id: row.querySelector( '.mmr-member-id' ),
				box: row.querySelector( '.mmr-member-results' )
			};
		}

		// Ids already picked in the other rows — no point offering them twice.
		function takenIds( exceptRow ) {
			var ids = [];
			rowsBox.querySelectorAll( '.mmr-member-row' ).forEach( function ( row ) {
				if ( row === exceptRow ) { return; }
				var id = Number( parts( row ).id.value );
				if ( id ) { ids.push( id ); }
			} );
			return ids;
		}

		function matches( term, exceptRow ) {
			var words = foldText( term ).split( /\s+/ ).filter( Boolean );
			var taken = takenIds( exceptRow );
			var found = students.filter( function ( s ) {
				if ( taken.indexOf( s.id ) >= 0 ) { return false; }
				return words.every( function ( w ) {
					return s.key.indexOf( w ) >= 0 || ( s.emailKey && s.emailKey.indexOf( w ) >= 0 );
				} );
			} );
			// Names that start with what was typed come first; the rest keep A–Z.
			if ( words.length ) {
				var first = words[ 0 ];
				found.sort( function ( a, b ) {
					var aw = a.key.split( /\s+/ ).some( function ( p ) { return p.indexOf( first ) === 0; } ) ? 0 : 1;
					var bw = b.key.split( /\s+/ ).some( function ( p ) { return p.indexOf( first ) === 0; } ) ? 0 : 1;
					return aw - bw;
				} );
			}
			return found;
		}

		function hide( row ) {
			var p = parts( row );
			p.box.hidden = true;
			p.box.textContent = '';
			activeIndex = -1;
		}

		function hideAll( exceptRow ) {
			rowsBox.querySelectorAll( '.mmr-member-row' ).forEach( function ( row ) {
				if ( row !== exceptRow ) { hide( row ); }
			} );
		}

		function pick( row, student ) {
			var p = parts( row );
			p.id.value = String( student.id );
			p.search.value = student.name;
			p.search.setCustomValidity( '' );
			hide( row );
			membersChanged( rowsBox );
			var share = row.querySelector( '.mmr-member-share' );
			if ( share ) { share.focus(); }
		}

		function highlight( row ) {
			var items = parts( row ).box.querySelectorAll( '.mmr-picker-item' );
			items.forEach( function ( item, i ) {
				item.classList.toggle( 'is-active', i === activeIndex );
			} );
			if ( items[ activeIndex ] ) { items[ activeIndex ].scrollIntoView( { block: 'nearest' } ); }
		}

		function show( row ) {
			var p = parts( row );
			hideAll( row );
			p.box.textContent = '';
			activeIndex = -1;
			var found = matches( p.search.value, row );
			if ( ! found.length ) {
				p.box.appendChild( el( 'div', { 'class': 'mmr-picker-empty' }, i18n.noStudents || 'No matching students' ) );
				p.box.hidden = false;
				return;
			}
			found.forEach( function ( student ) {
				var item = el( 'div', { 'class': 'mmr-picker-item' } );
				var text = el( 'span', { 'class': 'mmr-pi-text' } );
				text.appendChild( el( 'span', { 'class': 'mmr-pi-name' }, student.name ) );
				// Enough to tell two students with the same name apart.
				if ( student.email ) { text.appendChild( el( 'span', { 'class': 'mmr-pi-meta' }, student.email ) ); }
				item.appendChild( text );
				item.addEventListener( 'mousedown', function ( e ) {
					e.preventDefault();
					pick( row, student );
				} );
				p.box.appendChild( item );
			} );
			p.box.hidden = false;
			// With something typed, Enter takes the top suggestion.
			if ( p.search.value.trim() ) {
				activeIndex = 0;
				highlight( row );
			}
		}

		// Typing invalidates the earlier pick until a student is chosen again.
		rowsBox.addEventListener( 'input', function ( e ) {
			if ( ! e.target.classList.contains( 'mmr-member-search' ) ) { return; }
			var row = e.target.closest( '.mmr-member-row' );
			var p = parts( row );
			var hadPick = '0' !== p.id.value;
			p.id.value = '0';
			p.search.setCustomValidity( '' );
			show( row );
			if ( hadPick ) { membersChanged( rowsBox ); }
		} );

		// Focus alone opens the list, as on the Events "Who" field.
		rowsBox.addEventListener( 'focusin', function ( e ) {
			if ( ! e.target.classList.contains( 'mmr-member-search' ) ) { return; }
			var row = e.target.closest( '.mmr-member-row' );
			if ( '0' === parts( row ).id.value ) { show( row ); }
		} );

		// Leaving the box: an exact, unambiguous name still counts as a pick.
		rowsBox.addEventListener( 'focusout', function ( e ) {
			if ( ! e.target.classList.contains( 'mmr-member-search' ) ) { return; }
			var row = e.target.closest( '.mmr-member-row' );
			var p = parts( row );
			hide( row );
			if ( '0' !== p.id.value || ! p.search.value.trim() ) { return; }
			var typed = foldText( p.search.value.trim() );
			var exact = students.filter( function ( s ) { return s.key === typed; } );
			if ( 1 === exact.length && takenIds( row ).indexOf( exact[ 0 ].id ) < 0 ) {
				p.id.value = String( exact[ 0 ].id );
				p.search.value = exact[ 0 ].name;
				membersChanged( rowsBox );
			}
		} );

		rowsBox.addEventListener( 'keydown', function ( e ) {
			if ( ! e.target.classList.contains( 'mmr-member-search' ) ) { return; }
			var row = e.target.closest( '.mmr-member-row' );
			var p = parts( row );
			var open = ! p.box.hidden;
			var items = p.box.querySelectorAll( '.mmr-picker-item' );
			if ( 'ArrowDown' === e.key || 'ArrowUp' === e.key ) {
				e.preventDefault();
				if ( ! open ) { show( row ); return; }
				if ( ! items.length ) { return; }
				activeIndex += ( 'ArrowDown' === e.key ) ? 1 : -1;
				if ( activeIndex < 0 ) { activeIndex = items.length - 1; }
				if ( activeIndex >= items.length ) { activeIndex = 0; }
				highlight( row );
			} else if ( 'Enter' === e.key ) {
				// Enter in this box picks a student; it never submits the class.
				e.preventDefault();
				if ( open && items[ activeIndex ] ) {
					items[ activeIndex ].dispatchEvent( new MouseEvent( 'mousedown', { bubbles: true, cancelable: true } ) );
				}
			} else if ( 'Escape' === e.key && open ) {
				// First Escape closes the list, not the whole modal.
				e.stopPropagation();
				hide( row );
			}
		} );

		// Text that never became a student would otherwise be dropped silently
		// on save, so the browser blocks the save and says why.
		if ( form ) {
			form.addEventListener( 'submit', function ( e ) {
				var bad = null;
				rowsBox.querySelectorAll( '.mmr-member-row' ).forEach( function ( row ) {
					var p = parts( row );
					var unresolved = p.search.value.trim() && '0' === p.id.value;
					p.search.setCustomValidity( unresolved ? ( i18n.pickStudent || 'Pick a student from the list.' ) : '' );
					if ( unresolved && ! bad ) { bad = p.search; }
				} );
				if ( bad ) {
					e.preventDefault();
					bad.reportValidity();
				}
			} );
		}
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
	 * Class modal — prepaid credit repeater (2.8). Shown only while the
	 * settle method maps to rozliczenie. While hidden, every input in the
	 * box (credits_present included) is disabled, so nothing posts and the
	 * server leaves the stored payments untouched.
	 * ------------------------------------------------------------- */

	/* Display-only mirror of MMR_Data::event_type_hint() in
	   includes/class-mmr-data.php — that PHP function is the authority and
	   the server decides. Same order: invoice → revolut → cash/gotówka →
	   rozlicz, so "rozliczenie / cash" maps to cash here as it does there. */
	function settleTypeHint( method ) {
		var m = String( method || '' ).trim().toLowerCase();
		if ( '' === m ) { return ''; }
		if ( -1 !== m.indexOf( 'invoice' ) ) { return 'invoice'; }
		if ( -1 !== m.indexOf( 'revolut' ) ) { return 'revolut'; }
		if ( -1 !== m.indexOf( 'cash' ) || -1 !== m.indexOf( 'gotówka' ) ) { return 'cash'; }
		if ( /rozlicz/i.test( m ) ) { return 'rozliczenie'; }
		return '';
	}

	function creditRowsBox( box ) {
		return box ? box.querySelector( '#mmr-credit-rows' ) : null;
	}

	function updateCreditButton( box ) {
		var btn = box.querySelector( '#mmr-add-credit' );
		var rows = creditRowsBox( box );
		if ( ! btn || ! rows ) { return; }
		btn.textContent = rows.querySelector( '.mmr-credit-row' )
			? box.getAttribute( 'data-add-more' )
			: box.getAttribute( 'data-add-first' );
	}

	function clearCredits( box ) {
		var rows = creditRowsBox( box );
		if ( rows ) { rows.innerHTML = ''; }
		updateCreditButton( box );
	}

	/* Prepayments are paid by (2.10): three toggle buttons writing one
	   hidden input. NULL / unknown on the server reads as invoice. */
	function setPrepayMethod( box, value ) {
		var input = box.querySelector( '.mmr-prepay-input' );
		var valid = [ 'invoice', 'revolut', 'cash' ];
		if ( valid.indexOf( value ) < 0 ) { value = 'invoice'; }
		if ( input ) { input.value = value; }
		box.querySelectorAll( '.mmr-prepay-option' ).forEach( function ( btn ) {
			btn.setAttribute( 'aria-pressed', btn.getAttribute( 'data-prepay' ) === value ? 'true' : 'false' );
		} );
	}

	function creditTypeLabel( box, type ) {
		var labels = {};
		try { labels = JSON.parse( box.getAttribute( 'data-type-labels' ) || '{}' ) || {}; } catch ( err ) { labels = {}; }
		return labels[ type ] || type;
	}

	/* The Event cell of a saved payment (2.10): Create draft when it has no
	   event, else a chip — amber for a draft, green once issued. A new row
	   keeps the template's "Draft on save". */
	function renderCreditEvent( box, cell, credit ) {
		if ( ! cell || ! credit || ! Number( credit.id ) ) { return; }
		cell.textContent = '';
		if ( ! Number( credit.event_id ) ) {
			/* Submits the standalone form outside the modal (forms cannot
			   nest); the button's own name/value carries the payment ID. */
			cell.appendChild( el( 'button', {
				'type': 'submit',
				'class': 'button-link mmr-credit-create',
				'form': 'mmr-credit-draft-form',
				'name': 'credit_id',
				'value': String( credit.id ),
				'title': box.getAttribute( 'data-ev-create-title' ) || ''
			}, box.getAttribute( 'data-ev-create' ) || 'Create draft' ) );
			return;
		}
		var label = creditTypeLabel( box, credit.event_type );
		if ( Number( credit.event_draft ) ) {
			cell.appendChild( el( 'span', { 'class': 'mmr-credit-chip mmr-credit-chip--draft' },
				( box.getAttribute( 'data-ev-draft' ) || 'Draft %s' ).replace( '%s', label ) ) );
			return;
		}
		var text = label + ( ( 'invoice' === credit.event_type && credit.event_doc ) ? ' ' + credit.event_doc : '' );
		cell.appendChild( el( 'span', { 'class': 'mmr-credit-chip mmr-credit-chip--issued', 'title': text }, text ) );
	}

	/* A payment whose event is issued is a snapshot: date, amount and payer
	   are locked, and it cannot be removed. The payer select is disabled and
	   a hidden copy posts in its place (same position, so the row's arrays
	   stay aligned); data-locked keeps setCreditBoxVisible() from
	   re-enabling it. The server checks the locks again. */
	function lockCreditRow( box, frag, credit ) {
		var date = frag.querySelector( '.mmr-credit-date' );
		var amount = frag.querySelector( '.mmr-credit-amount' );
		var payer = frag.querySelector( '.mmr-credit-student' );
		var remove = frag.querySelector( '.mmr-remove-credit' );
		if ( date ) { date.readOnly = true; }
		if ( amount ) { amount.readOnly = true; }
		if ( payer ) {
			payer.setAttribute( 'data-locked', '1' );
			payer.disabled = true;
			payer.removeAttribute( 'name' );
			payer.parentNode.insertBefore( el( 'input', {
				'type': 'hidden',
				'name': 'credit_student[]',
				'class': 'mmr-credit-student-locked',
				'value': String( credit.student_id || 0 )
			} ), payer.nextSibling );
		}
		if ( remove ) {
			remove.disabled = true;
			remove.title = box.getAttribute( 'data-ev-locked' ) || 'Delete the event first';
		}
	}

	function addCreditRow( box, credit ) {
		var tpl = box.querySelector( '#mmr-credit-row-template' );
		var rows = creditRowsBox( box );
		if ( ! tpl || ! rows ) { return; }
		var frag = tpl.content.cloneNode( true );
		var date = frag.querySelector( '.mmr-credit-date' );
		var amount = frag.querySelector( '.mmr-credit-amount' );
		var note = frag.querySelector( '.mmr-credit-note' );
		var payer = frag.querySelector( '.mmr-credit-student' );
		var idInput = frag.querySelector( '.mmr-credit-id' );
		if ( credit ) {
			if ( idInput ) { idInput.value = String( Number( credit.id ) || 0 ); }
			renderCreditEvent( box, frag.querySelector( '.mmr-credit-event' ), credit );
			if ( Number( credit.id ) && Number( credit.event_id ) && ! Number( credit.event_draft ) ) {
				lockCreditRow( box, frag, credit );
			}
			/* The saved payer rides on the select, so it can be offered as
			   "(no longer in class)" once they are no longer a member row. */
			if ( payer && credit.student_id ) {
				payer.setAttribute( 'data-saved-id', String( credit.student_id ) );
				payer.setAttribute( 'data-saved-name', credit.student_name || '' );
				payer.setAttribute( 'data-want', String( credit.student_id ) );
			}
			if ( date ) { date.value = credit.date || ''; }
			if ( amount ) { amount.value = ( null === credit.amount || undefined === credit.amount ) ? '' : credit.amount; }
			if ( note ) { note.value = credit.note || ''; }
		} else if ( date ) {
			date.value = box.getAttribute( 'data-today' ) || '';
		}
		/* A row added while the box is hidden must not post either. */
		if ( box.hidden ) {
			frag.querySelectorAll( 'input, select' ).forEach( function ( input ) { input.disabled = true; } );
		}
		rows.appendChild( frag );
		syncCreditPayers( box );
		updateCreditButton( box );
		if ( ! credit && amount && ! box.hidden ) { amount.focus(); }
	}

	function populateCredits( box, credits ) {
		clearCredits( box );
		( credits || [] ).forEach( function ( c ) {
			addCreditRow( box, c );
		} );
	}

	/* Members currently picked in the modal: [ { id, name } ]. */
	function modalMembers( box ) {
		var form = box.closest( 'form' );
		var rows = form ? form.querySelector( '#mmr-members-rows' ) : null;
		var out = [];
		if ( ! rows ) { return out; }
		rows.querySelectorAll( '.mmr-member-row' ).forEach( function ( row ) {
			var id = row.querySelector( '.mmr-member-id' );
			var search = row.querySelector( '.mmr-member-search' );
			if ( id && '0' !== id.value && '' !== id.value ) {
				out.push( { id: id.value, name: search ? search.value.trim() : id.value } );
			}
		} );
		return out;
	}

	/* Per-member credit (2.9): rebuild every Paid by select from the member
	   rows, and show the column only while "Members pay separately" is
	   ticked and at least two members are picked. Outside that mode the
	   selects are hidden but not disabled, so a stored payer still posts and
	   switching modes never rewrites it. The server decides the mode again
	   from the posted data. */
	function syncCreditPayers( box ) {
		var form = box.closest( 'form' );
		var members = modalMembers( box );
		var ppm = form ? form.querySelector( '[data-field="pay_per_member"]' ) : null;
		var perMember = !! ( ppm && ppm.checked ) && members.length >= 2;

		box.querySelectorAll( '#mmr-credit-rows select.mmr-credit-student' ).forEach( function ( select ) {
			var want = select.getAttribute( 'data-want' );
			var current = ( null !== want ) ? want : select.value;
			select.removeAttribute( 'data-want' );
			var first = select.options[ 0 ];
			select.innerHTML = '';
			select.appendChild( first );
			var found = false;
			members.forEach( function ( m ) {
				var opt = el( 'option', { value: m.id }, m.name );
				select.appendChild( opt );
				if ( m.id === current ) { found = true; }
			} );
			var savedId = select.getAttribute( 'data-saved-id' );
			if ( ! found && savedId && savedId === current ) {
				var label = ( select.getAttribute( 'data-former' ) || '%s (no longer in class)' )
					.replace( '%s', select.getAttribute( 'data-saved-name' ) || ( '#' + savedId ) );
				select.appendChild( el( 'option', { value: savedId }, label ) );
				found = true;
			}
			select.value = found ? current : '0';
		} );

		box.classList.toggle( 'is-per-member', perMember );
		var hint = box.querySelector( '.mmr-credit-hint' );
		if ( hint ) {
			hint.textContent = hint.getAttribute( perMember ? 'data-per-member' : 'data-pot' );
		}
		var ppmHint = form ? form.querySelector( '#mmr-pay-per-member-hint' ) : null;
		if ( ppmHint && ppmHint.hasAttribute( 'data-plain' ) ) {
			ppmHint.textContent = ppmHint.getAttribute( box.hidden ? 'data-plain' : 'data-credit' );
		}
	}

	function setCreditBoxVisible( box, show ) {
		box.hidden = ! show;
		box.querySelectorAll( 'input, select' ).forEach( function ( input ) {
			input.disabled = ! show || input.hasAttribute( 'data-locked' );
		} );
		/* Shown with nothing entered yet: offer one row dated today. */
		if ( show ) {
			var rows = creditRowsBox( box );
			if ( rows && ! rows.querySelector( '.mmr-credit-row' ) ) {
				var tpl = box.querySelector( '#mmr-credit-row-template' );
				if ( tpl ) {
					rows.appendChild( tpl.content.cloneNode( true ) );
					var date = rows.querySelector( '.mmr-credit-date' );
					if ( date ) { date.value = box.getAttribute( 'data-today' ) || ''; }
					updateCreditButton( box );
				}
			}
		}
		syncCreditPayers( box );
	}

	function initCreditRepeater() {
		var box = document.getElementById( 'mmr-credit-box' );
		var settleInput = document.getElementById( 'mmr-class-settle' );
		if ( ! box || ! settleInput ) { return; }
		var form = box.closest( 'form' );

		function sync() {
			setCreditBoxVisible( box, 'rozliczenie' === settleTypeHint( settleInput.value ) );
		}

		if ( form ) {
			form.addEventListener( 'mmr:fill', function ( e ) {
				var detail = e.detail || {};
				box.hidden = true; // populate first, so new rows start disabled until sync().
				setPrepayMethod( box, detail.prepay_method || 'invoice' );
				populateCredits( box, detail.credits || [] );
				sync();
			} );
		}
		settleInput.addEventListener( 'input', sync );
		if ( form ) {
			form.addEventListener( 'mmr:members-changed', function () { syncCreditPayers( box ); } );
			var ppm = form.querySelector( '[data-field="pay_per_member"]' );
			if ( ppm ) { ppm.addEventListener( 'change', function () { syncCreditPayers( box ); } ); }
		}

		box.addEventListener( 'click', function ( e ) {
			var prepay = e.target.closest ? e.target.closest( '.mmr-prepay-option' ) : null;
			if ( prepay ) {
				e.preventDefault();
				setPrepayMethod( box, prepay.getAttribute( 'data-prepay' ) );
				return;
			}
			if ( e.target.id === 'mmr-add-credit' ) {
				e.preventDefault();
				addCreditRow( box, null );
			}
			if ( e.target.classList && e.target.classList.contains( 'mmr-remove-credit' ) ) {
				e.preventDefault();
				var row = e.target.closest( '.mmr-credit-row' );
				if ( row ) { row.remove(); }
				updateCreditButton( box );
			}
		} );

		sync();
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

	/* A class row plus its optional split row and its member-line sub-rows
	   (Members pay separately) move — and filter — as one unit. */
	function settlementGroups( tbody ) {
		var groups = [];
		var rows = tbody.querySelectorAll( 'tr.mmr-settle-row' );
		Array.prototype.forEach.call( rows, function ( row, index ) {
			var nodes = [ row ];
			var next = row.nextElementSibling;
			while ( next && ( next.classList.contains( 'mmr-split-row' ) || next.classList.contains( 'mmr-member-line' ) ) ) {
				nodes.push( next );
				next = next.nextElementSibling;
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
		initSettlementSelection();
		initSummaryExport();
	}

	/* Selection bar: tick uncovered lines, then create one event for them.
	   The client check only drives the button state — the server re-validates
	   payer, type, eligibility and coverage on submit. */
	function initSettlementSelection() {
		var table = document.querySelector( '.mmr-settlement-table' );
		var bar = document.getElementById( 'mmr-selection-form' );
		if ( ! table || ! bar ) { return; }

		var countOut = bar.querySelector( '.mmr-selection-bar__count' );
		var sumOut = bar.querySelector( '.mmr-selection-bar__sum' );
		var submit = bar.querySelector( '.mmr-selection-bar__submit' );
		var clear = bar.querySelector( '.mmr-selection-bar__clear' );
		var errorOut = bar.querySelector( '.mmr-selection-bar__error' );

		function ticked() {
			return Array.prototype.slice.call( table.querySelectorAll( 'input.mmr-line-check:checked' ) );
		}

		function update() {
			var boxes = ticked();
			var types = [];
			var payers = [];
			var sum = 0;

			table.querySelectorAll( 'input.mmr-line-check' ).forEach( function ( box ) {
				var row = box.closest( 'tr' );
				if ( row ) { row.classList.toggle( 'is-selected', box.checked ); }
			} );

			boxes.forEach( function ( box ) {
				var row = box.closest( 'tr' );
				var type = row.getAttribute( 'data-type' ) || '';
				var payer = row.getAttribute( 'data-payer-key' ) || '';
				if ( types.indexOf( type ) < 0 ) { types.push( type ); }
				if ( payers.indexOf( payer ) < 0 ) { payers.push( payer ); }
				/* Cents as integers, so the shown sum never drifts by a grosz. */
				sum += Math.round( ( parseFloat( row.getAttribute( 'data-amount' ) ) || 0 ) * 100 );
			} );

			var n = boxes.length;
			bar.hidden = 0 === n;
			if ( ! n ) { return; }

			countOut.textContent = 1 === n
				? bar.getAttribute( 'data-one' )
				: bar.getAttribute( 'data-many' ).replace( '%s', n );
			sumOut.textContent = '· ' + formatMoney( sum / 100 );
			submit.textContent = 1 === n ? bar.getAttribute( 'data-create' ) : bar.getAttribute( 'data-combine' );

			var message = '';
			if ( types.length > 1 ) {
				message = bar.getAttribute( 'data-mixed-type' );
			} else if ( payers.length > 1 ) {
				message = bar.getAttribute( 'data-mixed-payer' );
			}
			submit.disabled = '' !== message;
			errorOut.textContent = message;
			errorOut.hidden = '' === message;
		}

		table.addEventListener( 'change', function ( e ) {
			if ( e.target.matches( 'input.mmr-line-check' ) ) { update(); }
		} );
		clear.addEventListener( 'click', function () {
			ticked().forEach( function ( box ) { box.checked = false; } );
			update();
		} );
		bar.addEventListener( 'submit', function ( e ) {
			if ( submit.disabled || ! ticked().length ) { e.preventDefault(); }
		} );

		/* Browsers restore checkbox state on back/refresh. */
		update();
	}

	/* Client-side filter over the same data-* attributes the sort reads. The
	   filter is deliberately not persisted: a hidden stored filter would make
	   the table look like it had lost rows on the next visit. */
	function initSettlementFilter() {
		var table = document.querySelector( '.mmr-settlement-table' );
		var input = document.getElementById( 'mmr-class-filter' );
		if ( ! table || ! input ) { return; }

		var methodSelect = document.getElementById( 'mmr-method-filter' );
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
			/* The settle method is the dropdown's job; the text box only
			   matches class and company. */
			var method = methodSelect ? methodSelect.value.trim().toLowerCase() : '';
			var active = '' !== query || '' !== method;
			/* Credit-only rows (rozliczenie classes with no lesson this month)
			   exist only for the rozliczenie filter. */
			var showCreditOnly = /rozlicz/i.test( method );
			var groups = settlementGroups( tbody );
			var visible = 0;
			var counted = 0;
			var total = 0;

			groups.forEach( function ( group ) {
				var row = group.row;
				var creditOnly = row.classList.contains( 'mmr-credit-only' );
				var haystack = [
					row.dataset.sortClass || '',
					row.dataset.sortCompany || ''
				].join( ' ' ).toLowerCase();
				var match = ( '' === query || -1 !== haystack.indexOf( query ) )
					&& ( '' === method || ( row.dataset.sortMethod || '' ).trim().toLowerCase() === method );
				if ( creditOnly && ! showCreditOnly ) { match = false; }

				group.nodes.forEach( function ( node ) {
					node.classList.toggle( 'mmr-filtered-out', ! match );
				} );

				if ( ! creditOnly || showCreditOnly ) { counted++; }
				if ( match ) {
					visible++;
					total += parseFloat( row.dataset.classTotal ) || 0;
				}
			} );

			if ( methodSelect ) { methodSelect.classList.toggle( 'is-set', '' !== method ); }
			if ( clearBtn ) { clearBtn.hidden = ! active; }
			if ( countOut ) {
				countOut.textContent = ! active
					? ''
					: ( i18n.classesShown || 'Showing %1$s of %2$s classes' )
						.replace( '%1$s', visible )
						.replace( '%2$s', counted );
			}
			/* Only the table's own footer follows the filter. The Month total
			   and To bill chips stay full-month so the headline figures are
			   never a filtered half-truth. */
			if ( footTotal ) {
				footTotal.textContent = ! active ? fullTotal : formatMoney( total );
			}
			if ( footLabel ) {
				footLabel.textContent = ! active ? fullLabel : ( i18n.filteredTotal || 'Filtered total' );
			}
		}

		input.addEventListener( 'input', applyFilter );
		if ( methodSelect ) { methodSelect.addEventListener( 'change', applyFilter ); }
		if ( clearBtn ) {
			/* Clears the text only; the method dropdown keeps its value. */
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

		/* Rozliczenie draws down a prepayment: it is never in KSeF, never
		   sent and never paid, so those controls are hidden (the server
		   forces them to 0 anyway). */
		var isRozliczenie = typeField.value === 'rozliczenie';
		[ 'ksef', 'sent', 'paid' ].forEach( function ( flag ) {
			var box = form.querySelector( '[data-field="' + flag + '"]' );
			var wrap = box ? box.closest( 'label' ) : null;
			if ( ! box || ! wrap ) { return; }
			wrap.hidden = isRozliczenie;
			if ( isRozliczenie ) { box.checked = false; }
		} );
		var paidDate = form.querySelector( '[data-field="paid_date"]' );
		var paidDateWrap = paidDate ? paidDate.closest( '.mmr-field' ) : null;
		if ( paidDateWrap ) { paidDateWrap.hidden = isRozliczenie; }
		if ( paidDate && isRozliczenie ) { paidDate.value = ''; }
		var note = document.getElementById( 'mmr-event-rozliczenie-note' );
		if ( note ) { note.hidden = ! isRozliczenie; }
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
		/* A prepaid payment's event (2.10) keeps its stored type: it is never
		   re-derived from its class (which would give rozliczenie). The server
		   enforces the same rule in MMR_Data::resolve_event_who(). */
		var keepType = false;

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
			if ( keepType ) {
				typeOut.hidden = true;
				typeSelect.hidden = false;
				typeNote.textContent = i18n.typeCredit || 'Prepaid credit payment — the type is how the class’s prepayments are paid.';
				typeNote.hidden = false;
				syncKsefField( form );
				return;
			}
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
			set: function ( list, opts ) {
				keepType = !! ( opts && opts.keepType );
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
				/* Ticking Sent / Paid issues a draft (server-side auto-issue). */
				if ( ! Number( ev.is_draft ) ) {
					var draftChip = row.querySelector( '.mmr-draft-chip' );
					if ( draftChip ) { draftChip.parentNode.removeChild( draftChip ); }
				}
				row.classList.toggle( 'mmr-event--unpaid', ! Number( ev.paid ) && ! Number( ev.is_draft ) && 'rozliczenie' !== ev.type );

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
				who.set( data.classes || [], { keepType: 'credit' === data.source_kind } );
			} else {
				syncKsefField( form );
			}
			/* Draft status is shown, never edited — it clears itself on save
			   when a number is entered or Sent / Paid is ticked. */
			var draftStatus = document.getElementById( 'mmr-event-draft-status' );
			if ( draftStatus ) { draftStatus.hidden = ! Number( data.is_draft ); }
		} );

		/* ?edit={id} — deep link from the Settlement page's event panel: open
		   that row's edit modal on load. The link carries the issue year, so
		   the row is in the listed view. */
		var editId = new URLSearchParams( window.location.search ).get( 'edit' );
		if ( editId && /^\d+$/.test( editId ) ) {
			var editRow = page.querySelector( '.mmr-event-row[data-id="' + editId + '"]' );
			var editBtn = editRow ? editRow.querySelector( '.mmr-event-edit' ) : null;
			if ( editBtn ) { editBtn.click(); }
		}

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
		initCreditRepeater();
		initConfirmForms();
		initCalendar();
		initSettlement();
		initEvents();
	} );

} )();
