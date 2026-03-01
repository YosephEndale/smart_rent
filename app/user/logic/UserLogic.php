<?php
namespace App\User\Logic;

use PDO;
use App\User\Data\UserData;
use App\Notifications\Logic\SendNotification;

class UserLogic {
    private $userData;
    private $notificationSender;

    public function __construct(PDO $db) {
        $this->userData = new UserData($db);
        try {
            $this->notificationSender = new SendNotification($db);
        } catch (\Exception $e) {
            $this->notificationSender = null;
            error_log("Failed to instantiate SendNotification: " . $e->getMessage());
        }
    }

    public function getUserProfile($user_id) {
        $user = $this->userData->getUserById($user_id);
        if (!$user) {
            return ['error' => 'User not found'];
        }
        return ['success' => true, 'user' => $user];
    }

    public function updateProfile($user_id, $data, $encryption_key) {
        $errors = [];
        $successes = [];
        $updated = false;

        // Sanitize and validate inputs
        $name = htmlspecialchars(strip_tags($data['name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $email = filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $number = htmlspecialchars(strip_tags($data['number'] ?? ''), ENT_QUOTES, 'UTF-8');
        $telegram_id = htmlspecialchars(strip_tags($data['telegram_id'] ?? ''), ENT_QUOTES, 'UTF-8');
        $old_pass = $data['old_pass'] ?? '';
        $new_pass = $data['new_pass'] ?? '';
        $c_pass = $data['c_pass'] ?? '';

        // Validate name
        if (!empty($name)) {
            if (strlen($name) > 50) {
                $errors[] = 'Name must be 1-50 characters';
            } else {
                if ($this->userData->updateUserName($user_id, $name)) {
                    $successes[] = 'Name updated';
                    $updated = true;
                }
            }
        }

        // Validate email
        if (!empty($email)) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email format';
            } else {
                $result = $this->userData->updateUserEmail($user_id, $email);
                if (isset($result['error'])) {
                    $errors[] = $result['error'];
                } else {
                    $successes[] = 'Email updated';
                    $updated = true;
                }
            }
        }

        // Validate number
        if (!empty($number)) {
            if (!preg_match('/^[0-9]{0,10}$/', $number)) {
                $errors[] = 'Number must be up to 10 digits';
            } else {
                $result = $this->userData->updateUserNumber($user_id, $number);
                if (isset($result['error'])) {
                    $errors[] = $result['error'];
                } else {
                    $successes[] = 'Number updated';
                    $updated = true;
                }
            }
        }

        // Validate Telegram ID
        if (!empty($telegram_id)) {
            if (!preg_match('/^[0-9]{5,15}$/', $telegram_id)) {
                $errors[] = 'Telegram ID must be a valid numeric ID (5-15 digits)';
            } else {
                $hashed_telegram_id = password_hash($telegram_id, PASSWORD_DEFAULT);
                $encrypted_telegram_id = openssl_encrypt($telegram_id, 'AES-256-CBC', $encryption_key, 0, substr($encryption_key, 0, 16));
                if ($this->userData->updateUserTelegramId($user_id, $hashed_telegram_id, $encrypted_telegram_id)) {
                    $successes[] = 'Telegram ID updated';
                    $updated = true;
                }
            }
        }

        // Validate password
        if (!empty($old_pass)) {
            $user = $this->userData->getUserById($user_id);
            if (!password_verify($old_pass, $user['password'])) {
                $errors[] = 'Old password not matched';
            } elseif ($new_pass !== $c_pass) {
                $errors[] = 'Confirm password not matched';
            } elseif (empty($new_pass)) {
                $errors[] = 'Please enter a new password';
            } else {
                $hashed_new_pass = password_hash($new_pass, PASSWORD_DEFAULT);
                if ($this->userData->updateUserPassword($user_id, $hashed_new_pass)) {
                    $successes[] = 'Password updated successfully';
                    $updated = true;
                }
            }
        }

        if (!$updated && empty($name) && empty($email) && empty($number) && empty($telegram_id) && empty($old_pass)) {
            $errors[] = 'Please provide at least one field to update';
        }

        return ['success' => $successes, 'error' => $errors];
    }

    public function saveProperty($user_id, $property_id) {
        if (!$user_id) {
            return ['error' => 'Please login first'];
        }
        $verify = $this->userData->getSavedProperties($user_id);
        foreach ($verify as $saved) {
            if ($saved['id'] == $property_id) {
                if ($this->userData->removeSavedProperty($user_id, $property_id)) {
                    return ['success' => 'Removed from saved'];
                }
                return ['error' => 'Failed to remove from saved'];
            }
        }
        $save_id = uniqid('id_', true);
        if ($this->userData->saveProperty($user_id, $property_id, $save_id)) {
            return ['success' => 'Listing saved'];
        }
        return ['error' => 'Failed to save listing'];
    }

    public function getSavedProperties($user_id) {
        return $this->userData->getSavedProperties($user_id);
    }

    public function deleteProperty($user_id, $property_id) {
        $property = $this->userData->getPropertyById($property_id, $user_id);
        if (!$property) {
            return ['error' => 'Invalid or unauthorized property'];
        }
        if ($this->userData->deleteProperty($property_id)) {
            return ['success' => 'Listing deleted successfully'];
        }
        return ['error' => 'Failed to delete listing'];
    }

    public function getDashboardData($user_id) {
        $profile = $this->userData->getUserById($user_id);
        $properties = $this->userData->getUserProperties($user_id);
        $total_chats = $this->userData->getTotalChats($user_id);
        $total_saved = $this->userData->getTotalSavedProperties($user_id);
        $applicant_counts = $this->userData->getApplicantCounts($user_id);

        return [
            'profile' => $profile,
            'properties' => $properties,
            'total_properties' => count($properties),
            'total_chats' => $total_chats,
            'total_saved_properties' => $total_saved,
            'applicant_counts' => $applicant_counts
        ];
    }

    public function getApplicants($user_id, $property_id) {
        $property = $this->userData->getPropertyById($property_id, $user_id);
        if (!$property) {
            return ['error' => 'Invalid or unauthorized property'];
        }

        $preferences = $this->userData->getPreferences($property_id, $user_id);
        $applicants = $this->userData->getApplicants($property_id);

        foreach ($applicants as &$applicant) {
            $score_data = $this->userData->getTenantScore($property_id, $applicant['user_id']);
            if (!$score_data && $preferences) {
                $answers = $this->userData->getApplicantAnswers($property_id, $applicant['user_id']);
                if ($answers) {
                    $answer_data = [];
                    foreach ($answers as $answer) {
                        $answer_data[] = [
                            'question' => $answer['question_text'],
                            'answer' => $answer['answer_text'],
                            'created_at' => $answer['created_at']
                        ];
                    }

                    $api_key = $_ENV['OPENROUTER_API_KEY_SCORING'] ?? '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';
                    $api_url = 'https://openrouter.ai/api/v1/chat/completions';

                    $system_prompt = "
                        You are an expert real estate assistant tasked with scoring a tenant's application based on how well their answers align with the landlord's preferences. Return a JSON object containing:
                        - total_score: An integer between 0 and 100.
                        - criteria_breakdown: An object mapping specific criteria (e.g., 'no_smoking', 'income') to points awarded (e.g., {'no_smoking': 20}).
                        - explanation: A brief string explaining the score.
                        Scoring rules:
                        - Award +20 points per preference explicitly matched (e.g., 'non smoker' preference matches 'I do not smoke' answer).
                        - Award +5 points for answers submitted within 1 hour of question creation (compare 'created_at' timestamp).
                        - Award +10 points for detailed answers (>20 characters).
                        - Deduct points for mismatches if applicable (e.g., -10 for smoking if preference is 'non smoker').
                        Ensure the response is a valid JSON object and total_score is capped at 100.
                    ";

                    $prompt = "Landlord preferences: '$preferences'. Answers: " . json_encode($answer_data);

                    $post_data = [
                        'model' => 'meta-llama/llama-3.1-70b-instruct',
                        'messages' => [
                            ['role' => 'system', 'content' => $system_prompt],
                            ['role' => 'user', 'content' => $prompt]
                        ],
                        'response_format' => ['type' => 'json_object'],
                        'max_tokens' => 300
                    ];

                    try {
                        $ch = curl_init($api_url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                            'Content-Type: application/json',
                            'Authorization: Bearer ' . $api_key
                        ]);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

                        $response = curl_exec($ch);
                        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);

                        if ($http_code === 200) {
                            $result = json_decode($response, true);
                            $score_data = json_decode($result['choices'][0]['message']['content'] ?? '{}', true);

                            if (isset($score_data['total_score'], $score_data['criteria_breakdown'], $score_data['explanation'])) {
                                $total_score = min(max((int)$score_data['total_score'], 0), 100);
                                $criteria_breakdown = json_encode($score_data['criteria_breakdown']);
                                $explanation = htmlspecialchars($score_data['explanation'], ENT_QUOTES, 'UTF-8');

                                $this->userData->saveTenantScore($property_id, $applicant['user_id'], $total_score, $criteria_breakdown);

                                $applicant['score'] = $total_score;
                                $applicant['criteria_breakdown'] = $score_data['criteria_breakdown'];
                                $applicant['explanation'] = $explanation;
                            } else {
                                $applicant['score'] = 0;
                                $applicant['criteria_breakdown'] = [];
                                $applicant['explanation'] = 'Invalid API response format';
                                error_log("Invalid API response: " . print_r($result, true));
                            }
                        } else {
                            $applicant['score'] = 0;
                            $applicant['criteria_breakdown'] = [];
                            $applicant['explanation'] = 'API error: HTTP ' . $http_code;
                            error_log("API error: HTTP $http_code, Response: " . $response);
                        }
                    } catch (\Exception $e) {
                        $applicant['score'] = 0;
                        $applicant['criteria_breakdown'] = [];
                        $applicant['explanation'] = 'API request failed: ' . $e->getMessage();
                        error_log("API request failed: " . $e->getMessage());
                    }
                } else {
                    $applicant['score'] = 0;
                    $applicant['criteria_breakdown'] = [];
                    $applicant['explanation'] = 'No answers or preferences available';
                }
            } else {
                $applicant['score'] = $score_data['score'] ?? 0;
                $applicant['criteria_breakdown'] = json_decode($score_data['criteria_breakdown'] ?? '{}', true);
                $applicant['explanation'] = 'Previously scored';
            }

            $applicant['answers'] = $this->userData->getApplicantAnswers($property_id, $applicant['user_id']);
        }

        usort($applicants, fn($a, $b) => $b['score'] <=> $a['score']);

        return [
            'success' => true,
            'property' => $property,
            'applicants' => $applicants,
            'preferences' => $preferences
        ];
    }

    public function sendEnquiry($user_id, $property_id) {
        if (!$user_id) {
            return ['error' => 'Please login first'];
        }

        $receiver = $this->userData->getPropertyOwner($property_id);
        if (!$receiver) {
            return ['error' => 'Property not found'];
        }

        if ($this->userData->hasExistingRequest($property_id, $user_id, $receiver)) {
            return ['error' => 'Request already sent'];
        }

        try {
            $request_id = uniqid('msg_', true);
            if ($this->userData->insertRequest($request_id, $property_id, $user_id, $receiver)) {
                $message = "New enquiry for your property (ID: $property_id) from user ID $user_id. Check your messages.";
                if ($this->notificationSender) {
                    $this->notificationSender->sendTelegramNotification($receiver, $message, $property_id);
                } else {
                    error_log("Cannot send Telegram notification for property_id: $property_id, user_id: $user_id - SendNotification not available");
                }
                return ['success' => 'Request sent successfully', 'receiver' => $receiver];
            }
            return ['error' => 'Failed to send request'];
        } catch (\Exception $e) {
            error_log("UserLogic::sendEnquiry error: " . $e->getMessage());
            return ['error' => 'Failed to send request: ' . $e->getMessage()];
        }
    }
}