import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'

const navItem = ({ isActive }) =>
  `px-3 py-2 rounded-lg text-sm font-medium transition-colors ${
    isActive
      ? 'bg-emerald-600 text-white'
      : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
  }`

export default function Layout() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()

  const handleLogout = async () => {
    await logout()
    navigate('/login')
  }

  return (
    <div className="min-h-full flex flex-col">
      <header className="bg-white border-b border-slate-200 sticky top-0 z-10">
        <div className="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
          <div className="flex items-center gap-6">
            <span className="text-lg font-bold text-emerald-700 flex items-center gap-2">
              <span className="text-xl">🏷️</span> Menor Preço
            </span>
            <nav className="flex items-center gap-1">
              <NavLink to="/" end className={navItem}>
                Busca
              </NavLink>
              <NavLink to="/comparacao" className={navItem}>
                Comparação
              </NavLink>
              <NavLink to="/minha-lista" className={navItem}>
                Minha Lista
              </NavLink>
              <NavLink to="/analise" className={navItem}>
                Análise
              </NavLink>
              <NavLink to="/coleta" className={navItem}>
                Coleta
              </NavLink>
            </nav>
          </div>
          <div className="flex items-center gap-3">
            <span className="text-sm text-slate-500 hidden sm:inline">
              {user?.name}
            </span>
            <button
              onClick={handleLogout}
              className="text-sm text-slate-600 hover:text-red-600 font-medium"
            >
              Sair
            </button>
          </div>
        </div>
      </header>

      <main className="flex-1 max-w-6xl w-full mx-auto px-4 py-8">
        <Outlet />
      </main>
    </div>
  )
}
