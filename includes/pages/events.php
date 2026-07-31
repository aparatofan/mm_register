<?php
/**
 * Events page — the receivables register: what was issued, and who still
 * owes money.
 *
 * Unlike Settlement (which computes and stores nothing), every row here is a
 * record of a real document. Amounts are snapshots taken at the moment the
 * document was issued and never re-derive from lessons.
 *
 * @package MM_Register
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.NonceVerification -- read-only GET filters.
$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$year = isset( $_GET['year'] ) ? (int) $_GET['year'] : 0;
$type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '';
$state = isset( $_GET['state'] ) ? sanitize_key( wp_unslash( $_GET['state'] ) ) : 'all';
$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : '';
$order = isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : '';
// phpcs:enable

$types = MMR_Data::event_types();
if ( ! in_array( $type, $types, true ) ) {
	$type = '';
}
if ( ! in_array( $state, MMR_Data::event_states(), true ) ) {
	$state = 'all';
}
if ( ! in_array( $orderby, array( 'issue_date', 'payer_label', 'amount' ), true ) ) {
	$orderby = '';
}
$order = ( 'asc' === $order ) ? 'asc' : 'desc';

$today = current_time( 'Y-m-d' );
$this_year = (int) current_time( 'Y' );
$years = MMR_Data::event_years();
if ( ! in_array( $this_year, $years, true ) ) {
	array_unshift( $years, $this_year );
	rsort( $years );
}
if ( ! in_array( $year, $years, true ) ) {
	$year = $this_year;
}

$query_args = array(
	'year'    => $year,
	'type'    => $type,
	'state'   => $state,
	'search'  => $search,
	'orderby' => $orderby,
	'order'   => $order,
);
$events = MMR_Data::events( $query_args );
$totals = MMR_Data::event_totals( $query_args );

// Classes of every listed event, in one query — the edit modal restores the
// exact chip selection from this, and legacy rows simply have none.
$event_classes = MMR_Data::event_classes_map( wp_list_pluck( $events, 'id' ) );

$type_labels = array(
	'invoice' => __( 'invoice', 'mm-register' ),
	'revolut' => __( 'revolut', 'mm-register' ),
	'cash'    => __( 'cash', 'mm-register' ),
);
$state_labels = array(
	'all'     => __( 'All statuses', 'mm-register' ),
	'unpaid'  => __( 'Unpaid', 'mm-register' ),
	'paid'    => __( 'Paid', 'mm-register' ),
	'unsent'  => __( 'Not sent', 'mm-register' ),
	'no_ksef' => __( 'Invoice not in KSeF', 'mm-register' ),
);

// The chips follow the rows: say so in their labels, so a filtered total is
// never mistaken for the year's headline figure.
$is_filtered = ( '' !== $type || 'all' !== $state || '' !== $search );
$scope = $is_filtered
	/* translators: %s: selected year. */
	? sprintf( __( '%s · filtered', 'mm-register' ), $year )
	: (string) $year;

// Filters echoed back by row-action forms so saving/deleting keeps the view.
$filter_args = array(
	'year'  => $year,
	'type'  => $type,
	'state' => $state,
	's'     => $search,
);
if ( '' !== $orderby ) {
	$filter_args['orderby'] = $orderby;
	$filter_args['order'] = $order;
}

/**
 * Sortable column header, matching the Settlement page's markup. Events sort
 * server-side (the list can outgrow one screen), so the control is a link.
 *
 * @param string $key     Sort key.
 * @param string $label   Column label.
 * @param array  $current Current view args (year/type/state/s/orderby/order).
 * @return string Header HTML.
 */
if ( ! function_exists( 'mmr_events_sort_header' ) ) {
	function mmr_events_sort_header( $key, $label, $current ) {
		$active = ( $current['orderby'] === $key );
		// First click on a column sorts the way that column is most useful:
		// newest dates first, names A→Z, biggest amounts first.
		$default = ( 'payer_label' === $key ) ? 'asc' : 'desc';
		$next = $active ? ( 'asc' === $current['order'] ? 'desc' : 'asc' ) : $default;
		$url = mmr_page_url( 'events', array(
			'year'    => $current['year'],
			'type'    => $current['type'],
			'state'   => $current['state'],
			's'       => $current['search'],
			'orderby' => $key,
			'order'   => $next,
		) );
		$aria = $active ? ( 'asc' === $current['order'] ? 'ascending' : 'descending' ) : 'none';

		return '<th class="mmr-sortable" data-mmr-sort="' . esc_attr( $key ) . '" aria-sort="' . esc_attr( $aria ) . '">'
			. '<a class="mmr-sort-btn" href="' . esc_url( $url ) . '">'
			. '<span>' . esc_html( $label ) . '</span>'
			. '<span class="mmr-sort-ind" aria-hidden="true"></span>'
			. '</a></th>';
	}
}
?>
<div class="mmr-wrap mmr-events"
	data-year="<?php echo esc_attr( $year ); ?>"
	data-type="<?php echo esc_attr( $type ); ?>"
	data-state="<?php echo esc_attr( $state ); ?>"
	data-search="<?php echo esc_attr( $search ); ?>">
	<h1 class="mmr-page-title"><?php esc_html_e( 'Events', 'mm-register' ); ?>
		<span class="mmr-week-range"><?php echo esc_html( $year ); ?></span>
	</h1>

	<div class="mmr-toolbar">
		<div class="mmr-nav">
			<form method="get" class="mmr-filterbar mmr-events-filterbar">
				<input type="hidden" name="page" value="mmr-events">
				<?php if ( '' !== $orderby ) : ?>
					<input type="hidden" name="orderby" value="<?php echo esc_attr( $orderby ); ?>">
					<input type="hidden" name="order" value="<?php echo esc_attr( $order ); ?>">
				<?php endif; ?>
				<label class="screen-reader-text" for="mmr-event-year"><?php esc_html_e( 'Year', 'mm-register' ); ?></label>
				<select id="mmr-event-year" name="year">
					<?php foreach ( $years as $y ) : ?>
						<option value="<?php echo esc_attr( $y ); ?>" <?php selected( $year, $y ); ?>><?php echo esc_html( $y ); ?></option>
					<?php endforeach; ?>
				</select>
				<label class="screen-reader-text" for="mmr-event-type"><?php esc_html_e( 'Type', 'mm-register' ); ?></label>
				<select id="mmr-event-type" name="type">
					<option value=""><?php esc_html_e( 'All types', 'mm-register' ); ?></option>
					<?php foreach ( $types as $t ) : ?>
						<option value="<?php echo esc_attr( $t ); ?>" <?php selected( $type, $t ); ?>><?php echo esc_html( $type_labels[ $t ] ); ?></option>
					<?php endforeach; ?>
				</select>
				<label class="screen-reader-text" for="mmr-event-state"><?php esc_html_e( 'Status', 'mm-register' ); ?></label>
				<select id="mmr-event-state" name="state">
					<?php foreach ( $state_labels as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $state, $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<label class="screen-reader-text" for="mmr-event-search"><?php esc_html_e( 'Search events', 'mm-register' ); ?></label>
				<input type="search" id="mmr-event-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search who / document №…', 'mm-register' ); ?>">
				<button type="submit" class="button"><?php esc_html_e( 'Filter', 'mm-register' ); ?></button>
			</form>
			<button type="button" class="button button-primary mmr-open-modal" data-modal="mmr-event-modal" data-reset="1"><?php esc_html_e( '+ Add event', 'mm-register' ); ?></button>
		</div>
		<div class="mmr-totals">
			<span class="mmr-total-chip">
				<?php
				/* translators: %s: year, optionally with " · filtered". */
				printf( esc_html__( 'Issued (%s)', 'mm-register' ), esc_html( $scope ) );
				?>:
				<strong id="mmr-events-issued"><?php echo mmr_money_esc( $totals['issued'] ); ?></strong>
			</span>
			<span class="mmr-total-chip">
				<?php
				/* translators: %s: year, optionally with " · filtered". */
				printf( esc_html__( 'Received (%s)', 'mm-register' ), esc_html( $scope ) );
				?>:
				<strong id="mmr-events-received"><?php echo mmr_money_esc( $totals['received'] ); ?></strong>
			</span>
			<span class="mmr-total-chip mmr-total-month">
				<?php
				/* translators: %s: year, optionally with " · filtered". */
				printf( esc_html__( 'Outstanding (%s)', 'mm-register' ), esc_html( $scope ) );
				?>:
				<strong id="mmr-events-outstanding"><?php echo mmr_money_esc( $totals['outstanding'] ); ?></strong>
			</span>
		</div>
	</div>

	<table class="widefat striped mmr-table mmr-events-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Type', 'mm-register' ); ?></th>
				<th><?php esc_html_e( '#', 'mm-register' ); ?></th>
				<?php
				echo mmr_events_sort_header( 'issue_date', __( 'Issue date', 'mm-register' ), $query_args ); // phpcs:ignore WordPress.Security.EscapeOutput
				echo mmr_events_sort_header( 'payer_label', __( 'Who', 'mm-register' ), $query_args ); // phpcs:ignore WordPress.Security.EscapeOutput
				echo mmr_events_sort_header( 'amount', __( 'Amount', 'mm-register' ), $query_args ); // phpcs:ignore WordPress.Security.EscapeOutput
				?>
				<th class="mmr-check-col"><?php esc_html_e( 'KSEF', 'mm-register' ); ?></th>
				<th class="mmr-check-col"><?php esc_html_e( 'SENT', 'mm-register' ); ?></th>
				<th class="mmr-check-col"><?php esc_html_e( 'PAID', 'mm-register' ); ?></th>
				<th><?php esc_html_e( 'Paid date', 'mm-register' ); ?></th>
				<th><?php esc_html_e( 'Notes', 'mm-register' ); ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $events ) ) : ?>
				<tr><td colspan="11"><?php esc_html_e( 'No events yet. Add one, or take the amounts from a month\'s settlement.', 'mm-register' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $events as $event ) :
				$is_invoice = ( 'invoice' === $event->type );
				$is_paid = (bool) $event->paid;
				$classes_of_event = isset( $event_classes[ (int) $event->id ] ) ? $event_classes[ (int) $event->id ] : array();
				// A pre-2.3 event has no classes and no free-text field — its
				// whole "who" is the label. Show that label in the free-text
				// box so it survives a re-save verbatim; never guess which
				// class an old string meant.
				$who_text = (string) $event->who_text;
				if ( '' === $who_text && empty( $classes_of_event ) ) {
					$who_text = (string) $event->payer_label;
				}
				$json = wp_json_encode( array(
					'id'            => (int) $event->id,
					'type'          => $event->type,
					'doc_number'    => $event->doc_number,
					'issue_date'    => $event->issue_date,
					'classes'       => $classes_of_event,
					'who_text'      => $who_text,
					'amount'        => $event->amount,
					'ksef'          => (int) $event->ksef,
					'sent'          => (int) $event->sent,
					'paid'          => (int) $event->paid,
					'paid_date'     => (string) $event->paid_date,
					'notes'         => $event->notes,
					'source_kind'   => (string) $event->source_kind,
					'source_id'     => (int) $event->source_id,
					'source_period' => (string) $event->source_period,
				) );
				$delete_confirm = sprintf(
					/* translators: 1: payer label, 2: amount. */
					__( 'Delete the event for %1$s (%2$s)? This removes the row for good — the document itself stays in KSeF.', 'mm-register' ),
					$event->payer_label,
					mmr_money( $event->amount )
				);
				?>
				<tr class="mmr-event-row<?php echo $is_paid ? '' : ' mmr-event--unpaid'; ?>" data-id="<?php echo esc_attr( $event->id ); ?>">
					<td><?php echo esc_html( isset( $type_labels[ $event->type ] ) ? $type_labels[ $event->type ] : $event->type ); ?></td>
					<td><span class="mmr-ellipsis" title="<?php echo esc_attr( $event->doc_number ); ?>"><?php echo esc_html( $event->doc_number ); ?></span></td>
					<td><?php echo esc_html( mmr_date_label( $event->issue_date ) ); ?></td>
					<td><strong class="mmr-ellipsis" title="<?php echo esc_attr( $event->payer_label ); ?>"><?php echo esc_html( $event->payer_label ); ?></strong></td>
					<td class="mmr-num"><strong><?php echo mmr_money_esc( $event->amount ); ?></strong></td>
					<td class="mmr-check-cell">
						<?php if ( $is_invoice ) : ?>
							<input type="checkbox" class="mmr-event-flag" data-flag="ksef" <?php checked( (int) $event->ksef, 1 ); ?>
								aria-label="<?php echo esc_attr( sprintf( /* translators: %s: payer label. */ __( 'In KSeF — %s', 'mm-register' ), $event->payer_label ) ); ?>">
						<?php else : ?>
							<span class="mmr-muted" title="<?php esc_attr_e( 'KSeF applies to invoices only', 'mm-register' ); ?>">—</span>
						<?php endif; ?>
					</td>
					<td class="mmr-check-cell">
						<input type="checkbox" class="mmr-event-flag" data-flag="sent" <?php checked( (int) $event->sent, 1 ); ?>
							aria-label="<?php echo esc_attr( sprintf( /* translators: %s: payer label. */ __( 'Sent — %s', 'mm-register' ), $event->payer_label ) ); ?>">
					</td>
					<td class="mmr-check-cell">
						<input type="checkbox" class="mmr-event-flag" data-flag="paid" <?php checked( (int) $event->paid, 1 ); ?>
							aria-label="<?php echo esc_attr( sprintf( /* translators: %s: payer label. */ __( 'Paid — %s', 'mm-register' ), $event->payer_label ) ); ?>">
					</td>
					<td class="mmr-event-paid-date"><?php echo esc_html( mmr_date_label( $event->paid_date ) ); ?></td>
					<td><span class="mmr-ellipsis" title="<?php echo esc_attr( $event->notes ); ?>"><?php echo esc_html( $event->notes ); ?></span></td>
					<td class="mmr-row-actions">
						<button type="button" class="button button-small mmr-open-modal" data-modal="mmr-event-modal" data-json="<?php echo esc_attr( $json ); ?>"><?php esc_html_e( 'Edit', 'mm-register' ); ?></button>
						<form method="post" class="mmr-inline-form" data-confirm="<?php echo esc_attr( $delete_confirm ); ?>">
							<?php wp_nonce_field( 'mmr_form' ); ?>
							<input type="hidden" name="mmr_action" value="delete_event">
							<input type="hidden" name="id" value="<?php echo esc_attr( $event->id ); ?>">
							<?php foreach ( $filter_args as $fk => $fv ) : ?>
								<input type="hidden" name="filter_<?php echo esc_attr( $fk ); ?>" value="<?php echo esc_attr( $fv ); ?>">
							<?php endforeach; ?>
							<button type="submit" class="button-link mmr-delete-btn"><?php esc_html_e( 'Delete', 'mm-register' ); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<!-- Event modal -->
	<div class="mmr-modal-overlay" id="mmr-event-modal" hidden>
		<div class="mmr-modal" role="dialog" aria-modal="true" aria-labelledby="mmr-event-modal-title">
			<div class="mmr-modal__body">
				<h2 class="mmr-modal-title" id="mmr-event-modal-title"><?php esc_html_e( 'Event', 'mm-register' ); ?></h2>
				<form method="post">
					<?php wp_nonce_field( 'mmr_form' ); ?>
					<input type="hidden" name="mmr_action" value="save_event">
					<input type="hidden" name="id" value="0" data-field="id">
					<?php /* Provenance of the amount: written by the picker, kept even if the figure is edited afterwards. */ ?>
					<input type="hidden" name="source_kind" value="" data-field="source_kind">
					<input type="hidden" name="source_id" value="" data-field="source_id">
					<input type="hidden" name="source_period" value="" data-field="source_period">
					<?php foreach ( $filter_args as $fk => $fv ) : ?>
						<input type="hidden" name="filter_<?php echo esc_attr( $fk ); ?>" value="<?php echo esc_attr( $fv ); ?>">
					<?php endforeach; ?>
					<div class="mmr-form-grid">
						<?php /* Who is the entry point of this form: the classes billed by
						   this document, taken from the register itself, plus a free-text
						   escape hatch for a payer that has no class on record. Type is
						   derived from what is picked here. */ ?>
						<div class="mmr-field mmr-field--full mmr-who" id="mmr-event-who">
							<label for="mmr-event-who-search"><?php esc_html_e( 'Who', 'mm-register' ); ?> *</label>
							<div class="mmr-chips" id="mmr-event-chips"></div>
							<div class="mmr-who-picker">
								<input type="search" id="mmr-event-who-search" class="mmr-who-search" autocomplete="off"
									placeholder="<?php esc_attr_e( 'Search classes…', 'mm-register' ); ?>"
									aria-describedby="mmr-event-who-hint">
								<div class="mmr-who-results" id="mmr-event-who-results" hidden></div>
							</div>
							<p class="mmr-muted mmr-small" id="mmr-event-who-hint"><?php esc_html_e( 'Pick one or more classes. Archived classes are listed too, so past documents can still be recorded.', 'mm-register' ); ?></p>
							<label class="mmr-who-text-label" for="mmr-event-who-text"><?php esc_html_e( 'Other payer (free text)', 'mm-register' ); ?></label>
							<input type="text" id="mmr-event-who-text" name="who_text" data-field="who_text"
								placeholder="<?php esc_attr_e( 'Someone with no class on record', 'mm-register' ); ?>">
						</div>
						<?php /* Derived, not chosen: the selector only appears when the
						   settle methods cannot agree on one answer. */ ?>
						<div class="mmr-field" id="mmr-event-type-wrap">
							<label for="mmr-event-type-field"><?php esc_html_e( 'Type', 'mm-register' ); ?></label>
							<p class="mmr-derived" id="mmr-event-type-derived" role="status" aria-live="polite"></p>
							<select id="mmr-event-type-field" name="type" data-field="type" hidden>
								<?php foreach ( $types as $t ) : ?>
									<option value="<?php echo esc_attr( $t ); ?>"><?php echo esc_html( $type_labels[ $t ] ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="mmr-who-warning" id="mmr-event-type-note" hidden></p>
						</div>
						<div class="mmr-field">
							<label for="mmr-event-issue-date"><?php esc_html_e( 'Issue date', 'mm-register' ); ?></label>
							<input type="date" id="mmr-event-issue-date" name="issue_date" data-field="issue_date" value="<?php echo esc_attr( $today ); ?>">
						</div>
						<div class="mmr-field">
							<label for="mmr-event-amount"><?php esc_html_e( 'Amount (zł)', 'mm-register' ); ?></label>
							<input type="text" id="mmr-event-amount" name="amount" data-field="amount" inputmode="decimal">
							<button type="button" class="button button-small mmr-field-action" id="mmr-take-settlement"><?php esc_html_e( 'Take amount from settlement', 'mm-register' ); ?></button>
						</div>
						<div class="mmr-field">
							<label for="mmr-event-doc"><?php esc_html_e( 'Document number', 'mm-register' ); ?></label>
							<input type="text" id="mmr-event-doc" name="doc_number" data-field="doc_number" placeholder="<?php esc_attr_e( 'KSeF ID, “czekam na PO”, …', 'mm-register' ); ?>">
						</div>
						<div class="mmr-field">
							<label for="mmr-event-paid-date"><?php esc_html_e( 'Paid date', 'mm-register' ); ?></label>
							<input type="date" id="mmr-event-paid-date" name="paid_date" data-field="paid_date">
						</div>
						<div class="mmr-field mmr-field--checks">
							<span class="mmr-field-legend"><?php esc_html_e( 'Status', 'mm-register' ); ?></span>
							<label class="mmr-check"><input type="checkbox" name="ksef" value="1" data-field="ksef" id="mmr-event-ksef"> <?php esc_html_e( 'KSEF', 'mm-register' ); ?></label>
							<label class="mmr-check"><input type="checkbox" name="sent" value="1" data-field="sent"> <?php esc_html_e( 'SENT', 'mm-register' ); ?></label>
							<label class="mmr-check"><input type="checkbox" name="paid" value="1" data-field="paid" id="mmr-event-paid"> <?php esc_html_e( 'PAID', 'mm-register' ); ?></label>
						</div>
						<div class="mmr-field mmr-field--full">
							<label for="mmr-event-notes"><?php esc_html_e( 'Notes', 'mm-register' ); ?></label>
							<textarea id="mmr-event-notes" name="notes" data-field="notes" rows="3" placeholder="<?php esc_attr_e( 'NIP, address, “for the whole course — January–June”…', 'mm-register' ); ?>"></textarea>
						</div>
					</div>

					<?php /* Amount picker — a panel inside this modal, never a second modal. */ ?>
					<div class="mmr-suggest" id="mmr-suggest-panel" hidden>
						<div class="mmr-suggest-head">
							<button type="button" class="button button-small" id="mmr-suggest-prev" aria-label="<?php esc_attr_e( 'Previous month', 'mm-register' ); ?>">‹</button>
							<strong id="mmr-suggest-label" role="status" aria-live="polite"></strong>
							<button type="button" class="button button-small" id="mmr-suggest-next" aria-label="<?php esc_attr_e( 'Next month', 'mm-register' ); ?>">›</button>
							<button type="button" class="button button-small mmr-suggest-close" id="mmr-suggest-close"><?php esc_html_e( 'Close', 'mm-register' ); ?></button>
						</div>
						<div class="mmr-suggest-body" id="mmr-suggest-body"></div>
					</div>

					<p class="mmr-modal-actions">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'mm-register' ); ?></button>
						<button type="button" class="button mmr-close-modal"><?php esc_html_e( 'Cancel', 'mm-register' ); ?></button>
					</p>
				</form>
			</div>
		</div>
	</div>
</div>
