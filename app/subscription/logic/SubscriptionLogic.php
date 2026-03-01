<?php
namespace App\Subscription\Logic;
use App\Subscription\Data\SubscriptionData;

class SubscriptionLogic {
    private $subscriptionData;

    public function __construct() {
        $this->subscriptionData = new SubscriptionData();
    }

    public function getUserSubscriptionStatus($user_id) {
        return $this->subscriptionData->getUserSubscriptionStatus($user_id);
    }

    public function canAccessTenantScreening($user_id) {
        $is_premium = $this->subscriptionData->getUserSubscriptionStatus($user_id);
        $has_properties = $this->subscriptionData->hasPostedProperties($user_id);
        return $is_premium && $has_properties;
    }

    public function upgradePlan($user_id) {
        if ($this->subscriptionData->getUserSubscriptionStatus($user_id)) {
            return ['success' => false, 'message' => 'User is already on a premium plan.'];
        }
        $success = $this->subscriptionData->upgradeToPremium($user_id);
        return $success
            ? ['success' => true, 'message' => 'Successfully upgraded to Premium Plan!']
            : ['success' => false, 'message' => 'Failed to upgrade plan. Please try again.'];
    }

    public function downgradePlan($user_id) {
        if (!$this->subscriptionData->getUserSubscriptionStatus($user_id)) {
            return ['success' => false, 'message' => 'User is not on a premium plan.'];
        }
        $success = $this->subscriptionData->downgradeToFree($user_id);
        return $success
            ? ['success' => true, 'message' => 'Successfully downgraded to Free Plan!']
            : ['success' => false, 'message' => 'Failed to downgrade plan. Please try again.'];
    }
}