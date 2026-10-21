<?php
/**
 * Plugin bootstrap: menus, asset enqueue, hook wiring.
 *
 * @package MM_Register
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Singleton coordinator.
 */
class MMR_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var MMR_Plugin|null
	 */
	protected static $instance = null;

	/**
	 * Admin controller.
	 *
	 * @var MMR_Admin
	 */
	public $admin;

	/**
	 * Hook suffixes of our own admin pages, used to scope asset enqueue.
	 *
	 * @var string[]
	 */
	protected $page_hooks = array();

	/**
	 * Get / create the singleton.
	 *
	 * @return MMR_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor wires hooks.
	 */
	protected function __construct() {
		$this->admin = new MMR_Admin();

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		// Ahead of MMR_Admin::handle_post(), also on admin_init: the first
		// request after an update may be a save, and it must not write to a
		// table that dbDelta has not caught up with yet.
		add_action( 'admin_init', array( $this, 'maybe_upgrade' ), 1 );

		// AJAX + POST handlers.
		( new MMR_Ajax() )->register();
	}

	/**
	 * Run the DB upgrade routine if the plugin was updated without re-activation.
	 */
	public function maybe_upgrade() {
		MMR_DB::maybe_upgrade();
	}

	/**
	 * Register the top-level menu and submenus.
	 */
	public function register_menu() {
		$cap = MMR_CAP;

		// Register keeps its own top-level menu (it is a seven-page suite, not a
		// single screen). Position 3.1 places it immediately below the TBT hub
		// menu, which sits at position 3.
		$this->page_hooks['calendar'] = add_menu_page(
			__( 'TBT Register', 'mm-register' ),
			__( 'TBT Register', 'mm-register' ),
			$cap,
			'mmr-calendar',
			array( $this->admin, 'render_calendar' ),
			'dashicons-calendar-alt',
			3.1
		);

		$pages = array(
			'calendar'   => __( 'Calendar', 'mm-register' ),
			'settlement' => __( 'Settlement', 'mm-register' ),
			'events'     => __( 'Events', 'mm-register' ),
			'students'   => __( 'Students', 'mm-register' ),
			'classes'    => __( 'Classes', 'mm-register' ),
			'companies'  => __( 'Companies', 'mm-register' ),
			'tools'      => __( 'Tools', 'mm-register' ),
		);

		$callbacks = array(
			'calendar'   => 'render_calendar',
			'settlement' => 'render_settlement',
			'events'     => 'render_events',
			'students'   => 'render_students',
			'classes'    => 'render_classes',
			'companies'  => 'render_companies',
			'tools'      => 'render_tools',
		);

		foreach ( $pages as $slug => $title ) {
			// The first submenu re-uses the parent slug so "Calendar" is the default.
			$menu_slug = 'calendar' === $slug ? 'mmr-calendar' : 'mmr-' . $slug;
			$hook = add_submenu_page(
				'mmr-calendar',
				$title,
				$title,
				$cap,
				$menu_slug,
				array( $this->admin, $callbacks[ $slug ] )
			);
			$this->page_hooks[ $slug ] = $hook;
		}
	}

	/**
	 * Enqueue CSS/JS only on our own admin screens.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, $this->page_hooks, true ) ) {
			return;
		}

		wp_enqueue_style(
			'mmr-admin',
			MMR_URL . 'assets/css/admin.css',
			array(),
			MMR_VERSION
		);

		wp_enqueue_script(
			'mmr-admin',
			MMR_URL . 'assets/js/admin.js',
			array(),
			MMR_VERSION,
			true
		);

		$money_th  = mmr_week_money_thresholds();
		$lesson_th = mmr_week_lesson_thresholds();

		wp_localize_script(
			'mmr-admin',
			'MMR',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'mmr_ajax' ),
				'slots'    => MMR_SLOTS_PER_DAY,
				'days'     => MMR_DAY_COLUMNS,
				// Event type labels, so the derived Type reads the same in the
				// form as it does in the list and the filter.
				'eventTypes' => array(
					'invoice' => __( 'invoice', 'mm-register' ),
					'revolut' => __( 'revolut', 'mm-register' ),
					'cash'    => __( 'cash', 'mm-register' ),
				),
				// Week-pill status thresholds + labels, so live updates stay in
				// sync with the server render. Colour still lives only in CSS;
				// JS only picks the modifier class.
				'stats'    => array(
					'money'      => $money_th,
					'lessons'    => $lesson_th,
					'words'      => array(
						'danger' => mmr_status_words( 'danger' ),
						'warn'   => mmr_status_words( 'warn' ),
						'good'   => mmr_status_words( 'good' ),
					),
					'weekAria'   => __( 'Week total %1$s — %2$s', 'mm-register' ),
					'lessonAria' => __( 'Lessons this week: %1$s — %2$s', 'mm-register' ),
				),
				'i18n'     => array(
					'confirmDelete' => __( 'Delete this lesson? It will be treated as never having happened.', 'mm-register' ),
					'noResults'     => __( 'No matching classes', 'mm-register' ),
					'searchClass'   => __( 'Type to search classes…', 'mm-register' ),
					'saving'        => __( 'Saving…', 'mm-register' ),
					'error'         => __( 'Something went wrong. Please retry.', 'mm-register' ),
					/* translators: 1: matching class count, 2: total class count. */
					'classesShown'  => __( 'Showing %1$s of %2$s classes', 'mm-register' ),
					'filteredTotal' => __( 'Filtered total', 'mm-register' ),
					'loading'       => __( 'Loading…', 'mm-register' ),
					'use'           => __( 'Use', 'mm-register' ),
					'suggestClasses'  => __( 'Classes', 'mm-register' ),
					'suggestStudents' => __( 'Students', 'mm-register' ),
					'suggestCompanies' => __( 'Companies', 'mm-register' ),
					/* translators: %s: month name, e.g. "July 2026". */
					'noSuggestions' => __( 'No lessons booked in %s.', 'mm-register' ),
					/* translators: %s: settle method(s) the type was derived from. */
					'typeFrom'      => __( 'from settle method %s', 'mm-register' ),
					/* translators: %s: comma-separated class names. */
					'typeMixed'     => __( 'These classes settle differently: %s. Pick the type for this event.', 'mm-register' ),
					/* translators: %s: comma-separated class names. */
					'typeUnmapped'  => __( 'No settle method on: %s. Pick the type for this event.', 'mm-register' ),
					'typeFreeText'  => __( 'No class selected — pick the type for this event.', 'mm-register' ),
					'noMethod'      => __( 'no settle method', 'mm-register' ),
					'archived'      => __( 'archived', 'mm-register' ),
					/* translators: %s: class name. */
					'removeClass'   => __( 'Remove %s', 'mm-register' ),
					'noStudents'    => __( 'No matching students', 'mm-register' ),
					'pickStudent'   => __( 'Pick a student from the list.', 'mm-register' ),
				),
			)
		);
	}
}
