# Snippen Booking

A WordPress plugin for handling bookings at Snippen community house.

## Installation

1. Download the latest `snippen-booking.zip` file from the [Releases](https://github.com/jonasfh/snippen-booking/releases) page.
2. Log in to your WordPress Admin dashboard.
3. Go to **Plugins > Add New** and click **Upload Plugin**.
4. Choose the downloaded `snippen-booking.zip` file and click **Install Now**.
5. Once installed, click **Activate Plugin**.

## Usage

### Displaying the Booking Form
The booking form is displayed using the following shortcode. If called without arguments, all active booking objects are available:

```text
[snippen_booking]
```

To limit the form to a specific object or multiple objects, you can pass the `object_id` argument (use a comma-separated list for multiple objects):

```text
[snippen_booking object_id="1"]
[snippen_booking object_id="1,2"]
```

When multiple objects are available or selected, only time slots marked as "delt" (shared) will be available in this mode.

### Custom Instructions / Wash Time (Egendefinert melding)
Booking blocks and time slots can be configured in the WordPress Admin dashboard under **Snippen Booking > Bookingblokker** with the **Egendefinert melding / instruksjoner** text field (e.g. "Inkluderer utvask neste morgen frem til kl. 11:00").
When configured for a time slot, users booking that slot are informed directly in the booking wizard and summary confirmation box. Bookings with custom instructions are also flagged with an **Info** badge in the admin bookings overview.

### User Account Confirmation
Users must confirm their account via SMS before they can create bookings. The account confirmation form is displayed using the following shortcode:

```text
[snippen_account_confirmation]
```

### Displaying User Bookings
Residents can see their booking history (and self-cancel active bookings) on the frontend in a beautiful card list using the following shortcode:

```text
[snippen_booking_list]
```

By default, if the user is not logged in, the shortcode will return an empty string. To display a premium login form to guest users, you can set the `login-form` attribute:

```text
[snippen_booking_list login-form="1"]
```

### SMS Settings
Granular SMS settings are available in the WordPress admin dashboard under **Settings > SMS Innstillinger**. Here you can configure API credentials and enable or disable specific SMS notification types, such as booking confirmations and account confirmations.

If an SMS notification is disabled, the system will automatically fall back to sending that notification via email to ensure that confirmation codes and booking details are still delivered to the user.

### Notification Templates (Varslingsmaler)
You can configure custom templates for SMS and Email notifications (such as booking confirmations, account confirmations, admin alerts, and payment instructions/deadlines).
Navigate to **Snippen Booking > Varslingsmaler** in the WordPress Admin dashboard.
Here you can edit the text and subject lines. You can use dynamic placeholders (e.g. `{{user_name}}`, `{{booking_date}}`, `{{bank_account}}`, `{{vipps_number}}`, `{{booking_price}}`) to personalize the messages. Default templates are provided out of the box, and you can easily revert to them at any time.

### Communication History (Lagre brukerkommunikasjon)
All SMS and Email messages sent to users and administrators (including automatic booking confirmations, account activation codes, admin alert notifications, and manual messages sent via the Booking Assistant) are automatically logged in the database with recipient details, timestamps, delivery channel, and `user_id`/`booking_id` associations.
Administrators can inspect full communication logs directly for any booking by expanding the details row in the **Snippen Booking > Booking Oversikt** admin table.

### Door Codes
Booking objects (venues) can be configured with a door code in the WordPress Admin dashboard under **Snippen Booking > Lokaler**.

#### Configurable Active Time-Window
To ensure security, the door code is not displayed immediately upon booking. Instead, it is only visible within a configurable active time window.
In the WordPress Admin dashboard under **Snippen Booking > Innstillinger**, administrators can set:
- **Vis dørkode x timer før booking start**: How many hours before the booking starts the door code should become visible.
- **Vis dørkode y timer etter booking slutt**: How many hours after the booking ends the door code should remain visible.

#### Where Users Can Find Their Door Code
When a booking is within its active time window, the door code will automatically be displayed to the user in:
1. The **expanded details row** under **Mine Bookinger** (My Bookings) in their account page.
2. The secure front-end **Booking Details popup overlay** accessed via their unique booking link.

Outside of the configured active time window, the system securely hides the door code and displays:
`<Koden er ikke tilgjengelig før nærmere booking start>` (Code is not available until closer to the booking start).

For bookings containing **multiple rooms**, the system automatically sanitizes, combines, and deduplicates the door codes (displaying only a single code if the venues share the same entrance door code).

## Uninstalling

When the plugin is deleted from WordPress (via the Plugins page), it will by default clean up all associated data, including database tables, settings, and user capabilities, leaving no orphaned data behind.

If you are uninstalling the plugin temporarily or wish to keep the booking data, you can enable the **Behold data ved avinstallering** (Preserve data on uninstall) option under **Snippen Booking > Innstillinger** before deleting the plugin.

## Contributing and Development

If you are a developer looking to contribute to the code, set up the dev environment, or run tests, please refer to the developer documentation in [DEV_README.md](DEV_README.md).
