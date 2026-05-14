# Desenhando interfaces para testabilidade

Boas interfaces tornam o teste natural. As 3 regras abaixo cobrem 80% dos casos.

## 1. Aceite dependências, não as crie

```php
// Testável — gateway é injetado
class ProcessPayment
{
    public function __construct(private PaymentGateway $gateway) {}

    public function __invoke(Order $order): Charge
    {
        return $this->gateway->charge($order->total);
    }
}

// Difícil de testar — instância concreta hardcoded mid-method
class ProcessPayment
{
    public function __invoke(Order $order): Charge
    {
        $gateway = new StripeGateway(config('services.stripe.key'));
        return $gateway->charge($order->total);
    }
}
```

No segundo caso você precisa de `Http::fake()` ou substituir o binding no container — funciona, mas adiciona indireção. Prefira o primeiro.

## 2. Retorne resultados, não produza side effects

```php
// Testável — resultado é o retorno
function calculateDiscount(Cart $cart): Discount { ... }

// Difícil — muta o objeto sem retorno
function applyDiscount(Cart $cart): void
{
    $cart->total -= $discount;
}
```

Funções que retornam valor são triviais de testar com `expect(...)`. Side effects exigem inspecionar estado depois — mais ruído, mais acoplamento.

## 3. Superfície pequena

- Menos métodos públicos = menos testes pra cobrir
- Menos parâmetros = setup de teste mais simples
- Menos opções = menos combinações pra cobrir

Veja [deep-modules.md](deep-modules.md) — esses três pontos são consequências naturais de módulos profundos.

## Em Laravel

- **Form Request** > validação inline: superfície de teste fica em `Request::rules()` e `Request::authorize()` separadamente
- **Action invocável** (`__invoke`) > Service com vários métodos públicos: uma entrada, uma saída
- **Policy** > `if (auth()->user()->id === ...)`: encapsula a regra em método público nomeado, testável isoladamente
