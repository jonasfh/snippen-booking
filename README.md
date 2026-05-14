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

You can display the booking form on any page or post by using the following shortcode:

```text
[snippen_booking]
```

### Displaying Specific Objects
If you want to create a booking form for specific objects (e.g. only "Festsalen" and "Peisestuen"), you can pass a comma-separated list of object IDs to the shortcode:

```text
[snippen_booking objects="1,2"]
```

## Contributing and Development

If you are a developer looking to contribute to the code, set up the dev environment, or run tests, please refer to the developer documentation in [DEV_README.md](DEV_README.md).
