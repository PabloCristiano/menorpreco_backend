import { useState } from 'react'
import api, { LOCAIS, RAIOS } from '../api/client'
import { brl, km, data as fmtData, enderecoEstabelecimento } from '../components/format'

export default function Busca() {
  const [termo, setTermo] = useState('')
  const [local, setLocal] = useState(LOCAIS[0].codigo)
  const [raio, setRaio] = useState(5)

  // passo do fluxo: 'inicio' | 'categorias' | 'produtos'
  const [passo, setPasso] = useState('inicio')
  const [categorias, setCategorias] = useState([])
  const [categoria, setCategoria] = useState(null)
  const [produtos, setProdutos] = useState([])

  const [carregando, setCarregando] = useState(false)
  const [erro, setErro] = useState(null)
  const [salvando, setSalvando] = useState(false)
  const [msgSalvar, setMsgSalvar] = useState(null)

  // gtins de produtos e códigos de mercados já adicionados à minha lista
  const [produtosNaLista, setProdutosNaLista] = useState(() => new Set())
  const [mercadosNaLista, setMercadosNaLista] = useState(() => new Set())

  const nomeLocal = LOCAIS.find((l) => l.codigo === local)?.nome

  const adicionarProdutoLista = async (p) => {
    if (!p.gtin) return
    try {
      await api.post('/minha-lista/produtos', {
        gtin: p.gtin,
        ncm: p.ncm ?? '00000000',
        descricao: p.desc ?? '',
        categoria: categoria?.id ?? 0,
        local,
      })
      setProdutosNaLista((s) => new Set(s).add(p.gtin))
    } catch {
      setErro('Não foi possível adicionar o produto à lista.')
    }
  }

  const adicionarMercado = async (est) => {
    if (!est?.codigo) return
    try {
      await api.post('/minha-lista/estabelecimentos', {
        codigo: est.codigo,
        nome_fantasia: est.nm_fan ?? null,
        razao_social: est.nm_emp ?? null,
        bairro: est.bairro ?? null,
        cidade: est.mun ?? null,
        uf: est.uf ?? null,
        tp_logr: est.tp_logr ?? null,
        nm_logr: est.nm_logr ?? null,
        nr_logr: est.nr_logr ?? null,
      })
      setMercadosNaLista((s) => new Set(s).add(est.codigo))
    } catch {
      setErro('Não foi possível adicionar o estabelecimento à lista.')
    }
  }

  // Detecta código de barras: só dígitos, 8 a 14 caracteres (EAN-8/13, GTIN-14)
  const ehCodigoBarras = (txt) => /^\d{8,14}$/.test(txt.trim())

  // Submit: decide entre buscar por código de barras (gtin) ou por termo
  const onBuscar = async (e) => {
    e?.preventDefault()
    const t = termo.trim()
    if (!t) return

    if (ehCodigoBarras(t)) {
      // Código de barras → pula a etapa de categoria e vai direto aos produtos
      setCategorias([])
      await buscarProdutos({ id: 0, desc: 'Código de barras', gtin: t })
    } else {
      await buscarCategorias(t)
    }
  }

  // Passo 1 (busca por texto): lista as categorias do termo
  const buscarCategorias = async (t) => {
    setCarregando(true)
    setErro(null)
    setMsgSalvar(null)
    setCategoria(null)
    setProdutos([])
    try {
      const { data } = await api.get('/menorpreco/categorias', {
        params: { termo: t, local, raio },
      })
      const cats = (data.categorias ?? []).sort((a, b) => b.qtd - a.qtd)
      setCategorias(cats)
      setPasso('categorias')
      if (cats.length === 0) setErro('Nenhuma categoria encontrada para esse termo.')
    } catch {
      setErro('Erro ao consultar categorias. Tente novamente.')
    } finally {
      setCarregando(false)
    }
  }

  // Passo 2: busca os produtos (por categoria escolhida ou por gtin)
  const buscarProdutos = async (cat) => {
    setCarregando(true)
    setErro(null)
    setMsgSalvar(null)
    setCategoria(cat)
    try {
      const params = cat.gtin
        ? { gtin: cat.gtin, categoria: 0, local, raio, ordem: 1 }
        : { termo: termo.trim(), categoria: cat.id, local, raio, ordem: 1 }
      const { data } = await api.get('/menorpreco/consultar', { params })
      const lista = (data?.data?.produtos ?? []).sort(
        (a, b) => Number(a.valor) - Number(b.valor),
      )
      setProdutos(lista)
      setPasso('produtos')
    } catch {
      setErro('Erro ao consultar produtos. Tente novamente.')
    } finally {
      setCarregando(false)
    }
  }

  const monitorar = async () => {
    if (!categoria) return
    setSalvando(true)
    setMsgSalvar(null)
    try {
      const params = categoria.gtin
        ? { gtin: categoria.gtin, categoria: 0, local, raio }
        : { termo: termo.trim(), categoria: categoria.id, local, raio }
      const { data } = await api.get('/menorpreco/salvar', { params })
      setMsgSalvar(`✅ ${data.salvos} registro(s) salvos para monitoramento.`)
    } catch {
      setMsgSalvar('❌ Não foi possível salvar.')
    } finally {
      setSalvando(false)
    }
  }

  return (
    <div className="space-y-6">
      {/* Cabeçalho de busca */}
      <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
        <h1 className="text-xl font-bold text-slate-900 mb-1">
          Onde está mais barato?
        </h1>
        <p className="text-sm text-slate-500 mb-4">
          Busque um produto e veja as lojas com o menor preço, ao vivo.
        </p>

        <form onSubmit={onBuscar} className="flex flex-col gap-3">
          <input
            type="text"
            value={termo}
            onChange={(e) => setTermo(e.target.value)}
            placeholder="Nome (leite, café…) ou código de barras"
            className="w-full rounded-xl border border-slate-300 px-4 py-3 text-base focus:border-brand-500 focus:ring-2 focus:ring-brand-100 outline-none"
          />
          <div className="flex flex-col sm:flex-row gap-3">
            <Select
              label="Cidade"
              value={local}
              onChange={setLocal}
              options={LOCAIS.map((l) => ({ value: l.codigo, label: l.nome }))}
            />
            <Select
              label="Raio"
              value={raio}
              onChange={(v) => setRaio(Number(v))}
              options={RAIOS.map((r) => ({ value: r, label: `${r} km` }))}
            />
            <button
              type="submit"
              disabled={carregando}
              className="flex-1 bg-brand-600 hover:bg-brand-700 disabled:opacity-60 text-white font-semibold px-6 py-3 rounded-xl transition"
            >
              {carregando && passo === 'inicio' ? 'Buscando…' : 'Buscar'}
            </button>
          </div>
        </form>
      </div>

      {erro && (
        <div className="text-sm text-red-600 bg-red-50 rounded-xl px-4 py-3">
          {erro}
        </div>
      )}

      {/* Passo 1 — escolha de categoria */}
      {passo !== 'inicio' && categorias.length > 0 && (
        <CategoriaSeletor
          categorias={categorias}
          selecionada={categoria}
          onSelecionar={buscarProdutos}
        />
      )}

      {/* Passo 2 — produtos */}
      {passo === 'produtos' && (
        <ListaProdutos
          produtos={produtos}
          carregando={carregando}
          nomeLocal={nomeLocal}
          categoria={categoria}
          onMonitorar={monitorar}
          salvando={salvando}
          msgSalvar={msgSalvar}
          onAddProduto={adicionarProdutoLista}
          onAddMercado={adicionarMercado}
          produtosNaLista={produtosNaLista}
          mercadosNaLista={mercadosNaLista}
        />
      )}
    </div>
  )
}

function Select({ label, value, onChange, options }) {
  return (
    <label className="flex items-center gap-2 rounded-xl border border-slate-300 px-3 bg-white">
      <span className="text-xs font-medium text-slate-400">{label}</span>
      <select
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className="py-3 text-sm bg-transparent outline-none cursor-pointer"
      >
        {options.map((o) => (
          <option key={o.value} value={o.value}>
            {o.label}
          </option>
        ))}
      </select>
    </label>
  )
}

function CategoriaSeletor({ categorias, selecionada, onSelecionar }) {
  return (
    <div>
      <h2 className="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-2">
        1 · Escolha a categoria
      </h2>
      <div className="flex flex-wrap gap-2">
        {categorias.map((c) => {
          const ativa = selecionada?.id === c.id
          return (
            <button
              key={c.id}
              onClick={() => onSelecionar(c)}
              className={`flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium border transition ${
                ativa
                  ? 'bg-brand-600 border-brand-600 text-white'
                  : 'bg-white border-slate-200 text-slate-700 hover:border-brand-300'
              }`}
            >
              {c.desc}
              <span
                className={`text-xs rounded-full px-1.5 py-0.5 ${
                  ativa ? 'bg-white/20' : 'bg-slate-100 text-slate-500'
                }`}
              >
                {c.qtd}
              </span>
            </button>
          )
        })}
      </div>
    </div>
  )
}

function ListaProdutos({
  produtos,
  carregando,
  nomeLocal,
  categoria,
  onMonitorar,
  salvando,
  msgSalvar,
  onAddProduto,
  onAddMercado,
  produtosNaLista,
  mercadosNaLista,
}) {
  if (carregando) {
    return <div className="text-center text-slate-400 py-12">Carregando produtos…</div>
  }

  // separa produtos com e sem loja identificada
  const comLoja = produtos.filter((p) => p.estabelecimento?.nm_fan || p.estabelecimento?.nm_emp)
  const menorPreco = comLoja.length ? Number(comLoja[0].valor) : null

  return (
    <div>
      <div className="flex items-center justify-between mb-2 gap-3">
        <h2 className="text-sm font-semibold text-slate-500 uppercase tracking-wide">
          2 · {categoria?.desc} — {comLoja.length} oferta(s) em {nomeLocal}
        </h2>
        <button
          onClick={onMonitorar}
          disabled={salvando}
          className="shrink-0 text-sm font-medium text-brand-700 hover:text-brand-800 border border-brand-200 hover:bg-brand-50 rounded-lg px-3 py-1.5 disabled:opacity-60"
        >
          {salvando ? 'Salvando…' : '+ Monitorar'}
        </button>
      </div>

      {msgSalvar && (
        <div className="text-sm text-slate-700 bg-slate-100 rounded-lg px-4 py-2 mb-3">
          {msgSalvar}
        </div>
      )}

      {comLoja.length === 0 ? (
        <div className="text-center text-slate-500 py-12 bg-white rounded-2xl border border-slate-200">
          Nenhuma oferta com loja identificada nessa categoria/raio.
        </div>
      ) : (
        <div className="space-y-2.5">
          {comLoja.map((p, i) => (
            <ProdutoCard
              key={`${p.estabelecimento?.codigo}-${i}`}
              produto={p}
              destaque={Number(p.valor) === menorPreco}
              onAddProduto={onAddProduto}
              onAddMercado={onAddMercado}
              produtoNaLista={produtosNaLista.has(p.gtin)}
              mercadoNaLista={mercadosNaLista.has(p.estabelecimento?.codigo)}
            />
          ))}
        </div>
      )}
    </div>
  )
}

function ProdutoCard({
  produto: p,
  destaque,
  onAddProduto,
  onAddMercado,
  produtoNaLista,
  mercadoNaLista,
}) {
  const est = p.estabelecimento ?? {}
  return (
    <div
      className={`bg-white rounded-2xl border p-4 flex items-start justify-between gap-4 ${
        destaque ? 'border-brand-400 ring-1 ring-brand-200' : 'border-slate-200'
      }`}
    >
      <div className="min-w-0">
        {destaque && (
          <span className="inline-block text-[11px] font-bold text-brand-700 bg-brand-100 rounded-full px-2 py-0.5 mb-1">
            🏆 MENOR PREÇO
          </span>
        )}
        <h3 className="font-medium text-slate-900 leading-snug">{p.desc}</h3>
        <p className="text-sm font-medium text-slate-700 mt-1">
          {est.nm_fan || est.nm_emp}
        </p>
        <p className="text-xs text-slate-500 mt-0.5">{enderecoEstabelecimento(est)}</p>
        <div className="flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-slate-400 mt-1">
          {p.distkm != null && <span>📍 {km(p.distkm)}</span>}
          {p.datahora && <span>🕒 {fmtData(p.datahora)}</span>}
          {p.gtin && <span>GTIN {p.gtin}</span>}
        </div>

        {/* Ações: adicionar à minha lista */}
        <div className="flex flex-wrap gap-2 mt-2.5">
          <Chip
            ativo={produtoNaLista}
            desabilitado={!p.gtin}
            onClick={() => onAddProduto(p)}
            labelInativo={p.gtin ? '+ lista' : 'sem GTIN'}
            labelAtivo="✓ na lista"
          />
          <Chip
            ativo={mercadoNaLista}
            desabilitado={!est.codigo}
            onClick={() => onAddMercado(est)}
            labelInativo="+ estabelecimento"
            labelAtivo="✓ estabelecimento"
          />
        </div>
      </div>
      <div className="text-right shrink-0">
        <div className={`text-xl font-bold ${destaque ? 'text-brand-600' : 'text-slate-900'}`}>
          {brl(p.valor)}
        </div>
        {p.valor_tabela && Number(p.valor_tabela) > Number(p.valor) && (
          <div className="text-xs text-slate-400 line-through">{brl(p.valor_tabela)}</div>
        )}
      </div>
    </div>
  )
}

function Chip({ ativo, desabilitado, onClick, labelInativo, labelAtivo }) {
  if (ativo) {
    return (
      <span className="text-xs font-medium text-brand-700 bg-brand-50 border border-brand-200 rounded-full px-2.5 py-1">
        {labelAtivo}
      </span>
    )
  }
  return (
    <button
      onClick={onClick}
      disabled={desabilitado}
      className="text-xs font-medium text-slate-600 border border-slate-200 hover:border-brand-300 hover:text-brand-700 rounded-full px-2.5 py-1 disabled:opacity-40 disabled:cursor-not-allowed"
    >
      {labelInativo}
    </button>
  )
}
