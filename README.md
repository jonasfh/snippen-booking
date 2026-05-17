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
The booking form is displayed using the following shortcode, where object_id is the ID of the object you want to book:

```text
[snippen_booking object_id="1"]
```

Multiple objects can be booked by passing a comma-separated list of object IDs to the shortcode. Only time slots marked as "delt" (shared) will be available in this mode.

```text
[snippen_booking object_id="1,2"]
```

### User Account Confirmation
Users must confirm their account via SMS before they can create bookings. The account confirmation form is displayed using the following shortcode:

```text
[snippen_account_confirmation]
```

### SMS Settings
Granular SMS settings are available in the WordPress admin dashboard under **Settings > SMS Innstillinger**. Here you can configure API credentials and enable or disable specific SMS notification types, such as booking confirmations and account confirmations.

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

## Contributing and Development

If you are a developer looking to contribute to the code, set up the dev environment, or run tests, please refer to the developer documentation in [DEV_README.md](DEV_README.md).
