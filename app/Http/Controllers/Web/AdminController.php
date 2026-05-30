<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function users(Request $request)
    {
        $query = User::query()->orderBy('created_at', 'desc');
        
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }
        
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }
        
        $users = $query->paginate(20);
        
        return view('admin.users', compact('users'));
    }

    public function editUser(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    public function updateUser(Request $request, User $user)
    {
        $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'role'          => 'required|in:admin,management,employee',
            'password'      => 'nullable|string|min:8',
            'daily_rate'    => 'nullable|numeric|min:0',
            'overtime_rate' => 'nullable|numeric|min:0',
        ]);

        $data = [
            'name'          => $request->name,
            'email'         => $request->email,
            'role'          => $request->role,
            'daily_rate'    => $request->filled('daily_rate')    ? (float) $request->daily_rate    : null,
            'overtime_rate' => $request->filled('overtime_rate') ? (float) $request->overtime_rate : null,
        ];

        if ($request->filled('password')) {
            $data['password_hash'] = Hash::make($request->password);
        }

        $user->update($data);

        if ($user->wasChanged('role')) {
            app(\App\Services\AuditService::class)->log(
                'role_changed',
                auth()->id(),
                [
                    'target_user_id' => $user->id,
                    'old_role'       => $user->getOriginal('role'),
                    'new_role'       => $user->role,
                ]
            );
        }

        return redirect()->route('admin.users')->with('success', 'User updated successfully.');
    }

    public function deleteUser(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account');
        }

        $user->delete();

        return redirect()->route('admin.users')->with('success', 'User deleted successfully');
    }

    public function auditLogs(Request $request)
    {
        $query = AuditLog::with('user')->orderBy('created_at', 'desc');
        
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        
        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }
        
        $logs = $query->paginate(50);
        
        return view('admin.audit-logs', compact('logs'));
    }
}