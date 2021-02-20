<?php

namespace Tests\Unit\Services\Account\Company;

use App\Jobs\AuditLog\LogAccountAudit;
use App\Models\Account\Account;
use App\Models\Account\Company;
use App\Models\User\User;
use App\Services\Account\Company\CreateCompany;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CreateCompanyTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_stores_a_company()
    {
        Queue::fake();

        $account = factory(Account::class)->create([]);
        $user = factory(User::class)->create([
            'account_id' => $account->id,
        ]);

        $request = [
            'account_id' => $account->id,
            'author_id' => $user->id,
            'name' => 'central perk',
            'website' => 'https://centralperk.com',
            'number_of_employees' => 3,
        ];

        $company = app(CreateCompany::class)->execute($request);

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'account_id' => $account->id,
            'name' => 'central perk',
            'website' => 'https://centralperk.com',
            'number_of_employees' => 3,
        ]);

        $this->assertInstanceOf(
            Company::class,
            $company
        );

        Queue::assertPushed(LogAccountAudit::class, function ($job) use ($user) {
            return $job->auditLog['action'] === 'company_created' &&
                $job->auditLog['author_id'] === $user->id &&
                $job->auditLog['about_contact_id'] === null &&
                $job->auditLog['should_appear_on_dashboard'] === true &&
                $job->auditLog['objects'] === json_encode([
                    'name' => 'central perk',
                ]);
        });
    }

    /** @test */
    public function it_doesnt_store_a_company_if_a_company_with_this_name_already_exists()
    {
        Queue::fake();

        $account = factory(Account::class)->create([]);
        $user = factory(User::class)->create([
            'account_id' => $account->id,
        ]);
        $company = factory(Company::class)->create([
            'account_id' => $account->id,
            'name' => 'Lawyers Associate',
        ]);

        $request = [
            'account_id' => $account->id,
            'author_id' => $user->id,
            'name' => 'Lawyers Associate',
        ];

        $company = app(CreateCompany::class)->execute($request);

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'account_id' => $account->id,
            'name' => 'Lawyers Associate',
        ]);

        $this->assertInstanceOf(
            Company::class,
            $company
        );

        Queue::assertNotPushed(LogAccountAudit::class);
    }

    /** @test */
    public function it_fails_if_wrong_parameters_are_given()
    {
        $account = factory(Account::class)->create([]);

        $request = [
            'street' => '199 Lafayette Street',
        ];

        $this->expectException(ValidationException::class);
        app(CreateCompany::class)->execute($request);
    }
}
