# Coleta de preços (sincronização híbrida)

A coleta grava 1 snapshot de preço por **produto + loja + dia** na tabela
`menorpreco_historico_precos` — é o que alimenta os gráficos de tendência.

## Dois modos (híbrido)

| Modo | Como dispara | Origem registrada |
|------|--------------|-------------------|
| **Automático** | Scheduler do Laravel, todo dia às **06:00** | `agendado` |
| **Manual** | Botão **Sincronizar agora** (tela Coleta) → roda em segundo plano | `manual` |

Os dois rodam o mesmo comando: `php artisan app:menor-preco-sync`.
Cada execução é registrada em `coleta_execucoes` (status, totais, duração).

## Setup no VPS (Linux) — OBRIGATÓRIO

O scheduler do Laravel só funciona se o cron do sistema chamá-lo a cada minuto.
Edite o crontab (`crontab -e`) e adicione **uma** linha:

```cron
* * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
```

Confira o que está agendado:

```bash
php artisan schedule:list
```

## Notas de escala (centenas de produtos)

- A coleta é sequencial com `usleep(200ms)` entre chamadas, e o
  `MenorPrecoService` força IPv4 + retry (ver memória do projeto).
- `withoutOverlapping(60)` impede duas coletas simultâneas.
- O botão manual lança um processo de fundo (`Symfony Process`), então **não
  precisa de fila/worker**. Se no futuro passar de ~500 produtos, migrar para
  jobs em fila (`queue:work` + Supervisor) é o próximo passo.
- Execuções presas em `rodando` por mais de 30 min são marcadas como `erro`
  automaticamente ao consultar o status.

## Endpoints

- `POST /api/coleta/sincronizar` — dispara coleta manual (auth)
- `GET  /api/coleta/ultima` — última execução + histórico recente (auth)
