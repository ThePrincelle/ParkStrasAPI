<?php
# ----------------------------------------------
# fetch_all_parkings.php
# Fetch Parking API from Strasbourg OpenData
# Created by Maxime Princelle
# ----------------------------------------------

# Fetch parkings in Strasbourg through the API
# https://data.strasbourg.eu/api/records/1.0/search/?dataset=parkings&q=&lang=fr&timezone=Europe%2FParis
function fetch_all_parkings() {
    // Initiate SQLLite Database connection
    $db = new PDO('sqlite:'.dirname(__FILE__).'/../db/parkings.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get all parkings from the database
    $stmt = $db->prepare('SELECT * FROM parkings');
    $stmt->execute();

    // Fetch all parkings
    $parkings = $stmt->fetchAll();

    // Parse all parkings
    $parkings_parsed = [];
    foreach ($parkings as $parking) {
        $temp_parking = [
            "id" => $parking['id'],
            "name" => $parking['name'],
            "address" => json_decode($parking['address']),
            "position" => json_decode($parking['position'], true),
            "description" => json_decode($parking['description']),
            "url" => $parking['url'],
        ];

        // Add parking details (occupation, etat, updated_at) if they exist
        if ($parking['occupation'] != "null") {
            $temp_parking['occupation'] = json_decode($parking['occupation'], true);
        }

        if ($parking['etat'] != "null") {
            $temp_parking['etat'] = $parking['etat'];
        }

        if ($parking['updated_at'] != "null") {
            $temp_parking['updated_at'] = $parking['updated_at'];
        }

        // Add parking to the array
        $parkings_parsed[] = $temp_parking;
    }

    return $parkings_parsed;
}

# Fetch parkings in Strasbourg through the API
$parkings = fetch_all_parkings();

# Return parkings with json format
header('Content-Type: application/json');
echo json_encode($parkings);
