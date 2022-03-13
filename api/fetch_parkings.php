<?php
# ----------------------------------------------
# fetch_parkings.php
# Fetch Parking API from Strasbourg OpenData
# Created by Maxime Princelle
# ----------------------------------------------

# Fetch parkings in Strasbourg through the API
# https://data.strasbourg.eu/api/records/1.0/search/?dataset=parkings&q=&lang=fr&timezone=Europe%2FParis
function fetch_parkings($location = null, $radius = 800) {
    $parkings = [];

    $url = 'https://data.strasbourg.eu/api/records/1.0/search/?dataset=parkings&q=&lang=fr&timezone=Europe%2FParis';

    # If location is set, get only parkings in that location
    if ($location) {
        # geofilter.distance=48.584614,7.7507127,1000
        $url .= '&geofilter.distance=' . $location['lat'] . ',' . $location['lng'] . ',' . $radius;
    }

    $json = file_get_contents($url);
    $data = json_decode($json, true);

    # For Each $data->records
    foreach ($data['records'] as $record) {
        # Fetch Parking details : 
        #https://data.strasbourg.eu/api/records/1.0/search/?dataset=occupation-parkings-temps-reel&q=&lang=fr&timezone=Europe%2FParis&refine.idsurfs='.$record['fields']['idsurfs']

        $parking_details_url = 'https://data.strasbourg.eu/api/records/1.0/search/?dataset=occupation-parkings-temps-reel&q=&lang=fr&timezone=Europe%2FParis&refine.idsurfs='.$record['fields']['idsurfs'];

        $parking_details_json = file_get_contents($parking_details_url);
        $parking_details_data = json_decode($parking_details_json, true);

        $parking = [
            "id" => $record['fields']['idsurfs'],
            "name" => $record['fields']['name'],
            "address" => $record['fields']['address'],
            "position" => [
                "lat" => $record['fields']['position'][0],
                "lng" => $record['fields']['position'][1]
            ],
            "description" => isset($record['fields']['description']) ? $record['fields']['description'] : null,
            "url" => $record['fields']['friendlyurl'],
        ];

        // Add parking details if they exist
        if (isset($parking_details_data['records'][0])) {
            $parking_details = $parking_details_data['records'][0]['fields'];
            $details = [
                "occupation" => [
                    "available" => $parking_details['libre'],
                    "total" => $parking_details['total'],
                    "occupied" => $parking_details['total'] - $parking_details['libre'],
                    "percentage" => round(($parking_details['total'] - $parking_details['libre']) / $parking_details['total'] * 100, 2)
                ],
                "etat" => $parking_details['etat'],
                "updated_at" => $parking_details_data['records'][0]['record_timestamp']
            ];
            $parking = array_merge($parking, $details);
        }

        array_push($parkings, $parking);
    }

    return $parkings;
}

# Fetch parkings
if (isset($_GET['lat']) && isset($_GET['lng']) && isset($_GET['radius'])) {
    $location = [
        "lat" => $_GET['lat'],
        "lng" => $_GET['lng']
    ];
    $radius = $_GET['radius'];
    $parkings = fetch_parkings($location, $radius);
} elseif (isset($_GET['lat']) && isset($_GET['lng'])) {
    $location = [
        "lat" => $_GET['lat'],
        "lng" => $_GET['lng']
    ];
    $parkings = fetch_parkings($location);
} else {
    $parkings = fetch_parkings();
}

# Return parkings with json format
header('Content-Type: application/json');
echo json_encode($parkings);
