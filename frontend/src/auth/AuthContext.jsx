import { createContext, useContext, useState, useCallback } from 'react'
import api from '../api/client'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(() => {
    const stored = localStorage.getItem('user')
    return stored ? JSON.parse(stored) : null
  })

  const persist = useCallback((token, userData) => {
    localStorage.setItem('token', token)
    localStorage.setItem('user', JSON.stringify(userData))
    setUser(userData)
  }, [])

  const login = useCallback(
    async (email, password) => {
      const { data } = await api.post('/login', { email, password })
      persist(data.token, data.user)
      return data.user
    },
    [persist],
  )

  const register = useCallback(
    async (name, email, password) => {
      const { data } = await api.post('/register', { name, email, password })
      persist(data.token, data.user)
      return data.user
    },
    [persist],
  )

  const logout = useCallback(async () => {
    try {
      await api.post('/logout')
    } catch {
      // ignora — vamos limpar localmente de qualquer forma
    }
    localStorage.removeItem('token')
    localStorage.removeItem('user')
    setUser(null)
  }, [])

  return (
    <AuthContext.Provider value={{ user, login, register, logout }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth precisa estar dentro de <AuthProvider>')
  return ctx
}
