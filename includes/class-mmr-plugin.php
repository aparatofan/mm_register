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
		add_action( 'admin_init', array( $this, 'maybe_upgrade' ) );

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

		$this->page_hooks['calendar'] = add_menu_page(
			__( 'Register', 'mm-register' ),
			__( 'Register', 'mm-register' ),
			$cap,
			'mmr-calendar',
			array( $this->admin, 'render_calendar' ),
			'dashicons-calendar-alt',
			3
		);

		$pages = array(
			'calendar'   => __( 'Calendar', 'mm-register' ),
			'settlement' => __( 'Settlement', 'mm-register' ),
			'students'   => __( 'Students', 'mm-register' ),
			'classes'    => __( 'Classes', 'mm-register' ),
			'companies'  => __( 'Companies', 'mm-register' ),
			'tools'      => __( 'Tools', 'mm-register' ),
		);

		$callbacks = array(
			'calendar'   => 'render_calendar',
			'settlement' => 'render_settlement',
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
				),
			)
		);
	}
}
