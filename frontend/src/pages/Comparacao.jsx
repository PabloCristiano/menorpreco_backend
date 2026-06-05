import { useEffect, useState, useCallback } from 'react'
import { Link } from 'react-router-dom'
import api from '../api/client'
import { brl, data as fmtData } from '../components/format'

export default function Comparacao() {
  const [dados, setDados] = useState(null)
  const [carregando, setCarregando] = useState(true)
  const [atualizando, setAtualizando] = useState(false)
  const [msg, setMsg] = useState(null)

  const carregar = useCallback(async () => {
    setCarregando(true)
    try {
      const { data } = await api.get('/minha-lista/comparacao')
      setDados(data)
    } finally {
      setCarregando(false)
    }
  }, [])

  useEffect(() => {
    carregar()
  }, [carregar])

  const atualizarAgora = async () => {
    setAtualizando(true)
    setMsg(null)
    try {
      const { data } = await api.post('/minha-lista/atualizar')
      setMsg(`✅ ${data.atualizados} preço(s) atualizados agora (${fmtData(data.data)}).`)
      await carregar()
    } catch {
      setMsg('❌ Não foi possível atualizar os preços.')
    } finally {
      setAtualizando(false)
    }
  }

  if (carregando) {
    return <div className="text-center text-slate-400 py-16">Carregando comparação…</div>
  }

  const produtos = dados?.produtos ?? []
  const mercados = dados?.estabelecimentos ?? []
  const matriz = dados?.matriz ?? {}
  const menor = dados?.menor_por_produto ?? {}

  return (
    <div className="space-y-5">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Comparação</h1>
          <p className="text-slate-500 text-sm">
            Seus produtos × seus estabelecimentos. O menor preço de cada linha fica destacado.
          </p>
        </div>
        <button
          onClick={atualizarAgora}
          disabled={atualizando || produtos.length === 0}
          className="shrink-0 bg-brand-600 hover:bg-brand-700 disabled:opacity-60 text-white font-semibold px-5 py-2.5 rounded-xl transition"
        >
          {atualizando ? 'Atualizando…' : '↻ Atualizar agora'}
        </button>
      </div>

      {msg && (
        <div className="text-sm text-slate-700 bg-slate-100 rounded-lg px-4 py-2">{msg}</div>
      )}

      {produtos.length === 0 || mercados.length === 0 ? (
        <EstadoVazio temProdutos={produtos.length > 0} temMercados={mercados.length > 0} />
      ) : (
        <Matriz
          produtos={produtos}
          mercados={mercados}
          matriz={matriz}
          menor={menor}
        />
      )}
    </div>
  )
}

function Matriz({ produtos, mercados, matriz, menor }) {
  const nomeMercado = (m) => m.apelido || m.estabelecimento?.nome_fantasia || '—'

  return (
    <div className="overflow-x-auto bg-white rounded-2xl border border-slate-200">
      <table className="w-full border-collapse text-sm">
        <thead>
          <tr className="border-b border-slate-200">
            <th className="text-left font-semibold text-slate-500 p-3 sticky left-0 bg-white min-w-48">
              Produto
            </th>
            {mercados.map((m) => (
              <th key={m.id} className="text-center font-semibold text-slate-700 p-3 min-w-32">
                🏪 {nomeMercado(m)}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {produtos.map((p) => {
            const vencedor = menor[p.produto_id]
            return (
              <tr key={p.id} className="border-b border-slate-100 last:border-0">
                <td className="p-3 sticky left-0 bg-white">
                  <Link
                    to={`/monitorados/${p.produto_id}`}
                    className="font-medium text-slate-900 hover:text-brand-700 leading-snug block"
                  >
                    {p.produto?.descricao || p.produto?.palavrachave}
                  </Link>
                  {p.produto?.gtin && (
                    <span className="text-xs text-slate-400">GTIN {p.produto.gtin}</span>
                  )}
                </td>
                {mercados.map((m) => {
                  const cel = matriz[`${p.produto_id}-${m.estabelecimento_id}`]
                  const ehMenor = vencedor === m.estabelecimento_id
                  return (
                    <td key={m.id} className="p-3 text-center">
                      {cel ? (
                        <div
                          className={`inline-flex flex-col items-center rounded-lg px-3 py-1.5 ${
                            ehMenor ? 'bg-brand-100 text-brand-700 font-bold' : 'text-slate-700'
                          }`}
                        >
                          <span className={ehMenor ? 'text-base' : ''}>{brl(cel.preco)}</span>
                          <span className="text-[10px] text-slate-400 font-normal">
                            {fmtData(cel.data_coleta)}
                          </span>
                        </div>
                      ) : (
                        <span className="text-slate-300">—</span>
                      )}
                    </td>
                  )
                })}
              </tr>
            )
          })}
        </tbody>
      </table>
    </div>
  )
}

function EstadoVazio({ temProdutos, temMercados }) {
  return (
    <div className="text-center text-slate-500 py-16 bg-white rounded-2xl border border-slate-200 space-y-2">
      <p className="text-4xl">📋</p>
      <p className="font-medium text-slate-700">Monte sua comparação</p>
      <p className="text-sm">
        {!temProdutos && (
          <>
            Você ainda não tem <strong>produtos</strong> na lista.{' '}
          </>
        )}
        {!temMercados && (
          <>
            Você ainda não tem <strong>estabelecimentos</strong> na lista.{' '}
          </>
        )}
      </p>
      <p className="text-sm">
        Vá em{' '}
        <Link to="/" className="text-brand-600 font-medium hover:underline">
          Busca
        </Link>{' '}
        e use os botões <strong>+ lista</strong> (no produto) e{' '}
        <strong>+ estabelecimento</strong>. Gerencie em{' '}
        <Link to="/minha-lista" className="text-brand-600 font-medium hover:underline">
          Minha Lista
        </Link>
        .
      </p>
    </div>
  )
}
