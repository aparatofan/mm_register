<?php
/**
 * JSON importer / exporter.
 *
 * The import JSON format is documented in docs/import-format.md.
 * PhpSpreadsheet is deliberately kept OUT of this plugin — the JSON is
 * produced separately from REGISTER_2026.xlsx.
 *
 * @package MM_Register
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles importing a JSON payload and exporting a full JSON backup.
 */
class MMR_Import {

	/**
	 * Run an import.
	 *
	 * @param string $json Raw JSON string.
	 * @param array  $opts commit (bool), merge (bool).
	 * @return array Result: counts, warnings, error, blocked, committed.
	 */
	public function run( $json, $opts = array() ) {
		$commit = ! empty( $opts['commit'] );
		$merge = ! empty( $opts['merge'] );

		$result = array(
			'counts'    => array(),
			'warnings'  => array(),
			'error'     => '',
			'blocked'   => false,
			'committed' => false,
		);

		$data = json_decode( (string) $json, true );
		if ( null === $data || ! is_array( $data ) ) {
			$result['error'] = __( 'Could not parse JSON. Check the file is valid.', 'mm-register' );
			return $result;
		}

		// Guard: refuse to write into non-empty tables unless merging.
		if ( ! MMR_DB::is_empty() && ! $merge ) {
			$result['blocked'] = true;
			$result['error'] = __( 'Existing data found. Enable "merge" to import anyway.', 'mm-register' );
			// Still compute counts so the operator sees what would be imported.
		}

		$students = isset( $data['students'] ) && is_array( $data['students'] ) ? $data['students'] : array();
		$classes  = isset( $data['classes'] ) && is_array( $data['classes'] ) ? $data['classes'] : array();
		$lessons  = isset( $data['lessons'] ) && is_array( $data['lessons'] ) ? $data['lessons'] : array();

		// Build the set of known class refs / names for warning detection.
		$class_refs = array();
		$class_names = array();
		foreach ( $classes as $class ) {
			if ( isset( $class['ref'] ) ) {
				$class_refs[ (string) $class['ref'] ] = true;
			}
			if ( isset( $class['name'] ) ) {
				$class_names[ (string) $class['name'] ] = true;
			}
		}

		$membership_count = 0;
		foreach ( $classes as $class ) {
			if ( ! empty( $class['members'] ) && is_array( $class['members'] ) ) {
				$membership_count += count( $class['members'] );
			}
		}

		$result['counts'] = array(
			'students'    => count( $students ),
			'classes'     => count( $classes ),
			'memberships' => $membership_count,
			'lessons'     => count( $lessons ),
		);

		// Warnings — unknown class refs on lessons.
		$unknown_refs = array();
		foreach ( $lessons as $lesson ) {
			$ref = $this->lesson_class_ref( $lesson );
			if ( '' === $ref ) {
				$result['warnings'][] = __( 'A lesson has no class reference and will be skipped.', 'mm-register' );
				continue;
			}
			if ( ! isset( $class_refs[ $ref ] ) && ! isset( $class_names[ $ref ] ) ) {
				$unknown_refs[ $ref ] = true;
			}
		}
		foreach ( array_keys( $unknown_refs ) as $ref ) {
			/* translators: %s: unknown class reference. */
			$result['warnings'][] = sprintf( __( 'Unknown class "%s" referenced by one or more lessons — those lessons will be skipped.', 'mm-register' ), $ref );
		}

		// Warnings — duplicate student names.
		$seen = array();
		foreach ( $students as $student ) {
			$name = isset( $student['name'] ) ? trim( (string) $student['name'] ) : '';
			if ( '' === $name ) {
				continue;
			}
			if ( isset( $seen[ $name ] ) ) {
				/* translators: %s: duplicate student name. */
				$result['warnings'][] = sprintf( __( 'Duplicate student name "%s" appears more than once.', 'mm-register' ), $name );
			}
			$seen[ $name ] = true;
		}

		if ( ! $commit || $result['blocked'] ) {
			return $result;
		}

		// ---- Commit ----
		$this->commit( $students, $classes, $lessons, $result );
		$result['committed'] = true;
		return $result;
	}

	/**
	 * Extract the class reference from a lesson (ref or name based).
	 *
	 * @param array $lesson Lesson entry.
	 * @return string
	 */
	protected function lesson_class_ref( $lesson ) {
		if ( isset( $lesson['class_ref'] ) && '' !== $lesson['class_ref'] ) {
			return (string) $lesson['class_ref'];
		}
		if ( isset( $lesson['class_name'] ) && '' !== $lesson['class_name'] ) {
			return (string) $lesson['class_name'];
		}
		return '';
	}

	/**
	 * Perform the actual writes, resolving refs to real IDs.
	 *
	 * @param array $students Students.
	 * @param array $classes  Classes.
	 * @param array $lessons  Lessons.
	 * @param array $result   Result accumulator (by reference).
	 */
	protected function commit( $students, $classes, $lessons, &$result ) {
		$student_map = array(); // ref/name => id.
		$class_map = array();    // ref/name => id.
		$imported = array_fill_keys( array_keys( $result['counts'] ), 0 );

		// Students.
		foreach ( $students as $student ) {
			$name = isset( $student['name'] ) ? sanitize_text_field( $student['name'] ) : '';
			if ( '' === $name ) {
				continue;
			}
			$id = MMR_Data::save_student( array(
				'name'            => $name,
				'email'           => isset( $student['email'] ) ? sanitize_email( $student['email'] ) : '',
				'tbt_monthly_fee' => isset( $student['tbt_monthly_fee'] ) ? mmr_sanitize_money( $student['tbt_monthly_fee'] ) : 0,
				'notes'           => isset( $student['notes'] ) ? sanitize_textarea_field( $student['notes'] ) : '',
				'status'          => ( isset( $student['status'] ) && 'archived' === $student['status'] ) ? 'archived' : 'active',
			) );
			if ( isset( $student['ref'] ) ) {
				$student_map[ (string) $student['ref'] ] = $id;
			}
			$student_map[ $name ] = $id;
			$imported['students']++;
		}

		// Classes + memberships.
		foreach ( $classes as $class ) {
			$name = isset( $class['name'] ) ? sanitize_text_field( $class['name'] ) : '';
			if ( '' === $name ) {
				continue;
			}
			$id = MMR_Data::save_class( array(
				'name'          => $name,
				'fee'           => isset( $class['fee'] ) ? mmr_sanitize_money( $class['fee'] ) : 0,
				'client_type'   => isset( $class['client_type'] ) ? sanitize_text_field( $class['client_type'] ) : '',
				'venue'         => isset( $class['venue'] ) ? sanitize_text_field( $class['venue'] ) : '',
				'settle_method' => isset( $class['settle_method'] ) ? sanitize_text_field( $class['settle_method'] ) : '',
				'notes'         => isset( $class['notes'] ) ? sanitize_textarea_field( $class['notes'] ) : '',
				'status'        => ( isset( $class['status'] ) && 'archived' === $class['status'] ) ? 'archived' : 'active',
				'sort_name'     => isset( $class['sort_name'] ) ? sanitize_text_field( $class['sort_name'] ) : '',
			) );
			if ( isset( $class['ref'] ) ) {
				$class_map[ (string) $class['ref'] ] = $id;
			}
			$class_map[ $name ] = $id;
			$imported['classes']++;

			if ( ! empty( $class['members'] ) && is_array( $class['members'] ) ) {
				$members = array();
				foreach ( $class['members'] as $member ) {
					$sref = isset( $member['student_ref'] ) ? (string) $member['student_ref'] : ( isset( $member['student_name'] ) ? (string) $member['student_name'] : '' );
					if ( '' === $sref || ! isset( $student_map[ $sref ] ) ) {
						continue;
					}
					$share = ( isset( $member['share_per_lesson'] ) && '' !== $member['share_per_lesson'] && null !== $member['share_per_lesson'] )
						? mmr_sanitize_money( $member['share_per_lesson'] )
						: null;
					$members[] = array( 'student_id' => $student_map[ $sref ], 'share' => $share );
					$imported['memberships']++;
				}
				MMR_Data::set_members( $id, $members );
			}
		}

		// Lessons.
		foreach ( $lessons as $lesson ) {
			$ref = $this->lesson_class_ref( $lesson );
			if ( '' === $ref || ! isset( $class_map[ $ref ] ) ) {
				continue;
			}
			$date = isset( $lesson['date'] ) ? sanitize_text_field( $lesson['date'] ) : '';
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
				continue;
			}
			$slot = isset( $lesson['slot'] ) ? (int) $lesson['slot'] : 1;
			$fee = isset( $lesson['fee_snapshot'] ) ? mmr_sanitize_money( $lesson['fee_snapshot'] ) : null;
			$note = isset( $lesson['note'] ) ? sanitize_text_field( $lesson['note'] ) : '';
			MMR_Data::create_lesson( $date, $slot, $class_map[ $ref ], $fee, $note );
			$imported['lessons']++;
		}

		$result['imported'] = $imported;
	}

	/**
	 * Export every table as a structured array suitable for re-import.
	 * Uses stable string refs (s{id} / c{id}) so a re-import re-links correctly.
	 *
	 * @return array
	 */
	public function export() {
		global $wpdb;

		$out = array(
			'version'        => 2,
			'exported_at'    => current_time( 'mysql' ),
			'plugin_version' => MMR_VERSION,
			'students'       => array(),
			'classes'        => array(),
			'lessons'        => array(),
		);

		$student_ref = array();
		foreach ( MMR_Data::students( array( 'status' => 'all' ) ) as $s ) {
			$ref = 's' . $s->id;
			$student_ref[ (int) $s->id ] = $ref;
			$out['students'][] = array(
				'ref'             => $ref,
				'name'            => $s->name,
				'email'           => $s->email,
				'tbt_monthly_fee' => (float) $s->tbt_monthly_fee,
				'notes'           => $s->notes,
				'status'          => $s->status,
			);
		}

		$class_ref = array();
		foreach ( MMR_Data::classes( array( 'status' => 'all' ) ) as $c ) {
			$ref = 'c' . $c->id;
			$class_ref[ (int) $c->id ] = $ref;
			$members = array();
			foreach ( MMR_Data::class_members( $c->id ) as $m ) {
				$members[] = array(
					'student_ref'      => isset( $student_ref[ (int) $m->student_id ] ) ? $student_ref[ (int) $m->student_id ] : null,
					'share_per_lesson' => null === $m->share_per_lesson ? null : (float) $m->share_per_lesson,
				);
			}
			$out['classes'][] = array(
				'ref'           => $ref,
				'name'          => $c->name,
				'fee'           => (float) $c->fee,
				'client_type'   => $c->client_type,
				'venue'         => $c->venue,
				'settle_method' => $c->settle_method,
				'notes'         => $c->notes,
				'status'        => $c->status,
				'sort_name'     => $c->sort_name,
				'members'       => $members,
			);
		}

		$lessons = $wpdb->get_results( 'SELECT * FROM ' . MMR_DB::table( 'lessons' ) . ' ORDER BY lesson_date ASC, slot ASC' ); // phpcs:ignore WordPress.DB.PreparedSQL
		foreach ( $lessons as $l ) {
			$out['lessons'][] = array(
				'class_ref'    => isset( $class_ref[ (int) $l->class_id ] ) ? $class_ref[ (int) $l->class_id ] : null,
				'date'         => $l->lesson_date,
				'slot'         => (int) $l->slot,
				'fee_snapshot' => (float) $l->fee_snapshot,
				'note'         => $l->note,
			);
		}

		return $out;
	}
}
