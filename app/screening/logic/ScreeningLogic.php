<?php
namespace App\Screening\Logic;

use PDOException;
use App\Screening\Data\ScreeningData;
use App\Notifications\Logic\SendNotification;

class ScreeningLogic {
    private $dataLayer;
    private $config;
    private $sendNotification;

    public function __construct($conn, $config, $sendNotification) {
        $this->dataLayer = new ScreeningData($conn);
        $this->config = $config;
        $this->sendNotification = $sendNotification;
    }

    public function validatePropertyId($property_id, $user_id) {
        if (empty($property_id) || !filter_var($property_id, FILTER_VALIDATE_INT)) {
            return ['success' => false, 'message' => 'Invalid property ID.'];
        }
        try {
            $property = $this->dataLayer->getPropertyByIdAndUser($property_id, $user_id);
            if (!$property) {
                return ['success' => false, 'message' => 'Invalid or unauthorized property.'];
            }
            return ['success' => true, 'property' => $property];
        } catch (PDOException $e) {
            error_log("ScreeningLogic::validatePropertyId error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    public function handleScreeningPreferences($property_id, $user_id, $postData) {
        $preferences_text = htmlspecialchars(trim($postData['tenant_preferences'] ?? ''), ENT_QUOTES, 'UTF-8');
        $warning_msg = [];

        if (empty($preferences_text)) {
            return ['success' => false, 'warning_msg' => ['Please enter tenant preferences.']];
        }

        try {
            $this->dataLayer->saveScreeningPreferences($property_id, $user_id, $preferences_text);

            $questions = $this->generateScreeningQuestions($preferences_text);
            $questions_saved = 0;
            foreach ($questions as $question) {
                $sanitized_question = htmlspecialchars(trim($question), ENT_QUOTES, 'UTF-8');
                $ai_metadata = json_encode(['source' => empty($questions) ? 'fallback' : 'openrouter', 'model' => 'llama-3.1-70b']);
                if ($this->dataLayer->saveScreeningQuestion($property_id, $sanitized_question, $ai_metadata)) {
                    $questions_saved++;
                }
            }

            if ($questions_saved > 0) {
                return ['success' => true, 'success_msg' => ['Screening questions generated successfully!']];
            }
            return ['success' => false, 'warning_msg' => ['No questions could be saved to the database.']];
        } catch (PDOException $e) {
            error_log("ScreeningLogic::handleScreeningPreferences error: " . $e->getMessage());
            return ['success' => false, 'warning_msg' => ['Database error: ' . $e->getMessage()]];
        }
    }

    private function generateScreeningQuestions($preferences_text) {
        $api_key = $this->config['OPENROUTER_API_KEY_SCREENING'];
        $api_url = 'https://openrouter.ai/api/v1/chat/completions';

        $system_prompt = "
            You are an expert real estate assistant tasked with generating tenant screening questions for a landlord. Based on the provided landlord preferences, create a list of 3-5 formal, concise, and relevant screening questions for potential tenants. The questions must address common landlord concerns such as smoking, pets, income, lease compliance, or lifestyle, even if the preferences are vague or minimal. Return the questions as a flat JSON array of non-empty strings, e.g., ['Do you smoke or use tobacco products?', 'What is your monthly income?']. Each question must be clear, professional, at least 10 characters long, and suitable for a rental application. Do not return empty strings, nested arrays, objects, or non-string elements.
        ";

        $post_data = [
            'model' => 'meta-llama/llama-3.1-70b-instruct',
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => "Landlord preferences: " . $preferences_text]
            ],
            'response_format' => ['type' => 'json_object'],
            'max_tokens' => 200
        ];

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

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error || $http_code !== 200) {
            error_log("ScreeningLogic::generateScreeningQuestions API error: " . ($curl_error ?: "HTTP $http_code"));
            return [
                "Do you smoke or use tobacco products?",
                "What is your monthly income?",
                "Do you have any pets, and if so, what kind?",
                "Can you provide references from previous landlords?",
                "How many occupants will be living in the property?"
            ];
        }

        $result = json_decode($response, true);
        $questions = json_decode($result['choices'][0]['message']['content'] ?? '{}', true);
        $questions = $questions['questions'] ?? $questions;

        $valid_questions = [];
        if (is_array($questions)) {
            foreach ($questions as $question) {
                if (is_string($question) && !empty(trim($question)) && strlen(trim($question)) >= 10) {
                    $valid_questions[] = $question;
                }
            }
        }

        return !empty($valid_questions) ? $valid_questions : [
            "Do you smoke or use tobacco products?",
            "What is your monthly income?",
            "Do you have any pets, and if so, what kind?",
            "Can you provide references from previous landlords?",
            "How many occupants will be living in the property?"
        ];
    }

    public function handleReviewQuestions($property_id, $user_id, $postData) {
        try {
            $warning_msg = [];
            $success_msg = [];

            if (isset($postData['save_questions'])) {
                if (isset($postData['questions'])) {
                    foreach ($postData['questions'] as $question_id => $question_text) {
                        $question_id = filter_var($question_id, FILTER_VALIDATE_INT);
                        $question_text = htmlspecialchars(trim($question_text), ENT_QUOTES, 'UTF-8');
                        if (!empty($question_text)) {
                            $this->dataLayer->updateScreeningQuestion($question_id, $property_id, $question_text);
                        }
                    }
                }

                if (isset($postData['delete_questions'])) {
                    foreach ($postData['delete_questions'] as $question_id) {
                        $question_id = filter_var($question_id, FILTER_VALIDATE_INT);
                        $this->dataLayer->deleteScreeningQuestion($question_id, $property_id);
                    }
                }

                if (!empty($postData['new_question'])) {
                    $new_question = htmlspecialchars(trim($postData['new_question']), ENT_QUOTES, 'UTF-8');
                    $ai_metadata = json_encode(['source' => 'manual', 'model' => 'none']);
                    $this->dataLayer->saveScreeningQuestion($property_id, $new_question, $ai_metadata);
                }

                $success_msg[] = 'Questions updated successfully!';
            }

            $questions = $this->dataLayer->getScreeningQuestions($property_id);
            return [
                'success' => true,
                'success_msg' => $success_msg,
                'warning_msg' => $warning_msg,
                'questions' => $questions
            ];
        } catch (PDOException $e) {
            error_log("ScreeningLogic::handleReviewQuestions error: " . $e->getMessage());
            return ['success' => false, 'warning_msg' => ['Database error: ' . $e->getMessage()]];
        }
    }

    public function handleApply($property_id, $user_id, $postData) {
        try {
            if ($this->dataLayer->hasApplied($property_id, $user_id)) {
                return ['success' => false, 'warning_msg' => ['You have already applied for this property.']];
            }

            $questions = $this->dataLayer->getScreeningQuestions($property_id);
            if (empty($questions)) {
                return ['success' => false, 'warning_msg' => ['No screening questions available for this property.']];
            }

            $answers = $postData['answers'] ?? [];
            $all_answered = true;
            $sanitized_answers = [];

            foreach ($questions as $question) {
                if (!isset($answers[$question['id']]) || trim($answers[$question['id']]) === '') {
                    $all_answered = false;
                    break;
                }
                $sanitized_answers[$question['id']] = htmlspecialchars(trim($answers[$question['id']]), ENT_QUOTES, 'UTF-8');
            }

            if (!$all_answered) {
                return ['success' => false, 'warning_msg' => ['Please answer all questions.']];
            }

            $this->dataLayer->saveTenantAnswers($property_id, $user_id, $sanitized_answers);
            return ['success' => true, 'success_msg' => ['Application submitted successfully!']];
        } catch (PDOException $e) {
            error_log("ScreeningLogic::handleApply error: " . $e->getMessage());
            return ['success' => false, 'warning_msg' => ['Database error: ' . $e->getMessage()]];
        }
    }

    public function handleScoreApplicants($property_id, $user_id) {
        try {
            $property = $this->dataLayer->getPropertyDetails($property_id);
            if (!$property) {
                return ['success' => false, 'error' => 'Invalid or unauthorized property'];
            }

            $preferences = $this->dataLayer->getScreeningPreferences($property_id, $user_id);
            $applicants = $this->dataLayer->getApplicants($property_id);
            $results = [];

            foreach ($applicants as &$applicant) {
                $score_data = $this->dataLayer->getTenantScore($property_id, $applicant['user_id']);
                if ($score_data) {
                    $applicant['score'] = $score_data['score'];
                    $applicant['criteria_breakdown'] = json_decode($score_data['criteria_breakdown'], true);
                    $applicant['explanation'] = 'Previously scored.';
                } else {
                    $answers = $this->dataLayer->getTenantAnswers($property_id, $applicant['user_id']);
                    if ($answers && $preferences) {
                        $answer_data = [];
                        foreach ($answers as $answer) {
                            $answer_data[] = [
                                'question' => $answer['question_text'],
                                'answer' => $answer['answer_text'],
                                'answer_created_at' => $answer['created_at'],
                                'question_created_at' => $answer['question_created_at']
                            ];
                        }

                        $context = [
                            'property' => [
                                'name' => $property['property_name'],
                                'price' => $property['price'],
                                'address' => $property['address'],
                                'bhk' => $property['bhk'],
                                'furnished' => $property['furnished'],
                                'lift' => $property['lift'],
                                'parking_area' => $property['parking_area']
                            ],
                            'landlord_preferences' => $preferences,
                            'tenant' => [
                                'name' => $applicant['name'],
                                'reputation_score' => $applicant['reputation_score'],
                                'review_count' => $applicant['review_count']
                            ],
                            'answers' => $answer_data
                        ];

                        $score_data = $this->scoreTenant($context);
                        if ($score_data['success']) {
                            $this->dataLayer->saveTenantScore(
                                $property_id,
                                $applicant['user_id'],
                                $score_data['total_score'],
                                json_encode($score_data['criteria_breakdown'])
                            );
                            $applicant['score'] = $score_data['total_score'];
                            $applicant['criteria_breakdown'] = $score_data['criteria_breakdown'];
                            $applicant['explanation'] = $score_data['explanation'];
                        } else {
                            $applicant['score'] = 0;
                            $applicant['criteria_breakdown'] = [];
                            $applicant['explanation'] = $score_data['error'];
                        }
                    } else {
                        $applicant['score'] = 0;
                        $applicant['criteria_breakdown'] = [];
                        $applicant['explanation'] = 'No answers or preferences available.';
                    }
                }

                $applicant['answers'] = $this->dataLayer->getTenantAnswers($property_id, $applicant['user_id']);
                $results[] = $applicant;
            }

            usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
            return ['success' => true, 'applicants' => $results];
        } catch (PDOException $e) {
            error_log("ScreeningLogic::handleScoreApplicants error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }

    private function scoreTenant($context) {
        $api_key = $this->config['OPENROUTER_API_KEY_SCORING'];
        $api_url = 'https://openrouter.ai/api/v1/chat/completions';

        $system_prompt = "
            You are an expert real estate assistant tasked with scoring a tenant's application for a rental property. Based on the provided context, return a JSON object containing:
            - total_score: An integer between 0 and 100.
            - criteria_breakdown: An object mapping specific criteria (e.g., 'no_pets', 'income') to points awarded.
            - explanation: A brief string explaining the score.
            Context includes:
            - Property details (name, price, address, BHK, furnished, lift, parking).
            - Landlord preferences (text describing ideal tenant).
            - Tenant details (name, reputation_score [0-1], review_count).
            - Tenant answers (question, answer, answer_created_at, question_created_at).
            Scoring rules:
            - Award +20 points per preference explicitly matched.
            - Award +5 points for each answer submitted within 1 hour of question creation.
            - Award +10 points for each detailed answer (>20 characters).
            - Award +5 points if tenant's reputation_score is >= 0.8.
            - Cap total_score at 100.
            - If preferences or answers are missing, use reasonable judgment based on property details and tenant reputation.
            Ensure the response is a valid JSON object.
        ";

        $post_data = [
            'model' => 'meta-llama/llama-3.1-70b-instruct',
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => json_encode($context)]
            ],
            'response_format' => ['type' => 'json_object'],
            'max_tokens' => 500
        ];

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

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error || $http_code !== 200) {
            error_log("ScreeningLogic::scoreTenant API error: " . ($curl_error ?: "HTTP $http_code"));
            return ['success' => false, 'error' => 'API error: ' . ($curl_error ?: "HTTP $http_code")];
        }

        $result = json_decode($response, true);
        $score_data = json_decode($result['choices'][0]['message']['content'] ?? '{}', true);

        if (isset($score_data['total_score'], $score_data['criteria_breakdown'], $score_data['explanation'])) {
            return [
                'success' => true,
                'total_score' => min(max((int)$score_data['total_score'], 0), 100),
                'criteria_breakdown' => $score_data['criteria_breakdown'],
                'explanation' => htmlspecialchars($score_data['explanation'], ENT_QUOTES, 'UTF-8')
            ];
        }

        return ['success' => false, 'error' => 'Invalid API response format.'];
    }
}