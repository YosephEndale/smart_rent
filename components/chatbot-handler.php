<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_log('chatbot-handler.php accessed');

session_start();
include 'connect.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Temporary for local testing
header('Access-Control-Allow-Methods: POST');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['message'])) {
    error_log('Invalid request: Method=' . $_SERVER['REQUEST_METHOD'] . ', POST=' . print_r($_POST, true));
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$user_input = trim($_POST['message']);
$user_input = htmlspecialchars($user_input, ENT_QUOTES, 'UTF-8');
error_log("User input: $user_input, User ID: $user_id");

$intents = [
    'how do i save' => 'Click the heart icon next to the property to save it!',
    'can i contact the owner' => 'Yes! Use the "Chat with owner" button on the property page.',
    'talk to human' => 'Let me connect you to a human assistant. Please provide your name, email, and phone number.',
    'not helpful' => 'I’m sorry I couldn’t assist you better. Would you like to speak to a human assistant?'
];

function fuzzyMatch($input, $intent) {
    $input = strtolower($input);
    $intent = strtolower($intent);
    return strpos($input, $intent) !== false || levenshtein($input, $intent) <= 5;
}

$response = null;
foreach ($intents as $intent => $reply) {
    if (fuzzyMatch($user_input, $intent)) {
        $response = $reply;
        error_log("Rule-based response: $response");
        break;
    }
}

// Log user message
try {
    $log_query = $conn->prepare("INSERT INTO chat_logs (user_id, message, is_bot, timestamp) VALUES (?, ?, 0, NOW())");
    $log_query->execute([$user_id, $user_input]);
    error_log("User message logged");
} catch (PDOException $e) {
    error_log("Failed to log user message: " . $e->getMessage());
}

// Check for escalation
$escalation_keywords = ['talk to human', 'not helpful', 'escalate'];
$needs_escalation = false;
foreach ($escalation_keywords as $keyword) {
    if (fuzzyMatch($user_input, $keyword)) {
        $needs_escalation = true;
        break;
    }
}

if ($needs_escalation) {
    try {
        $escalate_query = $conn->prepare("INSERT INTO escalation_queue (user_id, message, needs_response, timestamp) VALUES (?, ?, 'Y', NOW())");
        $escalate_query->execute([$user_id, $user_input]);
        $response = 'Let me connect you to a human assistant. Please wait...';
        error_log("Escalation logged");
    } catch (PDOException $e) {
        error_log("Failed to escalate: " . $e->getMessage());
        $response = 'Sorry, something went wrong. Try again later.';
    }
} else {
    // Initialize OpenRouter API
    $api_key = getenv('OPENROUTER_API_KEY');
    if (!$api_key) {
        error_log('OPENROUTER_API_KEY not set in .env file');
        $response = 'API configuration error. Please try again later.';
        echo json_encode(['response' => $response]);
        exit;
    }
    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); 
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    // Fetch user preferences
    $context = '';
    if ($user_id) {
        try {
            $pref_query = $conn->prepare("SELECT * FROM user_preferences WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
            $pref_query->execute([$user_id]);
            $preferences = $pref_query->fetch(PDO::FETCH_ASSOC);
            if ($preferences) {
                $context = "User preferences: BHK: {$preferences['bhk']}, Type: {$preferences['property_type']}, Offer: {$preferences['offer_type']}, Budget: {$preferences['min_budget']} - {$preferences['max_budget']}.";
                error_log("Preferences: $context");
            } else {
                error_log("No preferences found for user_id: $user_id");
            }
        } catch (PDOException $e) {
            error_log("Failed to fetch preferences: " . $e->getMessage());
        }
    }

    // Fetch all properties from the database
    $properties_data = [];
    try {
        $query = "SELECT id, property_name, address, price, type, bhk, description, furnished, deposite, bedroom, bathroom, balcony, carpet, lift, security_guard, play_ground, garden, water_supply, power_backup, parking_area, gym, shopping_mall, hospital, school, market_area FROM property";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $properties_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Fetched " . count($properties_data) . " properties from database");
    } catch (PDOException $e) {
        error_log("Failed to fetch properties: " . $e->getMessage());
    }

    // Serialize properties into a JSON string for the LLM prompt
    $properties_json = json_encode($properties_data, JSON_PRETTY_PRINT);
    error_log("Properties JSON: $properties_json");

    // Step 1: Use LLM to search properties directly
    $search_prompt = [
        'model' => 'mistralai/mixtral-8x7b-instruct',
        'messages' => [
            [
                'role' => 'system',
                'content' => "You are a friendly and professional real estate assistant for Smart Rent, helping tenants find their perfect home. Your task is to search the provided property database based on the user's input and return an engaging, conversational response. The database is a JSON array of properties with fields: property_name, address, price, type, bhk, description, furnished, deposite, bedroom, bathroom, balcony, carpet, lift, security_guard, play_ground, garden, water_supply, power_backup, parking_area, gym, shopping_mall, hospital, school, market_area.

Instructions:
- Respond in a friendly, concise, and tenant-focused tone, keeping the response under 150 words.
- For matching properties, include: property_name (as plain text, NOT wrapped in a URL), address, price (formatted as '€X/month'), type, bhk, furnished status, deposite, description, and key amenities (e.g., lift, parking_area, gym, etc., if 'yes').
- Do NOT include fields like id, location_place_id, or any URLs for the property name (e.g., no 'view_property.php?get_id=XX').
- If no match is found, suggest refining the search with specific criteria or checking back later.
- Normalize prices by ignoring currency symbols (e.g., '€200.00' = '200').
- Map 'student apartment' to type 'flat' with 'student' in the description.
- After presenting a match, suggest verbally to search for the property using its name in quotes, in the format: 'Please search for \"[property_name]\" to learn more.'
- Use the context: {$context}.
- Ensure the response is complete and not cut off.

Property Database:
{$properties_json}"
            ],
            ['role' => 'user', 'content' => $user_input]
        ],
        'max_tokens' => 450
    ];

    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($search_prompt));
    $api_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    error_log("OpenRouter Search API: HTTP $http_code, Response: $api_response, Error: $curl_error");
    curl_close($ch);

    if ($http_code === 200) {
        $api_data = json_decode($api_response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $response = $api_data['choices'][0]['message']['content'] ?? 'Sorry, I couldn’t process that. Try again!';
            error_log("API response: $response");
        } else {
            error_log("JSON parse error for response: " . json_last_error_msg());
            $response = 'Sorry, I’m having trouble connecting. Please try again.';
        }
    } else {
        error_log("OpenRouter API failed: HTTP $http_code, Response: $api_response, Error: $curl_error");
        $response = 'Sorry, I’m having trouble connecting. Please try again.';
    }
}

// Log bot response
try {
    $log_query = $conn->prepare("INSERT INTO chat_logs (user_id, message, is_bot, timestamp) VALUES (?, ?, 1, NOW())");
    $log_query->execute([$user_id, $response]);
    error_log("Bot response logged");
} catch (PDOException $e) {
    error_log("Failed to log bot response: " . $e->getMessage());
}

echo json_encode(['response' => $response]);
exit;
?>
