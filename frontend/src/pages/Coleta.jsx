import { useEffect, useState, useCallback, useRef } from 'react'
import api from '../api/client'

const STATUS = {
  rodando: { label: 'Em andamento', cls: 'bg-amber-100 text-amber-700' },
  sucesso: { label: 'Concluída', cls: 'bg-brand-100 text-brand-700' },
  erro: { label: 'Erro', cls: 'bg-red-100 text-red-700' },
}

function dataHora(iso) {
  if (!iso) return '—'
  const d = new Date(iso)
  return Number.isNaN(d.getTime()) ? iso : d.toLocaleString('pt-BR')
}

export default function Coleta() {
  const [ultima, setUltima] = useState(null)
  const [recentes, setRecentes] = useState([])
  const [carregando, setCarregando] = useState(true)
  const [disparando, setDisparando] = useState(false)
  const [msg, setMsg] = useState(null)
  const pollRef = useRef(null)

  const carregar = useCallback(async () => {
    const { data } = await api.get('/coleta/ultima')
    setUltima(data.ultima)
    setRecentes(data.recentes ?? [])
    setCarregando(false)
    return data.ultima
  }, [])

  // Polling enquanto houver coleta em andamento
  useEffect(() => {
    carregar()
    return () => clearInterval(pollRef.current)
  }, [carregar])

  useEffect(() => {
    clearInterval(pollRef.current)
    if (ultima?.status === 'rodando') {
      pollRef.current = setInterval(carregar, 4000)
    }
    return () => clearInterval(pollRef.current)
  }, [ultima?.status, ultima?.id, carregar])

  const sincronizar = async () => {
    setDisparando(true)
    setMsg(null)
    try {
      const { data } = await api.post('/coleta/sincronizar')
      setMsg(
        data.ja_rodando
          ? 'Já existe uma coleta em andamento.'
          : 'Coleta iniciada em segundo plano…',
      )
      await carregar()
    } catch {
      setMsg('❌ Não foi possível iniciar a coleta.')
    } finally {
      setDisparando(false)
    }
  }

  const rodando = ultima?.status === 'rodando'

  if (carregando) {
    return <div className="text-center text-slate-400 py-16">Carregando…</div>
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Coleta de preços</h1>
          <p className="text-slate-500 text-sm">
            Automática todo dia às 06:00 · ou dispare manualmente a qualquer momento.
          </p>
        </div>
        <button
          onClick={sincronizar}
          disabled={disparando || rodando}
          className="shrink-0 bg-brand-600 hover:bg-brand-700 disabled:opacity-60 text-white font-semibold px-5 py-2.5 rounded-xl transition inline-flex items-center gap-2"
        >
          {rodando ? (
            <>
              <Spinner /> Coletando…
            </>
          ) : (
            '↻ Sincronizar agora'
          )}
        </button>
      </div>

      {msg && (
        <div className="text-sm text-slate-700 bg-slate-100 rounded-lg px-4 py-2">{msg}</div>
      )}

      {/* Card da última coleta */}
      {ultima && (
        <div className="bg-white rounded-2xl border border-slate-200 p-5">
          <div className="flex items-center justify-between mb-4">
            <h2 className="font-semibold text-slate-800">Última coleta</h2>
            <span
              className={`text-xs font-semibold rounded-full px-2.5 py-1 ${
                STATUS[ultima.status]?.cls ?? 'bg-slate-100 text-slate-600'
              }`}
            >
              {STATUS[ultima.status]?.label ?? ultima.status}
              {ultima.gatilho === 'agendado' ? ' · agendada' : ' · manual'}
            </span>
          </div>
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <Metric label="Produtos atualizados" value={ultima.total_processados} />
            <Metric label="Consultas à API" value={ultima.total_chamadas_api} />
            <Metric label="Erros" value={ultima.total_erros} />
            <Metric
              label="Duração"
              value={ultima.duracao_seg ? `${Math.round(ultima.duracao_seg)}s` : '—'}
            />
          </div>
          <p className="text-xs text-slate-400 mt-4">
            Iniciada em {dataHora(ultima.iniciado_em)}
            {ultima.finalizado_em && ` · finalizada em ${dataHora(ultima.finalizado_em)}`}
          </p>
          {ultima.mensagem_erro && (
            <p className="text-xs text-red-500 mt-1">{ultima.mensagem_erro}</p>
          )}
        </div>
      )}

      {/* Histórico */}
      <div>
        <h2 className="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-2">
          Histórico recente
        </h2>
        <div className="bg-white rounded-2xl border border-slate-200 overflow-hidden">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-slate-200 text-slate-500">
                <th className="text-left font-medium p-3">Quando</th>
                <th className="text-left font-medium p-3">Origem</th>
                <th className="text-center font-medium p-3">Status</th>
                <th className="text-right font-medium p-3">Atualizados</th>
                <th className="text-right font-medium p-3">Erros</th>
                <th className="text-right font-medium p-3">Duração</th>
              </tr>
            </thead>
            <tbody>
              {recentes.map((r) => (
                <tr key={r.id} className="border-b border-slate-100 last:border-0">
                  <td className="p-3 text-slate-700">{dataHora(r.iniciado_em)}</td>
                  <td className="p-3 text-slate-500">
                    {r.gatilho === 'agendado' ? 'Agendada' : 'Manual'}
                  </td>
                  <td className="p-3 text-center">
                    <span
                      className={`text-xs font-semibold rounded-full px-2 py-0.5 ${
                        STATUS[r.status]?.cls ?? 'bg-slate-100 text-slate-600'
                      }`}
                    >
                      {STATUS[r.status]?.label ?? r.status}
                    </span>
                  </td>
                  <td className="p-3 text-right text-slate-700">{r.total_processados}</td>
                  <td className="p-3 text-right text-slate-700">{r.total_erros}</td>
                  <td className="p-3 text-right text-slate-500">
                    {r.duracao_seg ? `${Math.round(r.duracao_seg)}s` : '—'}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  )
}

function Metric({ label, value }) {
  return (
    <div>
      <div className="text-2xl font-bold text-slate-900">{value ?? 0}</div>
      <div className="text-xs text-slate-500">{label}</div>
    </div>
  )
}

function Spinner() {
  return (
    <span className="inline-block w-4 h-4 border-2 border-white/40 border-t-white rounded-full animate-spin" />
  )
}
