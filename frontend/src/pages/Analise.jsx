import { useEffect, useState, useCallback } from 'react'
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
import { brl, data as fmtData } from '../components/format'

const PERIODOS = [
  { dias: 7, label: '7 dias' },
  { dias: 30, label: '30 dias' },
  { dias: 90, label: '90 dias' },
  { dias: 180, label: '6 meses' },
]

export default function Analise() {
  const [dias, setDias] = useState(30)
  const [resumo, setResumo] = useState(null)
  const [carregando, setCarregando] = useState(true)
  const [produtoSel, setProdutoSel] = useState(null)

  const carregar = useCallback(async () => {
    setCarregando(true)
    try {
      const { data } = await api.get('/analise/resumo', { params: { dias } })
      setResumo(data)
    } finally {
      setCarregando(false)
    }
  }, [dias])

  useEffect(() => {
    carregar()
  }, [carregar])

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Análise de preços</h1>
          <p className="text-slate-500 text-sm">
            Tendências, variações e rankings com base no histórico coletado.
          </p>
        </div>
        <div className="flex rounded-xl bg-white border border-slate-200 p-1 text-sm">
          {PERIODOS.map((p) => (
            <button
              key={p.dias}
              onClick={() => setDias(p.dias)}
              className={`px-3 py-1.5 rounded-lg font-medium transition ${
                dias === p.dias ? 'bg-brand-600 text-white' : 'text-slate-600 hover:bg-slate-100'
              }`}
            >
              {p.label}
            </button>
          ))}
        </div>
      </div>

      {carregando ? (
        <div className="text-center text-slate-400 py-16">Carregando análise…</div>
      ) : !resumo || resumo.periodo.dias_com_dados < 1 ? (
        <SemDados />
      ) : (
        <>
          <KPIs resumo={resumo} />

          <div className="grid gap-4 lg:grid-cols-2">
            <Ranking
              titulo="📈 Maiores altas"
              cor="text-red-600"
              itens={resumo.maiores_altas}
              onSelecionar={setProdutoSel}
              selecionado={produtoSel}
            />
            <Ranking
              titulo="📉 Maiores baixas"
              cor="text-brand-600"
              itens={resumo.maiores_baixas}
              onSelecionar={setProdutoSel}
              selecionado={produtoSel}
            />
          </div>

          {produtoSel ? (
            <DetalheProduto produtoId={produtoSel} dias={dias} />
          ) : (
            <p className="text-center text-sm text-slate-400 py-4">
              Clique em um produto nos rankings para ver a evolução detalhada.
            </p>
          )}
        </>
      )}
    </div>
  )
}

function KPIs({ resumo }) {
  const media = resumo.variacao_media_pct
  return (
    <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <Card titulo="Produtos monitorados" valor={resumo.totais.produtos} />
      <Card titulo="Lojas mapeadas" valor={resumo.totais.estabelecimentos} />
      <Card
        titulo="Dias de histórico"
        valor={resumo.periodo.dias_com_dados}
        rodape={
          resumo.periodo.inicio
            ? `${fmtData(resumo.periodo.inicio)} → ${fmtData(resumo.periodo.fim)}`
            : null
        }
      />
      <Card
        titulo="Variação média"
        valor={`${media > 0 ? '+' : ''}${media}%`}
        cor={media > 0 ? 'text-red-600' : media < 0 ? 'text-brand-600' : 'text-slate-900'}
      />
    </div>
  )
}

function Card({ titulo, valor, rodape, cor = 'text-slate-900' }) {
  return (
    <div className="bg-white rounded-2xl border border-slate-200 p-4">
      <div className={`text-2xl font-bold ${cor}`}>{valor}</div>
      <div className="text-xs text-slate-500 mt-0.5">{titulo}</div>
      {rodape && <div className="text-[10px] text-slate-400 mt-1">{rodape}</div>}
    </div>
  )
}

function Ranking({ titulo, cor, itens, onSelecionar, selecionado }) {
  return (
    <div className="bg-white rounded-2xl border border-slate-200 p-4">
      <h2 className="font-semibold text-slate-800 mb-3">{titulo}</h2>
      {itens.length === 0 ? (
        <p className="text-sm text-slate-400 py-4 text-center">Sem variação no período.</p>
      ) : (
        <div className="space-y-1">
          {itens.map((v) => (
            <button
              key={v.produto_id}
              onClick={() => onSelecionar(v.produto_id)}
              className={`w-full flex items-center justify-between gap-3 text-left rounded-lg px-2 py-2 transition ${
                selecionado === v.produto_id ? 'bg-slate-100' : 'hover:bg-slate-50'
              }`}
            >
              <div className="min-w-0">
                <p className="text-sm font-medium text-slate-800 truncate">
                  {v.produto?.descricao || v.produto?.palavrachave}
                </p>
                <p className="text-xs text-slate-400">
                  {brl(v.preco_inicial)} → {brl(v.preco_atual)}
                </p>
              </div>
              <span className={`text-sm font-bold shrink-0 ${cor}`}>
                {v.variacao_pct > 0 ? '+' : ''}
                {v.variacao_pct}%
              </span>
            </button>
          ))}
        </div>
      )}
    </div>
  )
}

function DetalheProduto({ produtoId, dias }) {
  const [dados, setDados] = useState(null)
  const [carregando, setCarregando] = useState(true)

  useEffect(() => {
    let ativo = true
    setCarregando(true)
    api
      .get(`/analise/produto/${produtoId}`, { params: { dias } })
      .then(({ data }) => ativo && setDados(data))
      .finally(() => ativo && setCarregando(false))
    return () => {
      ativo = false
    }
  }, [produtoId, dias])

  if (carregando) {
    return <div className="text-center text-slate-400 py-10">Carregando produto…</div>
  }
  if (!dados) return null

  const serie = (dados.serie ?? []).map((d) => ({
    data: fmtData(d.data_coleta),
    minimo: Number(d.min),
    medio: Number(Number(d.media).toFixed(2)),
    maximo: Number(d.max),
  }))
  const r = dados.resumo

  return (
    <div className="bg-white rounded-2xl border border-slate-200 p-5 space-y-4">
      <div className="flex items-start justify-between gap-3">
        <div>
          <h2 className="font-bold text-slate-900 leading-snug">
            {dados.produto?.descricao || dados.produto?.palavrachave}
          </h2>
          {dados.produto?.gtin && (
            <span className="text-xs text-slate-400">GTIN {dados.produto.gtin}</span>
          )}
        </div>
        {r && <Tendencia resumo={r} />}
      </div>

      {r && (
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <Mini titulo="Atual (menor)" valor={brl(r.preco_atual)} />
          <Mini titulo="No início" valor={brl(r.preco_inicial)} />
          <Mini titulo="Mínimo período" valor={brl(r.min_periodo)} cor="text-brand-600" />
          <Mini titulo="Máximo período" valor={brl(r.max_periodo)} cor="text-red-600" />
        </div>
      )}

      {serie.length > 1 ? (
        <ResponsiveContainer width="100%" height={280}>
          <LineChart data={serie} margin={{ top: 8, right: 16, left: 0, bottom: 0 }}>
            <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
            <XAxis dataKey="data" fontSize={11} stroke="#94a3b8" minTickGap={24} />
            <YAxis fontSize={11} stroke="#94a3b8" tickFormatter={(v) => brl(v)} width={72} />
            <Tooltip formatter={(v) => brl(v)} />
            <Line type="monotone" dataKey="minimo" name="Menor" stroke="#059669" strokeWidth={2} dot={false} />
            <Line type="monotone" dataKey="medio" name="Médio" stroke="#94a3b8" strokeWidth={1.5} strokeDasharray="4 4" dot={false} />
            <Line type="monotone" dataKey="maximo" name="Maior" stroke="#ef4444" strokeWidth={1} dot={false} />
          </LineChart>
        </ResponsiveContainer>
      ) : (
        <p className="text-sm text-slate-400 text-center py-8">
          Histórico insuficiente para o gráfico (precisa de 2+ dias).
        </p>
      )}
    </div>
  )
}

function Tendencia({ resumo }) {
  const mapa = {
    subiu: { txt: `▲ subiu ${resumo.variacao_pct}%`, cls: 'bg-red-100 text-red-700' },
    baixou: { txt: `▼ baixou ${Math.abs(resumo.variacao_pct)}%`, cls: 'bg-brand-100 text-brand-700' },
    estavel: { txt: '● estável', cls: 'bg-slate-100 text-slate-600' },
  }
  const t = mapa[resumo.tendencia] ?? mapa.estavel
  return (
    <span className={`shrink-0 text-sm font-semibold rounded-full px-3 py-1 ${t.cls}`}>
      {t.txt}
    </span>
  )
}

function Mini({ titulo, valor, cor = 'text-slate-900' }) {
  return (
    <div className="bg-slate-50 rounded-xl p-3">
      <div className={`text-lg font-bold ${cor}`}>{valor}</div>
      <div className="text-xs text-slate-500">{titulo}</div>
    </div>
  )
}

function SemDados() {
  return (
    <div className="text-center text-slate-500 py-16 bg-white rounded-2xl border border-slate-200 space-y-2">
      <p className="text-4xl">📊</p>
      <p className="font-medium text-slate-700">Ainda não há histórico suficiente</p>
      <p className="text-sm">
        A análise aparece conforme a coleta acumula dados dia após dia.
      </p>
    </div>
  )
}
