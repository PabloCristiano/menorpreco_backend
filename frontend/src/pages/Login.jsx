import { useState } from 'react'
import { useNavigate, Navigate } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'

export default function Login() {
  const { user, login, register } = useAuth()
  const navigate = useNavigate()

  const [modo, setModo] = useState('login') // 'login' | 'register'
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [erro, setErro] = useState(null)
  const [carregando, setCarregando] = useState(false)

  if (user) return <Navigate to="/" replace />

  const onSubmit = async (e) => {
    e.preventDefault()
    setErro(null)
    setCarregando(true)
    try {
      if (modo === 'login') {
        await login(email, password)
      } else {
        await register(name, email, password)
      }
      navigate('/')
    } catch (err) {
      const data = err.response?.data
      setErro(
        data?.message ||
          Object.values(data?.errors ?? {})?.[0]?.[0] ||
          'Não foi possível autenticar. Verifique os dados.',
      )
    } finally {
      setCarregando(false)
    }
  }

  return (
    <div className="min-h-screen flex items-center justify-center px-4 bg-gradient-to-br from-emerald-50 to-slate-100">
      <div className="w-full max-w-sm">
        <div className="text-center mb-8">
          <div className="text-4xl mb-2">🏷️</div>
          <h1 className="text-2xl font-bold text-slate-900">Menor Preço</h1>
          <p className="text-slate-500 text-sm mt-1">
            Compare preços e ache as lojas mais baratas
          </p>
        </div>

        <form
          onSubmit={onSubmit}
          className="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4"
        >
          <div className="flex rounded-lg bg-slate-100 p-1 text-sm font-medium">
            <button
              type="button"
              onClick={() => setModo('login')}
              className={`flex-1 py-1.5 rounded-md transition ${
                modo === 'login' ? 'bg-white shadow-sm' : 'text-slate-500'
              }`}
            >
              Entrar
            </button>
            <button
              type="button"
              onClick={() => setModo('register')}
              className={`flex-1 py-1.5 rounded-md transition ${
                modo === 'register' ? 'bg-white shadow-sm' : 'text-slate-500'
              }`}
            >
              Criar conta
            </button>
          </div>

          {modo === 'register' && (
            <Campo
              label="Nome"
              type="text"
              value={name}
              onChange={setName}
              placeholder="Seu nome"
            />
          )}
          <Campo
            label="E-mail"
            type="email"
            value={email}
            onChange={setEmail}
            placeholder="voce@email.com"
          />
          <Campo
            label="Senha"
            type="password"
            value={password}
            onChange={setPassword}
            placeholder="••••••"
          />

          {erro && (
            <div className="text-sm text-red-600 bg-red-50 rounded-lg px-3 py-2">
              {erro}
            </div>
          )}

          <button
            type="submit"
            disabled={carregando}
            className="w-full bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white font-medium py-2.5 rounded-lg transition"
          >
            {carregando
              ? 'Aguarde…'
              : modo === 'login'
                ? 'Entrar'
                : 'Criar conta'}
          </button>
        </form>
      </div>
    </div>
  )
}

function Campo({ label, type, value, onChange, placeholder }) {
  return (
    <label className="block">
      <span className="text-sm font-medium text-slate-700">{label}</span>
      <input
        type={type}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        placeholder={placeholder}
        required
        className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 outline-none"
      />
    </label>
  )
}
