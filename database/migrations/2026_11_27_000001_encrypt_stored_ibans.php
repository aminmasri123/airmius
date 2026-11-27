<?php

use App\Support\EncryptedIbanValue;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = [
        'clubs' => ['sepa_iban' => 40],
        'club_user' => ['sepa_iban' => 40],
        'club_external_members' => ['sepa_iban' => 40],
        'club_money_accounts' => ['iban' => 34],
        'club_business_partners' => ['iban' => 80],
        'bank_transactions' => ['debtor_iban' => 40, 'raw_data' => null],
        'payout_profiles' => ['iban' => 255],
        'club_membership_requests' => ['application_data' => null],
        'club_master_data_change_requests' => [
            'proposed_values' => null, 'base_values' => null, 'conflicts' => null,
        ],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            // SQLite does not enforce varchar lengths. Rebuilding the referenced
            // clubs table inside a transaction can cascade-delete child rows.
            if (DB::getDriverName() !== 'sqlite') {
                Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                    foreach ($columns as $column => $length) {
                        if ($length === null) {
                            $blueprint->longText($column)->nullable()->change();
                        } else {
                            $blueprint->text($column)->nullable()->change();
                        }
                    }
                });
            }

            foreach (array_keys($columns) as $column) {
                DB::table($table)->whereNotNull($column)->orderBy('id')
                    ->chunkById(200, function ($rows) use ($table, $column): void {
                        foreach ($rows as $row) {
                            $value = $row->{$column};
                            if ($value === '' || EncryptedIbanValue::isEncrypted($value)) {
                                continue;
                            }
                            DB::table($table)->where('id', $row->id)->update([
                                $column => EncryptedIbanValue::encrypt($value),
                            ]);
                        }
                    });
            }
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (array_keys($columns) as $column) {
                DB::table($table)->whereNotNull($column)->orderBy('id')
                    ->chunkById(200, function ($rows) use ($table, $column): void {
                        foreach ($rows as $row) {
                            if (EncryptedIbanValue::isEncrypted($row->{$column})) {
                                DB::table($table)->where('id', $row->id)->update([
                                    $column => EncryptedIbanValue::decrypt($row->{$column}),
                                ]);
                            }
                        }
                    });
            }

            if (DB::getDriverName() !== 'sqlite') {
                Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                    foreach ($columns as $column => $length) {
                        if ($length === null) {
                            $blueprint->json($column)->nullable()->change();
                        } else {
                            $blueprint->string($column, $length)->nullable()->change();
                        }
                    }
                });
            }
        }
    }
};
