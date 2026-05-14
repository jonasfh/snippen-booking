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

## Contributing and Development

If you are a developer looking to contribute to the code, set up the dev environment, or run tests, please refer to the developer documentation in [DEV_README.md](DEV_README.md).
