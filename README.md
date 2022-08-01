# ParkStras API

This API is simply a wrapper around the [one provided by the City of Strasbourg](https://data.strasbourg.eu/pages/accueil/).

There is only two routes:

- `https://parkstras.princelle.org/api/fetch_parkings.php`

> Multiple parameters can be passed to the API as GET parameters :
> - lat: The latitude of the point of interest
> - lng: The longitude of the point of interest
> - radius: The radius of the area around the point of interest (in meters, default: 800m)
> - results: The number of results returned (default: 10)

- `https://parkstras.princelle.org/api/fetch_all_parkings.php`
