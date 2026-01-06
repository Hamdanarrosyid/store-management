<?php

namespace App\Observers;

use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StoreObserver
{
    /**
     * Handle the Store "created" event.
     */
    public function created(Store $store): void
    {
        DB::transaction(function () use ($store) {
            $adminRoleId = DB::table('roles')->where('name', 'ADMIN')->value('id');
            $cashierRoleId = DB::table('roles')->where('name', 'CASHIER')->value('id');

            if (! $adminRoleId || ! $cashierRoleId) {
                throw new \RuntimeException('Role ADMIN/CASHIER is not found. Please seed the roles table first.');
            }

            $defaultPassword = config('app.store_default_user_password', 'Password123!');

            $adminEmail = $this->uniqueEmail("admin.{$store->code}@store.com");
            $cashierEmail = $this->uniqueEmail("cashier.{$store->code}@store.com");

            User::create([
                'store_id' => $store->id,
                'role_id' => $adminRoleId,
                'name' => "Admin {$store->name}",
                'email' => $adminEmail,
                'password' => bcrypt($defaultPassword),
                'is_active' => true,
            ]);

            User::create([
                'store_id' => $store->id,
                'role_id' => $cashierRoleId,
                'name' => "Kasir {$store->name}",
                'email' => $cashierEmail,
                'password' => bcrypt($defaultPassword),
                'is_active' => true,
            ]);
        });
    }

     private function uniqueEmail(string $baseEmail): string
    {
        $email = $baseEmail;

        if (!DB::table('users')->where('email', $email)->exists()) {
            return $email;
        }

        // fallback: tambahkan suffix random jika email sudah ada
        $parts = explode('@', $baseEmail, 2);
        $local = $parts[0] ?? 'user';
        $domain = $parts[1] ?? 'store.local';

        do {
            $email = $local . '+' . Str::lower(Str::random(6)) . '@' . $domain;
        } while (DB::table('users')->where('email', $email)->exists());

        return $email;
    }

    /**
     * Handle the Store "updated" event.
     */
    public function updated(Store $store): void
    {
        //
    }

    /**
     * Handle the Store "deleted" event.
     */
    public function deleted(Store $store): void
    {
        //
    }

    /**
     * Handle the Store "restored" event.
     */
    public function restored(Store $store): void
    {
        //
    }

    /**
     * Handle the Store "force deleted" event.
     */
    public function forceDeleted(Store $store): void
    {
        //
    }
}
