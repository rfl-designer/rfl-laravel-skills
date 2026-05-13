# Bons e maus testes (Pest + Laravel)

## Bons testes

**Integration-style**: testam pela interface real, não por mocks de partes internas.

```php
// BOM: testa comportamento observável
use function Pest\Laravel\actingAs;

it('lets a user check out with a valid cart', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 1990]);

    $cart = (new AddToCart)($user, $product, quantity: 2);
    $order = (new Checkout)($cart, paymentMethod: 'pix');

    expect($order->status)->toBe(OrderStatus::Confirmed);
    expect($order->total)->toBe(3980);
});
```

Características:

- Testa comportamento que usuários/callers se importam
- Usa só API pública (Action, Model, Livewire component)
- Sobrevive a refactor interno
- Descreve **O QUE**, não **COMO**
- Uma asserção lógica por teste

### Bom teste de Livewire

```php
use Livewire\Livewire;

it('adds a comment to the project thread', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')->create();

    Livewire::actingAs($user)
        ->test(ProjectCommentThread::class, ['project' => $project])
        ->set('body', 'Looks good to me')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('body', '');

    expect($project->refresh()->comments)->toHaveCount(1);
    expect($project->comments->first()->body)->toBe('Looks good to me');
});
```

Verifica pelo estado do componente (`assertSet`) e pelo modelo (`->refresh()`), não asserts contra HTML cru.

### Bom teste de Form Request

```php
it('rejects empty comment body', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')->create();

    actingAs($user)
        ->post(route('projects.comments.store', $project), ['body' => ''])
        ->assertSessionHasErrors('body');
});
```

## Maus testes

**Implementation-detail tests**: acoplados à estrutura interna.

```php
// RUIM: testa detalhes de implementação
it('checkout calls PaymentService::process', function () {
    $mock = $this->mock(PaymentService::class);
    $mock->shouldReceive('process')
        ->once()
        ->with($cart->total);

    (new Checkout)($cart, 'pix');
});
```

Sinais vermelhos:

- Mockar colaboradores internos (suas próprias classes)
- Testar métodos privados (via reflection ou expondo só pra teste)
- Asserções em quantidade/ordem de chamadas
- Teste quebra quando refatora sem mudança de comportamento
- Nome do teste descreve COMO, não O QUE
- Verificar por meios externos em vez da interface

```php
// RUIM: pula a interface pra verificar
it('createUser saves to database', function () {
    (new CreateUser)(['name' => 'Alice']);

    $row = DB::table('users')->where('name', 'Alice')->first();
    expect($row)->not->toBeNull();
});

// BOM: verifica pela interface
it('createUser makes user retrievable', function () {
    $user = (new CreateUser)(['name' => 'Alice']);

    $retrieved = User::find($user->id);
    expect($retrieved->name)->toBe('Alice');
});
```

### Anti-pattern Livewire: assert contra HTML cru

```php
// RUIM: acoplado ao markup, quebra ao trocar Flux por Tailwind cru
Livewire::test(ProjectList::class)
    ->assertSeeHtml('<div class="card">My Project</div>');

// BOM: verifica o estado/dados que o componente expõe
Livewire::test(ProjectList::class)
    ->assertSee('My Project')
    ->assertViewHas('projects', fn ($projects) => $projects->contains('name', 'My Project'));
```

## Padrões úteis do Pest

### Datasets para casos paramétricos

```php
it('rejects invalid email formats', function (string $email) {
    actingAs(User::factory()->create())
        ->post(route('profile.update'), ['email' => $email])
        ->assertSessionHasErrors('email');
})->with([
    'sem arroba' => 'aliceexample.com',
    'sem domínio' => 'alice@',
    'só espaço' => '   ',
]);
```

Use datasets quando o **comportamento é o mesmo** mas a entrada varia. Não use para esconder N testes diferentes.

### Higher-order tests

```php
it('exposes computed properties')
    ->expect(fn () => Livewire::test(ProjectDashboard::class))
    ->assertSet('totalProjects', 0);
```

Use com moderação — perde legibilidade rápido. Prefira a forma `function () { … }` quando o setup tem mais de uma linha.

### `beforeEach` para setup compartilhado

```php
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->for($this->user, 'owner')->create();
});

it('lets the owner edit the project', function () {
    actingAs($this->user)
        ->put(route('projects.update', $this->project), ['name' => 'New name'])
        ->assertRedirect();

    expect($this->project->refresh()->name)->toBe('New name');
});
```

Mantenha `beforeEach` curto. Se ele cresce, talvez você esteja testando coisas diferentes no mesmo arquivo — divida.
