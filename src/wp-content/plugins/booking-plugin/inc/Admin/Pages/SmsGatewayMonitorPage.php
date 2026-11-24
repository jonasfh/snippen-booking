<?php
/**
 * Admin page for monitoring Snippen SMS Service gateway polling health
 *
 * @package SnippenBooking\Admin\Pages
 */

namespace SnippenBooking\Admin\Pages;

use SnippenBooking\Helper\Capabilities;
use SnippenBooking\Service\Notification\MessageLoggerService;
use SnippenBooking\Service\Notification\NotificationManager;
use SnippenBooking\Service\Sms\SmsGatewayHealthService;

/**
 * Class SmsGatewayMonitorPage
 */
class SmsGatewayMonitorPage {

	/**
	 * Render the SMS gateway monitoring page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE_BOOKINGS ) ) {
			wp_die( esc_html__( 'Du har ikke tilstrekkelige rettigheter til å åpne denne siden.', 'snippen-booking' ) );
		}

		$action_notice = $this->handle_actions();
		$summary       = SmsGatewayHealthService::get_status_summary();

		$outbox_queued = MessageLoggerService::count_outbound_sms_messages( array( 'status' => 'queued' ) );
		$outbox_failed = MessageLoggerService::count_outbound_sms_messages( array( 'status' => 'failed' ) );
		$alert_logs    = $this->get_recent_alert_logs( 20 );

		$settings_url = admin_url( 'admin.php?page=snippen-booking-settings' );
		$outbox_url   = admin_url( 'admin.php?page=snippen-booking-sms-inbox&tab=outbox' );

		echo '<div class="wrap snippen-booking-admin-wrap">';
		echo '<h1>' . esc_html__( 'SMS Overvåking (Snippen SMS Service)', 'snippen-booking' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Overvåker at snippen-sms-service poller booking-endepunktet jevnlig når Snippen SMS Service er valgt som aktiv SMS-tilbyder.', 'snippen-booking' ) . '</p>';

		if ( ! empty( $action_notice ) ) {
			echo '<div class="notice notice-' . esc_attr( $action_notice['type'] ) . ' is-dismissible"><p>' . esc_html( $action_notice['message'] ) . '</p></div>';
		}

		$this->render_status_banner( $summary, $settings_url );
		$this->render_metrics_grid( $summary, $outbox_queued, $outbox_failed, $settings_url, $outbox_url );
		$this->render_actions_panel( $summary );
		$this->render_alert_history_table( $alert_logs );

		echo '</div>';
	}

	/**
	 * Handle manual actions from the monitoring page.
	 *
	 * @return array{type:string,message:string}|null
	 */
	private function handle_actions(): ?array {
		if ( ! isset( $_POST['snippen_sms_monitor_action'] ) ) {
			return null;
		}

		check_admin_referer( 'snippen_sms_monitor_nonce' );

		$action = sanitize_key( wp_unslash( $_POST['snippen_sms_monitor_action'] ) );

		if ( 'run_health_check' === $action ) {
			$result = SmsGatewayHealthService::check_health();
			$status = $result['status'] ?? 'unknown';

			$messages = array(
				'inactive'             => __( 'Overvåking er deaktivert (Snippen SMS Service er ikke aktiv/konfigurert, eller toleranse er satt til 0).', 'snippen-booking' ),
				'baseline_initialized' => __( 'Starttidspunkt for overvåking ble initialisert. Venter på første polling.', 'snippen-booking' ),
				'ok'                   => __( 'Helsesjekk fullført: Gateway poller innenfor konfigurert toleranse.', 'snippen-booking' ),
				'alert_sent'           => __( 'Helsesjekk fullført: Toleransen er overskredet og e-postvarsel ble sendt til booking-administratorene.', 'snippen-booking' ),
				'alert_failed'         => __( 'Helsesjekk fullført: Toleransen er overskredet, men utsending av e-postvarsel feilet eller er deaktivert.', 'snippen-booking' ),
				'already_alerted'      => __( 'Helsesjekk fullført: Varsel er allerede sendt for pågående nedetid.', 'snippen-booking' ),
			);

			return array(
				'type'    => in_array( $status, array( 'ok', 'baseline_initialized' ), true ) ? 'success' : 'warning',
				'message' => $messages[ $status ] ?? __( 'Helsesjekk utført.', 'snippen-booking' ),
			);
		}

		if ( 'reset_alert_state' === $action ) {
			SmsGatewayHealthService::reset_alert_state();
			return array(
				'type'    => 'success',
				'message' => __( 'Varslingsstatus nullstilt. Neste sjekk vil evaluere på nytt.', 'snippen-booking' ),
			);
		}

		return null;
	}

	/**
	 * Render top status banner.
	 *
	 * @param array<string,mixed> $summary      Status summary.
	 * @param string              $settings_url Settings URL.
	 * @return void
	 */
	private function render_status_banner( array $summary, string $settings_url ): void {
		$state_styles = array(
			'ok'           => array(
				'bg'     => '#f0fdf4',
				'border' => '#86efac',
				'badge'  => '#16a34a',
				'label'  => __( 'Aktiv & Frisk', 'snippen-booking' ),
				'detail' => __( 'snippen-sms-service poller normalt innenfor konfigurert toleranse.', 'snippen-booking' ),
			),
			'alert_active' => array(
				'bg'     => '#fef2f2',
				'border' => '#fca5a5',
				'badge'  => '#dc2626',
				'label'  => __( 'Varsel utløst / Nedetid', 'snippen-booking' ),
				'detail' => __( 'Toleransen uten polling er overskredet og varsel er sendt til booking-administratorer.', 'snippen-booking' ),
			),
			'overdue'      => array(
				'bg'     => '#fffbeb',
				'border' => '#fcd34d',
				'badge'  => '#d97706',
				'label'  => __( 'Toleranse overskredet', 'snippen-booking' ),
				'detail' => __( 'Polling har uteblitt lenger enn toleransen. Neste cron-sjekk vil sende varsel.', 'snippen-booking' ),
			),
			'baseline'     => array(
				'bg'     => '#eff6ff',
				'border' => '#93c5fd',
				'badge'  => '#2563eb',
				'label'  => __( 'Venter på første polling', 'snippen-booking' ),
				'detail' => __( 'Overvåking er aktiv, men ingen polling er registrert ennå.', 'snippen-booking' ),
			),
			'inactive'     => array(
				'bg'     => '#f8fafc',
				'border' => '#cbd5e1',
				'badge'  => '#64748b',
				'label'  => __( 'Deaktivert', 'snippen-booking' ),
				'detail' => __( 'Overvåking kjører kun når Snippen SMS Service er valgt som aktiv SMS-tilbyder, er konfigurert med API-token og har toleranse > 0 sekunder.', 'snippen-booking' ),
			),
		);

		$style = $state_styles[ $summary['state'] ] ?? $state_styles['inactive'];

		echo '<div style="margin:16px 0; padding:16px 20px; background:' . esc_attr( $style['bg'] ) . '; border:1px solid ' . esc_attr( $style['border'] ) . '; border-radius:8px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">';
		echo '<div>';
		echo '<span style="display:inline-block; padding:3px 10px; border-radius:9999px; background:' . esc_attr( $style['badge'] ) . '; color:#fff; font-weight:600; font-size:12px; margin-bottom:6px;">' . esc_html( $style['label'] ) . '</span>';
		echo '<p style="margin:4px 0 0 0; font-size:14px;">' . esc_html( $style['detail'] ) . '</p>';
		echo '</div>';
		echo '<a href="' . esc_url( $settings_url ) . '" class="button button-secondary">' . esc_html__( 'Åpne innstillinger', 'snippen-booking' ) . '</a>';
		echo '</div>';
	}

	/**
	 * Render key metrics cards.
	 *
	 * @param array<string,mixed> $summary       Status summary.
	 * @param int                 $outbox_queued Queued SMS count.
	 * @param int                 $outbox_failed Failed SMS count.
	 * @param string              $settings_url  Settings URL.
	 * @param string              $outbox_url    Outbox URL.
	 * @return void
	 */
	private function render_metrics_grid( array $summary, int $outbox_queued, int $outbox_failed, string $settings_url, string $outbox_url ): void {
		$last_poll_text = $summary['last_poll_at']
			? wp_date( 'Y-m-d H:i:s', (int) $summary['last_poll_at'] )
			: __( 'Aldri registrert', 'snippen-booking' );

		$elapsed_text = null !== $summary['seconds_elapsed']
			? sprintf(
				/* translators: %d: seconds elapsed */
				__( '%d sek siden', 'snippen-booking' ),
				(int) $summary['seconds_elapsed']
			)
			: '—';

		$cron_text = $summary['next_cron_at']
			? wp_date( 'Y-m-d H:i:s', (int) $summary['next_cron_at'] )
			: __( 'Ikke planlagt (inaktiv)', 'snippen-booking' );

		$alert_text = $summary['alert_sent_at']
			? sprintf(
				/* translators: %s: formatted date/time */
				__( 'Sendt %s', 'snippen-booking' ),
				wp_date( 'Y-m-d H:i:s', (int) $summary['alert_sent_at'] )
			)
			: __( 'Ingen aktivt varsel', 'snippen-booking' );

		echo '<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:20px;">';

		$this->render_metric_card(
			__( 'Aktiv SMS-tilbyder', 'snippen-booking' ),
			$summary['provider_name'],
			$summary['is_configured'] ? __( 'API-token konfigurert', 'snippen-booking' ) : __( 'Ikke konfigurert for gateway', 'snippen-booking' )
		);

		$this->render_metric_card(
			__( 'Siste registrerte polling', 'snippen-booking' ),
			$last_poll_text,
			$elapsed_text
		);

		$this->render_metric_card(
			__( 'Toleranse & E-postvarsel', 'snippen-booking' ),
			sprintf( __( '%d sek', 'snippen-booking' ), (int) $summary['tolerance_seconds'] ),
			$summary['email_alerts_on'] ? __( 'E-postvarsel aktivert', 'snippen-booking' ) : __( 'E-postvarsel deaktivert', 'snippen-booking' )
		);

		$this->render_metric_card(
			__( 'WP-Cron & Varslingsstatus', 'snippen-booking' ),
			$cron_text,
			$alert_text
		);

		$this->render_metric_card(
			__( 'Utboks-kø', 'snippen-booking' ),
			sprintf( __( '%1$d i kø / %2$d feilet', 'snippen-booking' ), $outbox_queued, $outbox_failed ),
			'<a href="' . esc_url( $outbox_url ) . '">' . esc_html__( 'Åpne SMS Utboks →', 'snippen-booking' ) . '</a>',
			true
		);

		echo '</div>';
	}

	/**
	 * Render a single metric card.
	 *
	 * @param string $title       Card title.
	 * @param string $value       Main value.
	 * @param string $subtext     Secondary text (escaped unless $subtext_raw is true).
	 * @param bool   $subtext_raw Whether subtext already contains safe HTML.
	 * @return void
	 */
	private function render_metric_card( string $title, string $value, string $subtext, bool $subtext_raw = false ): void {
		echo '<div style="background:#fff; border:1px solid #ccd0d4; border-radius:6px; padding:16px; box-shadow:0 1px 1px rgba(0,0,0,.04);">';
		echo '<div style="font-size:12px; color:#64748b; font-weight:600; text-transform:uppercase; margin-bottom:6px;">' . esc_html( $title ) . '</div>';
		echo '<div style="font-size:16px; font-weight:700; color:#0f172a; margin-bottom:4px;">' . esc_html( $value ) . '</div>';
		echo '<div style="font-size:12px; color:#475569;">' . ( $subtext_raw ? $subtext : esc_html( $subtext ) ) . '</div>';
		echo '</div>';
	}

	/**
	 * Render manual action buttons.
	 *
	 * @param array<string,mixed> $summary Status summary.
	 * @return void
	 */
	private function render_actions_panel( array $summary ): void {
		echo '<div style="background:#fff; border:1px solid #ccd0d4; border-radius:6px; padding:16px; margin-bottom:20px; display:flex; align-items:center; gap:12px; flex-wrap:wrap;">';
		echo '<strong style="margin-right:8px;">' . esc_html__( 'Handlinger:', 'snippen-booking' ) . '</strong>';

		echo '<form method="post" style="margin:0;">';
		wp_nonce_field( 'snippen_sms_monitor_nonce' );
		echo '<input type="hidden" name="snippen_sms_monitor_action" value="run_health_check">';
		echo '<button type="submit" class="button button-primary">' . esc_html__( 'Kjør helsesjekk nå', 'snippen-booking' ) . '</button>';
		echo '</form>';

		if ( $summary['alert_sent_at'] || $summary['monitor_since'] ) {
			echo '<form method="post" style="margin:0;">';
			wp_nonce_field( 'snippen_sms_monitor_nonce' );
			echo '<input type="hidden" name="snippen_sms_monitor_action" value="reset_alert_state">';
			echo '<button type="submit" class="button button-secondary">' . esc_html__( 'Nullstill varslingsstatus', 'snippen-booking' ) . '</button>';
			echo '</form>';
		}

		echo '</div>';
	}

	/**
	 * Render recent SMS gateway alert emails table.
	 *
	 * @param array $logs Recent log rows.
	 * @return void
	 */
	private function render_alert_history_table( array $logs ): void {
		echo '<h2>' . esc_html__( 'Historikk for nedetidsvarsler', 'snippen-booking' ) . '</h2>';
		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Tidspunkt', 'snippen-booking' ) . '</th>';
		echo '<th>' . esc_html__( 'Mottaker', 'snippen-booking' ) . '</th>';
		echo '<th>' . esc_html__( 'Emne', 'snippen-booking' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'snippen-booking' ) . '</th>';
		echo '<th>' . esc_html__( 'Detaljer', 'snippen-booking' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( empty( $logs ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'Ingen nedetidsvarsler er logget ennå.', 'snippen-booking' ) . '</td></tr>';
		} else {
			foreach ( $logs as $log ) {
				$meta    = ! empty( $log->metadata ) ? json_decode( $log->metadata, true ) : array();
				$details = '';
				if ( is_array( $meta ) && isset( $meta['seconds_since_poll'], $meta['tolerance_seconds'] ) ) {
					$details = sprintf(
						/* translators: 1: seconds since poll, 2: tolerance seconds */
						__( '%1$d sek uten polling (toleranse: %2$d sek)', 'snippen-booking' ),
						(int) $meta['seconds_since_poll'],
						(int) $meta['tolerance_seconds']
					);
				}

				$local_time = get_date_from_gmt( $log->created_at, 'Y-m-d H:i:s' );

				echo '<tr>';
				echo '<td>' . esc_html( $local_time ) . '</td>';
				echo '<td>' . esc_html( $log->recipient ) . '</td>';
				echo '<td>' . esc_html( $log->subject ?: '-' ) . '</td>';
				echo '<td><code>' . esc_html( $log->status ) . '</code></td>';
				echo '<td>' . esc_html( $details ?: '—' ) . '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
	}

	/**
	 * Fetch recent sms_gateway_alert logs from wp_snippen_messages.
	 *
	 * @param int $limit Max rows to return.
	 * @return array
	 */
	private function get_recent_alert_logs( int $limit = 20 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_messages';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE event_type = %s ORDER BY id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				NotificationManager::TYPE_SMS_GATEWAY_ALERT,
				$limit
			)
		);

		return is_array( $rows ) ? $rows : array();
	}
}
