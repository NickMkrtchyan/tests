<?php
/**
 * Plugin Name: Customer Manager (Login As)
 * Description: Adds Users → Customer Manager to let administrators log in as a customer with one click.
 * Version:     1.0.0
 * Author:      RankUP
 * License:     GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'RankUP_Customer_Login_Manager' ) ) {

	class RankUP_Customer_Login_Manager {

		const SLUG = 'rankup-customer-manager';

		public function __construct() {
			// Admin UI
			add_action( 'admin_menu', [ $this, 'add_menu' ] );

			// Action handler for login-as
			add_action( 'admin_post_rankup_login_as', [ $this, 'handle_login_as' ] );

			// Optional: message banner after restricted attempts
			add_action( 'admin_notices', [ $this, 'maybe_show_notice' ] );
		}

		public function add_menu() {
			// Under Users
			add_users_page(
				__( 'Customer Manager', 'rankup' ),
				__( 'Customer Manager', 'rankup' ),
				'edit_users', // capability to view/manage users
				self::SLUG,
				[ $this, 'render_page' ]
			);
		}

		private function current_admin_can_use() {
			// You can tighten this if you like (e.g., 'manage_woocommerce').
			return current_user_can( 'edit_users' );
		}

		public function render_page() {
			if ( ! $this->current_admin_can_use() ) {
				wp_die( esc_html__( 'You do not have permission to access this page.', 'rankup' ), 403 );
			}

			// Pagination + search
			$per_page = 20;
			$paged    = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
			$offset   = ( $paged - 1 ) * $per_page;

			$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

			$args = [
				'role'    => 'customer',
				'number'  => $per_page,
				'offset'  => $offset,
				'orderby' => 'ID',
				'order'   => 'DESC',
				'fields'  => [ 'ID', 'user_login', 'user_email', 'display_name' ],
			];

			if ( $search !== '' ) {
				$args['search']          = '*' . $search . '*';
				$args['search_columns']  = [ 'user_login', 'user_email', 'display_name' ];
			}

			$wp_user_query = new WP_User_Query( $args );
			$users         = (array) $wp_user_query->get_results();

			// Get total for pagination
			$total_args = [
				'role'   => 'customer',
				'fields' => 'ID',
			];
			if ( $search !== '' ) {
				$total_args['search']         = '*' . $search . '*';
				$total_args['search_columns'] = [ 'user_login', 'user_email', 'display_name' ];
			}
			$total_query = new WP_User_Query( $total_args );
			$total       = is_array( $total_query->get_results() ) ? count( $total_query->get_results() ) : 0;
			$total_pages = max( 1, (int) ceil( $total / $per_page ) );

			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Customer Manager', 'rankup' ); ?></h1>

				<form method="get" action="">
					<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>" />
					<p class="search-box">
						<label class="screen-reader-text" for="customer-search-input"><?php esc_html_e( 'Search Customers:', 'rankup' ); ?></label>
						<input type="search" id="customer-search-input" name="s" value="<?php echo esc_attr( $search ); ?>" />
						<?php submit_button( __( 'Search', 'rankup' ), '', '', false ); ?>
					</p>
				</form>

				<div class="tablenav top">
					<div class="tablenav-pages">
						<span class="displaying-num"><?php echo esc_html( number_format_i18n( $total ) . ' ' . _n( 'customer', 'customers', $total, 'rankup' ) ); ?></span>
						<?php
						$page_link_base = add_query_arg(
							array_filter([
								'page' => self::SLUG,
								's'    => $search !== '' ? $search : null,
							]),
							admin_url( 'users.php' )
						);

						$this->pagination_links( $paged, $total_pages, $page_link_base );
						?>
					</div>
				</div>

				<table class="widefat striped">
					<thead>
						<tr>
							<th style="width:80px;"><?php esc_html_e( 'ID', 'rankup' ); ?></th>
							<th><?php esc_html_e( 'Name', 'rankup' ); ?></th>
							<th><?php esc_html_e( 'Email', 'rankup' ); ?></th>
							<th style="width:160px;"><?php esc_html_e( 'Action', 'rankup' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php if ( empty( $users ) ) : ?>
						<tr><td colspan="4"><?php esc_html_e( 'No customers found.', 'rankup' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $users as $user ) : ?>
							<tr>
								<td><?php echo esc_html( $user->ID ); ?></td>
								<td><?php echo esc_html( $user->display_name ?: $user->user_login ); ?></td>
								<td><a href="mailto:<?php echo esc_attr( $user->user_email ); ?>"><?php echo esc_html( $user->user_email ); ?></a></td>
								<td>
									<?php
									$action_url = wp_nonce_url(
										add_query_arg(
											[
												'action'  => 'rankup_login_as',
												'user_id' => $user->ID,
											],
											admin_url( 'admin-post.php' )
										),
										'rankup_login_as_' . $user->ID
									);
									?>
									<a class="button button-primary" href="<?php echo esc_url( $action_url ); ?>" 
										title="<?php esc_attr_e( 'Log in as this customer', 'rankup' ); ?>">
										<?php esc_html_e( 'Login as Customer', 'rankup' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
					</tbody>
				</table>

				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<?php $this->pagination_links( $paged, $total_pages, $page_link_base ); ?>
					</div>
				</div>
			</div>
			<style>
				#customer-search-input { min-width: 240px; }
			</style>
			<?php
		}

		private function pagination_links( $current, $total_pages, $base_url ) {
			$current = (int) $current;
			$total_pages = (int) $total_pages;

			if ( $total_pages <= 1 ) return;

			$links = [];

			// First
			$links[] = sprintf(
				'<a class="first-page button%s" href="%s">&laquo;</a>',
				$current <= 1 ? ' disabled' : '',
				esc_url( add_query_arg( 'paged', 1, $base_url ) )
			);

			// Prev
			$links[] = sprintf(
				'<a class="prev-page button%s" href="%s">&lsaquo;</a>',
				$current <= 1 ? ' disabled' : '',
				esc_url( add_query_arg( 'paged', max( 1, $current - 1 ), $base_url ) )
			);

			// Current / Total
			$links[] = sprintf(
				'<span class="paging-input">%s <span class="total-pages">%s</span></span>',
				esc_html( $current ),
				esc_html( ' / ' . $total_pages )
			);

			// Next
			$links[] = sprintf(
				'<a class="next-page button%s" href="%s">&rsaquo;</a>',
				$current >= $total_pages ? ' disabled' : '',
				esc_url( add_query_arg( 'paged', min( $total_pages, $current + 1 ), $base_url ) )
			);

			// Last
			$links[] = sprintf(
				'<a class="last-page button%s" href="%s">&raquo;</a>',
				$current >= $total_pages ? ' disabled' : '',
				esc_url( add_query_arg( 'paged', $total_pages, $base_url ) )
			);

			echo wp_kses_post( implode( ' ', $links ) );
		}

		public function handle_login_as() {
			if ( ! $this->current_admin_can_use() ) {
				wp_die( esc_html__( 'Insufficient permissions.', 'rankup' ), 403 );
			}

			$user_id = isset( $_GET['user_id'] ) ? intval( $_GET['user_id'] ) : 0;

			if ( ! $user_id || ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'rankup_login_as_' . $user_id ) ) {
				wp_die( esc_html__( 'Invalid request.', 'rankup' ), 400 );
			}

			$user = get_user_by( 'ID', $user_id );
			if ( ! $user ) {
				wp_die( esc_html__( 'User not found.', 'rankup' ), 404 );
			}

			// Restrict to customers only (prevents logging into admins/editors by mistake).
			if ( ! in_array( 'customer', (array) $user->roles, true ) ) {
				// Bounce back with a message instead of dying.
				$redirect = add_query_arg(
					[
						'page'   => self::SLUG,
						'error'  => 'not_customer',
						'user'   => $user_id,
					],
					admin_url( 'users.php' )
				);
				wp_safe_redirect( $redirect );
				exit;
			}

			// Switch session to the target user
			wp_set_current_user( $user->ID );
			wp_set_auth_cookie( $user->ID, true ); // remember = true, adjust if you prefer session-only

			/**
			 * Trigger wp_login action for compatibility with plugins that listen to it
			 * (username, user object)
			 */
			do_action( 'wp_login', $user->user_login, $user );

			// Prefer WooCommerce My Account if available; else go to homepage.
			$target = home_url( '/' );

			if ( function_exists( 'wc_get_page_id' ) ) {
				$my_account_id = wc_get_page_id( 'myaccount' );
				if ( $my_account_id && $my_account_id > 0 ) {
					$target = get_permalink( $my_account_id );
				}
			}

			wp_safe_redirect( $target );
			exit;
		}

		public function maybe_show_notice() {
			if ( ! isset( $_GET['page'] ) || $_GET['page'] !== self::SLUG ) {
				return;
			}

			if ( isset( $_GET['error'] ) && $_GET['error'] === 'not_customer' ) {
				$user_id = isset( $_GET['user'] ) ? intval( $_GET['user'] ) : 0;
				$user    = $user_id ? get_user_by( 'ID', $user_id ) : null;

				$message = __( 'You can only log in as users with the "customer" role.', 'rankup' );
				if ( $user ) {
					$message = sprintf(
						/* translators: %s: user display name */
						__( 'User "%s" is not a customer. You can only log in as users with the "customer" role.', 'rankup' ),
						esc_html( $user->display_name ?: $user->user_login )
					);
				}

				printf(
					'<div class="notice notice-error"><p>%s</p></div>',
					wp_kses_post( $message )
				);
			}
		}
	}

	new RankUP_Customer_Login_Manager();
}
