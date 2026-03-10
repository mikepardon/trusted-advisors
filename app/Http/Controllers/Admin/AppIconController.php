<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppIcon;
use App\Traits\AuditsAdminActions;
use Illuminate\Http\Request;

class AppIconController extends Controller
{
    use AuditsAdminActions;

    public function publicIndex()
    {
        return response()->json(AppIcon::allKeyed());
    }

    public function index()
    {
        return response()->json(AppIcon::orderBy('category')->orderBy('key')->get());
    }

    public function update(Request $request, AppIcon $appIcon)
    {
        $validated = $request->validate([
            'icon_type' => 'required|in:emoji,image',
            'icon_value' => 'required|string|max:500',
        ]);

        $old = $appIcon->only(array_keys($validated));
        $appIcon->update($validated);
        $this->auditModelChange('update', $appIcon, $old);

        return response()->json($appIcon);
    }
}
