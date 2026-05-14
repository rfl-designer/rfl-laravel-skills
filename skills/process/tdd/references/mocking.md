# Quando mockar (Laravel + Pest)

Mocke apenas em **fronteiras de sistema**:

- APIs externas (gateway de pagamento, e-mail provider, webhooks de terceiros)
- Tempo e aleatoriedade
- Filesystem (às vezes — `Storage::fake()` é melhor que mock cru)

**Não mocke:**

- Suas próprias classes/módulos (Models, Actions, Services do app)
- Banco de dados — use `RefreshDatabase` + factories
- Componentes Livewire que você está testando
- O próprio Eloquent

## Helpers nativos do Laravel — prefira a mockaria manual

Laravel já vem com fakes de primeira classe. Use-os em vez de `Mockery::mock(...)`.

### `Mail::fake()`

```php
use Illuminate\Support\Facades\Mail;

it('emails the project owner when a comment is added', function () {
    Mail::fake();

    $project = Project::factory()->create();
    $user = User::factory()->create();

    (new AddComment)($project, $user, 'Looks good');

    Mail::assertSent(NewCommentNotification::class, function ($mail) use ($project) {
        return $mail->hasTo($project->owner->email);
    });
});
```

### `Queue::fake()` / `Bus::fake()`

```php
use Illuminate\Support\Facades\Queue;

it('queues the report generation job', function () {
    Queue::fake();

    (new ScheduleMonthlyReport)($tenant);

    Queue::assertPushed(GenerateMonthlyReportJob::class, function ($job) use ($tenant) {
        return $job->tenantId === $tenant->id;
    });
});
```

`Bus::fake()` para chains/batches; `Queue::fake()` para single jobs.

### `Event::fake()`

```php
use Illuminate\Support\Facades\Event;

it('fires ProjectArchived when archiving', function () {
    Event::fake();

    (new ArchiveProject)($project);

    Event::assertDispatched(ProjectArchived::class, fn ($e) => $e->project->is($project));
});
```

**Cuidado:** `Event::fake()` impede listeners reais de rodarem. Se sua slice depende do listener (ex.: listener manda e-mail), use `Event::fake([SpecificEvent::class])` para fakear só o que você quer assertar.

### `Http::fake()`

```php
use Illuminate\Support\Facades\Http;

it('fetches CEP from ViaCEP', function () {
    Http::fake([
        'viacep.com.br/*' => Http::response([
            'logradouro' => 'Av. Paulista',
            'localidade' => 'São Paulo',
            'uf' => 'SP',
        ]),
    ]);

    $address = (new FetchAddressByCep)('01310-100');

    expect($address->city)->toBe('São Paulo');
    Http::assertSent(fn ($req) => $req->url() === 'https://viacep.com.br/ws/01310-100/json/');
});
```

**Sempre** mocke chamadas HTTP em testes — depender de rede deixa a suite frágil.

### `Storage::fake()`

```php
use Illuminate\Support\Facades\Storage;

it('stores the uploaded receipt', function () {
    Storage::fake('receipts');

    Livewire::test(ReceiptUploader::class)
        ->set('file', UploadedFile::fake()->image('receipt.jpg'))
        ->call('save');

    Storage::disk('receipts')->assertExists('receipts/receipt.jpg');
});
```

### `Notification::fake()`

```php
use Illuminate\Support\Facades\Notification;

it('notifies admins of failed payment', function () {
    Notification::fake();
    $admins = User::factory()->count(2)->admin()->create();

    (new HandleFailedPayment)($charge);

    Notification::assertSentTo($admins, PaymentFailedNotification::class);
});
```

## Desenhando para testabilidade

Em fronteiras de sistema, desenhe interfaces fáceis de mockar:

### 1. Use injeção de dependência

Receba dependências externas em vez de criá-las internamente:

```php
// Fácil de testar — gateway é injetado
class ProcessPayment
{
    public function __construct(private PaymentGateway $gateway) {}

    public function __invoke(Order $order): Charge
    {
        return $this->gateway->charge($order->total);
    }
}

// Difícil de testar — instância concreta hardcoded
class ProcessPayment
{
    public function __invoke(Order $order): Charge
    {
        $gateway = new StripeGateway(config('services.stripe.key'));
        return $gateway->charge($order->total);
    }
}
```

No segundo caso você precisa de `Http::fake()` ou substituir no container — funciona, mas adiciona indireção. Prefira o primeiro.

### 2. Bind interface no container, troque no teste

```php
// Production
app()->bind(PaymentGateway::class, StripeGateway::class);

// Test (no setUp ou no próprio teste)
app()->bind(PaymentGateway::class, FakePaymentGateway::class);
```

Combinado com `app()->singleton(...)` te dá a mesma instância em todo o teste — útil pra inspecionar chamadas depois.

### 3. SDK-style sobre genéricos

```php
// BOM: cada método é mockável independentemente
class ViaCepClient
{
    public function findByCep(string $cep): Address { /* … */ }
    public function listCities(string $uf): Collection { /* … */ }
}

// RUIM: um único get() condicional
class GenericHttpClient
{
    public function get(string $endpoint, array $params = []): array { /* … */ }
}
```

Com SDK específico, cada `Http::fake([…])` reflete só um endpoint. Com cliente genérico, o teste vira uma cascata de `if`s no fake.
