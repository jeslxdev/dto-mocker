# DTO Mocker

Gerador leve de mocks para classes DTO/Inputs/Outputs em PHP. Útil para criar fixtures e factories
para testes unitários, de integração e para popular fixtures de ambiente de desenvolvimento.

Funciona de forma agnóstica ao framework (Laravel, Hyperf, Symfony etc.).

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

Você pode sobrescrever geradores por tipo:

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
- `--path` (padrão: `src`) — diretório a ser escaneado em busca de classes;
- `--out` (padrão: `tests/_fixtures`) — pasta de saída para os arquivos gerados;
- `--count` (padrão: `3`) — quantas instâncias serão geradas por classe.

O script detecta classes "DTO-like" por heurística (contém `Dto` no nome ou possui propriedades tipadas).

## Exemplos de saída

O script grava arquivos JSON com nome no formato `Namespace_SubNamespace_ClassName.json`.

Exemplo de uso em testes (PHPUnit): carregue o JSON como fixture e converta para array/objetos conforme precisar.

## Integração com frameworks

Para integração aprimorada (Artisan commands, Hyperf commands, templates de factories PHP) recomendo
transformar o utilitário em um comando via `symfony/console` e/ou adicionar providers para o framework
alvo. Posso ajudar a implementar integrações específicas se desejar.

## Testes

Rode a suíte com PHPUnit (ex.: em ambiente com PHP/Composer configurados):

```bash
composer install
vendor/bin/phpunit -v
```

## Contribuição

Pull requests bem-vindos. Para mudanças maiores, abra uma issue descrevendo a proposta primeiro.

Checklist básico para PRs:
- testes atualizados e passando;
- seguir PSR-12 (formatação); 
- documentação atualizada (README ou docs específicos).

## Licença

MIT — veja o `LICENSE`.

Você pode gerar fixtures JSON a partir dos DTOs do projeto com o script incluido:

```bash
composer dto-mocker:generate
# ou diretamente
php bin/generate-mocks.php --path=src --out=tests/_fixtures --count=3
```

Opções básicas: `--path`, `--out`, `--count`.
