import { useEffect, useMemo, useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  Tooltip,
  ResponsiveContainer,
  CartesianGrid,
} from 'recharts'
import api from '../api/client'
import { brl, data as fmtData, km, enderecoEstabelecimento } from '../components/format'

export default function ProdutoDetalhe() {
  const { id } = useParams()
  const [produto, setProduto] = useState(null)
  const [historico, setHistorico] = useState([])
  const [estatisticas, setEstatisticas] = useState([])
  const [carregando, setCarregando] = useState(true)

  useEffect(() => {
    let ativo = true
    ;(async () => {
      setCarregando(true)
      try {
        const [hist, stats] = await Promise.all([
          api.get(`/menorpreco/produtos/${id}/historico`),
          api.get(`/menorpreco/produtos/${id}/estatisticas`),
        ])
        if (!ativo) return
        setProduto(hist.data.produto)
        setHistorico(hist.data.historico ?? [])
        setEstatisticas(stats.data.dados ?? [])
      } finally {
        if (ativo) setCarregando(false)
      }
    })()
    return () => {
      ativo = false
    }
  }, [id])

  // Ofertas da última coleta, ordenadas pelo menor preço
  const ofertasAtuais = useMemo(() => {
    if (historico.length === 0) return []
    const ultima = historico.reduce(
      (max, h) => (h.data_coleta > max ? h.data_coleta : max),
      historico[0].data_coleta,
    )
    return historico
      .filter((h) => h.data_coleta === ultima)
      .sort((a, b) => Number(a.preco) - Number(b.preco))
  }, [historico])

  // Série temporal (mais antigo → mais recente) para o gráfico
  const serie = useMemo(
    () =>
      [...estatisticas]
        .sort((a, b) => a.data_coleta.localeCompare(b.data_coleta))
        .map((d) => ({
          data: fmtData(d.data_coleta),
          minimo: Number(d.preco_min),
          medio: Number(Number(d.preco_medio).toFixed(2)),
        })),
    [estatisticas],
  )

  if (carregando) {
    return <div className="text-center text-slate-400 py-16">Carregando…</div>
  }

  if (!produto) {
    return (
      <div className="text-center text-slate-500 py-16">
        Produto não encontrado.{' '}
        <Link to="/monitorados" className="text-emerald-600 hover:underline">
          Voltar
        </Link>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <div>
        <Link
          to="/monitorados"
          className="text-sm text-slate-500 hover:text-slate-800"
        >
          ← Voltar para monitorados
        </Link>
        <h1 className="text-2xl font-bold text-slate-900 mt-2">
          {produto.descricao || produto.palavrachave}
        </h1>
        <div className="flex flex-wrap gap-3 text-xs text-slate-400 mt-1">
          {produto.gtin && <span>GTIN {produto.gtin}</span>}
          {produto.ncm && <span>NCM {produto.ncm}</span>}
        </div>
      </div>

      {serie.length > 1 && (
        <div className="bg-white rounded-2xl border border-slate-200 p-4">
          <h2 className="font-semibold text-slate-800 mb-3">
            Evolução do preço
          </h2>
          <ResponsiveContainer width="100%" height={260}>
            <LineChart data={serie} margin={{ top: 8, right: 16, left: 0, bottom: 0 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
              <XAxis dataKey="data" fontSize={12} stroke="#94a3b8" />
              <YAxis
                fontSize={12}
                stroke="#94a3b8"
                tickFormatter={(v) => brl(v)}
                width={70}
              />
              <Tooltip formatter={(v) => brl(v)} />
              <Line
                type="monotone"
                dataKey="minimo"
                name="Menor preço"
                stroke="#059669"
                strokeWidth={2}
                dot={{ r: 3 }}
              />
              <Line
                type="monotone"
                dataKey="medio"
                name="Preço médio"
                stroke="#94a3b8"
                strokeWidth={1.5}
                strokeDasharray="4 4"
                dot={false}
              />
            </LineChart>
          </ResponsiveContainer>
        </div>
      )}

      <div>
        <h2 className="font-semibold text-slate-800 mb-3">
          Ofertas atuais{' '}
          {ofertasAtuais[0] && (
            <span className="font-normal text-slate-400 text-sm">
              · coleta de {fmtData(ofertasAtuais[0].data_coleta)}
            </span>
          )}
        </h2>
        {ofertasAtuais.length === 0 ? (
          <p className="text-slate-500 text-sm">Nenhuma oferta registrada.</p>
        ) : (
          <div className="space-y-2">
            {ofertasAtuais.map((h, i) => (
              <div
                key={h.id}
                className={`bg-white rounded-xl border p-3 flex items-center justify-between gap-4 ${
                  i === 0 ? 'border-emerald-400' : 'border-slate-200'
                }`}
              >
                <div className="min-w-0">
                  <p className="font-medium text-slate-900 text-sm">
                    {h.estabelecimento?.nome_fantasia ||
                      h.estabelecimento?.razao_social ||
                      '—'}
                  </p>
                  <p className="text-xs text-slate-500">
                    {enderecoEstabelecimento(h.estabelecimento)}
                  </p>
                  {h.distancia_km != null && (
                    <p className="text-xs text-slate-400 mt-0.5">
                      📍 {km(h.distancia_km)}
                    </p>
                  )}
                </div>
                <div
                  className={`text-lg font-bold ${
                    i === 0 ? 'text-emerald-600' : 'text-slate-900'
                  }`}
                >
                  {brl(h.preco)}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  )
}
