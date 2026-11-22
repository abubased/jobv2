<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Application;
use App\Models\JobApplication;
use App\Models\LalcoinApplication;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class AllApplicationsSchemaTest extends TestCase
{
    public function test_all_applications_has_the_universal_columns_and_indexes(): void
    {
        $this->assertTrue(Schema::hasTable('all_applications'));

        $this->assertSame([
            'id',
            'form_slug',
            'form_name',
            'name',
            'phone',
            'address',
            'form_data',
            'payment_method',
            'payment_reference',
            'payment_url',
            'payment_status',
            'payment_amount',
            'merchant_name',
            'submitted',
            'created_at',
            'updated_at',
        ], Schema::getColumnListing('all_applications'));

        $indexes = collect(Schema::getIndexes('all_applications'));

        foreach (['form_slug', 'phone', 'payment_reference', 'payment_status'] as $column) {
            $index = $indexes->first(fn (array $index): bool => $index['columns'] === [$column] && ! $index['primary']);

            $this->assertNotNull($index, "Missing index on {$column}.");
            $this->assertFalse($index['unique'], "Index on {$column} must allow duplicates.");
        }
    }

    public function test_form_data_casts_to_array_and_payment_amount_keeps_two_decimals(): void
    {
        $lalcoin = Application::create([
            'form_slug' => 'lalcoin',
            'form_name' => 'লাল কয়েন',
            'name' => 'Karim',
            'phone' => '01711111111',
            'address' => 'Dhaka',
            'form_data' => [
                'coin_count' => '100',
                'coin_year' => '2026',
            ],
            'payment_amount' => '99.00',
            'merchant_name' => 'লাল কয়েন',
        ]);

        $job = Application::create([
            'form_slug' => 'job',
            'form_name' => 'Job Application',
            'name' => 'Karim',
            'phone' => '01711111111',
            'address' => 'Dhaka',
            'form_data' => [
                'father_or_husband' => 'Father Name',
                'mother' => 'Mother Name',
                'education' => 'bachelor',
                'courier_help' => 'yes',
                'nearest_courier' => '99',
            ],
            'payment_amount' => '99.5',
        ]);

        $shohojCash = Application::create([
            'form_slug' => 'shohojcash',
            'form_name' => 'সহজ ক্যাশ',
            'name' => 'Rahim',
            'phone' => '01822222222',
            'address' => 'Road 10, Uttara, Dhaka',
            'form_data' => [
                'fatherName' => 'Father Name',
                'motherName' => 'Mother Name',
                'dob' => '1995-01-01',
                'nid' => '1234567890',
                'division' => 'Dhaka',
                'district' => 'Dhaka',
                'thana' => 'Uttara',
                'road' => 'Road 10',
                'loanType' => 'ব্যক্তিগত ঋণ',
                'loanAmount' => 50000,
                'duration' => 6,
                'source' => 'shohojcash.online',
            ],
            'payment_amount' => '20',
            'merchant_name' => 'সহজ ক্যাশ',
        ]);

        $lalcoin->refresh();
        $job->refresh();
        $shohojCash->refresh();

        $this->assertSame(['coin_count' => '100', 'coin_year' => '2026'], $lalcoin->form_data);
        $this->assertSame('99', $job->form_data['nearest_courier']);
        $this->assertSame('shohojcash.online', $shohojCash->form_data['source']);
        $this->assertSame(50000, $shohojCash->form_data['loanAmount']);
        $this->assertSame('99.00', $lalcoin->payment_amount);
        $this->assertSame('99.50', $job->payment_amount);
        $this->assertSame('20.00', $shohojCash->payment_amount);
        $this->assertTrue($lalcoin->submitted);
        $this->assertNull($lalcoin->payment_method);
        $this->assertNull($lalcoin->payment_reference);
        $this->assertNull($lalcoin->payment_url);
        $this->assertNull($lalcoin->payment_status);
    }

    public function test_duplicate_phone_numbers_are_allowed(): void
    {
        $payload = [
            'form_slug' => 'job',
            'form_name' => 'Job Application',
            'name' => 'Same Person',
            'phone' => '01700000001',
            'address' => 'Dhaka',
            'form_data' => ['education' => 'primary'],
        ];

        Application::create($payload);
        Application::create($payload);

        $this->assertSame(2, Application::query()->where('phone', '01700000001')->count());
    }

    public function test_name_phone_and_address_are_required(): void
    {
        $this->expectException(QueryException::class);

        Application::create([
            'form_slug' => 'lalcoin',
            'form_name' => 'লাল কয়েন',
            'name' => null,
            'phone' => '01711111111',
            'address' => 'Dhaka',
        ]);
    }

    public function test_scopes_filter_by_form_and_payment_status(): void
    {
        Application::create([
            'form_slug' => 'lalcoin',
            'form_name' => 'লাল কয়েন',
            'name' => 'A',
            'phone' => '01711111111',
            'address' => 'Dhaka',
            'payment_status' => 'initiated',
            'submitted' => true,
        ]);

        Application::create([
            'form_slug' => 'job',
            'form_name' => 'Job Application',
            'name' => 'B',
            'phone' => '01822222222',
            'address' => 'Dhaka',
            'payment_status' => 'due',
            'submitted' => false,
        ]);

        $this->assertSame(1, Application::forForm('lalcoin')->count());
        $this->assertSame(1, Application::submitted()->count());
        $this->assertSame(1, Application::paymentStatus('initiated')->count());
    }

    public function test_legacy_application_tables_stay_unchanged(): void
    {
        $jobColumns = Schema::getColumnListing('job_applications');
        $lalcoinColumns = Schema::getColumnListing('lalcoin_applications');

        Application::create([
            'form_slug' => 'shohojcash',
            'form_name' => 'সহজ ক্যাশ',
            'name' => 'Rahim',
            'phone' => '01933333333',
            'address' => 'Uttara',
            'form_data' => ['source' => 'shohojcash.online'],
        ]);

        $this->assertSame($jobColumns, Schema::getColumnListing('job_applications'));
        $this->assertSame($lalcoinColumns, Schema::getColumnListing('lalcoin_applications'));
        $this->assertSame(0, JobApplication::query()->count());
        $this->assertSame(0, LalcoinApplication::query()->count());
        $this->assertSame(1, Application::query()->count());

        $this->assertContains('payment_number', $jobColumns);
        $this->assertContains('meta', $lalcoinColumns);
        $this->assertNotContains('form_slug', $jobColumns);
        $this->assertNotContains('form_slug', $lalcoinColumns);
        $this->assertNotContains('form_data', $lalcoinColumns);

        $migration = file_get_contents(database_path('migrations/2026_11_22_180000_create_all_applications_table.php'));
        $this->assertIsString($migration);
        $this->assertStringNotContainsString('lalcoin_applications', $migration);
        $this->assertStringNotContainsString('job_applications', $migration);
    }
}
