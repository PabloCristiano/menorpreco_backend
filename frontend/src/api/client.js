import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api',
  headers: {
    Accept: 'application/json',
  },
})

// Injeta o token Sanctum em toda requisição, se houver
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Desloga automaticamente em 401
api.interceptors.response.use(
  (res) => res,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      if (window.location.pathname !== '/login') {
        window.location.href = '/login'
      }
    }
    return Promise.reject(error)
  },
)

// Localizações disponíveis (geohash usado pela API do Nota Paraná)
export const LOCAIS = [
  { codigo: '6g9fp8frx', nome: 'Cascavel' },
  { codigo: '6g3ntyecf', nome: 'Foz do Iguaçu' },
  { codigo: '6g9g357w3', nome: 'Toledo' },
]

// Opções de raio de busca, em km
export const RAIOS = [2, 5, 10, 20, 50]

export default api
