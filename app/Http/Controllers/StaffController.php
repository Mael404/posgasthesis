<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(): View
    {
        $activeStaff = $this->manageableStaffQuery()
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->get();

        $inactiveStaff = $this->manageableStaffQuery()
            ->where('status', 'inactive')
            ->orderByDesc('created_at')
            ->get();

        return view('pages.admin.managestaffs', [
            'title' => 'Staff Management',
            'activeStaff' => $activeStaff,
            'inactiveStaff' => $inactiveStaff,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'first_name' => 'required|string|max:100',
                'last_name' => 'required|string|max:100',
                'email' => 'required|email|max:255|unique:users,email',
                'phone' => 'nullable|string|max:20',
            ]);

            $firstName = $this->cleanName($validated['first_name']);
            $lastName = $this->cleanName($validated['last_name']);
            $fullName = trim($firstName . ' ' . $lastName);
            $rawPassword = $this->defaultPassword($firstName, $lastName);

            DB::table('users')->insert([
                'employee_id' => $this->nextEmployeeId(),
                'name' => $fullName,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => strtolower($validated['email']),
                'role' => User::ROLE_STAFF,
                'phone' => $validated['phone'] ?? null,
                'password' => Hash::make($rawPassword),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Staff added successfully. Default password: ' . $rawPassword,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        } catch (\Throwable $e) {
            return $this->jsonError('System Error: ' . $e->getMessage(), 500);
        }
    }

    public function toggleStatus(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'id' => 'required|exists:users,id',
                'status' => 'required|in:active,inactive',
            ]);

            if (! $this->findManageableStaff((int) $validated['id'])) {
                return $this->jsonError('Selected account cannot be managed from the staff module.', 403);
            }

            DB::table('users')
                ->where('id', $validated['id'])
                ->update([
                    'status' => $validated['status'],
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Staff status updated to ' . $validated['status'] . '.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        } catch (\Throwable $e) {
            return $this->jsonError('System Error: ' . $e->getMessage(), 500);
        }
    }

    public function resetPassword(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'id' => 'required|exists:users,id',
                'new_password' => 'required|string|min:6',
                'confirm_password' => 'required|same:new_password',
            ]);

            if (! $this->findManageableStaff((int) $validated['id'])) {
                return $this->jsonError('Selected account cannot be managed from the staff module.', 403);
            }

            DB::table('users')
                ->where('id', $validated['id'])
                ->update([
                    'password' => Hash::make($validated['new_password']),
                    'password_changed_at' => now(),
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Password reset successfully.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        } catch (\Throwable $e) {
            return $this->jsonError('System Error: ' . $e->getMessage(), 500);
        }
    }

    private function manageableStaffQuery()
    {
        return DB::table('users')
            ->where('role', '!=', User::ROLE_VP_ADMIN);
    }

    private function findManageableStaff(int $id): ?object
    {
        return $this->manageableStaffQuery()
            ->where('id', $id)
            ->first();
    }

    private function nextEmployeeId(): string
    {
        $lastId = DB::table('users')->max('id') ?? 0;

        return 'EMP-' . str_pad((string) ($lastId + 1), 3, '0', STR_PAD_LEFT);
    }

    private function defaultPassword(string $firstName, string $lastName): string
    {
        return strtolower(preg_replace('/\s+/', '', $firstName . $lastName));
    }

    private function cleanName(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', $name));
    }

    private function jsonError(string $message, int $status = 422): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
