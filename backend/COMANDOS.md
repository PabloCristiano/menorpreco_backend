# 📒 Comandos do projeto Menor Preço

Referência rápida de todos os comandos. Backend Laravel (porta 8000) +
frontend React/Vite (porta 5173).

---

## 🚀 Subir o projeto (dia a dia)

```bash
# 1) Backend (na raiz do projeto)
php artisan serve                 # http://127.0.0.1:8000

# 2) Frontend (em outro terminal)
cd frontend
npm run dev                       # http://localhost:5173
```

---

## 🛠️ Primeira instalação (setup do zero)

```bash
# Backend
composer install                  # instala dependências PHP
cp .env.example .env              # cria o .env (ajuste o banco MySQL nele)
php artisan key:generate          # gera a APP_KEY
php artisan migrate               # cria as tabelas

# Frontend
cd frontend
npm install                       # instala dependências JS
cp .env .env                      # garante VITE_API_URL (já versionado)
```

> No `.env` confirme: `DB_CONNECTION=mysql`, `DB_DATABASE=menorpreco`,
> `DB_USERNAME`, `DB_PASSWORD`. O projeto usa MySQL.

---

## 🗄️ Banco de dados

```bash
php artisan migrate               # aplica migrations pendentes
php artisan migrate:status        # lista o que já rodou
php artisan migrate:fresh         # ⚠️ apaga tudo e recria (perde os dados!)
php artisan migrate:rollback      # desfaz o último lote de migrations
```

---

## 🔄 Coleta de preços (sincronização)

```bash
# Roda a coleta manualmente, no terminal (vê o progresso na tela)
php artisan app:menor-preco-sync

# Ver o que está agendado (coleta automática diária às 06:00)
php artisan schedule:list

# Simular o agendador localmente (roda o que estiver "due")
php artisan schedule:run
```

A coleta também pode ser disparada pelo **botão "Sincronizar agora"** na aba
**Coleta** do site (roda em segundo plano).

### Cron no servidor (VPS) — obrigatório em produção

Adicione esta única linha no `crontab -e`:

```cron
* * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
```

Detalhes em [SINCRONIZACAO.md](SINCRONIZACAO.md).

---

## 📈 Dados de demonstração (para testar os gráficos da Análise)

```bash
# Gera 45 dias de histórico fictício (a partir dos preços de hoje)
php artisan app:seed-historico-demo --dias=45

# ⚠️ Remove os dados fictícios (apaga TODO histórico com data anterior a hoje)
php artisan app:seed-historico-demo --limpar
```

> Use só para visualizar a aba Análise antes de ter histórico real acumulado.

---

## 🏗️ Build do frontend (deploy)

```bash
cd frontend
npm run build                     # gera os arquivos finais em frontend/dist
npm run preview                   # testa o build localmente
```

---

## 🧰 Utilitários Laravel

```bash
php artisan route:list            # lista todas as rotas da API
php artisan config:clear          # limpa cache de config (após mexer no .env)
php artisan optimize:clear        # limpa todos os caches
php artisan tinker                # console interativo (testar models/queries)
```

---

## 🔌 Endpoints principais da API

| Método | Rota | O quê |
|--------|------|-------|
| POST | `/api/register` · `/api/login` | Autenticação (token) |
| GET | `/api/menorpreco/categorias` | Categorias de um termo (passo 1) |
| GET | `/api/menorpreco/consultar` | Produtos (por termo+categoria ou gtin) |
| GET | `/api/menorpreco/salvar` | Consulta e salva no banco |
| GET/POST/DELETE | `/api/minha-lista/produtos` | Lista de produtos do usuário |
| GET/POST/PATCH/DELETE | `/api/minha-lista/estabelecimentos` | Mercados do usuário |
| GET | `/api/minha-lista/comparacao` | Matriz produtos × mercados |
| POST | `/api/minha-lista/atualizar` | Atualiza preços da lista (ao vivo) |
| POST | `/api/coleta/sincronizar` | Dispara coleta global em 2º plano |
| GET | `/api/coleta/ultima` | Status da última coleta |
| GET | `/api/analise/resumo` | KPIs + rankings de variação |
| GET | `/api/analise/produto/{id}` | Série e variação de um produto |

> Rotas de `minha-lista`, `coleta` e `analise` exigem token (Bearer) de login.
