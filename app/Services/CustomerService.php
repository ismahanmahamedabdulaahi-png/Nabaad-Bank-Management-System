<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\KycVerification;
use App\Notifications\CustomerInvitation;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    public function __construct(private CustomerRepositoryInterface $repo) {}

    public function list(array $filters = []): LengthAwarePaginator
    {
        return $this->repo->paginate($filters);
    }

    // Customers are onboarded via invitation, same as staff: the account starts
    // unusable (random hash, must_change_password=true) until the customer
    // accepts and is emailed a one-time password — see CustomerInvitationController.
    // `status` stays 'pending' throughout (KYC approval, unrelated to this flow,
    // is still what ultimately allows login).
    public function create(array $data): Customer
    {
        $customerNumber = $this->generateCustomerNumber();

        $customer = DB::transaction(function () use ($data, $customerNumber) {
            $customer = $this->repo->create([
                'customer_number'          => $customerNumber,
                'name'                     => $data['name'],
                'email'                    => $data['email'],
                'phone'                    => $data['phone'],
                'password'                 => Hash::make(Str::random(40)),
                'must_change_password'     => true,
                'gender'                   => $data['gender'] ?? null,
                'nationality'              => $data['nationality'] ?? null,
                'region'                   => $data['region'] ?? null,
                'marital_status'           => $data['marital_status'] ?? null,
                'date_of_birth'            => $data['date_of_birth'] ?? null,
                'occupation'               => $data['occupation'] ?? null,
                'address'                  => $data['address'] ?? null,
                'city'                     => $data['city'] ?? null,
                'next_of_kin_name'         => $data['next_of_kin_name'] ?? null,
                'next_of_kin_phone'        => $data['next_of_kin_phone'] ?? null,
                'next_of_kin_relationship' => $data['next_of_kin_relationship'] ?? null,
                'status'                   => 'pending',
                'email_verified_at'        => now(),
            ]);

            // Store photo and signature (need the customer ID for the path)
            $updates = [];
            if (!empty($data['photo'])) {
                $updates['photo_path'] = $this->storeFile($data['photo'], "customers/{$customer->id}", 'photo');
            }
            if (!empty($data['signature'])) {
                $updates['signature_path'] = $this->storeFile($data['signature'], "customers/{$customer->id}", 'signature');
            }
            if ($updates) {
                $customer->update($updates);
            }

            // Auto-create KYC record so documents can be uploaded immediately
            KycVerification::create([
                'customer_id' => $customer->id,
                'status'      => 'pending',
            ]);

            return $customer;
        });

        $acceptUrl = URL::temporarySignedRoute(
            'customer-invitation.show',
            now()->addDays(7),
            ['customer' => $customer->id]
        );

        $customer->notify(new CustomerInvitation($acceptUrl));

        AuditLog::record('created', 'customers', "Invited customer {$customer->name} ({$customerNumber})", [], [
            'name'            => $customer->name,
            'email'           => $customer->email,
            'customer_number' => $customerNumber,
        ]);

        return $customer;
    }

    public function update(Customer $customer, array $data): Customer
    {
        $old = $customer->only(['name', 'email', 'phone', 'status', 'occupation', 'address']);

        $fields = [
            'name'                     => $data['name'],
            'email'                    => $data['email'],
            'phone'                    => $data['phone'],
            'gender'                   => $data['gender'] ?? null,
            'nationality'              => $data['nationality'] ?? null,
            'region'                   => $data['region'] ?? null,
            'marital_status'           => $data['marital_status'] ?? null,
            'date_of_birth'            => $data['date_of_birth'] ?? null,
            'occupation'               => $data['occupation'] ?? null,
            'address'                  => $data['address'] ?? null,
            'city'                     => $data['city'] ?? null,
            'next_of_kin_name'         => $data['next_of_kin_name'] ?? null,
            'next_of_kin_phone'        => $data['next_of_kin_phone'] ?? null,
            'next_of_kin_relationship' => $data['next_of_kin_relationship'] ?? null,
            'status'                   => $data['status'],
        ];

        if (!empty($data['photo'])) {
            if ($customer->photo_path) Storage::disk('local')->delete($customer->photo_path);
            $fields['photo_path'] = $this->storeFile($data['photo'], "customers/{$customer->id}", 'photo');
        }
        if (!empty($data['signature'])) {
            if ($customer->signature_path) Storage::disk('local')->delete($customer->signature_path);
            $fields['signature_path'] = $this->storeFile($data['signature'], "customers/{$customer->id}", 'signature');
        }

        $customer = $this->repo->update($customer, $fields);

        AuditLog::record('updated', 'customers', "Updated customer {$customer->name} ({$customer->customer_number})", $old, [
            'name'   => $customer->name,
            'email'  => $customer->email,
            'status' => $customer->status,
        ]);

        return $customer;
    }

    public function toggleStatus(Customer $customer): Customer
    {
        $map = [
            'active'      => 'inactive',
            'inactive'    => 'active',
            'blacklisted' => 'active',
            'pending'     => 'inactive',
        ];

        if (!array_key_exists($customer->status, $map)) {
            throw ValidationException::withMessages([
                'status' => "Cannot toggle status for a {$customer->status} customer.",
            ]);
        }

        $old = $customer->status;
        $new = $map[$old];

        $customer->update(['status' => $new]);

        AuditLog::record('status_changed', 'customers', "Changed customer {$customer->name} status from {$old} to {$new}", ['status' => $old], ['status' => $new]);

        return $customer->fresh();
    }

    public function resetPassword(Customer $customer): string
    {
        $temp = 'Nabaad@' . random_int(1000, 9999);
        $customer->update(['password' => Hash::make($temp), 'must_change_password' => true]);

        AuditLog::record('password_reset', 'customers', "Password reset for customer {$customer->name} ({$customer->customer_number})");

        return $temp;
    }

    public function delete(Customer $customer): void
    {
        if ($customer->accounts()->where('status', '!=', 'closed')->exists()) {
            throw ValidationException::withMessages([
                'customer' => 'Cannot delete a customer with active accounts. Close all accounts first.',
            ]);
        }

        if ($customer->hasActiveLoan()) {
            throw ValidationException::withMessages([
                'customer' => 'Cannot delete a customer with an active loan.',
            ]);
        }

        AuditLog::record('deleted', 'customers', "Deleted customer {$customer->name} ({$customer->customer_number})");

        foreach (['photo_path', 'signature_path'] as $col) {
            if ($customer->$col) Storage::disk('local')->delete($customer->$col);
        }

        $this->repo->delete($customer);
    }

    private function storeFile($file, string $directory, string $name): string
    {
        $ext  = $file->getClientOriginalExtension();
        $path = "{$directory}/{$name}.{$ext}";
        Storage::disk('local')->put($path, file_get_contents($file));
        return $path;
    }

    private function generateCustomerNumber(): string
    {
        return DB::transaction(function () {
            $setting = DB::table('settings')
                ->where('key', 'customer_number_sequence')
                ->lockForUpdate()
                ->first();

            if (!$setting) {
                $next = 1;
                DB::table('settings')->insert([
                    'key'        => 'customer_number_sequence',
                    'value'      => '1',
                    'group'      => 'system',
                    'label'      => 'Customer Number Sequence',
                    'type'       => 'integer',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $next = (int) $setting->value + 1;
                DB::table('settings')->where('key', 'customer_number_sequence')->update([
                    'value'      => (string) $next,
                    'updated_at' => now(),
                ]);
            }

            return 'GAR-C-' . str_pad($next, 5, '0', STR_PAD_LEFT);
        });
    }
}
