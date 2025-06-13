<?php

namespace App\Tests;

use App\Entity\Plan;
use App\Entity\User;

trait TestFixtures
{
    protected function createTestUser(array $overrides = []): User
    {
        $user = new User();
        $user->setEmail($overrides['email'] ?? 'test@example.com');
        $user->setFirstName($overrides['firstName'] ?? 'Test');
        $user->setLastName($overrides['lastName'] ?? 'User');
        $user->setPassword($overrides['password'] ?? '$2y$10$test_hash');
        $user->setIsActive($overrides['isActive'] ?? true);
        $user->setIsVerified($overrides['isVerified'] ?? true);
        $user->setCreatedAt($overrides['createdAt'] ?? new \DateTimeImmutable());
        $user->setUpdatedAt($overrides['updatedAt'] ?? new \DateTimeImmutable());

        // Créer un plan par défaut si nécessaire
        if (!isset($overrides['plan']) && method_exists($user, 'setPlan')) {
            $plan = $this->createTestPlan('Test Plan', 'test-plan');
            $this->persistAndFlush($plan);
            $user->setPlan($plan);
        } elseif (isset($overrides['plan'])) {
            $user->setPlan($overrides['plan']);
        }

        return $user;
    }

    protected function createTestPlan(array $overrides = []): Plan
    {
        $plan = new Plan();
        $plan->setName($overrides['name'] ?? 'Test Plan');
        $plan->setSlug($overrides['slug'] ?? 'test-plan');
        $plan->setPriceMonthly($overrides['price'] ?? 0.0);
        $plan->setIsActive($overrides['isActive'] ?? true);
        $plan->setIsBuyable($overrides['isBuyable'] ?? true);
        $plan->setMaxProjects($overrides['maxProjects'] ?? 5);
        $plan->setMaxMonthlyErrors($overrides['maxMonthlyErrors'] ?? 1000);

        return $plan;
    }
}
