<?php

namespace App\Services;

use App\Models\PriceRecord;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UserAccounts
{
    public static function current(User $user): ?User
    {
        return User::find($user->id);
    }

    public static function authorize(User $actor, string $permission): void
    {
        Gate::forUser(self::current($actor))->authorize($permission);
    }

    public static function listing(string $search)
    {
        return User::query()->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
            $query->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')->orWhere('employee_number', 'like', '%'.$search.'%');
        }))->orderBy('name')->paginate(15)->withQueryString();
    }

    public static function save(array $data, User $actor, ?User $user = null): User
    {
        self::authorize($actor, 'manage-users');

        return DB::transaction(function () use ($data, $actor, $user) {
            $admins = User::where('role', 'system_admin')->where('is_active', true)->orderBy('id')->lockForUpdate()->get();
            $account = $user ? User::whereKey($user->id)->lockForUpdate()->firstOrFail() : new User;
            if ($account->role === 'system_admin' && $account->is_active && $admins->count() <= 1
                && ($data['role'] !== 'system_admin' || ! $data['is_active'])) {
                throw ValidationException::withMessages(['role' => __('Keep at least one active System Administrator.')]);
            }
            $account->name = $data['name'];
            $account->employee_number = $data['employee_number'];
            $account->email = $data['email'];
            $account->role = $data['role'];
            $account->is_active = $data['is_active'];
            if (! empty($data['password'])) {
                $account->password = $data['password'];
            }
            $account->save();
            Log::info('users.saved', ['actor_id' => $actor->id, 'user_id' => $account->id]);

            return $account;
        });
    }

    public static function delete(User $user, User $actor): void
    {
        self::authorize($actor, 'manage-users');

        DB::transaction(function () use ($user, $actor) {
            $admins = User::where('role', 'system_admin')->where('is_active', true)->orderBy('id')->lockForUpdate()->get();
            $account = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($account->id === $actor->id) {
                throw ValidationException::withMessages(['user' => __('You cannot delete your own account.')]);
            }
            if ($account->role === 'system_admin' && $account->is_active && $admins->count() <= 1) {
                throw ValidationException::withMessages(['user' => __('Keep at least one active System Administrator.')]);
            }
            $account->is_active = false;
            $account->last_seen_at = null;
            $account->save();
            $account->delete();
            Log::info('users.deleted', ['actor_id' => $actor->id, 'user_id' => $account->id]);
        });
    }

    public static function touch(User $user): void
    {
        if (! $user->last_seen_at || $user->last_seen_at->lt(now()->subMinute())) {
            User::whereKey($user->id)->update(['last_seen_at' => now()]);
        }
    }

    public static function offline(User $user): void
    {
        User::whereKey($user->id)->update(['last_seen_at' => null]);
    }
}

class PriceMonitoring
{
    public static function listing(string $search)
    {
        return PriceRecord::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    foreach (['particulars', 'brand_model', 'department', 'control_number', 'store', 'canvasser'] as $field) {
                        $query->orWhere($field, 'like', '%'.$search.'%');
                    }
                });
            })->latest('id')->paginate(25)->withQueryString();
    }

    public static function find(int $id): PriceRecord
    {
        return PriceRecord::findOrFail($id);
    }

    public static function save(array $rows, User $actor, ?int $id = null): void
    {
        UserAccounts::authorize($actor, 'edit-data');

        try {
            DB::transaction(function () use ($rows, $id) {
                foreach ($rows as $index => $data) {
                    // Normalize quantities and prices before computing the duplicate fingerprint.
                    $quantity = self::scaled($data['qty'], 3);
                    $amount = self::scaled($data['amount'], 2);
                    $data['qty'] = self::decimal($quantity, 3);
                    $data['amount'] = self::decimal($amount, 2);
                    $data['brand_model'] = $data['brand_model'] ?? '';
                    $data['store'] = $data['store'] ?? '';
                    $identity = [];
                    foreach (PriceRecord::FINGERPRINT_FIELDS as $field) {
                        $identity[] = mb_strtolower(trim($data[$field]));
                    }
                    $data['fingerprint'] = hash('sha256', json_encode($identity, JSON_UNESCAPED_UNICODE));
                    $exists = PriceRecord::where('fingerprint', $data['fingerprint'])
                        ->when($id, fn ($query) => $query->where('id', '!=', $id))->exists();
                    if ($exists) {
                        throw ValidationException::withMessages(['rows.'.$index.'.particulars' => __('Row :row already exists. Remove the duplicate or edit the existing record.', ['row' => $index + 1])]);
                    }
                    $record = $id ? self::find($id) : new PriceRecord;
                    $record->fill($data)->save();
                }
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['rows' => __('A matching record was just saved. Refresh and review your rows before trying again.')]);
        }
        Log::info('price-monitoring.saved', ['actor_id' => $actor->id, 'record_id' => $id, 'count' => count($rows)]);
    }

    public static function delete(int $id, User $actor): void
    {
        UserAccounts::authorize($actor, 'delete-data');

        self::find($id)->delete();
        Log::info('price-monitoring.deleted', ['actor_id' => $actor->id, 'record_id' => $id]);
    }

    private static function scaled(string $value, int $places): int
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return (int) $whole * (10 ** $places) + (int) str_pad($fraction, $places, '0');
    }

    private static function decimal(int $value, int $places): string
    {
        $scale = 10 ** $places;

        return intdiv($value, $scale).'.'.str_pad((string) ($value % $scale), $places, '0', STR_PAD_LEFT);
    }
}
