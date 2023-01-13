<?php

namespace Tests\Feature\FinancialOperations;

use App\Models\Account;
use App\Models\FinancialOperation;
use App\Models\Lending;
use App\Models\OperationType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CreateOperationTest extends TestCase
{
    use DatabaseTransactions;

    private Model $user, $account, $type, $lendingType, $repaymentType;
    private array $headers;

    public function setUp(): void
    {
        parent::setUp();

        $this->user = User::firstOrCreate(['email' => 'a@b.c']);
        $this->account = Account::factory()->create(['user_id' => $this->user]);
        $this->type = OperationType::firstOrCreate(['name' => 'type', 'lending' => false]);
        $this->lendingType = OperationType::firstOrCreate(['name' => 'lending', 'lending' => true]);
        $this->repaymentType = OperationType::firstOrCreate(['name' => 'repayment', 'repayment' => true]);

        $this->headers = [
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ];

    }

    public function test_create_operation_form_data(){
        /*$op = FinancialOperation::factory()
                    ->create([
                        'account_id' => $this->account,
                        'operation_type_id' => $this->lendingType
                    ]);
        Lending::factory()->create(['id' => $op]);

        $exp = [$op->id];
*/
        $response = $this->actingAs($this->user)
                            ->withHeaders($this->headers)
                            ->get(
                                '/accounts/' . $this->account->id
                                . '/operations/create'
                            );

        $response->assertStatus(200);
        $response
            ->assertJsonPath('operation_types', OperationType::where('repayment', '=', false)->get()->toArray());

        /*foreach ($response['unrepaid_lendings'] as $lending) {
            in_array($lending['id'], $exp);
        }*/
    }

    public function test_create_operation(){

        $operationData = [
            'title' => 'test',
            'date' => now()->format('Y-m-d'),
            'operation_type_id' => $this->type->id,
            'subject' => 'test',
            'sum' => 100,
            'attachment' => null
        ];

        $response = $this->actingAs($this->user)->withHeaders($this->headers)
            ->post('/accounts/' . $this->account->id . '/operations', $operationData);

        $response->assertStatus(201);
        $this->assertDatabaseHas('financial_operations', $operationData);

    }

    public function test_cannot_create_operation_with_negative_sum(){

        $operationData = [
            'title' => 'test',
            'date' => now()->format('Y-m-d'),
            'operation_type_id' => $this->type->id,
            'subject' => 'test',
            'sum' => -100,
            'attachment' => null
        ];

        $response = $this->actingAs($this->user)->withHeaders($this->headers)
            ->post('/accounts/' . $this->account->id . '/operations', $operationData);

        $response->assertStatus(422);

    }

    public function test_create_operation_with_lending(){

        $operationData = [
            'title' => 'test',
            'date' => now()->format('Y-m-d'),
            'operation_type_id' => $this->lendingType->id,
            'subject' => 'test',
            'sum' => 100,
            'attachment' => null
        ];

        $lendingData = [
            'expected_date_of_return' => now()->addDays(1)->format('Y-m-d'),
            'previous_lending_id' => null
        ];

        $response = $this->actingAs($this->user)->withHeaders($this->headers)
            ->post('/accounts/' . $this->account->id . '/operations', array_merge($operationData, $lendingData));

        $response->assertStatus(201);
        $this->assertDatabaseHas('financial_operations', $operationData);
        $this->assertDatabaseHas('lendings', $lendingData);

    }

    public function test_create_repayment(){

        $loan = FinancialOperation::factory()->create(
            [
                'account_id' => $this->account,
                'operation_type_id' => $this->lendingType
            ]);
        $lending = Lending::factory()->create(['id' => $loan]);

        $operationData = [
            'date' => now()->format('Y-m-d')
        ];

        $response = $this->actingAs($this->user)->withHeaders($this->headers)
            ->post('/operations/' . $lending->id . '/repayment', $operationData);
        //dd($response->content());
        $response->assertStatus(201);

        $this->assertDatabaseHas('lendings', ['previous_lending_id' => $lending->id]);

    }

    public function test_cannot_repay_nonlending_operation(){

        $operation = FinancialOperation::factory()->create(
            [
                'account_id' => $this->account,
                'operation_type_id' => $this->lendingType
            ]);

        $postData = [
            'date' => now()->format('Y-m-d')
        ];

        $response = $this->actingAs($this->user)->withHeaders($this->headers)
            ->post('/operations/' . $operation->id . '/repayment', $postData);
        $response->assertStatus(404);

    }

    public function test_cannot_repay_repayment(){

        $repaymentOperation = FinancialOperation::factory()->create(
            [
                'account_id' => $this->account,
                'operation_type_id' => $this->repaymentType
            ]);
        $repayment = Lending::factory()->create(['id' => $repaymentOperation]);

        $postData = [
            'date' => now()->format('Y-m-d')
        ];

        $response = $this->actingAs($this->user)->withHeaders($this->headers)
            ->post('/operations/' . $repayment->id . '/repayment', $postData);
        $response->assertStatus(500);

    }

    public function test_cannot_repay_loan_twice()
    {
        $loan = FinancialOperation::factory()->create(
            [
                'account_id' => $this->account,
                'operation_type_id' => $this->lendingType
            ]);
        $lending = Lending::factory()->create(['id' => $loan]);

        $operationData = [
            'date' => now()->format('Y-m-d')
        ];

        $response = $this->actingAs($this->user)->withHeaders($this->headers)
            ->post('/operations/' . $lending->id . '/repayment', $operationData);

        $response->assertStatus(201);
        $this->assertDatabaseHas('lendings', ['previous_lending_id' => $lending->id]);

        $operationData = [
            'date' => now()->addDay()
        ];

        $response = $this->actingAs($this->user)->withHeaders($this->headers)
            ->post('/operations/' . $lending->id . '/repayment', $operationData);

        $response->assertStatus(500);
        $repaymentsCount = Lending::where('previous_lending_id', '=', $lending->id)->get()->count();

        $this->assertEquals(1, $repaymentsCount);
    }

    public function test_create_operation_with_lending_cannot_be_repayed_before_provided(){

        $loan = FinancialOperation::factory()->create(
            [
                'account_id' => $this->account,
                'operation_type_id' => $this->lendingType
            ]);
        $lending = Lending::factory()->create(['id' => $loan]);

        $postData = [
            'date' => $loan->date->subDays(1)
        ];

        $response = $this->actingAs($this->user)->withHeaders($this->headers)
            ->post('/operations/' . $lending->id . '/repayment', $postData);

        $response->assertStatus(422)
            ->assertJsonPath(
                'errors.date.0',
                trans('validation.repayment_invalid_date')
            );
    }

    public function test_create_operation_with_file(){

        Storage::fake('local');

        $postData = [
            'title' => 'test_with_file',
            'date' => '2022-12-24',
            'operation_type_id' => $this->type->id,
            'subject' => 'test',
            'sum' => 100,
            'attachment' => UploadedFile::fake()->create('test.pdf')
        ];

        $response = $this->actingAs($this->user)->withHeaders($this->headers)
            ->post('/accounts/' . $this->account->id . '/operations', $postData);

        $response->assertStatus(201);
        $path = FinancialOperation::firstWhere('title', 'test_with_file')->attachment;
        Storage::disk('local')->assertExists($path);

        Storage::fake('local');
    }
}
