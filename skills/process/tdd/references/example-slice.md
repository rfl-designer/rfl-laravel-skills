# Exemplo end-to-end: slice "membro deixa comentário em projeto"

Esta é uma slice vertical Laravel completa do começo ao fim, mostrando os 4 commits que ela produziria. Use como modelo do tamanho e granularidade que se espera em cada ciclo RED→GREEN.

## Contexto

- **Issue:** #42 — "Members can leave comments on projects"
- **Acceptance criteria:**
  - [ ] Member of project pode adicionar comentário
  - [ ] Não-membro recebe 403
  - [ ] Body vazio é rejeitado com erro de validação
  - [ ] Owner do projeto recebe notificação por e-mail

A feature inteira tem 4 slices vertical (uma por critério). Vou mostrar a **primeira** completa.

---

## Slice 1: "Member of project pode adicionar comentário"

### RED — escreva 1 teste que falha

**Arquivo:** `tests/Feature/ProjectComments/MemberLeavesCommentTest.php`

```php
<?php

use App\Models\{Project, User};
use App\Livewire\ProjectComments\CommentForm;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('lets a project member leave a comment', function () {
    $owner  = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->attach($member);

    Livewire::actingAs($member)
        ->test(CommentForm::class, ['project' => $project])
        ->set('body', 'Looks promising')
        ->call('submit')
        ->assertHasNoErrors();

    expect($project->refresh()->comments)
        ->toHaveCount(1)
        ->first()->body->toBe('Looks promising')
        ->and($project->comments->first()->author)->is($member);
});
```

```bash
vendor/bin/pest --filter=MemberLeavesComment
# FAIL — Project::comments() não existe, CommentForm component não existe
```

### GREEN — código mínimo

**Camada 1 — Migration + Model**

```bash
php artisan make:migration create_comments_table
```

```php
// database/migrations/<ts>_create_comments_table.php
public function up(): void
{
    Schema::create('comments', function (Blueprint $table) {
        $table->id();
        $table->foreignId('project_id')->constrained()->cascadeOnDelete();
        $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
        $table->text('body');
        $table->timestamps();
    });
}
```

```php
// app/Models/Comment.php
class Comment extends Model
{
    protected $fillable = ['project_id', 'author_id', 'body'];

    public function project() { return $this->belongsTo(Project::class); }
    public function author()  { return $this->belongsTo(User::class, 'author_id'); }
}

// app/Models/Project.php  (adicionar)
public function comments() { return $this->hasMany(Comment::class); }
public function members()  { return $this->belongsToMany(User::class, 'project_members'); }
```

**Camada 2 — Action + Form Request + Policy**

```php
// app/Http/Requests/Comments/StoreCommentRequest.php
class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('comment', $this->route('project'));
    }

    public function rules(): array
    {
        return ['body' => ['required', 'string', 'min:1', 'max:2000']];
    }
}

// app/Policies/ProjectPolicy.php  (adicionar)
public function comment(User $user, Project $project): bool
{
    return $project->owner_id === $user->id
        || $project->members()->whereKey($user->id)->exists();
}

// app/Actions/Comments/AddCommentToProject.php
class AddCommentToProject
{
    public function __invoke(Project $project, User $author, string $body): Comment
    {
        return $project->comments()->create([
            'author_id' => $author->id,
            'body'      => $body,
        ]);
    }
}
```

**Camada 3 — Livewire/Volt + Flux**

```php
// app/Livewire/ProjectComments/CommentForm.php
class CommentForm extends Component
{
    public Project $project;
    public string  $body = '';

    public function submit(AddCommentToProject $action)
    {
        $this->authorize('comment', $this->project);
        $data = $this->validate(['body' => ['required', 'string', 'min:1', 'max:2000']]);
        $action($this->project, auth()->user(), $data['body']);
        $this->reset('body');
    }

    public function render() { return view('livewire.project-comments.comment-form'); }
}
```

```blade
{{-- resources/views/livewire/project-comments/comment-form.blade.php --}}
<form wire:submit="submit">
    <flux:field>
        <flux:label>Comment</flux:label>
        <flux:textarea wire:model="body" rows="3" />
        <flux:error name="body" />
    </flux:field>
    <flux:button type="submit" variant="primary">Post comment</flux:button>
</form>
```

**Camada 4 — Pest test** já está escrito (RED).

```bash
vendor/bin/pest --filter=MemberLeavesComment
# PASS
```

### Refactor (opcional, ainda em GREEN)

Olhando a slice: nada chamando atenção. `AddCommentToProject` é simples, Policy bem nomeada. **Pula refactor.** Em outra slice talvez extraísse `CommentForm::submit` se ficasse maior.

### Commit — fim de ciclo

```bash
vendor/bin/pest --filter=MemberLeavesComment   # ✓ PASS
vendor/bin/pint                                 # estilo aplicado

git add database/migrations/*_create_comments_table.php \
        app/Models/Comment.php \
        app/Models/Project.php \
        app/Http/Requests/Comments/StoreCommentRequest.php \
        app/Policies/ProjectPolicy.php \
        app/Actions/Comments/AddCommentToProject.php \
        app/Livewire/ProjectComments/CommentForm.php \
        resources/views/livewire/project-comments/comment-form.blade.php \
        tests/Feature/ProjectComments/MemberLeavesCommentTest.php

git commit -m "feat(comments): allow project member to leave comment"
```

**Um commit. Uma slice. Um critério da issue entregue.**

---

## Próximas slices (esboço)

Cada uma é seu próprio ciclo RED→GREEN→commit:

| Slice | Teste (`it(...)`)                                        | Commit                                              |
|-------|----------------------------------------------------------|-----------------------------------------------------|
| 2     | `it('returns 403 when non-member tries to comment')`    | `feat(comments): reject comment from non-member`    |
| 3     | `it('rejects empty body with validation error')`        | `feat(comments): validate comment body`             |
| 4     | `it('emails the project owner when comment is added')`  | `feat(comments): notify project owner by email`     |

Quando todas as 4 slices estiverem prontas (4 commits `feat(comments):...`), invoque `/open-pr`. O título derivado será `feat(comments): allow project member to leave comment` — composto pelo primeiro commit `feat:` da série, e o body do PR linka todos os 4 commits aos 4 critérios da issue #42.

## O que esta slice ensina

1. **Vertical** — atravessa migration/model/policy/livewire/test num único ciclo
2. **Mínima** — não construiu suporte pra "editar comentário", "deletar", "responder" — isso são outras slices, outras issues
3. **Comportamento, não estrutura** — teste descreve "deixar comentário", não "chamar `Project::comments()->create()`"
4. **Verificada pela interface** — `$project->refresh()->comments` em vez de `DB::table('comments')->count()`
5. **Commit captura UMA slice** — granularidade que `/open-pr` e `/review-pr` conseguem mapear de volta a 1 critério da issue
