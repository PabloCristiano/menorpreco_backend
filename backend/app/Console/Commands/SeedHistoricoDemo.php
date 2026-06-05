<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MenorprecoHistoricoPreco;
use Carbon\Carbon;

class SeedHistoricoDemo extends Command
{
    protected $signature = 'app:seed-historico-demo {--dias=45 : Dias de histórico para gerar} {--limpar : Remove os dados de demonstração (datas anteriores a hoje)}';

    protected $description = 'Gera histórico de preços fictício (passado) para visualizar os gráficos de análise. Reversível com --limpar.';

    public function handle(): int
    {
        $hoje = Carbon::today();

        if ($this->option('limpar')) {
            $removidos = MenorprecoHistoricoPreco::where('data_coleta', '<', $hoje->toDateString())->delete();
            $this->info("🧹 Removidos {$removidos} registros de demonstração (datas < hoje).");
            return self::SUCCESS;
        }

        $dias = (int) $this->option('dias');

        // Usa os registros de hoje como ponto de partida (preço atual real)
        $base = MenorprecoHistoricoPreco::where('data_coleta', $hoje->toDateString())->get();

        if ($base->isEmpty()) {
            $this->warn('Nenhum registro de hoje para usar como base. Rode a coleta antes.');
            return self::FAILURE;
        }

        $this->info("📈 Gerando {$dias} dias de histórico para {$base->count()} pares produto/loja…");
        $bar = $this->output->createProgressBar($base->count());

        $criados = 0;
        foreach ($base as $reg) {
            $preco = (float) $reg->preco;

            // Caminhada aleatória para trás (preço do passado oscila ao redor do atual)
            for ($d = 1; $d <= $dias; $d++) {
                $data = $hoje->copy()->subDays($d)->toDateString();

                // varia entre -3% e +3% por dia, acumulando para trás
                $fator = 1 + (mt_rand(-30, 30) / 1000);
                $preco = round(max(0.5, $preco * $fator), 2);

                MenorprecoHistoricoPreco::updateOrCreate(
                    [
                        'produto_id'         => $reg->produto_id,
                        'estabelecimento_id' => $reg->estabelecimento_id,
                        'data_coleta'        => $data,
                    ],
                    [
                        'preco'         => $preco,
                        'datahora_nota' => Carbon::parse($data)->setTime(10, 0),
                    ]
                );
                $criados++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✅ {$criados} registros de demonstração criados. Para remover: php artisan app:seed-historico-demo --limpar");

        return self::SUCCESS;
    }
}
