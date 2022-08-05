<?php
# ----------------------------------------------
# fetch_all_parkings.php
# Fetch Parking API from Strasbourg OpenData
# Created by Maxime Princelle
# ----------------------------------------------

# Fetch parkings in Strasbourg through the API
# https://data.strasbourg.eu/api/records/1.0/search/?dataset=parkings&q=&lang=fr&timezone=Europe%2FParis
function fetch_all_parkings() {
    $parkings = [];

    $url = 'https://data.strasbourg.eu/api/records/1.0/search/?dataset=parkings&q=&lang=fr&timezone=Europe%2FParis';

    $url .= '&rows=' . 200;

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
        if ($parking['description'] != null) {
            $parking['description'] = str_replace("     ", "\n\n", $parking['description']);
            $parking['description'] = str_replace("    ", "\n\n", $parking['description']);
            $parking['description'] = str_replace("   ", "\n\n", $parking['description']);
            $parking['description'] = str_replace("  ", "\n\n", $parking['description']);
            $parking['description'] = str_replace(" - ", "\n- ", $parking['description']);
        }

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

# Fetch parkings in Strasbourg through the API
$parkings = fetch_all_parkings();

# Return parkings with json format
# header('Content-Type: application/json');

// Initiate SQLLite Database connection
$db = new PDO('sqlite:'.dirname(__FILE__).'/../db/parkings.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

# Create table if it doesn't exist
$db->exec("CREATE TABLE IF NOT EXISTS parkings (id TEXT PRIMARY KEY, name TEXT, address TEXT, position TEXT, description TEXT, url TEXT, occupation TEXT, etat INTEGER, updated_at TEXT)");

# Add or update parkings in the database
foreach ($parkings as $parking) {
    $db->prepare("INSERT OR REPLACE INTO parkings (id, name, address, position, description, url, occupation, etat, updated_at) VALUES (:id, :name, :address, :position, :description, :url, :occupation, :etat, :updated_at)")
        ->execute([
            ":id" => $parking['id'],
            ":name" => $parking['name'],
            ":address" => json_encode($parking['address']),
            ":position" => json_encode($parking['position']),
            ":description" => json_encode($parking['description'] ?? null),
            ":url" => $parking['url'] ?? null,
            ":occupation" => json_encode($parking['occupation'] ?? null),
            ":etat" => $parking['etat'] ?? null,
            ":updated_at" => $parking['updated_at'] ?? null
        ]);
}

echo "Done";