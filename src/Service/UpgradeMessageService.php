<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Plan;

class UpgradeMessageService
{
    public function __construct(
        private ErrorLimitService $errorLimitService
    ) {}

    /**
     * Génère un message d'upgrade adapté à la situation de l'utilisateur
     */
    public function getUpgradeMessage(User $user, string $context = 'general'): array
    {
        $stats = $this->errorLimitService->getUsageStats($user);
        $plan = $user->getPlan();

        // Cas: Plan expiré
        if ($stats['plan_expired']) {
            return [
                'type' => 'plan_expired',
                'title' => 'Plan expiré',
                'message' => 'Votre plan a expiré. Renouvelez votre abonnement pour continuer à utiliser Error Explorer.',
                'action_text' => 'Renouveler mon plan',
                'action_url' => '/pricing',
                'urgency' => 'high'
            ];
        }

        // Cas: Aucun plan
        if (!$plan) {
            return [
                'type' => 'no_plan',
                'title' => 'Aucun plan actif',
                'message' => 'Souscrivez à un plan pour débloquer toutes les fonctionnalités d\'Error Explorer.',
                'action_text' => 'Choisir un plan',
                'action_url' => '/pricing',
                'urgency' => 'high'
            ];
        }

        // Cas: Limite de projets atteinte
        if ($stats['projects']['percentage'] >= 100) {
            return [
                'type' => 'project_limit',
                'title' => 'Limite de projets atteinte',
                'message' => sprintf(
                    'Vous avez atteint votre limite de %s projets. Passez à un plan supérieur pour créer plus de projets.',
                    $stats['projects']['max_label']
                ),
                'action_text' => 'Upgrader mon plan',
                'action_url' => '/pricing',
                'urgency' => 'medium',
                'current' => $stats['projects']['current'],
                'max' => $stats['projects']['max']
            ];
        }

        // Cas: Limite d'erreurs atteinte
        if ($stats['monthly_errors']['percentage'] >= 100) {
            return [
                'type' => 'error_limit',
                'title' => 'Limite mensuelle d\'erreurs atteinte',
                'message' => sprintf(
                    'Vous avez atteint votre limite mensuelle de %s erreurs. Passez à un plan supérieur pour continuer à recevoir des erreurs.',
                    $stats['monthly_errors']['max_label']
                ),
                'action_text' => 'Upgrader mon plan',
                'action_url' => '/pricing',
                'urgency' => 'high',
                'current' => $stats['monthly_errors']['current'],
                'max' => $stats['monthly_errors']['max']
            ];
        }

        // Cas: Approche des limites (80%+)
        if ($stats['projects']['percentage'] >= 80) {
            return [
                'type' => 'project_warning',
                'title' => 'Limite de projets bientôt atteinte',
                'message' => sprintf(
                    'Vous approchez de votre limite de projets (%d/%s). Pensez à upgrader votre plan.',
                    $stats['projects']['current'],
                    $stats['projects']['max_label']
                ),
                'action_text' => 'Voir les plans',
                'action_url' => '/pricing',
                'urgency' => 'low',
                'current' => $stats['projects']['current'],
                'max' => $stats['projects']['max']
            ];
        }

        if ($stats['monthly_errors']['percentage'] >= 80) {
            return [
                'type' => 'error_warning',
                'title' => 'Limite d\'erreurs bientôt atteinte',
                'message' => sprintf(
                    'Vous approchez de votre limite mensuelle d\'erreurs (%s/%s). Pensez à upgrader votre plan.',
                    number_format($stats['monthly_errors']['current']),
                    $stats['monthly_errors']['max_label']
                ),
                'action_text' => 'Voir les plans',
                'action_url' => '/pricing',
                'urgency' => 'low',
                'current' => $stats['monthly_errors']['current'],
                'max' => $stats['monthly_errors']['max']
            ];
        }

        // Cas: Plan gratuit avec suggestions d'upgrade
        if ($plan->isFree()) {
            return [
                'type' => 'free_plan',
                'title' => 'Débloquez plus de fonctionnalités',
                'message' => 'Passez à un plan payant pour obtenir plus de projets, d\'erreurs et des fonctionnalités avancées.',
                'action_text' => 'Découvrir les plans',
                'action_url' => '/pricing',
                'urgency' => 'low'
            ];
        }

        // Aucun message d'upgrade nécessaire
        return [
            'type' => 'none',
            'message' => null
        ];
    }

    /**
     * Vérifie si un message d'upgrade doit être affiché
     */
    public function shouldShowUpgradeMessage(User $user): bool
    {
        $message = $this->getUpgradeMessage($user);
        return $message['type'] !== 'none';
    }

    /**
     * Génère un message d'upgrade pour une API response
     */
    public function getApiUpgradeMessage(User $user, string $errorCode): array
    {
        $stats = $this->errorLimitService->getUsageStats($user);
        $plan = $user->getPlan();

        switch ($errorCode) {
            case 'PLAN_EXPIRED':
                return [
                    'message' => 'Votre plan a expiré. Renouvelez votre abonnement pour continuer.',
                    'upgrade_url' => '/pricing',
                    'expires_at' => $user->getPlanExpiresAt()?->format('c')
                ];

            case 'NO_ACTIVE_PLAN':
                return [
                    'message' => 'Aucun plan actif. Souscrivez à un plan pour utiliser ce service.',
                    'upgrade_url' => '/pricing'
                ];

            case 'MONTHLY_LIMIT_REACHED':
                return [
                    'message' => sprintf(
                        'Limite mensuelle de %s erreurs atteinte. Passez à un plan supérieur.',
                        $stats['monthly_errors']['max_label']
                    ),
                    'upgrade_url' => '/pricing',
                    'current_usage' => $stats['monthly_errors']['current'],
                    'limit' => $stats['monthly_errors']['max'],
                    'reset_date' => (new \DateTime('first day of next month'))->format('c')
                ];

            case 'PROJECT_LIMIT_REACHED':
                return [
                    'message' => sprintf(
                        'Limite de %s projets atteinte. Passez à un plan supérieur.',
                        $stats['projects']['max_label']
                    ),
                    'upgrade_url' => '/pricing',
                    'current_projects' => $stats['projects']['current'],
                    'limit' => $stats['projects']['max']
                ];

            default:
                return [
                    'message' => 'Limite de plan atteinte. Consultez nos plans pour plus de fonctionnalités.',
                    'upgrade_url' => '/pricing'
                ];
        }
    }

    /**
     * Retourne les suggestions de plans basées sur l'usage actuel
     */
    public function getSuggestedPlans(User $user): array
    {
        $stats = $this->errorLimitService->getUsageStats($user);
        $currentPlan = $user->getPlan();
        
        $suggestions = [];

        // Si l'utilisateur dépasse ses limites actuelles
        if ($stats['projects']['percentage'] >= 90 || $stats['monthly_errors']['percentage'] >= 90) {
            $suggestions[] = [
                'reason' => 'usage_high',
                'message' => 'Votre usage actuel nécessite un plan supérieur',
                'recommended_features' => ['Plus de projets', 'Plus d\'erreurs mensuelles', 'Support prioritaire']
            ];
        }

        // Si l'utilisateur est sur le plan gratuit
        if ($currentPlan && $currentPlan->isFree()) {
            $suggestions[] = [
                'reason' => 'free_user',
                'message' => 'Débloquez des fonctionnalités avancées',
                'recommended_features' => ['Alertes email', 'Intégrations', 'Rétention de données étendue']
            ];
        }

        // Si l'utilisateur n'a pas certaines fonctionnalités
        if ($currentPlan && !$currentPlan->hasEmailAlerts()) {
            $suggestions[] = [
                'reason' => 'missing_features',
                'message' => 'Recevez des alertes en temps réel',
                'recommended_features' => ['Alertes email', 'Notifications Slack', 'Webhooks sortants']
            ];
        }

        return $suggestions;
    }
}