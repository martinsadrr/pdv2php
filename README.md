# pdv2php — Controle de estoque para restaurantes

PHP 8 + Postgres, pronto para deploy na Vercel (runtime `vercel-php`).

## Funcionalidades
- Login simples (usuário/senha por variável de ambiente)
- Categorias, produtos (unidade, custo, mínimo, fornecedor) e movimentações de entrada/saída
- Filtros em produtos e movimentações, edição, exclusão e paginação
- Alertas de estoque baixo e de validade no dashboard

## Variáveis de ambiente
Veja `.env.example`: `DATABASE_URL`, `DB_SSLMODE`, `APP_KEY`, `ADMIN_USER`, `ADMIN_PASS`.
As tabelas são criadas automaticamente no primeiro acesso (`schema.sql`).

## Deploy na Vercel
1. No Supabase, copie a connection string do **Transaction pooler** (Project Settings → Database). A conexão direta é só IPv6 e não funciona bem na Vercel.
2. Importe este repositório na Vercel.
3. Em *Settings → Environment Variables*, cadastre as variáveis acima.
4. Faça o deploy. O app habilita RLS nas tabelas ao criá-las, então a API pública do Supabase não expõe os dados.

> O SQLite não funciona na Vercel (sistema de arquivos efêmero), por isso o projeto usa Postgres.

## Rodar localmente
Requer PHP 8.1+ com `pdo_pgsql` e um Postgres local.

```bash
export DATABASE_URL=postgres://user:pass@localhost:5432/estoque DB_SSLMODE=disable APP_KEY=dev ADMIN_PASS=admin
php -S localhost:8000 router.php
```
