import { useEffect, useState, useCallback } from 'react'
import { Link } from 'react-router-dom'
import api from '../api/client'
import { enderecoEstabelecimento } from '../components/format'

export default function Mercados() {
  const [itens, setItens] = useState([])
  const [busca, setBusca] = useState('')
  const [filtro, setFiltro] = useState('')
  const [pagina, setPagina] = useState(1)
  const [ultimaPagina, setUltimaPagina] = useState(1)
  const [total, setTotal] = useState(0)
  const [carregando, setCarregando] = useState(true)

  const carregar = useCallback(async (pag, q) => {
    setCarregando(true)
    try {
      const { data } = await api.get('/estabelecimentos', {
        params: { page: pag, q: q || undefined },
      })
      setItens((prev) => (pag === 1 ? data.data : [...prev, ...data.data]))
      setPagina(data.pagina_atual)
      setUltimaPagina(data.ultima_pagina)
      setTotal(data.total)
    } finally {
      setCarregando(false)
    }
  }, [])

  useEffect(() => {
    carregar(1, '')
  }, [carregar])

  const onBuscar = (e) => {
    e.preventDefault()
    setFiltro(busca.trim())
    carregar(1, busca.trim())
  }

  const alternarFavorito = async (item) => {
    if (item.lista_id) {
      await api.delete(`/minha-lista/estabelecimentos/${item.lista_id}`)
      atualizarItem(item.id, { lista_id: null })
    } else {
      const { data } = await api.post('/minha-lista/estabelecimentos', {
        codigo: item.codigo,
        nome_fantasia: item.nome_fantasia,
        razao_social: item.razao_social,
        bairro: item.bairro,
        cidade: item.cidade,
        uf: item.uf,
        tp_logr: item.tp_logr,
        nm_logr: item.nm_logr,
        nr_logr: item.nr_logr,
      })
      atualizarItem(item.id, { lista_id: data.item.id })
    }
  }

  const atualizarItem = (id, patch) =>
    setItens((l) => l.map((i) => (i.id === id ? { ...i, ...patch } : i)))

  return (
    <div className="space-y-5">
      <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Estabelecimentos da base</h1>
          <p className="text-slate-500 text-sm">
            Todos os estabelecimentos já mapeados ({total}). Adicione à sua{' '}
            <Link to="/minha-lista" className="text-brand-600 font-medium hover:underline">
              lista
            </Link>{' '}
            para comparar.
          </p>
        </div>
        <form onSubmit={onBuscar} className="flex gap-2">
          <input
            value={busca}
            onChange={(e) => setBusca(e.target.value)}
            placeholder="Buscar por nome ou bairro…"
            className="rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-brand-500 w-56"
          />
          <button className="bg-slate-800 text-white text-sm font-medium px-4 rounded-lg">
            Filtrar
          </button>
        </form>
      </div>

      {filtro && (
        <p className="text-sm text-slate-500">
          Resultados para “{filtro}”.{' '}
          <button
            onClick={() => {
              setBusca('')
              setFiltro('')
              carregar(1, '')
            }}
            className="text-brand-600 hover:underline"
          >
            limpar
          </button>
        </p>
      )}

      <div className="grid gap-2.5 sm:grid-cols-2">
        {itens.map((item) => (
          <div
            key={item.id}
            className="bg-white rounded-xl border border-slate-200 p-3 flex items-start justify-between gap-3"
          >
            <div className="min-w-0">
              <p className="font-medium text-slate-900 leading-snug truncate">
                {item.nome_fantasia || item.razao_social || '(sem nome)'}
              </p>
              <p className="text-xs text-slate-500 truncate">
                {enderecoEstabelecimento(item)}
              </p>
              <span className="text-xs text-slate-400">
                {item.total_produtos} produto(s) coletado(s)
              </span>
            </div>
            <button
              onClick={() => alternarFavorito(item)}
              className={`shrink-0 text-xs font-medium rounded-full px-3 py-1.5 border transition ${
                item.lista_id
                  ? 'bg-brand-50 border-brand-200 text-brand-700 hover:bg-red-50 hover:border-red-200 hover:text-red-600'
                  : 'border-slate-200 text-slate-600 hover:border-brand-300 hover:text-brand-700'
              }`}
            >
              {item.lista_id ? '✓ nos meus' : '+ adicionar'}
            </button>
          </div>
        ))}
      </div>

      {carregando && <div className="text-center text-slate-400 py-4">Carregando…</div>}

      {!carregando && pagina < ultimaPagina && (
        <div className="text-center">
          <button
            onClick={() => carregar(pagina + 1, filtro)}
            className="text-sm font-medium text-brand-700 border border-brand-200 hover:bg-brand-50 rounded-lg px-5 py-2"
          >
            Carregar mais
          </button>
        </div>
      )}

      {!carregando && itens.length === 0 && (
        <div className="text-center text-slate-500 py-12 bg-white rounded-2xl border border-slate-200">
          Nenhum estabelecimento encontrado.
        </div>
      )}
    </div>
  )
}
