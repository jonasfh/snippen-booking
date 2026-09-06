# Changelog

## [2.28.3] - 2026-09-06
- (#295) Vis booking-status og betalings-status i kompakt mobilvisning i admin:
  - Lagt til booking-status og betalings-status badges i den kompakte 1-celles mobilvisningen i admin (`BookingsPage`).
  - Fjernet redundant status-visning langt nede i detaljvisningen.
  - Gjort statusoppdatering via AJAX i `admin.js` mer presis ved målrettet oppdatering av `.snippen-status-badge` uten å påvirke betalingsstatus.
  - Oppdatert UI-fixtures og Playwright visual snapshots for mobil.

## [2.28.2] - 2026-09-06
- (#293) Forbedre mobilvisning og kortvisning for booking-oversikten i admin:
  - Introdusert 1-celles sammendragsvisning på mobil som samler leietakers navn, tidsrom (dato og tid), booking-objekt og åpne/lukke-pil (`v`/`^`) i ett kompakt kort.
  - Skjult skrivebordskolonnene i sammenslått tilstand på mobil for å eliminere oppstykking i 6 separate linjer.
  - Etablert en tydelig markert kortramme og farget aksentlinje (`border-left: 4px solid var(--primary-color)`) rundt hele bookingen som sømløst omslutter både hovedkort og detaljseksjon når den utvides.
  - Handlingsknapper har fått distinkte farger (Avbryt: rød, Godkjenn: gul/grønn) med tekstetiketter i detaljseksjonen, slik at handlinger ikke forveksles med dialoglukking.
  - Oppdatert `UserBookingsPage` med tilsvarende 1-celles kortvisning for mobil.
  - Oppdatert Playwright-tester og golden snapshots for mobil- og desktopvisning.

## [2.28.1] - 2026-09-06
- (#291) Rettet fatal error ved opplasting av betalingskvittering:
  - Fjernet referanse til ikke-eksisterende klasse `BookingObjectRepository` i `NotificationManager::send_payment_receipt_uploaded_notification`.
  - Henter tilknyttede bookingobjekter via databasetabellene med fallback til `booking_snapshot`, tilsvarende øvrige varslingsmetoder i `NotificationManager`.
  - Henter formatert bookingtidspunkt fra snapshot/tidsslott.
  - Lagt til filter for overstyring av opplastingshåndtering i `UploadPaymentReceiptApi` for programmatic- og enhetstesting.
  - Opprettet integrasjonstester i `PaymentReceiptNotificationTest` og utvidet `PaymentApiTest`.

## [2.28.0] - 2026-09-06
- (#289) Melding til bruker når admin endrer bookingstatus:
  - Lagt til to nye connected_to-hendelser i varslingssystemet: `booking-confirmed` (Reservasjon bekreftet) og `payment-received` (Betalingsstatus: Betalt).
  - Opprettet standard e-post- og SMS-maler med plassholdere for {{booking_date}} {{booking_time}} og tilpasset ordlyd.
  - Lagt til innstillinger for å aktivere/deaktivere automatisk utsendelse av e-post og SMS for de nye hendelsene under admin-innstillinger.
  - Koblet automatisk utsendelse til kunden ved administrativ godkjenning av booking (`BookingActionsApi::update_status`) og ved registrering av betalingsstatus som "Betalt" (`UpdatePaymentStatusApi::update_payment_status`).
  - Opprettet migrasjon `Migration_2_28_0` for automatisk seeding av standardmaler i eksisterende installasjoner.

## [2.27.5] - 2026-09-06
- (#284) Oppdatert avhengigheter for å lukke kjente sårbarheter:
  - Utførte `npm audit fix` som oppdaterte `browserslist` (4.28.2 => 4.28.9) og tilknyttede pakker (`caniuse-lite`, `update-browserslist-db`, `electron-to-chromium`, `node-releases`, `baseline-browser-mapping`) for å eliminere sårbarheter (GHSA-c83g-rgw3-j3cx, GHSA-73wf-gq98-2v4g).
  - Oppdaterte Composer-avhengigheter (`composer update`) med tilgjengelige patch-oppdateringer for PHPUnit, PHPStan, php-parser, deep-copy og sebastian-biblioteker. Verifiserte at `composer audit` rapporterer 0 kjente sårbarheter.

## [2.27.4] - 2026-09-06
- (#286) Fjernet hendelsesfiltre i kommunikasjonshistorikken for booking-detaljer:
  - Droppet filterkontrollene (`.msg-filter-controls`) og tilhørende avkryssingsbokser for hendelsestyper i booking-detaljer i administrasjonen.
  - Viser all meldingshistorikk direkte og ufiltrert når kommunikasjonsseksjonen åpnes.
  - Ryddet bort tilhørende filterlogikk i JavaScript (`admin.js`) og CSS (`admin.css`).
  - Oppdatert tester og snapshots for visuell og funksjonell verifisering.

## [2.27.3] - 2026-09-06
- (#283) Forbedret mobilvisning for hurtiglenker i administrasjonens booking-oversikt:
  - Erstattet fastlåst inline flex-styling i `BookingsPage::render_quick_links` med responsive CSS-klasser (`.snippen-quick-links`, `.snippen-quick-links-header`, `.snippen-quick-links-list`, `.snippen-quick-link`).
  - Tillatt naturlig wrapping (`flex-wrap: wrap`) av hurtiglenker ved mange lenker, og stablet layout på mobil (`<= 768px`) uten forstyrrende horisontale `|` skilletegn.
  - Sikret at filtergrupper og inputs i booking-oversikten aldri overskrider skjermbredden på mobil.
  - Oppdatert booking-oversikt UI-tester og fixtures til å verifisere responsiv oppførsel og fravær av horisontal scrolling ved mange hurtiglenker.

## [2.27.2] - 2026-09-06
- (#281) Forbedret mobilvisning for prisregler og rabattregler i admin:
  - Fikset overlappende titteltekst i `.snippen-admin-header h1` med romslig linjehøyde (`line-height: 1.35;`) og fleksibel flyt/gap for handlingsknapper på smale skjermer.
  - Pakket tabeller inn i `.snippen-table-responsive` med jevn berøringsrulling (`-webkit-overflow-scrolling: touch;`), slik at tabeller med mange kolonner aldri flyter utenfor `.snippen-card` eller skjermkanten på mobil.
  - Forbedret `admin-table-filter.js` til å automatisk pakke filterbare tabeller i en responsiv rullekontainer, plassere filterkontroller over kontaineren, og unngå tomme filterrader når ingen filtre er definert.
  - Komprimerte tabell-headere, celler og filter-inputs på mobil (`<= 768px`) for bedre overblikk og plassutnyttelse.
  - Lagt til `data-filter-type` og `data-sort-type` på `DiscountPage` for full filtrering- og sorteringsstøtte.
  - Lagt til Playwright UI- og visuell regresjonstest for regeltabellene under desktop og mobil.

## [2.27.1] - 2026-09-06
- (#279) Forbedret mobilresponsivitet og kommunikasjonshistorikk i administrasjonens booking-oversikt:
  - Fikset bredde og layout for detaljvisning på mobil: erstattet fastlåste `grid-column: span`-regler med responsive klasser og lagt til eksplisitt breddekontroll (`100%`, `box-sizing: border-box`, `min-width: 0`) slik at ingen felt eller knapper havner utenfor skjermkanten.
  - Gjorde kommunikasjonshistorikken sammenleggbar som standard med en kompakt header og en "Vis kommunikasjon" / "Skjul kommunikasjon"-knapp for renere og mer oversiktlig detaljvisning.
  - Fjernet interne scrollbars på meldingsliste og enkeltmeldinger slik at hele meldingsteksten er lesbar og visningen utelukkende styres av sidens hoved-scroll.
  - Lagt til automatisk åpning av meldingshistorikken ved utsendelse av nye meldinger fra Booking-hjelperen.
  - Etablert UI- og visuell regresjonstest for booking-oversikten under desktop og mobil i Playwright.

## [2.27.0] - 2026-09-06
- (#259) Implemented two-way SMS inbox with rule-based booking resolution, quarantine management, and admin inbox UI:
  - Added `SmsInboxResolverService` (`inc/Service/Sms/`) with prioritized 6-rule resolution engine:
    - Rule 1 (Active session): Automatically associates replies within conversation TTL to the previous booking context.
    - Rule 2 (Disambiguation selection): Resolves tenant replies (numeric `1`, `2`, `nr. 1`, ordinals `første`, `andre`) to candidate bookings and updates awaiting quarantine messages.
    - Rule 3 (Single active booking): Unambiguous automatic matching to the active booking.
    - Rule 4 (Multiple active bookings): Places inbound SMS in quarantine (`pending_selection`) and enqueues an automated disambiguation prompt SMS listing candidates with numbers.
    - Rule 5 (Registered user without active booking): Links to `user_id` with `booking_id = NULL` under status `general_inquiry`.
    - Rule 6 (Unknown sender): Safely retains unlinked messages in quarantine (`quarantine`) for administrative handling.
  - Added dynamic admin settings in `SnippenSmsProvider`: `snippen_sms_active_booking_past_days` (default 2 days for paid bookings), `snippen_sms_unpaid_booking_past_days` (default 0 days for unpaid bookings), `snippen_sms_conversation_ttl_minutes` (default 120 mins), and `snippen_sms_auto_disambiguate` toggle.
  - Enhanced REST endpoints in `SmsGatewayApi`: `POST /wp-json/snippen/v1/sms/inbox` running the resolution engine with detailed results, and `GET /wp-json/snippen/v1/sms/inbox` for querying and filtering inbound SMS messages.
  - Added dedicated Admin page `SMS Innboks & Karantene` (`SmsInboxPage`) under Snippen Booking menu for inspecting inbound SMS, filtering by status, and manual single-click assignment to reservations.
  - Added helper methods in `MessageLoggerService` (`get_inbound_messages`, `count_inbound_messages`, `assign_message_to_booking`).
  - Added unit tests (`SmsInboxResolverServiceTest`) covering all 6 rules and payment status filtering, and integration tests (`SmsGatewayApiTest`) for `POST` and `GET /inbox`.

## [2.26.0] - 2026-09-05
- (#275) Established automated UI verification and visual regression testing tooling:
  - Configured Playwright with focused golden snapshot testing for desktop (1280px) and mobile (375px) viewports across critical frontend components (booking calendar week grid and booking details overlay).
  - Created isolated, lightweight HTML fixtures (`tests/ui/fixtures/`) executing against real plugin styles (`booking.css`) with zero backend/database startup overhead, achieving ~1.8s execution time.
  - Added targeted npm scripts: `npm run test:ui:fast` for quick local UI regression checks, `npm run test:ui` for full test suites, and `npm run test:ui:update` for golden snapshot baselines.
  - Added `.github/workflows/ui-tests.yml` for automated CI visual regression testing with artifact diff uploads on failure.
  - Pre-cached Chromium and Playwright system dependencies in DevContainer (`.devcontainer/Dockerfile` and `devcontainer.json`).
  - Added `.agents/node-agent-instructions` submodule and documented UI testing guidelines in `AGENTS.md`, `.agents/TESTING.md`, and `DEV_README.md`.

## [2.25.0] - 2026-09-05
- (#272) Improved mobile user experience and responsiveness across all modal and overlay views (single booking UUID popup, calendar booking info modal, rental terms iframe modal, and admin notification dispatch dialog):
  - Added background scroll locking (`body.snippen-modal-open`) preventing the underlying page from scrolling while overlays are active.
  - Constrained overlays within viewport bounds using dynamic viewport units (`100dvh` / `calc(100dvh - 32px)` with `vh` fallback) so modals never exceed screen height or width on mobile devices (including Safari and Android address bars and landscape orientations).
  - Structured modals with fixed headers and scrollable bodies (`overflow-y: auto`, `overscroll-behavior: contain`), ensuring the close button and title remain pinned and always accessible.
  - Added keyboard `Escape` key listeners to dismiss active modals cleanly across all views.
  - Optimized mobile touch targets and padding on small screens.

## [2.24.0] - 2026-09-05
- (#270) Enhanced demo and gateway setup scripts (`bin/demo-gateway.php`, `composer demo:gateway`) to automatically create and publish frontend booking demo pages (combined calendar, room booking pages, account activation), seed multiple resident test users (`test.guest` / `+4799887766`, `kari.nordmann` / `+4799887767`, `per.hansen` / `+4799887768`), and seed multiple bookings across rooms and dates for testing.

## [2.23.0] - 2026-09-03
- (#265) Supported automated headless provisioning and SMS gateway demo setup for Docker Compose integration testing alongside `snippen-testing` and `snippen-sms-service`.
  - Added `composer demo:gateway` and `composer demo:e2e` scripts (`bin/demo-gateway.php`) configuring API token (`SNIPPEN_SMS_API_TOKEN` / `test-integration-token`), active provider (`snippen_sms_service`), and seeding test resident user, confirmed booking, and queued outbound message for recognizable test phone `+4799887766`.
  - Added container healthcheck support (`bin/healthcheck.sh`, `composer healthcheck`) verifying both WordPress root HTTP responsiveness and `/wp-json/snippen/v1/sms/outbox` endpoint availability.
  - Enhanced `.devcontainer/docker-entrypoint.sh` and root `Dockerfile` with headless startup parameters (`INIT_GATEWAY`, `INIT_DEMO`, `AUTO_DEMO`), dynamic `WP_HOME` / `WP_SITEURL` multi-host resolution, non-interactive execution, and fixed Apache DocumentRoot rewrite configuration before setup exit.
  - Added `HEALTHCHECK` instructions and integration test `DemoGatewayTest`.

## [2.22.0] - 2026-09-01
- (#263) Added Snippen SMS Service provider (`snippen_sms_service`) and REST synchronization gateway endpoints (`/wp-json/snippen/v1/sms/outbox`, `/outbox/status`, `/inbox`, `/bookings`). Outbound SMS messages are queued in `snippen_messages` table for gateway edge dispatch, status transitions to `sent`/`failed` are reported back, and incoming tenant SMS messages are recorded in communication history. Added admin settings configuration for API token, sender name, active SMS provider toggle, and queue status badge in communication history.

## [2.21.0] - 2026-08-25

- (#247) Implemented automated payment reminder notifications for unpaid bookings with configurable interval days (e.g. 30 and 21 days before booking), idempotent database tracking table `snippen_booking_payment_reminders`, payment receipt upload exemption, extensible `snippen_booking_should_send_payment_reminder` filter, default SMS and Email notification templates under `payment-reminder` context, and daily WP-Cron scheduling.

## [2.20.0] - 2026-08-24
- (#250) Harmonized SMS and Email notification templates by consolidating storage into custom database table `snippen_notification_templates`, implementing `NotificationTemplateRepository`, updating Admin management UI with dynamic placeholder status styling, and adding idempotent `composer demo:templates` setup.
- (#251) Standardized template placeholders by introducing a central `PlaceholderRegistry` single source of truth, removing old hardcoded placeholder handling, adding template placeholder validation on Admin save, and creating unit and integration tests for all 14 placeholders.

## [2.19.4] - 2026-08-22
- (#245) Fixed Dependabot security alerts #3, #4, #5, #6, and #11 by updating `js-yaml` and `brace-expansion` npm dependencies.

## [2.19.3] - 2026-08-22
- (#243) Fixed Dependabot security alerts #1 and #2 by updating `wp-coding-standards/wpcs` to v3.4.1 (CVE-2026-45293) and `squizlabs/php_codesniffer` to v3.13.6 (CVE-2026-67434).

## [2.19.2] - 2026-08-21
- (#240) Resolved CodeQL JavaScript code-scanning alerts #3, #4, and #5 by removing unused local variables in `account-confirmation.js`, `admin.js`, and `admin-table-filter.test.js`.

## [2.19.1] - 2026-08-21
- (#234) Resolved PHPCS errors and warnings across the codebase, configured `phpcs.xml` for PSR-4 autoloading & WordPress security sniffs, and fixed sanitization/escaping and loose comparison issues.

## [2.19.0] - 2026-08-21
- (#232) Displayed booking time in HH:mm format alongside slot name in the "Dato / Tid" column of the admin booking overview page.

## [2.18.0] - 2026-08-21
- (#223) Added user communication message logging (`snippen_messages` database table, `MessageLoggerService`). Logged sent emails and SMS (both automatic notifications and manual dispatches) with metadata context, `user_id`, and `booking_id`. Added communication history display section under booking details on the admin booking overview page.

## [2.17.0] - 2026-08-21
- (#208) Removed legacy `TimeSlotsPage.php` class and integration test `TimeSlotsPageTest.php`, cleaning up dead code replaced by `BookingBlocksPage`.

## [2.16.0] - 2026-08-21
- (#228) Added support for configurable custom instructions (`custom_instructions`) on time slots and booking blocks. Updated Admin UI with a text input field to configure custom messages per block (e.g. for weekend wash-time next morning until 11:00), display an info badge in time slot list and admin bookings overview, and inform frontend users during wizard selection and summary confirmation when custom instructions are configured.

## [2.15.0] - 2026-08-20
- (#226) Enhanced notification manual dispatch modal in admin booking overview with template selector dropdown (defaulting to booking confirmation), interactive placeholder chips with clipboard copy and cursor insertion, user warning upon template switch if message content was modified, and server-side placeholder replacement when sending.

## [2.14.0] - 2026-08-20
- (#222) Harmonized available placeholders across all notification templates, moved placeholder overview box to the top of the Notification Templates admin page, and added `booking_time` placeholder for booking notifications.

## [2.13.0] - 2026-08-20
- (#219) Allowed users to delete/cancel their own unconfirmed and unpaid bookings prior to the configurable cancellation deadline (default 14 days before booking start). Added cancellation deadline setting to admin general settings tab, updated frontend booking list shortcode and admin user bookings page, and enforced server-side validation.

## [2.12.0] - 2026-08-11
- (#220) Simplified weekly calendar view to display per-object availability status badges ("Ledig", "Delvis opptatt", "Opptatt") for better clarity and responsive mobile UX, moving detailed block selection and admin booking info into the day wizard view.

## [2.11.1] - 2026-08-10
- (#216) Ensured inactive/deactivated discount rules are excluded from `DiscountRuleRepository::find_all()` by default and passed date parameter to `find_applicable_rule` in legacy booking API.

## [2.11.0] - 2026-08-10
- (#214) Added confirmation modal/dialog for customer email and SMS manual dispatch in admin booking overview, allowing admins to review and edit message text before sending.

## [2.10.2] - 2026-08-10
- (#212) Updated booking block listings in Pricing Rule UI (form checkboxes and price preview dropdown) to display block name, description, active status, and time range.

## [2.10.1] - 2026-08-10
- (#209) Fixed user booking list views (`UserBookingsPage` admin menu and `BookingListShortcode` shortcode) to parse and display time range and venue object names from `booking_snapshot`.

## [2.10.0] - 2026-08-09
- (#206) Added `isActive` (`is_active`) state and inline AJAX toggle switch to Time Slots, Discount Rules, and Pricing Rules in admin list views.
- (#206) Updated time slot overlap validation so that deactivated time slots do not trigger overlap errors, allowing overlapping time slots as long as no more than 1 of them is active.

## [2.9.0] - 2026-08-09
- (#204) Added `booking_snapshot` JSON column to `wp_snippen_bookings` and migration `Migration_2_9_0` to freeze booking details (times, objects, blocks) and prevent double bookings when block configurations change.

## [2.8.3] - 2026-08-09
- (#202) Fixed missing time range (`Tidsrom:`) in admin bookings list when using booking blocks.

## [2.8.2] - 2026-08-09
- (#108) Added `composer test:fast` script for fast unit testing, optimized `TestCase.php` to skip DB operations for non-DB unit tests, enabled PHPUnit result caching, and updated developer documentation.

## [2.8.1] - 2026-08-09
- (#187) Changed login form in `[snippen_booking_list login-form="1"]` shortcode to use AJAX authentication (`UserApi::login`) to resolve server 503 Service Unavailable errors on POST to `wp-login.php`.

## [2.8.0] - 2026-08-09
- (#197) Changed booking object tabs in booking shortcode to closed horizontal collapsible accordion drawers that toggle open/closed on click with indicator icons (v / ^).

## [2.7.1] - 2026-08-09
- Simplified CSS styling to align with active WordPress default theme (Twenty Twenty-Five) and added design guidelines to `AGENTS.md`.

## [2.7.0] - 2026-08-08
- (#187) Fixed login authentication for username and email identifiers by tuning authentication filter priority and handling in `PhoneHelper::normalize_phone`.
- (#194) Moved payment instructions/deadline to a dedicated Notification Template (`payment_instructions`) with placeholders (`{{bank_account}}`, `{{vipps_number}}`, `{{user_name}}`, `{{booking_objects}}`, `{{booking_date}}`, `{{booking_price}}`).

## [2.6.1] - 2026-08-08
- (#188-#192) Added `Migration_2_6_1` to clean up obsolete `PENDING_VERIFICATION` payment status from `wp_snippen_payment_statuses` table and re-index canonical statuses (`UNPAID`, `PAID`, `EXEMPT`).
- (#188-#192) Automatically confirm pending bookings when admin registers payment status as `PAID` or `EXEMPT`.
- (#188-#192) Isolate receipt uploads into unguessable directory structure `wp-content/uploads/userdata/booking_uuid_<uuid>/`.

## [2.6.0] - 2026-08-08
- (#188) Added database table `wp_snippen_payment_statuses` (`UNPAID`, `PENDING_VERIFICATION`, `PAID`, `EXEMPT`) with `is_settled` status tracking, and updated bookings schema for payment receipts and notes (`Migration_2_6_0`).
- (#189) Added admin payment settings tab for configuring bank account, Vipps instructions, payment terms, and admin email notification settings.
- (#190) Added frontend payment details and receipt image/PDF upload form (`UploadPaymentReceiptApi`) supporting both logged-in users and guest access via UUID token link.
- (#191) Added admin payment management controls to view receipt attachments, update payment status, and record transaction notes (`UpdatePaymentStatusApi`).
- (#192) Added payment status filtering (`UNPAID`, `PENDING_VERIFICATION`, `PAID`, `EXEMPT`, `settled`, `unsettled`) and payment status badges to admin booking overview list.

## [2.5.0] - 2026-08-05
- (#182) Updated Help admin page with documentation on tagging WordPress pages with `snippen-booking` for admin overview quick links.
- (#183) Added fixed price (`fixed_price`) discount type option in discount rules calculation and admin interface.
- (#184) Updated account confirmation shortcode to display a highlighted activation status notice with the user's name when logged in.
- (#185) Displayed actual time interval (e.g. `kl 16 - 23`) under timeslot names on calendar buttons and wizard.

## [2.4.0] - 2026-08-04
- (#180) Updated booking shortcode header to render individual object selector buttons and object descriptions interactively when multiple objects are shown in calendar.

## [2.3.0] - 2026-08-04
- (#178) Updated user booking list shortcode to show compact list layout, filter current/upcoming bookings by default (sorted ASC), and added archive toggle to view past bookings (sorted DESC).

## [2.2.1] - 2026-06-14
- (#174) Added a new setting to configure the booking horizon (how far ahead in time the calendar dropdown menu displays, default 52 weeks).

## [2.2.0] - 2026-06-13
- (#172) Added functionality to link door codes directly to bookings. Administrators can now easily filter and add door codes to bookings from the booking overview page.

## [2.1.9] - 2026-06-12
- (#170) Made the admin booking list and user bookings list responsive on mobile screens.

## [2.1.8] - 2026-06-11
- (#168) Added support for routing password reset notifications based on global settings regardless of input method.
- Added autocomplete="new-password" to KeySMS API Key field to prevent auto-fill.

## [2.1.7] - 2026-06-11
- (#166) Added setting to completely disable all email dispatching to prevent timeouts on environments without email configuration.

## [2.1.6] - 2026-06-10
- (#162) Added option to preserve user metadata and the resident role during plugin uninstallation, allowing safe data retention for reinstallations.

## [2.1.5] - 2026-06-10
- (#160) Added door code, discount, and specific start/end times to the booking details in the admin overview.

## [2.1.4] - 2026-06-09
- (#157) Removed price display from the calendar view to avoid confusion with dynamic pricing based on user selection.

## [2.1.3] - 2026-06-08
- (#155) Changed calendar rendering to visualize remaining booking capacity with a segment indicator instead of a binary available/unavailable status.

## [2.1.2] - 2026-06-07
- (#152) Updated English translation files (`en_US`) to cover new features like discount rules and wizard.

## [2.1.1] - 2026-06-07
- (#150) Fixed uninstall script to drop all missing `snippen_` tables.

## [2.1.0] - 2026-06-07
- (#147) Added duration-based discount rules for bookings

## [2.0.1] - 2026-06-07
### Added
- Feature (#144): Added `demo:wizard2` composer script and `create_starter_setup_v2` in SetupWizard to support a simpler, legacy-style variant with only 2 blocks per day (Dag and Kveld).
## [2.0.0] - 2026-06-06
### Changed
- Feature (#142): Changed shortcode `[snippen_booking]` to display all active booking objects when called without the `object_id` argument. Updated related documentation and help page.
### Added
- Feature (#130): Added Admin Management of Pricing Rules.
- Feature (#130): Added `PricingPreviewApi` and UI for admins to preview rules in real-time.
- Feature (#130): Upgraded the Pricing Rules Admin UI to support the new `is_active` toggle, day restrictions, date restrictions, and holiday overrides.
- Refactor (#130): Created `PricingRuleRepository` for CRUD and pricing resolution logic based on priority.
- Feature (#129): Added Admin Management of Booking Blocks.
- Feature (#129): Added a new backend view `BookingBlocksPage` with support for managing, filtering and viewing weekly setup.
- Feature (#129): Replaced `TimeSlotsPage` in admin dashboard with `BookingBlocksPage`.
- Feature (#128): Created a new modern interactive wizard-based frontend booking interface supporting block-based selections (`booking_blocks`).
- Feature (#128): Implemented week-based layout (Monday-Sunday) and responsive CSS to fold columns vertically on mobile.
- Feature (#128): Hid past/unbookable days on mobile view to optimize scrolling.
- Feature (#128): Enforced adjacent block selection and enabled live pricing calculations during room/block selection.
- Refactor (#128): Maintained 100% backward compatibility for legacy slot-based bookings in Booking API and Repositories.
- Redesign (#127): Redesigned core domain model to support flexible block-based selections (`booking_blocks`) and priority-based pricing rules (`pricing_rules`) instead of legacy slot-based systems.
- Feature (#127): Updated demo-data seeding within the Setup Wizard to support Mon-Thu hourly bookings (08-23), Friday hourly bookings (08-16) plus an evening block (16-23), and weekend/holiday Day (08-16) and Evening (16-23) blocks with matching pricing.
- Refactor (#127): Retained backward compatibility with slot-based tables and wrappers in Availability and Pricing services during transition.

## [1.23.5] - 2026-06-06
### Fixed
- Bug (#137): Skjuler nå fortidige dager (.day-column.past) på mobilvisning (skjermer under 600px bredde) i uke-kalenderen, for å unngå at brukeren må scrolle forbi dager som ikke er bookbare.

## [1.23.4] - 2026-06-06
### Fixed
- Bug (#134): Fikset et problem der `snippen-booking-list-container` brøt ut av sin egen div på grunn av et ekstra, feilaktig plassert `</div>`-element.

## [1.23.3] - 2026-06-06
### Fixed
- Bug (#133): Fikset problem med at Safari autofyll lagret SMS-kode som brukernavn ved å legge til et skjult brukernavnfelt og skjema under kontoaktivering.

## [1.23.2] - 2026-06-04
### Added
- Feature (#125): Lagt til avhukingsboks i innstillinger under fanen Generelt for å aktivere eller deaktivere dørkode-systemet.
- Feature (#125): Skjult dørkoder og tilhørende innstillinger/skjemaer fra alle administrative og offentlige visninger (SettingsPage, BookingObjectsPage, Plugin popup, UserBookingsPage, BookingListShortcode) dersom systemet er deaktivert.

## [1.23.1] - 2026-06-04
### Added
- Feature (#123): Lagt til egne avhukingsbokser (checkboxes) for varsling til administratorer om nye bookinger på både E-post og KeySMS (SMS).
- Feature (#123): Lagt til egne avhukingsbokser (checkboxes) for hver enkelt varslingstype (bookingbekreftelse, kontoregistrering, tilbakestilling av passord) under henholdsvis E-post og KeySMS. Gjør det mulig å sende varsler til både e-post og SMS samtidig.
- Refactor (#123): Refaktorert innstillingssiden (Settings UI) til et ryddig, fanebasert grensesnitt (E-post, KeySMS, Generelt) med egne "Aktiv"-valg for varslingskanaler.
- Refactor (#123): Forenklet varslingsrutingen slik at SMS (KeySMS) foretrekkes hvis den er aktivert og kunden har telefonnummer registrert, med automatisk fallback til e-post.
- Refactor (#123): Fjernet SMS Sandbox-modus og tilhørende innstillinger/logging.
- Feature (#123): Fullførte implementeringen av Booking-hjelperen (Booking Assistant) i administrasjonsgrensesnittet med knapper for manuell utsendelse av e-post til kunde, SMS til kunde og varsel til admin, komplett med tilhørende integrasjonstester.

## [1.23.0] - 2026-06-04
### Added
- Feature (#123): Implementerte synkron utsendelse av SMS/e-post via en dedikert "Booking Hjelper" (Booking Assistant) i administrasjonsgrensesnittet.

## [1.22.13] - 2026-06-03
### Added
- Feature (#120): Lagt til detaljert logging i `EmailProvider` og `NotificationManager` for å kunne spore nøyaktig hvor den synkrone utsendelsen stopper eller henger på produksjonsservere.

## [1.22.12] - 2026-06-03
### Added
- Feature (#120): Lagt til valg for utsendelsesmetode i Innstillinger. Tillater å velge mellom asynkron (WP-Cron) og synkron (direkte i AJAX-kallet) utsendelse av booking-varsler for å omgå loopback/WP-Cron problemer på delte webhotell (f.eks. ProISP).

## [1.22.11] - 2026-06-03
### Added
- Feature (#118): Lagt til detaljert feilsøkingslogging (verbose debug logging) i `KeySmsService` for å gjøre det enklere å spore og diagnostisere feil ved utsendelse av SMS på produksjonsservere.

## [1.22.10] - 2026-06-03
### Added
- Feature (#114): Viser nå tilknyttet pris i redigeringsvisningen for tidsluker som en skrivebeskyttet (read-only) tekst med en lenke direkte til den tilhørende prisregelen.
- Fikset colspan i tidsluketabellen når det ikke finnes noen tidsluker (endret fra 5 til 7).

## [1.22.9] - 2026-06-03
### Fixed
- Fixed booking filters permission error ("Sorry, you are not allowed to access this page") on the overview page (#116) by correcting the form's page slug parameter from `snippen-booking-oversikt` to the registered `snippen-booking`.

## [1.22.8] - 2026-06-03
### Changed
- Feature (#107): Optimalisert kjøring av integrasjonstester ved å ta i bruk databasetransaksjoner (`START TRANSACTION` / `ROLLBACK`). Dette reduserer kjøretiden for integrasjonstestene fra over ett minutt til under 10 sekunder (85% forbedring).

## [1.22.7] - 2026-06-03
### Changed
- Feature (#106): Lagt til `$requires_db` flagg i `TestCase` for å unngå unødvendig databasetømming og populering i enhetstester som ikke krever det. Dette reduserer kjøretiden for enhetstester med 50%.

## [1.22.6] - 2026-06-02
### Changed
- Kansellerte (avbrutte) bookinger skjules nå som standard i kalenderen, i "Booking Oversikt" og i "Mine Bookinger" (med mindre man spesifikt filtrerer på dem i admin-panelet).

## [1.22.5] - 2026-06-02
### Fixed
- Fikset et problem hvor treg SMTP-tilkobling for e-postutsending førte til at booking-forespørsler (AJAX) fikk timeout (Tilkoblingsfeil). Utsendelse av e-post og SMS er nå asynkron.

## [1.22.4] - 2026-06-02
### Changed
- Feature (#102): Oppdatert rekkefølge og default valg i admin-menyen slik at "Oversikt" er standard for booking-administratorer og "Hjelp / Manual" plasseres nederst. For vanlige innloggede brukere uten booking-administrator tilgang er "Hjelp / Manual" fortsatt standard.

## [1.22.3] - 2026-06-02
### Changed
- Slettet output som skaper støy i loggene når bruker blar i kalenderen.

## [1.22.2] - 2026-06-02
### Fixed
- Feature (#99): Fikset en feil i API-et og demo-data scriptet hvor bookinger med kombinasjons-tidsluker (f.eks. "Hele området") kunne bli lagret med kun ett enkelt booking-lokale, noe som skapte feil i administrasjonsvisningen.

## [1.22.1] - 2026-06-02
### Fixed
- Feature (#97): Kansellerte bookinger blokkerer ikke lenger tilgjengelighet for tidsluker.

## [1.22.0] - 2026-06-02
### Changed
- Changed relationship between Prices and Time Slots from 1-to-1 to 1-to-Many.
- Updated database schema to move `price_id` to `wp_snippen_time_slots` table.
- Revamped Pricing admin page UI to support assigning a single price to multiple time slots via checkboxes.
- Added "Tilknyttet Pris" column and "Uten pris" filter to Time Slots admin page.
- Added Julaften and Nyttårsaften as holidays for booking purposes.

## [1.21.0] - 2026-06-01
### Fixed
- Fikset en feil i `js/booking.js` som gjorde at ingen tidsluker ble vist i kalenderen (feil type-sjekk for slot.id).
- Lagt til `Migration_1_21_0` for å rette opp feil navn på tidsluker som ble med over fra forrige versjon.

### Changed
- Fjernet den separate "Gjelder kun helligdager"-bryteren i backend, og byttet dette ut med "Helligdag" som et av valgene i listen over ukedager (`days_of_week`). Dette tillater å merke tidsluker for både helg og helligdag, osv.

## [1.20.0] - 2026-06-01
### Added
- Innførte koblingstabell `snippen_time_slot_booking_objects` for å koble tidsluker direkte til spesifikke booking-objekter (f.eks. "Festsalen" eller "Peisestuen").
- Oppdaterte oppsettsveiviseren (SetupWizard) til å opprette unike tidsluker per rom/kombinasjon (totalt 21 start-luker i stedet for 9).

### Changed
- Fjernet funksjonalitet for `allow_multi_object` fra tidsluker. Tidsluker er nå konfigurert med checkboxes for valg av spesifikke rom i Admin UI.
- Fjernet koblingstabellen `snippen_price_booking_objects`. Pris er nå utelukkende knyttet til en tidsluke, noe som forenkler priskalkulering og fjerner tvetydighet.
- Oppdaterte AvailabilityService og PricingService til å reflektere den nye direktekoblingen mellom rom og tidsluker.
- Forbedret Admin UI for tidsluker (viser nå rom direkte i tabellen).
- Forenklet Admin UI for prisregler ved å fjerne visning og valg av rom, siden pris følger tidsluken.

## [1.19.0] - 2026-06-01
### Changed
- Feature (#95): Refactored availability rules from prices to time slots.
- Added database migration `Migration_1_19_0` to support the new schema.
- Updated pricing service and time slots UI to reflect new availability data structure.

## [1.18.0] - 2026-05-31
### Added
- Feature (#93): Added advanced filtering and sorting for Pricing Rules (Prisregler) in the admin dashboard.
- Built a reusable `admin-table-filter.js` component for easy extension to other admin tables.
- Added Jest and JSDOM to the project for JavaScript unit testing, and added tests for the new filter component.

## [1.17.1] - 2026-05-28
### Fixed
- **Password Reset**: Fixed an issue where the password reset link was not displayed on the login form for users logging in with their phone number.
- **Update help docs**: Updated help docs to include instructions on the booking-list shortcode.

## [1.17.0] - 2026-05-28
### Added
- Feature (#90): Implemented password reset via SMS. Users who log in using their phone number will now receive a password reset link via SMS instead of email.
- Added `password_reset` event type to Notification Templates, allowing customization of both SMS and Email password reset messages.


## [1.16.0] - 2026-05-29
### Added
- Feature (#84): Support SMS and Email channels with default templates
- Dynamic placeholder replacement (e.g. {{user_name}}, {{booking_date}})
- Reset templates to defaults functionality
- Integrate templates into NotificationManager for rendering
- Add comprehensive test coverage for template functionality
- Update version to 1.16.0 and changelog

## [1.15.0] - 2026-05-28
### Added
- Feature (#88): Require acceptance of rental terms upon booking. Added a new setting for the terms URL and a mandatory checkbox in the booking form.

## [1.13.3] - 2026-05-25

### Fixed
- **Resident Import**: Fixed an issue where the resident import script inadvertently overwrote the roles of existing administrators. Administrators are now excluded from being marked as deleted if they are not in the import list, and a warning is logged when this occurs.

## [1.13.2] - 2026-05-24

### Changed
- **Visual Adjustments**: Applied identical styling (padding, background, border-radius, shadow) to the "Mine bookinger" list container as the main reservation shortcode to ensure design consistency.
- **Form Refinements**: Reduced vertical padding and margins on booking form input fields and textareas to prevent overlapping with adjacent design elements and improve compactness.

## [1.13.1] - 2026-05-24

### Changed
- **Visual Refresh**: Redesigned the empty reservations state (`.snippen-empty-bookings-card`) to perfectly match the minimalist, typography-first WordPress theme. Removed noisy dashed borders, bright colors, and generic dashboard elements in favor of a clean, subtle layout featuring a monochrome SVG icon and elegant typography.

## [1.13.0] - 2026-05-24

### Changed
- **WordPress 7.0 Support**: Upgraded the local development environment and GitHub Actions test matrix to use WordPress 7.0 to ensure full compatibility with the latest core release.

## [1.12.0] - 2026-05-24

### Changed
- **Terminology Update**: Refactored the internal identifier and display name for residents. Renamed "Holmen Sameie Resident" to "Snippen Resident" across the codebase, translations, and documentation to better align with the plugin's branding.
- **Database Migration**: Added `Migration_1_12_0` to safely migrate any existing users with the `holmen_resident` role to the new `snippen_resident` role.

## [1.11.0] - 2026-05-23

### Added
- **Security Helper**: New centralized `SnippenBooking\Helper\Security` class providing reusable utilities for nonce verification, safe POST/GET access, and SQL LIKE escaping.

### Security
- **CSRF Protection**: Added nonce verification to all previously unprotected AJAX endpoints:
  - `AvailabilityApi::get_availability()` — verified for logged-in users (public read-only endpoint)
  - `UserApi::search_users()` — strict admin nonce verification
  - `UserApi::request_confirmation_code()` — confirmation nonce verification
  - `UserApi::verify_confirmation_code()` — confirmation nonce verification
- **XSS Prevention (PHP)**: Escaped all dynamic output in HTML attributes using `esc_attr()`:
  - `BookingShortcode` data attributes (`data-user-id`, `data-user-name`, `data-user-email`, `data-user-phone`)
  - `BookingsPage` and `UserBookingsPage` booking ID attributes
  - `Plugin.php` booking status CSS class and price output
- **XSS Prevention (JavaScript)**: Added `escHtml()` helper to `booking.js` to escape user data before rendering in:
  - Admin booking detail modal
  - User search results dropdown
- **SQL Injection Prevention**: Applied `$wpdb->esc_like()` to search term in `BookingsPage` LIKE clause to prevent wildcard injection.
- **Input Sanitization**: Sanitized `$_POST['action']` in `SetupWizardPage` form handler.
- **Output Escaping**: Escaped import log output with `esc_html()` in `ImportPage`.

## [1.10.0] - 2026-05-22

### Added
- **Pluggable Resident Import Architecture**: Refactored the resident import page to use a flexible, provider-based architecture.
- **Import Providers**: Split the existing "Line-by-line" and "TSV" parsing logic into their own independent provider classes for easier maintenance and future extensibility.
- **Provider UI Customization**: Allowed each import provider to render its own specific configuration fields within the import page.

## [1.9.0] - 2026-05-22

### Added
- **User Manual**: Added a dedicated "Hjelp / Manual" section in the WordPress admin interface.
- **Onboarding**: Improved discoverability after installation by providing built-in documentation about core concepts, shortcodes, and configuration.

## [1.8.0] - 2026-05-21

### Added
- **Uninstall Routine**: Added `uninstall.php` to clean up all database tables, options, and user meta when the plugin is deleted.
- **Uninstall Settings**: Added a new "Behold data ved avinstallering" option in Settings to preserve data when uninstalling, which is useful for temporary plugin deactivation.

## [1.7.1] - 2026-05-21

### Added
- **English Translation**: Added English translation (`en_US`) by generating the `.po` and `.mo` files for all user-facing strings.

## [1.7.0] - 2026-05-21

### Added
- **Full Internationalization (i18n)**: Implemented full translation support for the plugin to meet WordPress standards.
- **Text Domain**: Registered `snippen-booking` text domain with a placeholder `/languages` directory.
- **JavaScript Localization**: Passed translated PHP strings to JS via `wp_localize_script`, replacing all hardcoded Norwegian strings in `js/booking.js` and `js/account-confirmation.js`.
- **PHP Localization**: Audited and replaced raw strings in API responses, Shortcodes, Admin pages, and Services with translation functions (`__()`, `esc_html__()`, etc.).
- **Robust SMS/Email Notification Strings**: Updated `NotificationManager` subjects and messages to use `sprintf` and `__()` properly instead of raw concatenations.

### Fixed
- **Test Flakiness**: Fixed two flaky integration/unit tests (`SetupWizardTest` and `BookingsPageTest`) by ensuring clean database and post states before execution.
## [1.6.2] - 2026-05-21

### Changed
- **Capability-Based Authorization**: Replaced all hardcoded string checks for `manage_snippen_bookings` with the `Capabilities` helper class.
- **Admin Access**: Removed implicit `manage_options` mapping to `manage_snippen_bookings` capability for administrators. The capability must now be explicitly assigned.

## [1.6.1] - 2026-05-20

### Fixed
- **Test Suite**: Resolved massive test failures in `Unit` and `Integration` tests caused by the v1.6.0 removal of automatic database seeding.
  - Implemented dynamic seed data injection within the core `TestCase::setUp()` environment via `SetupWizard::create_starter_setup()`.
  - Added strict database table isolation using `TRUNCATE` instead of `DELETE` to ensure `AUTO_INCREMENT` values start cleanly at 1 for every test.
  - Updated legacy test assertions, capability checks (`manage_snippen_bookings`), and email subject matching strategies to pass under the newly decoupled architecture.
  - Mitigated test flakiness by forcing `uniqid()` on mock user creation routines.

## [1.6.0] - 2026-05-20

### Changed
- **Removed Automatic Seed Data on Activation**: Plugin activation no longer automatically creates demo booking objects, time slots, and pricing models. This aligns with WordPress.org plugin expectations and ensures production-safe deployments.
  - Plugin activation now only creates database schema, registers capabilities, and initializes required options.
  - Database remains empty after activation, preventing pollution of production environments.

### Added
- **Optional Setup Wizard**: Implemented a lightweight setup wizard accessible after first plugin activation or manually via admin menu (Snippen Booking > Setup Wizard).
  - **Onboarding Flow**: Guides administrators through optional configuration with the ability to create starter data.
  - **Skippable**: Administrators can skip the wizard entirely and configure everything manually later.
  - **Repeatable**: Setup wizard can be re-run at any time from the admin menu.
  - **Idempotent Creation**: Wizard prevents duplicate seed data creation if run multiple times.
  - **Smart Redirect**: Automatically redirects administrators to wizard after first activation (only once, only for single activation, respects bulk plugin activation).

- **SetupWizard Class**: New `SnippenBooking\Admin\SetupWizard` class providing:
  - `is_completed()` - Check if wizard has been completed
  - `mark_completed()` - Mark wizard as completed (stores version info for future migrations)
  - `reset()` - Reset wizard state for testing/re-running
  - `create_starter_setup()` - Create starter booking objects, time slots, and pricing (idempotent)

- **SetupWizardPage Admin Page**: New admin interface displaying:
  - Welcome screen with setup wizard overview
  - Button to create starter setup with 2 sample booking objects (Festsalen, Peisestuen), 3 time slots, and pricing for weekdays/weekends/holidays
  - Option to skip wizard for manual configuration
  - Information about what starter setup includes

- **Comprehensive Test Suite**:
  - `SetupWizardTest.php` - Unit tests for wizard state management and idempotent data creation
  - `SetupWizardPageTest.php` - Integration tests for admin page rendering and form submission
  - Updated `InstallTest.php` - Verification that activation no longer creates seed data

## [1.5.6] - 2026-05-20

### Added
- **Pluggable Notification Architecture**: Replaced tightly-coupled, hardcoded SMS and email fallback logic with an extensible, provider-based notification system registered via dynamic filters.
- **Delivery Channels Routing**: Added a configuration panel under "Innstillinger" allowing administrators to individually route each system notification type (`user_activation`, `booking_confirmation`, `admin_booking_notification`) to either **Kun E-post (Email only)** or **SMS (med e-post fallback)**.
- **SMS Sandbox / Utviklingsmodus**: Introduced a global settings sandbox checkbox. When enabled, all SMS notifications bypass actual sending and gracefully route to their email fallback, preventing unnecessary SMS fees during development and testing.
- **Status Badges & Dynamic Admin Forms**: Rendered premium provider selection cards displaying active indicators and real-time status badges (Configured vs Missing settings). Custom dynamic setting forms load fields dynamically based on provider schemas with fluid CSS fade-in animations.
- **Unit and Integration Tests**: Implemented a comprehensive suite of tests (`NotificationManagerTest.php` and `NotificationPluggableTest.php`) verifying dynamic provider registration, schema loading, sandbox mode interceptions, fallback routing, and legacy option migration pathways.

## [1.5.5] - 2026-05-20

### Added
- **Booking Management Capability**: Introduced the dedicated `manage_snippen_bookings` capability to decouple booking administration from the WordPress `administrator` role.
- **User Profile UI Field**: Added a new "Booking administrator" checkbox to the WordPress user profile screen (visible only to those who can edit users) to manually grant or revoke the capability.
- **Backward Compatibility Mapping**: Implemented a dynamic mapping filter on `user_has_cap` to automatically grant `manage_snippen_bookings` capability to existing site administrators (users with `manage_options`) without silent database modifications.
- **Notification email routing**: Updated email notification dispatching so booking request notifications are sent exclusively to users possessing the `manage_snippen_bookings` capability.
- **Integration Tests**: Created a full suite of integration tests in `BookingManagementCapabilityTest.php` covering dynamic user capability assignment, backwards compatibility, action access controls, and notification email routing.

## [1.5.4] - 2026-05-19

### Changed
- **Account Confirmation Shortcode**: Updated the `[snippen_account_confirmation]` shortcode to return an empty string when the user is already logged in, ensuring no redundant output is displayed.
- **Unit and Integration Tests**: Added a new integration test suite `AccountConfirmationShortcodeTest.php` to verify shortcode behavior for logged-in and guest users.


## [1.5.3] - 2026-05-19

### Removed
- **Vipps Login**: Removed all references, CSS styling, documentation, and buttons/links to Vipps login across the plugin UI and code.
- **Login Card Divider**: Removed the "eller" divider and Vipps login container from the guest login card.

### Changed
- **Modal and Calendar Login Buttons**: Replaced the Vipps login buttons in the booking details modal and the main calendar page with standard "Logg inn" buttons/links styled with primary brand color blue.

## [1.5.2] - 2026-05-19

### Added
- **Booking List Shortcode**: Created the `[snippen_booking_list]` shortcode to render the current user's booking history in a premium card list on the frontend.
  - Supports self-cancellation of bookings with responsive AJAX updates.
  - Displays dynamic secure door codes within the configurable time window.
  - Incorporates the `login-form` attribute to display a sleek, custom-designed login card and "Logg inn med Vipps" button to guests.
- **Demo Page Integration**: Automatically appended the booking list shortcode to the Account Confirmation demo page to showcase both user-facing actions in one view.
- **Unit and Integration Tests**: Implemented comprehensive unit and integration test coverage for the shortcode registration, guest-rendering, login box, and resident booking lists.

## [1.5.1] - 2026-05-19

### Added
- **Front Page Login Redirect for Residents**: Always redirect users with the custom role "Snippen Beboer" (`snippen_resident`) to the front page (`home_url('/')`) upon logging in. This improves user experience and avoids confusing backend redirects to WordPress profile page/WP Admin.
- **Integration Tests**: Added comprehensive PHPUnit integration tests in `LoginRedirectTest.php` to verify redirection behaviour for residents, regular users, and login errors.

## [1.5.0] - 2026-05-19

### Changed
- **Decoupled Time Slots Model**: Refactored the `snippen_time_slots` database model to make time slots global and shared, removing the direct `booking_object_id` relation.
  - Added a database migration (`Migration_1_5_0`) to deduplicate existing time slots (trimming and grouping by lowercase name), map all existing bookings and pricing configurations to the new canonical slot IDs, and safely drop the `booking_object_id` column and its index.
  - Refactored `Install.php` to seed global, shared time slots and individual/combined prices correctly.
  - Refactored `AvailabilityService` and `PricingService` fallback logic to support global slot validation.
  - Refactored `BookingApi` and `AvailabilityApi` controller AJAX endpoints to simplify slot validation, pricing lookups, and multi-object filters.
  - Simplified Admin UIs by removing the venue selector/filter from the **Tidsluker (Slots)** page and removing the venue grouping (`optgroup`) from the **Prisregler (Pricing)** rule creation form.

## [1.4.5] - 2026-05-18

### Fixed
- **Admin Redirection & Headers Warning**: Fixed the `Cannot modify header information - headers already sent` PHP warning when saving or deleting pricing rules, booking objects, or time slots.
  - Implemented the standard WordPress early-load action hook (`load-{$page_hook}`) to process form submissions (POST) and deletions (GET) before any HTML output or HTTP headers are sent.
  - Refactored `AdminLoader.php`, `PricingPage.php`, `BookingObjectsPage.php`, and `TimeSlotsPage.php` to follow the Post/Redirect/Get (PRG) pattern.
  - Handled early validation errors and success notices cleanly by passing query parameters and using custom notice render logic.

## [1.4.4] - 2026-05-18
### Added
- **SMTP Settings Page Fields**: Added premium SMTP setup fields under the plugin's "Innstillinger" dashboard.
  - **SMTP Configuration**: Enables configuring custom SMTP host, port, username, password, encryption type (None, SSL, TLS), and sender identity (From Email and From Name).
  - **Dynamic Saving**: Safely sanitizes, validates, and persists all SMTP preferences.
- **Midnight Crossing Door Code Bugfix**: Fixed a bug in `DoorCodeService::is_in_window()` where bookings/slots crossing midnight could calculate negative duration/incorrect window endpoints due to date mismatch, resolving test suite flaky failures under late-hour environments.
- **Integration & Unit Tests**: Added a new `SettingsPageTest.php` suite to assert the rendering and saving of SMTP settings, and verified the PHPMailer configuration action integration.

## [1.4.3] - 2026-05-17
### Added
- **Resident Import Page**: Implemented a new "Beboer Import" settings page under the admin dashboard for bulk importing resident accounts via copy-paste.
  - **Data Formats**: Supports both Line-by-Line ABBL format (Name, Email, Phone) and Tab-Separated Values (TSV) format with custom column mapping.
  - **Look-Ahead Shift Recovery**: Built a robust look-ahead parser for line-by-line format that automatically recovers from missing email or phone fields (data shifts) and logs detailed, clear error/warning skip lists.
  - **Custom User Role**: Imported/updated users are assigned the custom WordPress role `snippen_resident` ("Snippen Beboer") which inherits subscriber capabilities.
  - **Deletion Sync**: Automatically flags any `snippen_resident` not present in the current import list as deleted via `snippen_user_deleted = 'yes'` user metadata. Clears this flag for any imported users.
  - **Manual Deletion Toggle**: Added a manual "Slettet beboer" checkbox to the WP User Profile screen, enabling admins to manually mark or reactivate residents.
  - **Access & Deletion Enforcement**: Robust 4-layer enforcement blocking deleted users: prevents login via the `wp_authenticate_user` pipeline, blocks password reset via `allow_password_reset`, blocks API booking requests, and blocks SMS code verification requests.
- **Integration Tests**: Added comprehensive integration tests in `ImportPageTest.php` verifying parsing, look-ahead shifts, TSV mapping, phone normalization, custom role creation, deletion sync, login blocks, and manual toggles.

## [1.4.2] - 2026-05-17
### Added
- **SMS Fallback to Email**: Implemented a comprehensive email fallback for SMS when SMS options are disabled in WordPress settings.
  - **Account Confirmation**: If account confirmation SMS is disabled, the verification code is sent via email fallback. Step-by-step UI instructions and AJAX responses are dynamically updated to guide the user accordingly.
  - **Booking Confirmation**: If booking confirmation SMS is disabled, customers automatically receive their booking details and unique UUID link via email.
  - **Integration Tests**: Added `SmsFallbackTest` suite covering fallback notification scenarios for both account and booking processes.

## [1.4.1] - 2026-05-17
### Fixed
- **Norwegian Holiday Timezone Bug**: Resolved a timezone mismatch/DST transition bug in `HolidayService` where Easter-based moving holidays (such as 2. pinsedag / Whit Monday) would shift to the wrong date if the PHP default timezone was different from the system timezone (a common scenario in WordPress sites set to UTC). Implemented a robust, mathematically exact, and timezone-independent calculation using `easter_days()` and `DateTimeImmutable`.
- **Unit Tests**: Added complete test coverage for moving and fixed holidays across multiple years under mismatched timezone conditions.

## [1.4.0] - 2026-05-17
### Added
- **Door Code Support for Venues**: Added a `door_code` field to booking objects (rooms/venues) inside the Admin booking objects screen.
- **Configurable Door Code Time Window**: Introduced Admin configuration options under "Innstillinger" to customize how many hours before booking start (**x**) and how many hours after booking end (**y**) the door code should be active.
- **Secure Dynamic Synchronizer**: Built a robust `DoorCodeService` that synchronizes room door codes into active upcoming bookings within the active display window, while keeping them hidden and secure outside the window. Handles multiple assigned rooms, sanitizing and automatically deduplicating identical door codes to a single entry.
- **Front-End & Admin Displays**: Integrated beautiful door code displays within both the public front-end booking details modal overlay and the "Mine Bookinger" expanded row layouts, showing the code inside the active window and `<Koden er ikke tilgjengelig før nærmere booking start>` outside of it.
- **Unit and Integration Tests**: Implemented comprehensive unit and integration test coverage for settings options, time windows, multi-room code combinations, database migrations, and front-end rendering.

## [1.3.2] - 2026-05-17
### Added
- **Secure Single Booking Popup**: Implemented a secure read-only single booking popup overlay on the front-end when `booking_uuid` is detected in the URL query parameters.
- **Access Control & Redirect**: Restricts booking details access to only the booking owner or administrators, prompting guest visitors with a premium Vipps login overlay that redirects back seamlessly after login.
- **Database & Data Migration**: Added a unique `uuid` column to the bookings database table and automatically backfilled existing records with unique UUIDs.
- **SMS Integration**: Updated the booking confirmation SMS to include the direct, non-guessable popup link to their booking.
- **Demo Generator UUIDs**: Enforced secure UUID generation in `demo-bookings` creation script.

## [1.3.1] - 2026-05-17
### Added
- **Tagged Booking Pages**: Enabled tag (post_tag) support for WordPress pages. Pages tagged with `snippen-booking` will now display as quick links at the top of the Admin Booking overview page.

## [1.3.0] - 2026-05-17
### Added
- **User Account Confirmation**: Added functionality for users to confirm their accounts via a shortcode. Confirmed users can then create bookings.
- **Granular SMS Settings**: Added "SMS Innstillinger" page for enabling/disabling specific SMS notification types.
- **Booking Confirmation SMS**: Added SMS confirmation to users upon successful booking.

## [1.2.2] - 2026-05-16
### Added
- **Demo Commands**: Added `composer demo:sms`, `composer demo:me`, and `composer demo:env` for easier development setup.
- **Environment Support**: Added `.env` file support for demo scripts via `.env.example`.

## [1.2.1] - 2026-05-17
### Fixed
- **SMS Service**: Trim + form phone numbers before sending. SMS messages are now sent reliably.

## [1.2.0] - 2026-05-16
### Added
- **SMS Service**: Now working with KeySMS.

## [1.1.9] - 2026-05-16
### Added
- **SMS Service**: Integrated KeySMS API for automated SMS notifications.
- **Admin Settings**: Added a new "Innstillinger" page to manage SMS API keys and sender info.
- **Booking Notifications**: Automatic SMS confirmation to users upon successful booking.

## [1.1.8] - 2026-05-16
### Added
- Enable AI agents to solve GitHub issues directly.
- Added GitHub CLI (`gh`) to devcontainer.
- Updated `AGENTS.md` and `DEV_README.md` with GitHub issue workflow.

## [1.1.7] - 2026-05-15
### Added
- **My Bookings Page**: New admin page for users to view their own bookings and details.
- **Self-Cancellation**: Enabled users to cancel their own bookings directly from the "Mine Bookinger" page.
- **Security**: Implemented ownership verification for AJAX booking actions to prevent unauthorized cancellations.

## [1.14.0] - 2026-05-27
### Added
- Feature (#86): Support login and password reset using phone number.

## [1.13.3] - 2026-05-24
### Added
- **My Bookings Page**: New admin page for users to view their own bookings and details.
- **Self-Cancellation**: Enabled users to cancel their own bookings directly from the "Mine Bookinger" page.
- **Security**: Implemented ownership verification for AJAX booking actions to prevent unauthorized cancellations.

## [1.1.7] - 2026-05-16
### Added
- **My Bookings Page**: New admin page for users to view their own bookings and details.
- **Self-Cancellation**: Enabled users to cancel their own bookings directly from the "Mine Bookinger" page.
- **Security**: Implemented ownership verification for AJAX booking actions to prevent unauthorized cancellations.

## [1.1.6] - 2026-05-16
### Added
- **User Phone Numbers**: Integrated secure phone number handling linked to user profiles.
- **Admin User Editing**: Added a custom field to the WordPress user profile screen so admins can edit phone numbers.
- **Booking Security**: The phone number field in the booking form is now read-only and fetched securely from user metadata, E.164 standard (+47 prefix) format required.
- **Validation**: Prevented bookings from being submitted by users without a registered phone number.
- **Demo Data Enhancement**: Updated `demo:users` to generate random Norwegian phone numbers.
### Fixed
- **Form UI**: Improved form resetting and fixed aggressive "snap-back" when clearing the admin user search.
- **Validation**: Enforced phone number validation immediately upon form loading for all users.
- **Error Messages**: Ensured phone number validation errors are cleared correctly when an admin selects a different user or starts a new search.

## [1.1.5] - 2026-05-15
### Added
- **Admin Calendar Info**: Display associated locales/objects in the booking details modal.
### Fixed
- **AJAX Performance**: Optimized availability API to avoid duplicate booking rows and fetch object names efficiently.

## [1.1.5] - 2026-05-15
### Fixed
- **Pricing Admin**: Fixed a bug in the price list view where date-based conditions caused an "Undefined property" warning due to mismatched database column names.

## [1.1.4] - 2026-05-15
### Changed
- **Release Assets**: Updated GitHub Release workflow to name ZIP files with version numbers (e.g., `snippen-booking-v1.1.4.zip`).
- **Update Checker**: Configured PUC to use a regular expression (`/^snippen-booking-.*\.zip$/i`) to identify release assets, making it more robust against future naming changes.

## [1.1.3] - 2026-05-15
### Changed
- **Auto-Update**: Explicitly enabled GitHub Release assets in Plugin Update Checker configuration.

## [1.1.2] - 2026-05-15
### Fixed
- **Auto-Update Fix**: Removed `setBranch('main')` configuration. This ensures the update checker uses the correctly packaged GitHub Release ZIP assets instead of downloading the raw repository source, which was causing installation failures.

## [1.1.1] - 2026-05-15
### Fixed
- **Asset Caching**: Fixed an issue where old JS/CSS files remained cached in browsers after updates.
- **JS Attribute Safety**: Improved handling of special characters in customer names to prevent breaking calendar interactions.

## [1.1.0] - 2026-05-15
### Added
- **Admin Calendar Info**: Display customer names directly in the calendar for administrators.
- **Booking Detail Modal**: View complete booking information by clicking booked slots in the calendar (admin only).
- **Improved Bookings List**: Default sorting by date (ASC) and automatic 14-day history filtering.
- **History Toggle**: Easily show/hide older bookings in the admin dashboard.

## [1.0.0] - 2026-05-15
### Added
- **Admin Booking**: Admins can now create bookings on behalf of other residents directly from the calendar.
- **User Search**: Integrated a premium user search box in the booking form for admins.
- **Migration System**: New robust database migration system to handle schema and data updates.
- **Mandatory User ID**: All bookings are now linked to a registered WordPress user for better tracking.
- **Demo User Generation**: Added `php bin/demo-data.php users` command to generate subscriber users.
- **Enhanced Demo Data**: Generated bookings are now randomly assigned to subscriber users.

## [0.2.2] - 2026-05-15
### Fixed
- Fatal error `Class "Parsedown" not found` in `plugin-update-checker` by restoring missing dependency files.
### Added
- Forced update check trigger for development testing (`?check_booking_updates=1` query param when `WP_DEBUG` is on).
- Documented WordPress path and preferred testing commands in `AGENTS.md`.

## [0.2.1] - 2026-05-15

### Changed
- **Admin UI**: Removed the "Tøm demo-data" button from the Booking Overview page to prevent accidental data loss.
- **Mobile View**: Timeslots in the calendar are now visible by default on small screens, removing the need to click each day to check availability.
- **Calendar UX**: Removed the day-header click toggle on mobile for a more streamlined booking experience.

## [0.2.0] - 2026-05-10
- Initial beta release with core booking functionality.
