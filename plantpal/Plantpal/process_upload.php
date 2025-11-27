<?php
session_start();
require_once 'config/database.php';
require_once 'config/api_config.php'; // Include API key config

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start output buffering to prevent any output before redirects
ob_start();

// Debug output
error_log("Starting process_upload.php");
error_log("POST data: " . print_r($_POST, true));
error_log("FILES data: " . print_r($_FILES, true));

// Function to call Plant.id API
function identifyPlant($imagePath, $apiKey) {
    $apiUrl = 'https://api.plant.id/v2/identify';
    
    // Prepare image data
    $image_data = base64_encode(file_get_contents($imagePath));
    
    $data = [
        'images' => [$image_data],
        'modifiers' => ["similar_images", "plant_details", "pests"],
        'plant_details' => [
            "common_names",
            "url",
            "wiki_description",
            "taxonomy",
            "care_instructions",
            "growth",
            "watering",
            "light",
            "soil",
            "pests",
            "diseases"
        ],
        'language' => 'en'
    ];
    
    $payload = json_encode($data);

    // Prepare cURL request
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Api-Key: ' . $apiKey
    ]);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        error_log("cURL Error: " . $curl_error);
        return ['error' => 'API request failed (cURL error).'];
    }

    if ($httpcode != 200) {
        error_log("API Error: HTTP Code " . $httpcode . " Response: " . $response);
        return ['error' => 'Plant identification failed. API returned status ' . $httpcode];
    }

    $result = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON Decode Error: " . json_last_error_msg());
        return ['error' => 'Failed to parse API response.'];
    }
    
    if (!isset($result['suggestions']) || empty($result['suggestions'])) {
         return ['error' => 'Could not identify the plant from the image.'];
    }

    // Return the first (most likely) suggestion
    return $result['suggestions'][0]; 
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['plant_image']) || $_FILES['plant_image']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = "Error uploading image. Please try again.";
        error_log("Upload error: " . print_r($_FILES['plant_image']['error'], true));
        header('Location: index.php');
        exit;
    }

    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $file_type = $_FILES['plant_image']['type'];

    if (!in_array($file_type, $allowed_types)) {
        $_SESSION['error'] = "Invalid file type. Please upload a JPEG, PNG, or GIF image.";
        error_log("Invalid file type: " . $file_type);
        header('Location: index.php');
        exit;
    }

    // Check if API key is set
    if (!defined('PLANT_ID_API_KEY') || PLANT_ID_API_KEY === 'YOUR_PLANT_ID_API_KEY') {
         $_SESSION['error'] = "Plant Identification API key is not configured. Please contact support.";
         error_log("Plant.id API Key not configured.");
         header('Location: index.php');
         exit;
    }

    $upload_dir = 'uploads/plants/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file_extension = pathinfo($_FILES['plant_image']['name'], PATHINFO_EXTENSION);
    $file_name = uniqid() . '.' . $file_extension;
    $target_path = $upload_dir . $file_name;

    error_log("Attempting to upload file to: " . $target_path);

    if (move_uploaded_file($_FILES['plant_image']['tmp_name'], $target_path)) {
        error_log("File successfully uploaded to: " . $target_path);
        
        // Store the uploaded image path in session for display on index page
        $_SESSION['last_uploaded_image'] = $target_path;
        
        // Call the Plant Identification API
        $identification_result = identifyPlant($target_path, PLANT_ID_API_KEY);

        if (isset($identification_result['error'])) {
            $_SESSION['error'] = "Plant identification failed: " . $identification_result['error'] . 
                                 " Please try again or contact support if the problem persists.";
            error_log("Identification error: " . $identification_result['error']);
            // Optionally delete the uploaded file if identification fails
            // unlink($target_path); 
            header('Location: index.php');
            exit;
        }

        // Prepare data for session (use the first suggestion)
        $plant_details = $identification_result['plant_details'];
        
        // Build comprehensive care instructions
        $care_instructions = [];
        
        // Add specific care details if available
        if (!empty($plant_details['watering'])) {
            $care_instructions[] = "Watering: " . $plant_details['watering'];
        }
        if (!empty($plant_details['light'])) {
            $care_instructions[] = "Light: " . $plant_details['light'];
        }
        if (!empty($plant_details['soil'])) {
            $care_instructions[] = "Soil: " . $plant_details['soil'];
        }
        if (!empty($plant_details['growth'])) {
            $care_instructions[] = "Growth: " . $plant_details['growth'];
        }
        
        // If no specific care instructions found, use wiki description
        if (empty($care_instructions) && !empty($plant_details['wiki_description']['value'])) {
            $care_instructions[] = $plant_details['wiki_description']['value'];
        }
        
        // If still no instructions, provide a generic message
        if (empty($care_instructions)) {
            $care_instructions[] = "No specific care instructions available for this plant. Please research care requirements for " . 
                                 ($plant_details['common_names'][0] ?? $identification_result['plant_name'] ?? 'this plant');
        }

        // Build pest information
        $pest_information = [];
        
        // Add specific pest details if available
        if (!empty($plant_details['pests'])) {
            $pest_information[] = "Common Pests:\n" . $plant_details['pests'];
        }
        if (!empty($plant_details['diseases'])) {
            $pest_information[] = "Common Diseases:\n" . $plant_details['diseases'];
        }
        
        // If no specific pest information found, provide a generic message
        if (empty($pest_information)) {
            $pest_information[] = "No specific pest information available for this plant. Please research common pests and diseases for " . 
                                ($plant_details['common_names'][0] ?? $identification_result['plant_name'] ?? 'this plant');
        }
        
        // Debug output for pest information
        error_log("Pest information: " . print_r($pest_information, true));
        
        $identified_plant = [
            'id' => null,
            'name' => $identification_result['plant_name'] ?? 'Unknown Plant',
            'scientific_name' => $plant_details['scientific_name'] ?? null,
            'common_names' => $plant_details['common_names'] ?? [],
            'confidence' => $identification_result['probability'] ?? 0,
            'api_url' => $plant_details['url'] ?? null,
            'wiki_description' => $plant_details['wiki_description']['value'] ?? 'No description available.',
            'api_image' => ($identification_result['similar_images'][0]['url'] ?? null),
            'care_instructions' => implode("\n\n", $care_instructions),
            'pest_information' => implode("\n\n", $pest_information)
        ];
        
        // Debug output for identified plant
        error_log("Identified plant data: " . print_r($identified_plant, true));
        
        // Store results in session
        $_SESSION['identified_plant'] = $identified_plant;
        $_SESSION['plant_image'] = $target_path; // Keep the path to the originally uploaded image
        
        error_log("Redirecting to plant_details.php with identified plant: " . print_r($identified_plant, true));
        header('Location: plant_details.php');
        exit;

    } else {
        $_SESSION['error'] = "Error saving the uploaded file.";
        error_log("Failed to move uploaded file to: " . $target_path);
        header('Location: index.php');
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}

// End output buffering and discard any output
ob_end_clean();
?> 