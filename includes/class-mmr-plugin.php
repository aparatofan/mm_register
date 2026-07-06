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
	 * Run dbDelta if the plugin was updated without re-activation.
	 */
	public function maybe_upgrade() {
		if ( get_option( 'mmr_db_version' ) !== MMR_VERSION ) {
			MMR_DB::install();
		}
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
			'calendar'          => __( 'Calendar', 'mm-register' ),
			'settlement'        => __( 'Settlement', 'mm-register' ),
			'receivables'       => __( 'Receivables', 'mm-register' ),
			'students-classes'  => __( 'Students & Classes', 'mm-register' ),
			'budget'            => __( 'Budget', 'mm-register' ),
			'expenses'          => __( 'Expenses', 'mm-register' ),
			'projections'       => __( 'Projections', 'mm-register' ),
			'tools'             => __( 'Tools', 'mm-register' ),
		);

		$callbacks = array(
			'calendar'         => 'render_calendar',
			'settlement'       => 'render_settlement',
			'receivables'      => 'render_receivables',
			'students-classes' => 'render_students_classes',
			'budget'           => 'render_budget',
			'expenses'         => 'render_expenses',
			'projections'      => 'render_projections',
			'tools'            => 'render_tools',
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

		wp_localize_script(
			'mmr-admin',
			'MMR',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'mmr_ajax' ),
				'slots'    => MMR_SLOTS_PER_DAY,
				'days'     => MMR_DAY_COLUMNS,
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
