# DTO Mocker

Gerador leve de mocks para classes DTO/Inputs/Outputs em PHP. Gera instâncias e arquivos de
fixtures para uso em testes e desenvolvimento.

Compatível com qualquer framework PHP (Laravel, Hyperf, Symfony etc.).

## Instalação (desenvolvimento)

Recomendo instalar como dependência de desenvolvimento:

```bash
composer require --dev jeslxdev/dto-mocker
```

> Observação: este repositório já contém um script CLI mínimo para gerar fixtures sem dependências de framework.

## Uso programático

Exemplo simples em código:

```php
use DtoMocker\DtoMocker;

$mocker = new DtoMocker();
$dto = $mocker->make(\App\Dto\UserDto::class);

// $dto é uma instância populada com valores gerados
var_dump($dto);
```

Você pode sobrescrever geradores por tipo, por exemplo:

```php
$mocker->extend('string', fn() => 'valor_fixo');
```

## CLI: gerar fixtures

Inclui um utilitário mínimo para varrer classes e gerar fixtures JSON em `tests/_fixtures`.

Executar via composer script:

```bash
composer dto-mocker:generate
```

Ou direto:

```bash
php bin/generate-mocks.php --path=src --out=tests/_fixtures --count=3
```

Opções suportadas:
- `--path` (padrão: `src`) — diretório a ser escaneado;
- `--out` (padrão: `tests/_fixtures`) — pasta de saída;
- `--count` (padrão: `3`) — quantas instâncias por classe.

Novas opções:
- `--format` (json|php) — formato de saída; `json` por padrão;
- `--include-non-dto` — incluir classes sem sufixo `Dto` (usa detecção por propriedades tipadas);
- `--only` — lista separada por vírgula de classes (ou short names) a gerar, ex.: `--only=UserDto,App\\Dto\\OrderDto`;
- `--max-depth` — profundidade máxima de serialização/recursão (padrão: 3).

Detecção: por padrão o script inclui classes cujo nome termina com `Dto`. Use `--include-non-dto` para
fazer detecção por propriedades tipadas. Use `--only` para restringir a lista a classes específicas.

O que são fixtures
-------------------
Fixtures são conjuntos de dados usados para inicializar o estado de testes ou ambientes de desenvolvimento.
Este projeto gera fixtures em JSON ou PHP (arrays) que podem ser carregados em testes para simular entradas
ou estados sem depender de banco de dados real.

## Exemplos de saída

O script grava arquivos JSON com nome no formato `Namespace_SubNamespace_ClassName.json`.

Exemplo de uso em testes (PHPUnit): carregue o JSON gerado com `json_decode()` e use os dados como fixtures.

## Integração com frameworks

Para integração com frameworks considere implementar um comando dedicado (Artisan/Hyperf) e templates
de factories PHP; posso ajudar a adicionar essas integrações.

## Testes

Rode a suíte com PHPUnit (ex.: em ambiente com PHP/Composer configurados):

```bash
composer install
vendor/bin/phpunit -v
```

## Contribuição

Pull requests são bem-vindos. Para mudanças maiores, abra uma issue primeiro.

PR checklist mínimo:
- testes atualizados e passando;
- seguir PSR-12;
- atualizar a documentação se necessário.