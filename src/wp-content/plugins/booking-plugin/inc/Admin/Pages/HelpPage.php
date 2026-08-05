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
							<p><?php esc_html_e( 'Den raskeste måten å komme i gang på er å bruke Setup Wizard (veiviseren). Den oppretter standard lokaler, tidsluker og priser som du senere kan tilpasse.', 'snippen-booking' ); ?></p>
							<p><strong><?php esc_html_e( 'VIKTIG:', 'snippen-booking' ); ?></strong> <?php esc_html_e( 'For å se resten av booking-menyene (som Oversikt, Lokaler, etc.), må du gi deg selv tilgangen "Booking Administrator" på din egen brukerprofil under "Brukere" i WordPress.', 'snippen-booking' ); ?></p>
							<ol>
								<li><strong><?php esc_html_e( 'Kjør Setup Wizard', 'snippen-booking' ); ?>:</strong> <?php esc_html_e( 'Gå til "Setup Wizard" for å generere et ferdig oppsett.', 'snippen-booking' ); ?></li>
								<li><strong><?php esc_html_e( 'Legg inn booking-skjema', 'snippen-booking' ); ?>:</strong> <?php esc_html_e( 'Legg til shortcoden [snippen_booking] på en side for å la brukere booke.', 'snippen-booking' ); ?></li>
								<li><strong><?php esc_html_e( 'Brukertilgang', 'snippen-booking' ); ?>:</strong> <?php esc_html_e( 'Legg til shortcoden [snippen_account_confirmation] på en egen side slik at beboere kan bekrefte kontoen sin.', 'snippen-booking' ); ?></li>
							</ol>
						</div>
					</div>

					<!-- 2. Getting Started With Shortcodes -->
					<div class="postbox">
						<h2 class="hndle"><span><?php esc_html_e( 'Bruk av Shortcodes', 'snippen-booking' ); ?></span></h2>
						<div class="inside">
							<p><?php esc_html_e( 'Systemet bruker shortcodes for å vise innhold for brukerne. Disse plasseres på vanlige WordPress-sider.', 'snippen-booking' ); ?></p>
							
							<h4><?php esc_html_e( '1. Bookingskjema:', 'snippen-booking' ); ?></h4>
							<p><code>[snippen_booking]</code></p>
							<p><?php esc_html_e( 'Denne viser kalender og skjema for booking av alle lokaler. Hvis du vil begrense skjemaet til ett bestemt lokale, kan du bruke object_id:', 'snippen-booking' ); ?> <code>[snippen_booking object_id="1"]</code></p>
							
							<h4><?php esc_html_e( '2. Brukeraktivering:', 'snippen-booking' ); ?></h4>
							<p><code>[snippen_account_confirmation]</code></p>
							<p><?php esc_html_e( 'Brukes på en side der importerte brukere/beboere kan skrive inn telefonnummeret sitt for å motta en SMS-kode, og dermed aktivere kontoen sin for booking.', 'snippen-booking' ); ?></p>
							
							<h4><?php esc_html_e( '3. Mine bookinger:', 'snippen-booking' ); ?></h4>
							<p><code>[snippen_booking_list]</code></p>
							<p><?php esc_html_e( 'Denne viser en liste over den innloggede brukerens egne bookinger (inkludert dørkoder for aktive bookinger). Hvis du også vil inkludere et innloggingsskjema for brukere som ikke er logget inn, kan du bruke:', 'snippen-booking' ); ?> <code>[snippen_booking_list login-form="1"]</code></p>
							
							<h4><?php esc_html_e( '4. Hurtiglenker på administrasjonssiden (Page Tagging):', 'snippen-booking' ); ?></h4>
							<p><?php esc_html_e( 'Hvis du tagger en WordPress-side med stikkordet (tag) "snippen-booking", vil det automatisk vises en hurtiglenke til denne siden øverst i oversikten på administrasjonssiden.', 'snippen-booking' ); ?></p>
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
							<p><strong><?php esc_html_e( 'VIKTIG FOR ADMINISTRATORER:', 'snippen-booking' ); ?></strong> <?php esc_html_e( 'Selv om du er WordPress Administrator, MÅ du manuelt huke av for "Booking Administrator" inne på din egen brukerprofil (under "Brukere") for å få tilgang til oppsett og administrering av bookinger.', 'snippen-booking' ); ?></p>
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
						<h2 class="hndle"><span><?php esc_html_e( 'Tidsluker (Time Slots) og Vasketid', 'snippen-booking' ); ?></span></h2>
						<div class="inside">
							<p><?php esc_html_e( 'Når du oppretter en tidsluke, definerer du start- og sluttidspunkt, samt eventuelle timer til vask/rydding før og etter. Vasketiden legger beslag på lokalet og forhindrer andre bookinger i den perioden.', 'snippen-booking' ); ?></p>
							<p><strong><?php esc_html_e( 'Konsekvenser av vasketid (eksempel):', 'snippen-booking' ); ?></strong></p>
							<ul>
								<li><?php esc_html_e( 'Hvis du har en dagleie fra 08:00 - 16:00, og angir "2 timer vask etterpå", vil lokalet være sperret frem til kl. 18:00.', 'snippen-booking' ); ?></li>
								<li><?php esc_html_e( 'Dette betyr at en kveldsleie som starter kl. 16:00 eller 17:00 VIL VÆRE UTILGJENGELIG samme dag.', 'snippen-booking' ); ?></li>
								<li><?php esc_html_e( 'Neste tilgjengelige tidsluke kan tidligst starte kl. 18:00.', 'snippen-booking' ); ?></li>
							</ul>
							<p><?php esc_html_e( 'Luker vil ikke la seg overstyre dersom det oppstår kollisjon i tidspunktene (inkludert vasketid), med mindre du eksplisitt tillater overlapping for delte arrangementer.', 'snippen-booking' ); ?></p>
						</div>
					</div>

					<!-- 7. Pricing Rules -->
					<div class="postbox">
						<h2 class="hndle"><span><?php esc_html_e( 'Prisregler og Prioritet', 'snippen-booking' ); ?></span></h2>
						<div class="inside">
							<p><?php esc_html_e( 'Prisregler styrer hva det koster å booke, basert på ukedag, tidsluke og lokale.', 'snippen-booking' ); ?></p>
							<ul>
								<li><strong><?php esc_html_e( 'Prioritet:', 'snippen-booking' ); ?></strong> <?php esc_html_e( 'Hvis to regler gjelder for samme dag (f.eks. en generell pris og en spesifikk helligdagspris), er det "Priority"-feltet som avgjør hvilken som vinner. Et HØYERE tall trumfer et lavere tall. (For eksempel vil en regel med prioritet 10 overstyre en regel med prioritet 0).', 'snippen-booking' ); ?></li>
								<li><strong><?php esc_html_e( 'Flere lokaler (Multi-object):', 'snippen-booking' ); ?></strong> <?php esc_html_e( 'Husk at dersom du tillater leie av flere lokaler samtidig (f.eks. "Leie hele bygget"), MÅ du opprette egne prisregler som gjelder for akkurat denne kombinasjonen av lokaler. Hvis ikke vil ikke systemet vite hva kombinasjonen koster.', 'snippen-booking' ); ?></li>
							</ul>
						</div>
					</div>

					<!-- 8. Multi-Object Bookings -->
					<div class="postbox">
						<h2 class="hndle"><span><?php esc_html_e( 'Kombinasjon av flere lokaler (Multi-object)', 'snippen-booking' ); ?></span></h2>
						<div class="inside">
							<p><?php esc_html_e( 'Du kan tillate at brukere booker flere lokaler i samme slengen ved å sette inn en shortcode med flere ID-er:', 'snippen-booking' ); ?></p>
							<p><code>[snippen_booking object_id="1,2"]</code></p>
							<p><strong><?php esc_html_e( 'Viktige forutsetninger (Implikasjoner i oppsettet):', 'snippen-booking' ); ?></strong></p>
							<ul>
								<li><?php esc_html_e( 'Tidsluken du ønsker å tilby MÅ ha krysset av for "Tillat delt booking (Shared booking)". Kun da kan den gjelde over flere uavhengige lokaler samtidig.', 'snippen-booking' ); ?></li>
								<li><?php esc_html_e( 'Du må som nevnt over ha opprettet en egen "Prisregel" i systemet der BEGGE lokalene er krysset av, slik at systemet vet totalprisen for kombinasjonen.', 'snippen-booking' ); ?></li>
							</ul>
						</div>
					</div>

				</div>
			</div>
		</div>
		<?php
	}
}
