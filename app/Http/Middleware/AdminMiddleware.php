<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Permission → allowed roles map.
     * super_admin always bypasses (handled in User::hasAdminRole).
     */
    private const PERMISSION_ROLES = [
        // Content management
        'admin.characters' => ['content_admin'],
        'admin.cards' => ['content_admin'],
        'admin.events' => ['content_admin'],
        'admin.items' => ['content_admin'],
        'admin.seasons' => ['content_admin'],
        'admin.achievements' => ['content_admin'],
        'admin.unlockables' => ['content_admin'],
        'admin.challenges' => ['content_admin'],
        'admin.addons' => ['content_admin'],
        'admin.sounds' => ['content_admin'],
        'admin.dice' => ['content_admin'],
        'admin.kingdom-styles' => ['content_admin'],
        'admin.media' => ['content_admin'],
        'admin.announcements' => ['content_admin'],
        'admin.rotating-events' => ['content_admin'],
        'admin.ai' => ['content_admin'],
        'admin.csv' => ['content_admin'],
        'admin.bot-games' => ['content_admin'],

        // User / game management
        'admin.users' => ['moderator'],
        'admin.gifts' => ['moderator'],
        'admin.games' => ['moderator'],
        'admin.payments' => ['moderator'],

        // Analytics (read-only)
        'admin.dashboard' => ['content_admin', 'moderator', 'analyst'],
        'admin.balance' => ['content_admin', 'moderator', 'analyst'],
        'admin.retention' => ['content_admin', 'moderator', 'analyst'],
        'admin.levels' => ['content_admin', 'moderator', 'analyst'],

        // System (super_admin only — empty array means only super_admin)
        'admin.audit-log' => [],
        'admin.roles' => [],
        'admin.rules' => [],
    ];

    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        if (!$request->user() || !$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // If a specific permission is required, check role
        if ($permission !== null) {
            $allowedRoles = self::PERMISSION_ROLES[$permission] ?? [];
            if (!$request->user()->hasAdminRole(...$allowedRoles)) {
                return response()->json(['message' => 'Insufficient permissions'], 403);
            }
        }

        return $next($request);
    }
}
