import { Routes, Route } from 'react-router-dom'
import Layout from './components/Layout'
import ProtectedRoute from './components/ProtectedRoute'
import Login from './pages/Login'
import Busca from './pages/Busca'
import Comparacao from './pages/Comparacao'
import MinhaLista from './pages/MinhaLista'
import Coleta from './pages/Coleta'
import Analise from './pages/Analise'
import Mercados from './pages/Mercados'
import Monitorados from './pages/Monitorados'
import ProdutoDetalhe from './pages/ProdutoDetalhe'

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />

      <Route
        element={
          <ProtectedRoute>
            <Layout />
          </ProtectedRoute>
        }
      >
        <Route path="/" element={<Busca />} />
        <Route path="/comparacao" element={<Comparacao />} />
        <Route path="/minha-lista" element={<MinhaLista />} />
        <Route path="/mercados" element={<Mercados />} />
        <Route path="/coleta" element={<Coleta />} />
        <Route path="/analise" element={<Analise />} />
        <Route path="/monitorados" element={<Monitorados />} />
        <Route path="/monitorados/:id" element={<ProdutoDetalhe />} />
      </Route>
    </Routes>
  )
}
