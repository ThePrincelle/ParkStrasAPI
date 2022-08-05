<?php
# ----------------------------------------------
# fetch_parkings.php
# Fetch Parking API from Strasbourg OpenData
# Created by Maxime Princelle
# ----------------------------------------------

# Fetch parkings in Strasbourg through the API
# https://data.strasbourg.eu/api/records/1.0/search/?dataset=parkings&q=&lang=fr&timezone=Europe%2FParis
function fetch_parkings($location = null, $radius = 800, $results = 10) {
    $parkings = [];

    $url = 'https://data.strasbourg.eu/api/records/1.0/search/?dataset=parkings&q=&lang=fr&timezone=Europe%2FParis';

    # If location is set, get only parkings in that location
    if ($location) {
        # geofilter.distance=48.584614,7.7507127,1000
        $url .= '&geofilter.distance=' . $location['lat'] . ',' . $location['lng'] . ',' . $radius;
    }

    # If results is set, get only the n results
    if ($results) {
        $url .= '&rows=' . $results;
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

        // Format description
        $parking['description'] = str_replace("     ", "\n\n", $parking['description']);
        $parking['description'] = str_replace("    ", "\n\n", $parking['description']);
        $parking['description'] = str_replace("   ", "\n", $parking['description']);
        $parking['description'] = str_replace("  ", "\n", $parking['description']);
        $parking['description'] = str_replace(" - ", "\n- ", $parking['description']);

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

            if ($details["etat"] == 0) {
                // Delete occupation from details
                unset($details["occupation"]);

                // If infousager is OUVERT, set etat to 1 (OPEN)
                if ($parking_details["infousager"] == "OUVERT") {
                    $details["etat"] = 1;
                }
            }

            $parking = array_merge($parking, $details);
        }

        array_push($parkings, $parking);
    }

    return $parkings;
}

# Fetch parkings
$location = null;
$radius = 800;
$results = 10;

if (isset($_GET['lat']) && isset($_GET['lng']) && isset($_GET['radius'])) {
    $location = [
        "lat" => $_GET['lat'],
        "lng" => $_GET['lng']
    ];

    // Trim lat & lng to 10 characters
    $location['lat'] = substr($location['lat'], 0, 10);
    $location['lng'] = substr($location['lng'], 0, 10);

    $radius = $_GET['radius'];
} 

if (isset($_GET['lat']) && isset($_GET['lng'])) {
    $location = [
        "lat" => $_GET['lat'],
        "lng" => $_GET['lng']
    ];

    // Trim lat & lng to 10 characters
    $location['lat'] = substr($location['lat'], 0, 10);
    $location['lng'] = substr($location['lng'], 0, 10);
}

if (isset($_GET['results'])) {
    $results = $_GET['results'];
}

$parkings = fetch_parkings($location, $radius, $results);

# Return parkings with json format
header('Content-Type: application/json');
echo json_encode($parkings);
