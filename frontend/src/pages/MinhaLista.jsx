import { useEffect, useState, useCallback } from 'react'
import { Link } from 'react-router-dom'
import api from '../api/client'
import { enderecoEstabelecimento } from '../components/format'

export default function MinhaLista() {
  const [produtos, setProdutos] = useState([])
  const [mercados, setMercados] = useState([])
  const [carregando, setCarregando] = useState(true)

  const carregar = useCallback(async () => {
    setCarregando(true)
    try {
      const [p, e] = await Promise.all([
        api.get('/minha-lista/produtos'),
        api.get('/minha-lista/estabelecimentos'),
      ])
      setProdutos(p.data.data ?? [])
      setMercados(e.data.data ?? [])
    } finally {
      setCarregando(false)
    }
  }, [])

  useEffect(() => {
    carregar()
  }, [carregar])

  const removerProduto = async (id) => {
    await api.delete(`/minha-lista/produtos/${id}`)
    setProdutos((l) => l.filter((i) => i.id !== id))
  }

  const removerMercado = async (id) => {
    await api.delete(`/minha-lista/estabelecimentos/${id}`)
    setMercados((l) => l.filter((i) => i.id !== id))
  }

  if (carregando) {
    return <div className="text-center text-slate-400 py-16">Carregando…</div>
  }

  return (
    <div className="space-y-8">
      <div>
        <h1 className="text-2xl font-bold text-slate-900">Minha Lista</h1>
        <p className="text-slate-500 text-sm">
          Gerencie os produtos e os estabelecimentos que entram na sua{' '}
          <Link to="/comparacao" className="text-brand-600 font-medium hover:underline">
            comparação
          </Link>
          .
        </p>
      </div>

      {/* Produtos */}
      <section>
        <h2 className="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-3">
          🛒 Produtos ({produtos.length})
        </h2>
        {produtos.length === 0 ? (
          <Vazio texto="Nenhum produto. Adicione pela Busca com o botão + lista." />
        ) : (
          <div className="space-y-2">
            {produtos.map((item) => (
              <div
                key={item.id}
                className="bg-white rounded-xl border border-slate-200 p-3 flex items-center justify-between gap-3"
              >
                <div className="min-w-0">
                  <p className="font-medium text-slate-900 leading-snug truncate">
                    {item.produto?.descricao || item.produto?.palavrachave}
                  </p>
                  <span className="text-xs text-slate-400">
                    GTIN {item.produto?.gtin} · NCM {item.produto?.ncm}
                  </span>
                </div>
                <button
                  onClick={() => removerProduto(item.id)}
                  className="shrink-0 text-sm text-slate-400 hover:text-red-600 px-2"
                >
                  Remover
                </button>
              </div>
            ))}
          </div>
        )}
      </section>

      {/* Mercados */}
      <section>
        <div className="flex items-center justify-between mb-3">
          <h2 className="text-sm font-semibold text-slate-500 uppercase tracking-wide">
            🏪 Estabelecimentos ({mercados.length})
          </h2>
          <Link
            to="/mercados"
            className="text-xs font-medium text-brand-700 border border-brand-200 hover:bg-brand-50 rounded-lg px-3 py-1.5"
          >
            🔎 Explorar estabelecimentos da base
          </Link>
        </div>
        {mercados.length === 0 ? (
          <Vazio texto="Nenhum estabelecimento. Adicione pela Busca ou em Estabelecimentos." />
        ) : (
          <div className="space-y-2">
            {mercados.map((item) => (
              <MercadoItem
                key={item.id}
                item={item}
                onRemover={() => removerMercado(item.id)}
                onRenomeado={carregar}
              />
            ))}
          </div>
        )}
      </section>
    </div>
  )
}

function MercadoItem({ item, onRemover, onRenomeado }) {
  const est = item.estabelecimento
  const [editando, setEditando] = useState(false)
  const [apelido, setApelido] = useState(item.apelido ?? '')

  const salvar = async () => {
    await api.patch(`/minha-lista/estabelecimentos/${item.id}`, {
      apelido: apelido.trim() || null,
    })
    setEditando(false)
    onRenomeado()
  }

  return (
    <div className="bg-white rounded-xl border border-slate-200 p-3 flex items-center justify-between gap-3">
      <div className="min-w-0">
        {editando ? (
          <div className="flex items-center gap-2">
            <input
              autoFocus
              value={apelido}
              onChange={(e) => setApelido(e.target.value)}
              placeholder="Apelido (ex.: perto de casa)"
              className="rounded-lg border border-slate-300 px-2 py-1 text-sm outline-none focus:border-brand-500"
            />
            <button onClick={salvar} className="text-sm text-brand-700 font-medium">
              Salvar
            </button>
          </div>
        ) : (
          <p className="font-medium text-slate-900 leading-snug truncate">
            {item.apelido ? (
              <>
                {item.apelido}{' '}
                <span className="text-slate-400 font-normal">· {est?.nome_fantasia}</span>
              </>
            ) : (
              est?.nome_fantasia || est?.razao_social || '—'
            )}
          </p>
        )}
        <span className="text-xs text-slate-400">{enderecoEstabelecimento(est)}</span>
      </div>
      <div className="shrink-0 flex items-center gap-1">
        {!editando && (
          <button
            onClick={() => setEditando(true)}
            className="text-sm text-slate-400 hover:text-slate-700 px-2"
          >
            Renomear
          </button>
        )}
        <button
          onClick={onRemover}
          className="text-sm text-slate-400 hover:text-red-600 px-2"
        >
          Remover
        </button>
      </div>
    </div>
  )
}

function Vazio({ texto }) {
  return (
    <div className="text-sm text-slate-500 bg-white rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center">
      {texto}
    </div>
  )
}
