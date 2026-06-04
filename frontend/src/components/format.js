export const brl = (valor) => {
  const n = Number(valor)
  if (Number.isNaN(n)) return '—'
  return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

export const data = (iso) => {
  if (!iso) return '—'
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return iso
  return d.toLocaleDateString('pt-BR')
}

export const km = (valor) => {
  const n = Number(valor)
  if (Number.isNaN(n)) return '—'
  return `${n.toLocaleString('pt-BR', { maximumFractionDigits: 1 })} km`
}

export const enderecoEstabelecimento = (est) => {
  if (!est) return ''
  const partes = [
    [est.tp_logr, est.nm_logr, est.nr_logr].filter(Boolean).join(' '),
    est.bairro,
    [est.cidade, est.uf].filter(Boolean).join('/'),
  ].filter(Boolean)
  return partes.join(' · ')
}
