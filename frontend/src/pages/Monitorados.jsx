import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api from '../api/client'
import { brl, data as fmtData } from '../components/format'

export default function Monitorados() {
  const [itens, setItens] = useState([])
  const [carregando, setCarregando] = useState(true)
  const [busca, setBusca] = useState('')

  const carregar = async (q = '') => {
    setCarregando(true)
    try {
      const { data } = await api.get('/menorpreco/produtos', {
        params: q ? { q } : {},
      })
      setItens(data.data ?? [])
    } finally {
      setCarregando(false)
    }
  }

  useEffect(() => {
    carregar()
  }, [])

  const onBuscar = (e) => {
    e.preventDefault()
    carregar(busca.trim())
  }

  return (
    <div>
      <div className="mb-6 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Produtos monitorados</h1>
          <p className="text-slate-500 text-sm">
            Produtos salvos no banco com o menor preço da última coleta.
          </p>
        </div>
        <form onSubmit={onBuscar} className="flex gap-2">
          <input
            type="text"
            value={busca}
            onChange={(e) => setBusca(e.target.value)}
            placeholder="Filtrar…"
            className="rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-emerald-500"
          />
          <button className="bg-slate-800 text-white text-sm font-medium px-4 rounded-lg">
            Filtrar
          </button>
        </form>
      </div>

      {carregando ? (
        <div className="text-center text-slate-400 py-16">Carregando…</div>
      ) : itens.length === 0 ? (
        <div className="text-center text-slate-500 py-16 bg-white rounded-2xl border border-slate-200">
          Nenhum produto monitorado ainda.
          <br />
          <Link to="/" className="text-emerald-600 font-medium hover:underline">
            Busque um produto
          </Link>{' '}
          e clique em “Monitorar”.
        </div>
      ) : (
        <div className="grid gap-3 sm:grid-cols-2">
          {itens.map((item) => {
            const est = item.menor_preco?.estabelecimento
            return (
              <Link
                key={item.produto.id}
                to={`/monitorados/${item.produto.id}`}
                className="bg-white rounded-2xl border border-slate-200 hover:border-emerald-300 hover:shadow-sm transition p-4 block"
              >
                <h3 className="font-medium text-slate-900 leading-snug line-clamp-2">
                  {item.produto.descricao || item.produto.palavrachave}
                </h3>
                <div className="flex items-end justify-between mt-3">
                  <div>
                    {item.menor_preco ? (
                      <>
                        <div className="text-xl font-bold text-emerald-600">
                          {brl(item.menor_preco.preco)}
                        </div>
                        <div className="text-xs text-slate-500">
                          {est?.nm_fan || est?.razao_social || '—'}
                        </div>
                      </>
                    ) : (
                      <div className="text-sm text-slate-400">Sem coleta</div>
                    )}
                  </div>
                  <div className="text-right text-xs text-slate-400">
                    <div>{item.total_ofertas} oferta(s)</div>
                    <div>{fmtData(item.ultima_coleta)}</div>
                  </div>
                </div>
              </Link>
            )
          })}
        </div>
      )}
    </div>
  )
}
