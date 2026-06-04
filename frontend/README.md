# Menor Preço — Frontend

SPA em **React + Vite** que consome a API Laravel do projeto. Permite buscar
produtos ao vivo no Nota Paraná, ver as lojas mais baratas e acompanhar
produtos monitorados com histórico de preço.

## Stack

- React 19 + React Router 7
- Vite 6
- Tailwind CSS 4
- Axios (auth via token Sanctum)
- Recharts (gráfico de evolução de preço)

## Rodando

Pré-requisito: o backend Laravel rodando em `http://127.0.0.1:8000`
(`php artisan serve`).

```bash
cd frontend
npm install
npm run dev
```

Abre em http://localhost:5173.

A URL da API é configurável via `.env` (`VITE_API_URL`).

## Estrutura

```
src/
  api/client.js        # axios + interceptors de token + lista de locais
  auth/AuthContext.jsx # login / register / logout (Sanctum)
  components/          # Layout, ProtectedRoute, helpers de formatação
  pages/
    Login.jsx          # entrar / criar conta
    Busca.jsx          # busca ao vivo (consulta API + monitorar)
    Monitorados.jsx    # dashboard dos produtos salvos
    ProdutoDetalhe.jsx # histórico + gráfico + ofertas atuais
```

## Telas

- **Busca ao vivo** (`/`) — consulta `GET /api/menorpreco/consultar`, ordena pelo
  menor preço e destaca a loja mais barata. Botão "Monitorar" chama
  `GET /api/menorpreco/salvar`.
- **Monitorados** (`/monitorados`) — lista `GET /api/menorpreco/produtos`.
- **Detalhe** (`/monitorados/:id`) — `historico` + `estatisticas` do produto.
