CREATE TABLE IF NOT EXISTS categorias (
    id SERIAL PRIMARY KEY,
    nome TEXT NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS produtos (
    id SERIAL PRIMARY KEY,
    sku TEXT NOT NULL UNIQUE,
    nome TEXT NOT NULL,
    categoria_id INTEGER REFERENCES categorias(id) ON DELETE SET NULL,
    unidade TEXT NOT NULL DEFAULT 'un',
    custo NUMERIC(12,2) NOT NULL DEFAULT 0,
    estoque_minimo NUMERIC(12,3) NOT NULL DEFAULT 0,
    quantidade NUMERIC(12,3) NOT NULL DEFAULT 0 CHECK (quantidade >= 0),
    fornecedor TEXT,
    observacoes TEXT,
    criado_em TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS movimentacoes (
    id SERIAL PRIMARY KEY,
    produto_id INTEGER NOT NULL REFERENCES produtos(id) ON DELETE CASCADE,
    tipo TEXT NOT NULL CHECK (tipo IN ('entrada','saida')),
    motivo TEXT NOT NULL,
    quantidade NUMERIC(12,3) NOT NULL CHECK (quantidade > 0),
    custo_unitario NUMERIC(12,2),
    nota TEXT,
    validade DATE,
    observacao TEXT,
    criado_em TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_mov_produto ON movimentacoes(produto_id);
CREATE INDEX IF NOT EXISTS idx_mov_criado ON movimentacoes(criado_em DESC);
