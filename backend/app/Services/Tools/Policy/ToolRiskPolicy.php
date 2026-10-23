<?php

declare(strict_types=1);

namespace App\Services\Tools\Policy;

use App\Enums\ToolRiskLevel;
use App\Models\User;

final class ToolRiskPolicy
{
    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $input
     * @return array{risk_level: string, approval_required: bool}
     */
    public function classify(User $user, array $definition, array $input): array
    {
        $risk = $definition['risk_level'] ?? ToolRiskLevel::Low->value;
        $riskLevel = ToolRiskLevel::tryFrom((string) $risk) ?? ToolRiskLevel::Low;

        $approvalRequired = (bool) ($definition['approval_required'] ?? false);
        $limits = $definition['limits'] ?? [];

        if ($riskLevel === ToolRiskLevel::High || $riskLevel === ToolRiskLevel::Critical) {
            $approvalRequired = true;
        }

        if (! empty($limits['require_approval_for_roles'])) {
            $roles = $limits['require_approval_for_roles'];
            if (is_array($roles) && in_array($user->role->value, $roles, true)) {
                $approvalRequired = true;
            }
        }

        return [
            'risk_level' => $riskLevel->value,
            'approval_required' => $approvalRequired,
        ];
    }
}
