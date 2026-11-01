<?php
/**
 * Settlement page — read-only, computed monthly report.
 *
 * Nothing is stored: every number is derived from mmr_lessons (fee and
 * member-share snapshots), so any month, past or future, is reproducible.
 *
 * @package MM_Register
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$period = isset( $_GET['period'] ) ? mmr_sanitize_period( sanitize_text_field( wp_unslash( $_GET['period'] ) ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification
if ( ! $period ) {
	$period = current_time( 'Y-m' );
}
$report = MMR_Data::settlement( $period );

// Prev/next month for navigation.
$period_ts = strtotime( $period . '-01' );
$prev_period = wp_date( 'Y-m', strtotime( '-1 month', $period_ts ) );
$next_period = wp_date( 'Y-m', strtotime( '+1 month', $period_ts ) );

// Month lesson count, summed from the same rows the table renders — the
// settlement query already carries a per-class count, so nothing extra is read.
$month_lessons = 0;
foreach ( $report['classes'] as $row ) {
	$month_lessons += (int) $row['lesson_count'];
}
$lessons_status = mmr_status_for_month_count( $month_lessons );
$lessons_aria = sprintf(
	/* translators: 1: lesson count, 2: status in words, e.g. "on target". */
	__( 'Lessons this month: %1$s — %2$s', 'mm-register' ),
	$month_lessons,
	mmr_status_words( $lessons_status )
);

// Payload for the copy / CSV buttons.
$summary_json = array(
	'period'  => $period,
	'headers' => array(
		__( 'Student', 'mm-register' ),
		__( 'Company', 'mm-register' ),
		__( 'Lessons', 'mm-register' ),
		__( 'Lesson total', 'mm-register' ),
		__( 'TBT surcharge', 'mm-register' ),
		__( 'To bill', 'mm-register' ),
	),
	'rows'    => array(),
	'total'   => $report['to_bill_total'],
);
foreach ( $report['students'] as $s ) {
	$summary_json['rows'][] = array(
		'student'      => $s['student_name'],
		'company'      => $s['company'],
		'lessons'      => $s['lessons'],
		'lesson_total' => $s['lesson_total'],
		'tbt'          => $s['tbt'],
		'to_bill'      => $s['to_bill'],
	);
}

/*
 * Draft events (2.7). Billable lines and their coverage are read from the
 * same report the table renders — no figure is recomputed here. Events are
 * shown read-only; every change goes through a POST form.
 */
$lines = MMR_Data::billable_lines( $period, $report );
$coverage = MMR_Data::events_for_period( $period );
$lines_by_key = array();
$class_lines = array(); // class_id => lines.
$covered_count = 0;
$remaining = 0;
foreach ( $lines as $line ) {
	$lines_by_key[ $line['key'] ] = $line;
	$class_lines[ $line['class_id'] ][] = $line;
	if ( ! empty( $coverage[ $line['key'] ] ) ) {
		$covered_count++;
	} elseif ( '' === $line['ineligible_reason'] ) {
		$remaining++;
	}
}

// Per event: status, the current settlement figure of its lines, and drift.
$event_info = array();
$describe_event = function ( $event ) use ( $lines_by_key ) {
	$current = 0.0;
	foreach ( $event->line_keys as $key ) {
		if ( isset( $lines_by_key[ $key ] ) ) {
			$current += (float) $lines_by_key[ $key ]['amount'];
		}
	}
	return array(
		'event'   => $event,
		'status'  => MMR_Data::event_status( $event ),
		'current' => round( $current, 2 ),
		'drift'   => MMR_Data::event_drift( $event, $current ),
		'lines'   => count( $event->line_keys ),
	);
};
foreach ( $coverage as $events_of_line ) {
	foreach ( $events_of_line as $event ) {
		if ( ! isset( $event_info[ (int) $event->id ] ) ) {
			$event_info[ (int) $event->id ] = $describe_event( $event );
		}
	}
}

// Event panel: only for an event drafted from this month.
$panel = null;
$panel_id = isset( $_GET['event'] ) ? absint( $_GET['event'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
if ( $panel_id ) {
	if ( isset( $event_info[ $panel_id ] ) ) {
		$panel = $event_info[ $panel_id ];
	} else {
		$panel_event = MMR_Data::event( $panel_id );
		if ( $panel_event && $panel_event->source_period === $period ) {
			$panel_classes = MMR_Data::event_classes_map( array( $panel_id ) );
			$panel_event->line_keys = MMR_Data::line_keys_for_event( $panel_event, isset( $panel_classes[ $panel_id ] ) ? $panel_classes[ $panel_id ] : array() );
			$panel = $describe_event( $panel_event );
		}
	}
}

$type_labels = array(
	'invoice'     => __( 'Invoice', 'mm-register' ),
	'revolut'     => __( 'Revolut', 'mm-register' ),
	'cash'        => __( 'Cash', 'mm-register' ),
	'rozliczenie' => __( 'Rozliczenie', 'mm-register' ),
);

/**
 * The Event cell of one billable line: chips for covering events, or a
 * Create event button (disabled with the reason when ineligible).
 */
$render_event_cell = function ( $line ) use ( $coverage, $event_info, $period ) {
	$events = isset( $coverage[ $line['key'] ] ) ? $coverage[ $line['key'] ] : array();
	if ( empty( $events ) ) {
		if ( '' !== $line['ineligible_reason'] ) {
			echo '<button type="button" class="button button-small" disabled>' . esc_html__( 'Create event', 'mm-register' ) . '</button>';
			echo '<span class="mmr-line-reason">' . esc_html( $line['ineligible_reason'] ) . '</span>';
			return;
		}
		echo '<form method="post" class="mmr-inline-form">';
		wp_nonce_field( 'mmr_form' );
		echo '<input type="hidden" name="mmr_action" value="settle_create_event">';
		echo '<input type="hidden" name="period" value="' . esc_attr( $period ) . '">';
		echo '<input type="hidden" name="line_keys[]" value="' . esc_attr( $line['key'] ) . '">';
		echo '<button type="submit" class="button button-small">' . esc_html__( 'Create event', 'mm-register' ) . '</button>';
		echo '</form>';
		return;
	}
	echo '<span class="mmr-event-chips">';
	foreach ( $events as $event ) {
		$info = $event_info[ (int) $event->id ];
		$is_draft = 'draft' === $info['status']['key'];
		$text = $info['status']['label'] . ' · E-' . (int) $event->id;
		if ( $is_draft && null !== $info['drift'] ) {
			$text .= ' · ' . __( 'out of date', 'mm-register' );
			$modifier = 'warn';
		} else {
			if ( $info['lines'] > 1 ) {
				/* translators: %d: number of billable lines the event covers. */
				$text .= ' · ' . sprintf( _n( '%d line', '%d lines', $info['lines'], 'mm-register' ), $info['lines'] );
			}
			$modifier = $info['status']['key'];
		}
		$url = mmr_page_url( 'settlement', array( 'period' => $period, 'event' => (int) $event->id ) );
		echo '<a class="mmr-status-chip mmr-status-chip--' . esc_attr( $modifier ) . '" href="' . esc_url( $url ) . '">' . esc_html( $text ) . '</a>';
		if ( ! $is_draft && null !== $info['drift'] ) {
			echo '<span class="mmr-line-reason">' . esc_html( sprintf(
				/* translators: 1: amount on the issued event, 2: current settlement figure. */
				__( 'Billed %1$s — settlement is now %2$s', 'mm-register' ),
				mmr_money( $event->amount ),
				mmr_money( $info['drift'] )
			) ) . '</span>';
		}
	}
	echo '</span>';
};

/**
 * The selection checkbox of one billable line. Ticked keys submit with the
 * selection bar's form (the `form` attribute), so no JS builds the POST.
 */
$render_check_cell = function ( $line ) use ( $coverage ) {
	if ( ! empty( $coverage[ $line['key'] ] ) ) {
		return;
	}
	/* translators: %s: line name. */
	$aria = sprintf( __( 'Select %s', 'mm-register' ), $line['name'] );
	$disabled = '' !== $line['ineligible_reason'];
	echo '<input type="checkbox" class="mmr-line-check" form="mmr-selection-form" name="line_keys[]" value="' . esc_attr( $line['key'] ) . '"'
		. ' aria-label="' . esc_attr( $aria ) . '"'
		. ( $disabled ? ' disabled title="' . esc_attr( $line['ineligible_reason'] ) . '"' : '' ) . '>';
};

/** data-* attributes the selection bar reads from a line row. */
$line_attrs = function ( $line ) {
	return ' data-line-key="' . esc_attr( $line['key'] ) . '"'
		. ' data-payer-key="' . esc_attr( $line['payer_key'] ) . '"'
		. ' data-type="' . esc_attr( $line['type'] ) . '"'
		. ' data-amount="' . esc_attr( number_format( (float) $line['amount'], 2, '.', '' ) ) . '"';
};
?>
<div class="mmr-wrap mmr-settlement">
	<h1 class="mmr-page-title"><?php esc_html_e( 'Settlement', 'mm-register' ); ?>
		<span class="mmr-week-range"><?php echo esc_html( mmr_period_label( $period ) ); ?></span>
	</h1>

	<div class="mmr-toolbar">
		<div class="mmr-nav">
			<a class="button" href="<?php echo esc_url( mmr_page_url( 'settlement', array( 'period' => $prev_period ) ) ); ?>">‹ <?php echo esc_html( mmr_period_label( $prev_period ) ); ?></a>
			<a class="button" href="<?php echo esc_url( mmr_page_url( 'settlement', array( 'period' => $next_period ) ) ); ?>"><?php echo esc_html( mmr_period_label( $next_period ) ); ?> ›</a>
			<form method="get" class="mmr-inline-form">
				<input type="hidden" name="page" value="mmr-settlement">
				<label class="screen-reader-text" for="mmr-period-jump"><?php esc_html_e( 'Jump to month', 'mm-register' ); ?></label>
				<input type="month" id="mmr-period-jump" name="period" value="<?php echo esc_attr( $period ); ?>">
				<button type="submit" class="button"><?php esc_html_e( 'Go', 'mm-register' ); ?></button>
			</form>
		</div>
		<div class="mmr-totals">
			<span class="mmr-pill mmr-pill--lessons mmr-pill--<?php echo esc_attr( $lessons_status ); ?>" aria-label="<?php echo esc_attr( $lessons_aria ); ?>">
				<span class="mmr-pill-label"><?php esc_html_e( 'Lessons', 'mm-register' ); ?>:</span> <strong class="mmr-pill-value"><?php echo (int) $month_lessons; ?></strong>
			</span>
			<span class="mmr-total-chip"><?php esc_html_e( 'Month total', 'mm-register' ); ?>: <strong><?php echo mmr_money_esc( $report['month_to_bill'] ); ?></strong></span>
			<span class="mmr-total-chip mmr-total-month"><?php esc_html_e( 'To bill', 'mm-register' ); ?>: <strong><?php echo mmr_money_esc( $report['to_bill_total'] ); ?></strong></span>
			<?php if ( $lines ) : ?>
				<span class="mmr-total-chip mmr-events-chip<?php echo ( $covered_count === count( $lines ) ) ? ' is-complete' : ''; ?>">
					<?php
					/* translators: 1: lines with an event, 2: all billable lines. */
					printf( esc_html__( 'Events: %1$s of %2$s lines', 'mm-register' ), '<strong>' . (int) $covered_count . '</strong>', (int) count( $lines ) );
					?>
				</span>
			<?php endif; ?>
		</div>
		<?php if ( $lines ) : ?>
			<form method="post" class="mmr-inline-form mmr-create-remaining"
				<?php if ( $remaining ) : ?>
					data-confirm="<?php echo esc_attr( sprintf( /* translators: 1: number of drafts, 2: month, e.g. "October 2026". */ _n( 'Create %1$s draft event for %2$s?', 'Create %1$s draft events for %2$s?', $remaining, 'mm-register' ), $remaining, mmr_period_label( $period ) ) ); ?>"
				<?php endif; ?>>
				<?php wp_nonce_field( 'mmr_form' ); ?>
				<input type="hidden" name="mmr_action" value="settle_create_remaining">
				<input type="hidden" name="period" value="<?php echo esc_attr( $period ); ?>">
				<button type="submit" class="button button-primary" <?php disabled( 0, $remaining ); ?>>
					<?php
					/* translators: %d: number of uncovered, eligible lines. */
					printf( esc_html__( 'Create drafts for remaining (%d)', 'mm-register' ), (int) $remaining );
					?>
				</button>
			</form>
		<?php endif; ?>
	</div>

	<?php if ( empty( $report['classes'] ) ) : ?>
		<p><?php esc_html_e( 'No lessons booked in this month.', 'mm-register' ); ?></p>
	<?php else : ?>

	<h2 class="mmr-section-title"><?php esc_html_e( 'Per class', 'mm-register' ); ?></h2>

	<?php if ( $panel ) :
		$pe = $panel['event'];
		$p_draft = 'draft' === $panel['status']['key'];
		$p_type = (string) $pe->type;
		$p_class_names = array();
		foreach ( $report['classes'] as $row ) {
			$p_class_names[ (int) $row['class_id'] ] = $row['class_name'];
		}
		$p_hidden = function ( $do, $extra = array() ) use ( $period, $pe ) {
			wp_nonce_field( 'mmr_form' );
			echo '<input type="hidden" name="mmr_action" value="settle_event_action">';
			echo '<input type="hidden" name="period" value="' . esc_attr( $period ) . '">';
			echo '<input type="hidden" name="id" value="' . esc_attr( $pe->id ) . '">';
			echo '<input type="hidden" name="do" value="' . esc_attr( $do ) . '">';
			foreach ( $extra as $k => $v ) {
				echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
			}
		};
		if ( $p_draft ) {
			$p_help = array(
				'invoice'     => __( 'Add the invoice number in Events and tick KSeF. Entering the number issues the event and locks its amount.', 'mm-register' ),
				'revolut'     => __( 'Sending the Revolut request issues the event and locks its amount.', 'mm-register' ),
				'cash'        => __( 'Handing over cash is the payment: marking it received issues the event and locks its amount.', 'mm-register' ),
				'rozliczenie' => __( 'Rozliczenie records prepaid lessons used — it never counts as money owed. Confirming locks the amount.', 'mm-register' ),
			);
			$p_help = isset( $p_help[ $p_type ] ) ? $p_help[ $p_type ] : '';
		} else {
			$p_help = __( 'Locked. Lessons booked later no longer change this amount — the row shows a warning instead.', 'mm-register' );
		}
		$p_status_mod = ( $p_draft && null !== $panel['drift'] ) ? 'warn' : $panel['status']['key'];
		?>
		<section class="mmr-event-panel" aria-labelledby="mmr-event-panel-title">
			<header class="mmr-event-panel__head">
				<h3 id="mmr-event-panel-title">
					<?php
					/* translators: %d: event ID. */
					printf( esc_html__( 'Event E-%d', 'mm-register' ), (int) $pe->id );
					?>
				</h3>
				<span class="mmr-status-chip mmr-status-chip--<?php echo esc_attr( $p_status_mod ); ?>"><?php echo esc_html( $panel['status']['label'] ); ?></span>
				<span class="mmr-muted"><?php echo esc_html( isset( $type_labels[ $p_type ] ) ? $type_labels[ $p_type ] : $p_type ); ?></span>
				<strong class="mmr-event-panel__payer"><?php echo esc_html( $pe->payer_label ); ?></strong>
			</header>
			<table class="mmr-subtable mmr-event-panel__lines">
				<tbody>
					<?php foreach ( $pe->line_keys as $key ) :
						$pl = isset( $lines_by_key[ $key ] ) ? $lines_by_key[ $key ] : null;
						if ( $pl ) {
							/* translators: 1: student name, 2: class name. */
							$pl_name = $pl['student_id'] ? sprintf( __( '%1$s (%2$s)', 'mm-register' ), $pl['name'], $pl['class_name'] ) : $pl['name'];
						} else {
							$parts = explode( ':', $key );
							$pl_name = isset( $p_class_names[ (int) $parts[0] ] ) ? $p_class_names[ (int) $parts[0] ] : sprintf( '#%s', $key );
						}
						?>
						<tr>
							<td><?php echo esc_html( $pl_name ); ?></td>
							<td class="mmr-num">
								<?php if ( $pl ) : ?>
									<?php echo mmr_money_esc( $pl['amount'] ); ?>
								<?php else : ?>
									<span class="mmr-line-reason"><?php esc_html_e( 'No longer in this month’s settlement', 'mm-register' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="mmr-event-panel__act">
								<?php if ( $p_draft ) : ?>
									<form method="post" class="mmr-inline-form">
										<?php $p_hidden( 'remove_line', array( 'line_key' => $key ) ); ?>
										<button type="submit" class="button-link"><?php esc_html_e( 'Remove from event', 'mm-register' ); ?></button>
									</form>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
				<tfoot>
					<tr>
						<th><?php esc_html_e( 'Event amount', 'mm-register' ); ?></th>
						<th class="mmr-num"><?php echo mmr_money_esc( $pe->amount ); ?></th>
						<th>
							<?php if ( null !== $panel['drift'] ) : ?>
								<span class="mmr-line-reason">
									<?php
									/* translators: %s: current settlement figure. */
									printf( esc_html__( 'settlement now %s', 'mm-register' ), esc_html( mmr_money( $panel['drift'] ) ) );
									?>
								</span>
							<?php endif; ?>
						</th>
					</tr>
				</tfoot>
			</table>
			<?php if ( '' !== $p_help ) : ?>
				<p class="mmr-muted mmr-small"><?php echo esc_html( $p_help ); ?></p>
			<?php endif; ?>
			<div class="mmr-event-panel__actions">
				<?php if ( $p_draft && null !== $panel['drift'] ) : ?>
					<form method="post" class="mmr-inline-form"><?php $p_hidden( 'update' ); ?><button type="submit" class="button button-primary"><?php esc_html_e( 'Update draft to settlement', 'mm-register' ); ?></button></form>
				<?php endif; ?>
				<?php if ( 'invoice' === $p_type ) : ?>
					<a class="button" href="<?php echo esc_url( mmr_page_url( 'events', array( 'year' => (int) substr( $pe->issue_date, 0, 4 ), 'edit' => (int) $pe->id ) ) ); ?>"><?php esc_html_e( 'Open in Events', 'mm-register' ); ?></a>
				<?php endif; ?>
				<?php if ( $p_draft && 'revolut' === $p_type ) : ?>
					<form method="post" class="mmr-inline-form"><?php $p_hidden( 'issue' ); ?><button type="submit" class="button"><?php esc_html_e( 'Mark as requested', 'mm-register' ); ?></button></form>
				<?php endif; ?>
				<?php if ( $p_draft && 'cash' === $p_type ) : ?>
					<form method="post" class="mmr-inline-form"><?php $p_hidden( 'issue' ); ?><button type="submit" class="button"><?php esc_html_e( 'Mark as received', 'mm-register' ); ?></button></form>
				<?php endif; ?>
				<?php if ( $p_draft && 'rozliczenie' === $p_type ) : ?>
					<form method="post" class="mmr-inline-form"><?php $p_hidden( 'issue' ); ?><button type="submit" class="button"><?php esc_html_e( 'Confirm rozliczenie', 'mm-register' ); ?></button></form>
				<?php endif; ?>
				<?php if ( ! $p_draft && ! (int) $pe->paid && in_array( $p_type, array( 'invoice', 'revolut' ), true ) ) : ?>
					<form method="post" class="mmr-inline-form"><?php $p_hidden( 'pay' ); ?><button type="submit" class="button"><?php esc_html_e( 'Mark as paid', 'mm-register' ); ?></button></form>
				<?php endif; ?>
				<?php if ( $p_draft ) : ?>
					<form method="post" class="mmr-inline-form" data-confirm="<?php echo esc_attr( sprintf( /* translators: %d: event ID. */ __( 'Delete draft E-%d?', 'mm-register' ), (int) $pe->id ) ); ?>"><?php $p_hidden( 'delete' ); ?><button type="submit" class="button-link mmr-delete-btn"><?php esc_html_e( 'Delete draft', 'mm-register' ); ?></button></form>
				<?php endif; ?>
				<a class="button-link" href="<?php echo esc_url( mmr_page_url( 'settlement', array( 'period' => $period ) ) ); ?>"><?php esc_html_e( 'Close', 'mm-register' ); ?></a>
			</div>
		</section>
	<?php endif; ?>

	<div class="mmr-filterbar mmr-settle-filterbar">
		<label class="screen-reader-text" for="mmr-class-filter"><?php esc_html_e( 'Filter classes', 'mm-register' ); ?></label>
		<input type="search" id="mmr-class-filter" autocomplete="off"
			placeholder="<?php esc_attr_e( 'Filter by class, company or settle method…', 'mm-register' ); ?>">
		<button type="button" class="button" id="mmr-class-filter-clear" hidden><?php esc_html_e( 'Clear', 'mm-register' ); ?></button>
		<span class="mmr-muted" id="mmr-class-filter-count" role="status" aria-live="polite"></span>
	</div>
	<?php /* Sticky while at least one line is ticked. The ticked checkboxes belong to this form via their `form` attribute. */ ?>
	<form method="post" id="mmr-selection-form" class="mmr-selection-bar" hidden
		data-one="<?php esc_attr_e( '1 line selected', 'mm-register' ); ?>"
		data-many="<?php /* translators: %s: number of lines. */ esc_attr_e( '%s lines selected', 'mm-register' ); ?>"
		data-create="<?php esc_attr_e( 'Create event', 'mm-register' ); ?>"
		data-combine="<?php esc_attr_e( 'Combine into one event', 'mm-register' ); ?>"
		data-mixed-type="<?php esc_attr_e( 'Different settle methods can\'t share one event.', 'mm-register' ); ?>"
		data-mixed-payer="<?php esc_attr_e( 'Different payers can\'t share one event.', 'mm-register' ); ?>">
		<?php wp_nonce_field( 'mmr_form' ); ?>
		<input type="hidden" name="mmr_action" value="settle_create_event">
		<input type="hidden" name="period" value="<?php echo esc_attr( $period ); ?>">
		<span class="mmr-selection-bar__count" role="status" aria-live="polite"></span>
		<span class="mmr-selection-bar__sum"></span>
		<button type="submit" class="button button-primary mmr-selection-bar__submit"></button>
		<button type="button" class="button mmr-selection-bar__clear"><?php esc_html_e( 'Clear selection', 'mm-register' ); ?></button>
		<span class="mmr-selection-bar__error" hidden></span>
	</form>
	<div class="mmr-table-scroll">
		<table class="widefat striped mmr-table mmr-settlement-table">
			<thead>
				<tr>
					<th class="mmr-select-col"><span class="screen-reader-text"><?php esc_html_e( 'Select', 'mm-register' ); ?></span></th>
					<th class="mmr-sortable" data-mmr-sort="class" aria-sort="none">
						<button type="button" class="mmr-sort-btn">
							<span><?php esc_html_e( 'Class', 'mm-register' ); ?></span>
							<span class="mmr-sort-ind" aria-hidden="true"></span>
						</button>
					</th>
					<th><?php esc_html_e( 'Client type', 'mm-register' ); ?></th>
					<th class="mmr-sortable" data-mmr-sort="company" aria-sort="none">
						<button type="button" class="mmr-sort-btn">
							<span><?php esc_html_e( 'Company', 'mm-register' ); ?></span>
							<span class="mmr-sort-ind" aria-hidden="true"></span>
						</button>
					</th>
					<th class="mmr-sortable" data-mmr-sort="method" aria-sort="none">
						<button type="button" class="mmr-sort-btn">
							<span><?php esc_html_e( 'Settle method', 'mm-register' ); ?></span>
							<span class="mmr-sort-ind" aria-hidden="true"></span>
						</button>
					</th>
					<th class="mmr-num"><?php esc_html_e( 'Lessons', 'mm-register' ); ?></th>
					<th><?php esc_html_e( 'Rate (snapshot)', 'mm-register' ); ?></th>
					<th class="mmr-num"><?php esc_html_e( 'To bill', 'mm-register' ); ?></th>
					<th><?php esc_html_e( 'Members', 'mm-register' ); ?></th>
					<th class="mmr-event-col"><?php esc_html_e( 'Event', 'mm-register' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $report['classes'] as $row ) :
					$fee_parts = array();
					foreach ( $row['fees_used'] as $fee => $count ) {
						$fee_parts[] = mmr_money( $fee ) . ' ×' . $count;
					}
					$row_lines = isset( $class_lines[ (int) $row['class_id'] ] ) ? $class_lines[ (int) $row['class_id'] ] : array();
					// Members pay separately (≥ 2 members): the member sub-rows are
					// the billable lines; otherwise the class row is the one line.
					$member_lines = ( $row_lines && $row_lines[0]['student_id'] ) ? $row_lines : array();
					$class_line = ( $row_lines && ! $row_lines[0]['student_id'] ) ? $row_lines[0] : null;
					?>
					<tr class="mmr-settle-row<?php echo $member_lines ? ' mmr-per-member' : ''; ?>"
						data-sort-class="<?php echo esc_attr( $row['class_name'] ); ?>"
						data-sort-company="<?php echo esc_attr( $row['company'] ?? '' ); ?>"
						data-sort-method="<?php echo esc_attr( $row['settle_method'] ?? '' ); ?>"
						data-class-total="<?php echo esc_attr( $row['to_bill'] ); ?>"<?php echo $class_line ? $line_attrs( $class_line ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in $line_attrs. ?>>
						<td class="mmr-select-cell"><?php if ( $class_line ) { $render_check_cell( $class_line ); } ?></td>
						<td><strong><?php echo esc_html( $row['class_name'] ); ?></strong></td>
						<td><?php echo esc_html( $row['client_type'] ); ?></td>
						<td><?php echo esc_html( $row['company'] ); ?></td>
						<td><?php echo esc_html( $row['settle_method'] ); ?></td>
						<td class="mmr-num"><?php echo (int) $row['lesson_count']; ?></td>
						<td class="mmr-muted"><?php echo esc_html( implode( ', ', $fee_parts ) ); ?></td>
						<td class="mmr-num">
							<strong><?php echo mmr_money_esc( $row['to_bill'] ); ?></strong>
							<?php if ( $row['tbt_total'] > 0 ) : ?>
								<?php /* Say so, so a figure the lesson rates alone cannot explain never looks like an error. */ ?>
								<span class="mmr-muted mmr-small mmr-incl-tbt">
									<?php
									/* translators: %s: TBT surcharge included in the figure above. */
									printf( esc_html__( 'incl. TBT %s', 'mm-register' ), esc_html( mmr_money( $row['tbt_total'] ) ) );
									?>
								</span>
							<?php endif; ?>
						</td>
						<td>
							<?php echo count( $row['members'] ); ?>
							<?php if ( $row['members'] && ! $member_lines ) : ?>
								<button type="button" class="button-link mmr-toggle-split" aria-expanded="false"><?php esc_html_e( 'split ▾', 'mm-register' ); ?></button>
							<?php endif; ?>
						</td>
						<td class="mmr-event-cell">
							<?php if ( $member_lines ) : ?>
								<span class="mmr-muted mmr-small"><?php esc_html_e( 'One event per member ↓', 'mm-register' ); ?></span>
							<?php elseif ( $class_line ) : ?>
								<?php $render_event_cell( $class_line ); ?>
							<?php endif; ?>
						</td>
					</tr>
					<?php foreach ( $member_lines as $ml ) :
						$mb_row = null;
						foreach ( $row['members'] as $mb ) {
							if ( (int) $mb['student_id'] === (int) $ml['student_id'] ) {
								$mb_row = $mb;
								break;
							}
						}
						?>
						<tr class="mmr-member-line"<?php echo $line_attrs( $ml ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in $line_attrs. ?>>
							<td class="mmr-select-cell"><?php $render_check_cell( $ml ); ?></td>
							<td class="mmr-member-line__name"><?php echo esc_html( $ml['name'] ); ?></td>
							<td></td>
							<td></td>
							<td></td>
							<td class="mmr-num"><?php echo (int) $ml['lesson_count']; ?></td>
							<td class="mmr-muted">
								<?php if ( $mb_row ) : ?>
									<?php echo esc_html( mmr_money( $mb_row['lessons_amount'] ) ); ?>
								<?php endif; ?>
							</td>
							<td class="mmr-num">
								<?php echo mmr_money_esc( $ml['amount'] ); ?>
								<?php if ( $mb_row && $mb_row['tbt'] > 0 ) : ?>
									<span class="mmr-muted mmr-small mmr-incl-tbt">
										<?php
										/* translators: %s: TBT surcharge included in the figure above. */
										printf( esc_html__( 'incl. TBT %s', 'mm-register' ), esc_html( mmr_money( $mb_row['tbt'] ) ) );
										?>
									</span>
								<?php endif; ?>
							</td>
							<td></td>
							<td class="mmr-event-cell"><?php $render_event_cell( $ml ); ?></td>
						</tr>
					<?php endforeach; ?>
					<?php if ( $row['members'] && ! $member_lines ) : ?>
						<tr class="mmr-split-row" hidden>
							<td colspan="10">
								<table class="mmr-subtable">
									<thead>
										<tr>
											<th><?php esc_html_e( 'Member', 'mm-register' ); ?></th>
											<th class="mmr-num"><?php esc_html_e( 'Lessons', 'mm-register' ); ?></th>
											<th class="mmr-num"><?php esc_html_e( 'Lesson total', 'mm-register' ); ?></th>
											<th class="mmr-num"><?php esc_html_e( 'TBT', 'mm-register' ); ?></th>
											<th class="mmr-num"><?php esc_html_e( 'Total', 'mm-register' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $row['members'] as $mb ) : ?>
											<tr>
												<td><?php echo esc_html( $mb['student_name'] ); ?></td>
												<td class="mmr-num"><?php echo (int) $mb['lessons']; ?></td>
												<td class="mmr-num"><?php echo mmr_money_esc( $mb['lessons_amount'] ); ?></td>
												<td class="mmr-num"><?php echo $mb['tbt'] > 0 ? mmr_money_esc( $mb['tbt'] ) : '—'; ?></td>
												<td class="mmr-num"><strong><?php echo mmr_money_esc( $mb['total'] ); ?></strong></td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</td>
						</tr>
					<?php endif; ?>
				<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr>
					<th colspan="7" class="mmr-foot-label" id="mmr-class-foot-label"><?php esc_html_e( 'Month total', 'mm-register' ); ?></th>
					<th class="mmr-num" id="mmr-class-foot-total"><?php echo mmr_money_esc( $report['month_to_bill'] ); ?></th>
					<th></th>
					<th></th>
				</tr>
			</tfoot>
		</table>
	</div>

	<h2 class="mmr-section-title">
		<?php esc_html_e( 'Per student — to bill', 'mm-register' ); ?>
	</h2>
	<div class="mmr-toolbar">
		<div class="mmr-nav">
			<button type="button" class="button" id="mmr-copy-summary"><?php esc_html_e( 'Copy to clipboard', 'mm-register' ); ?></button>
			<button type="button" class="button" id="mmr-download-csv"><?php esc_html_e( 'Download CSV', 'mm-register' ); ?></button>
			<span class="mmr-muted mmr-small" id="mmr-copy-feedback" role="status" aria-live="polite"></span>
		</div>
	</div>
	<div class="mmr-table-scroll">
		<table class="widefat striped mmr-table mmr-student-summary-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Student', 'mm-register' ); ?></th>
					<th><?php esc_html_e( 'Company', 'mm-register' ); ?></th>
					<th class="mmr-num"><?php esc_html_e( 'Lessons', 'mm-register' ); ?></th>
					<th class="mmr-num"><?php esc_html_e( 'Lesson total', 'mm-register' ); ?></th>
					<th class="mmr-num"><?php esc_html_e( 'TBT surcharge', 'mm-register' ); ?></th>
					<th class="mmr-num"><?php esc_html_e( 'To bill', 'mm-register' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $report['students'] as $s ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $s['student_name'] ); ?></strong></td>
						<td><?php echo esc_html( $s['company'] ); ?></td>
						<td class="mmr-num"><?php echo (int) $s['lessons']; ?></td>
						<td class="mmr-num"><?php echo mmr_money_esc( $s['lesson_total'] ); ?></td>
						<td class="mmr-num"><?php echo $s['tbt'] > 0 ? mmr_money_esc( $s['tbt'] ) : '—'; ?></td>
						<td class="mmr-num"><strong><?php echo mmr_money_esc( $s['to_bill'] ); ?></strong></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr>
					<th colspan="5" class="mmr-foot-label"><?php esc_html_e( 'To bill total', 'mm-register' ); ?></th>
					<th class="mmr-num"><?php echo mmr_money_esc( $report['to_bill_total'] ); ?></th>
				</tr>
			</tfoot>
		</table>
	</div>

	<script type="application/json" id="mmr-settlement-summary-data"><?php echo wp_json_encode( $summary_json ); ?></script>

	<?php endif; ?>
</div>
