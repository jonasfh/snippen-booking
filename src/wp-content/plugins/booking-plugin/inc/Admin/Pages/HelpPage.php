<?php

namespace SnippenBooking\Admin\Pages;

/**
 * Help and User Manual Page
 */
class HelpPage {

	/**
	 * Render the Help / Manual page
	 */
	public function render() {
		?>
		<div class="wrap snippen-admin-wrap">
			<h1><?php esc_html_e( 'Brukermanual & Hjelp', 'snippen-booking' ); ?></h1>
			<p><?php esc_html_e( 'Velkommen til Snippen Booking. Her finner du informasjon om hvordan du setter opp og bruker systemet.', 'snippen-booking' ); ?></p>

			<div class="postbox-container" style="width: 100%; max-width: 800px;">
				<div class="meta-box-sortables">

					<!-- 1. TL;DR / Quick Start -->
					<div class="postbox">
						<h2 class="hndle"><span><?php esc_html_e( 'Hurtigstart (TL;DR)', 'snippen-booking' ); ?></span></h2>
						<div class="inside">
							<ol>
								<li><strong><?php esc_html_e( 'Opprett lokaler', 'snippen-booking' ); ?>:</strong> <?php esc_html_e( 'Gå til "Lokaler" for å definere hva som kan bookes.', 'snippen-booking' ); ?></li>
								<li><strong><?php esc_html_e( 'Tidsluker', 'snippen-booking' ); ?>:</strong> <?php esc_html_e( 'Gå til "Tidsluker" og konfigurer når lokalene er tilgjengelige.', 'snippen-booking' ); ?></li>
								<li><strong><?php esc_html_e( 'Priser', 'snippen-booking' ); ?>:</strong> <?php esc_html_e( 'Gå til "Prisregler" for å sette priser.', 'snippen-booking' ); ?></li>
								<li><strong><?php esc_html_e( 'Shortcode', 'snippen-booking' ); ?>:</strong> <?php esc_html_e( 'Legg til [snippen_booking] på en side der brukerne skal kunne booke.', 'snippen-booking' ); ?></li>
								<li><strong><?php esc_html_e( 'Tilgang', 'snippen-booking' ); ?>:</strong> <?php esc_html_e( 'Gi "Booking Administrator" tilgang til de ansatte som skal administrere bookinger.', 'snippen-booking' ); ?></li>
							</ol>
						</div>
					</div>

					<!-- 2. Getting Started With Shortcodes -->
					<div class="postbox">
						<h2 class="hndle"><span><?php esc_html_e( 'Bruk av Shortcodes', 'snippen-booking' ); ?></span></h2>
						<div class="inside">
							<p><?php esc_html_e( 'Systemet bruker shortcodes for å vise booking-skjemaet for brukerne. Shortcoden plasseres på en hvilken som helst side i WordPress.', 'snippen-booking' ); ?></p>
							<p><strong><?php esc_html_e( 'Standard bruk:', 'snippen-booking' ); ?></strong></p>
							<p><code>[snippen_booking]</code></p>
							<p><?php esc_html_e( 'Denne viser skjemaet og lar brukeren velge blant tilgjengelige lokaler. Hvis du vil begrense skjemaet til ett bestemt lokale, kan du bruke object_id-attributtet:', 'snippen-booking' ); ?></p>
							<p><code>[snippen_booking object_id="1"]</code></p>
							<p><?php esc_html_e( 'Du kan også kombinere flere lokaler:', 'snippen-booking' ); ?></p>
							<p><code>[snippen_booking object_id="1,2"]</code></p>
						</div>
					</div>

					<!-- 3. What Setup Wizard Installs -->
					<div class="postbox">
						<h2 class="hndle"><span><?php esc_html_e( 'Hva Setup Wizard (veiviseren) installerer', 'snippen-booking' ); ?></span></h2>
						<div class="inside">
							<p><?php esc_html_e( 'Hvis du har kjørt veiviseren (Setup Wizard), er det automatisk opprettet demodata for å hjelpe deg i gang. Dette inkluderer:', 'snippen-booking' ); ?></p>
							<ul>
								<li><?php esc_html_e( 'Eksempellokaler', 'snippen-booking' ); ?></li>
								<li><?php esc_html_e( 'Standard tidsluker (f.eks. dag-, kvelds- og helgeleie)', 'snippen-booking' ); ?></li>
								<li><?php esc_html_e( 'Standard prisregler', 'snippen-booking' ); ?></li>
							</ul>
							<p><?php esc_html_e( 'Dette er kun for demonstrasjon. Du kan trygt redigere eller slette disse dataene for å tilpasse ditt eget oppsett.', 'snippen-booking' ); ?></p>
						</div>
					</div>

					<!-- 4. Core Concepts / How It Works -->
					<div class="postbox">
						<h2 class="hndle"><span><?php esc_html_e( 'Slik fungerer det (kjernekonsepter)', 'snippen-booking' ); ?></span></h2>
						<div class="inside">
							<p><?php esc_html_e( 'Snippen Booking bygger på følgende konsepter:', 'snippen-booking' ); ?></p>
							<ul>
								<li><strong><?php esc_html_e( 'Lokaler:', 'snippen-booking' ); ?></strong> <?php esc_html_e( 'Det fysiske rommet eller ressursen som kan bookes.', 'snippen-booking' ); ?></li>
								<li><strong><?php esc_html_e( 'Tidsluker:', 'snippen-booking' ); ?></strong> <?php esc_html_e( 'Definerer nøyaktig når, og hvor lenge, et lokale kan bookes.', 'snippen-booking' ); ?></li>
								<li><strong><?php esc_html_e( 'Prisregler:', 'snippen-booking' ); ?></strong> <?php esc_html_e( 'Regler som bestemmer kostnaden basert på tidsluke, dag og lokale.', 'snippen-booking' ); ?></li>
								<li><strong><?php esc_html_e( 'Bookinger:', 'snippen-booking' ); ?></strong> <?php esc_html_e( 'Selve reservasjonen som brukeren oppretter.', 'snippen-booking' ); ?></li>
							</ul>
						</div>
					</div>

					<!-- 5. Capabilities / Permissions -->
					<div class="postbox">
						<h2 class="hndle"><span><?php esc_html_e( 'Rettigheter og tilgang', 'snippen-booking' ); ?></span></h2>
						<div class="inside">
							<p><?php esc_html_e( 'Det er to hovedtyper brukere med tilgang til administrasjon av bookinger:', 'snippen-booking' ); ?></p>
							<ul>
								<li><strong><?php esc_html_e( 'WordPress Administrator:', 'snippen-booking' ); ?></strong> <?php esc_html_e( 'Har full tilgang til alt.', 'snippen-booking' ); ?></li>
								<li><strong><?php esc_html_e( 'Brukere med egenskapen "manage_snippen_bookings":', 'snippen-booking' ); ?></strong> <?php esc_html_e( 'Kan administrere bookinger og motta varsler, men trenger ikke være fulle WordPress-administratorer. Dette gis via brukerprofilen.', 'snippen-booking' ); ?></li>
							</ul>
							<p><?php esc_html_e( 'Vanlige brukere som bare skal bestille et lokale trenger IKKE admin-tilgang. De gjør alt på forsiden (via shortcode).', 'snippen-booking' ); ?></p>
						</div>
					</div>

					<!-- 6. Time Slots -->
					<div class="postbox">
						<h2 class="hndle"><span><?php esc_html_e( 'Tidsluker (Time Slots)', 'snippen-booking' ); ?></span></h2>
						<div class="inside">
							<p><?php esc_html_e( 'Når du oppretter en tidsluke, definerer du start- og sluttidspunkt, samt eventuelle timer til vask/rydding før og etter.', 'snippen-booking' ); ?></p>
							<p><?php esc_html_e( 'Eksempler:', 'snippen-booking' ); ?></p>
							<ul>
								<li><?php esc_html_e( 'Dagleie: 08:00 - 16:00', 'snippen-booking' ); ?></li>
								<li><?php esc_html_e( 'Kveldsleie: 17:00 - 23:00', 'snippen-booking' ); ?></li>
								<li><?php esc_html_e( 'Hele dagen: 08:00 - 23:00', 'snippen-booking' ); ?></li>
							</ul>
							<p><?php esc_html_e( 'Luker vil ikke la seg overstyre dersom det oppstår kollisjon i tidspunktene, med mindre du eksplisitt tillater overlapping.', 'snippen-booking' ); ?></p>
						</div>
					</div>

					<!-- 7. Pricing Rules -->
					<div class="postbox">
						<h2 class="hndle"><span><?php esc_html_e( 'Prisregler', 'snippen-booking' ); ?></span></h2>
						<div class="inside">
							<p><?php esc_html_e( 'Prisregler lar deg styre prisen basert på flere faktorer:', 'snippen-booking' ); ?></p>
							<ul>
								<li><strong><?php esc_html_e( 'Standardpris:', 'snippen-booking' ); ?></strong> <?php esc_html_e( 'Gjelder vanligvis mandag til torsdag.', 'snippen-booking' ); ?></li>
								<li><strong><?php esc_html_e( 'Helgepris:', 'snippen-booking' ); ?></strong> <?php esc_html_e( 'Du kan opprette en egen regel for fredag til søndag med en høyere pris.', 'snippen-booking' ); ?></li>
								<li><strong><?php esc_html_e( 'Prioritet:', 'snippen-booking' ); ?></strong> <?php esc_html_e( 'Hvis to regler overlapper, brukes regelen med høyest prioritet.', 'snippen-booking' ); ?></li>
							</ul>
						</div>
					</div>

					<!-- 8. Multi-Object Bookings -->
					<div class="postbox">
						<h2 class="hndle"><span><?php esc_html_e( 'Kombinasjon av flere lokaler', 'snippen-booking' ); ?></span></h2>
						<div class="inside">
							<p><?php esc_html_e( 'Du kan tillate at brukere booker flere lokaler samtidig (f.eks. "Leie hele bygget"). Dette oppnås ved å bruke shortcode med flere ID-er:', 'snippen-booking' ); ?></p>
							<p><code>[snippen_booking object_id="1,2"]</code></p>
							<p><?php esc_html_e( 'Prisen vil da kalkuleres basert på regler som dekker ett eller begge lokalene.', 'snippen-booking' ); ?></p>
						</div>
					</div>

				</div>
			</div>
		</div>
		<?php
	}
}
