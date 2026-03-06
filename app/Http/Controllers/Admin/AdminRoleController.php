<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\AuditsAdminActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminRoleController extends Controller
{
    use AuditsAdminActions;
    private const VALID_ROLES = ['super_admin', 'content_admin', 'moderator', 'analyst'];

    public function index(): JsonResponse
    {
        $admins = User::where('is_admin', true)
            ->select('id', 'name', 'email', 'admin_role', 'is_admin', 'created_at')
            ->orderBy('name')
            ->get();

        return response()->json($admins);
    }

    public function updateRole(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'admin_role' => ['required', Rule::in(self::VALID_ROLES)],
        ]);

        // Prevent non-super_admin from modifying super_admin users
        if ($user->isSuperAdmin() && !$request->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Cannot modify super admin'], 403);
        }

        // Prevent removing your own super_admin role
        if ($user->id === $request->user()->id && $validated['admin_role'] !== 'super_admin' && $user->isSuperAdmin()) {
            return response()->json(['message' => 'Cannot demote yourself from super admin'], 422);
        }

        $oldRole = $user->admin_role;
        $user->update([
            'admin_role' => $validated['admin_role'],
            'is_admin' => true,
        ]);
        $this->auditLog('update_role', 'User', $user->id, ['admin_role' => ['old' => $oldRole, 'new' => $validated['admin_role']]]);

        return response()->json($user->only('id', 'name', 'email', 'admin_role'));
    }
}
