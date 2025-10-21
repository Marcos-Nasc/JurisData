<?php
// Inicia a sessão se ainda não tiver sido iniciada.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. INCLUIR ARQUIVOS ESSENCIAIS
require_once '../includes/verifica_login.php';
require_once '../includes/conexao.php';
require_once '../includes/carregar_tema.php'; // Define $themeClass

// --- LÓGICA PHP PARA BUSCAR E PROCESSAR OS DADOS ---

// Pega o ano do filtro, ou usa o ano atual como padrão
$ano_selecionado = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT) ?: date('Y');
$anos_disponiveis = range(date('Y'), 2020);
$meses_nomes = ["", "Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho", "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"];

// Inicializa a estrutura de dados para 12 meses
$dados_mensais = [];
for ($i = 1; $i <= 12; $i++) {
    $dados_mensais[$i] = [
        'mes_nome' => $meses_nomes[$i],
        'civeis' => 0,
        'trabalhistas' => 0,
        'outros' => 0, // Adiciona a nova categoria
        'total' => 0
    ];
}

try {
    // Busca todos os registros do ano selecionado
    $sql = "SELECT mes, tipo_vara, quantidade FROM relatorio_mensal_processos WHERE ano = :ano";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':ano' => $ano_selecionado]);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Lógica para "pivotar" os dados: agrupar por mês
    foreach ($resultados as $row) {
        $mes = (int)$row['mes'];
        if ($mes >= 1 && $mes <= 12) { // Garante que o mês é válido
            $quantidade = (int)$row['quantidade'];
            
            if (stripos($row['tipo_vara'], 'CÍVEIS') !== false || stripos($row['tipo_vara'], 'CIVEIS') !== false) {
                $dados_mensais[$mes]['civeis'] += $quantidade;
            } elseif (stripos($row['tipo_vara'], 'TRABALHISTA') !== false) {
                $dados_mensais[$mes]['trabalhistas'] += $quantidade;
            } else {
                $dados_mensais[$mes]['outros'] += $quantidade; // Agrupa os demais em "Outros"
            }
            
            $dados_mensais[$mes]['total'] += $quantidade;
        }
    }

} catch (PDOException $e) {
    die("Erro ao consultar o banco de dados: " . $e->getMessage());
}

// --- PREPARA OS DADOS PARA O CHART.JS ---
$labels_para_grafico = $meses_nomes;
array_shift($labels_para_grafico); // Remove o primeiro item vazio do ARRAY
$labels_para_grafico_curto = array_map(function($m) { return substr($m, 0, 3); }, $labels_para_grafico);
$chart_labels = json_encode($labels_para_grafico_curto); // Converte o array final para JSON

$chart_civeis_data = json_encode(array_column($dados_mensais, 'civeis'));
$chart_trabalhistas_data = json_encode(array_column($dados_mensais, 'trabalhistas'));
$chart_outros_data = json_encode(array_column($dados_mensais, 'outros')); // Prepara dados para o gráfico

?>
<!DOCTYPE html>
<html lang="pt-br" class="<?php echo $themeClass; ?>">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Volume de Entradas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="app-layout">
        <?php require_once '../includes/sidebar.php'; ?>
        <main class="main-content">
            <h1 class="text-2xl font-bold text-gray-800 mb-6">Relatório de Volume de Entradas de Processos</h1>
            
            <div class="bg-white p-4 rounded-lg shadow mb-6">
                <form action="volume_entrada.php" method="GET" class="flex items-center gap-4">
                    <div>
                        <label for="ano" class="block text-sm font-medium text-gray-700">Ano</label>
                        <select name="ano" id="ano" class="p-2 border rounded-md">
                            <?php foreach($anos_disponiveis as $ano_opcao): ?>
                                <option value="<?php echo $ano_opcao; ?>"<?php echo ($ano_opcao == $ano_selecionado) ? ' selected' : ''; ?>><?php echo $ano_opcao; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded self-end">
                        Gerar Relatório
                    </button>
                </form>
            </div>

            <div class="bg-white p-6 rounded-lg shadow mb-6">
                <h2 class="text-lg font-semibold text-gray-700 mb-4">Total de Novas Ações por Mês</h2>
                <div>
                    <canvas id="graficoVolumeEntradas" style="height: 400px;"></canvas>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                        <tr>
                            <th class="py-3 px-4">Mês</th>
                            <th class="py-3 px-4">Processos Cíveis</th>
                            <th class="py-3 px-4">Processos Trabalhistas</th>
                            <th class="py-3 px-4">Outros</th>
                            <th class="py-3 px-4">Total Mensal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dados_mensais as $dados_mes): ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="py-3 px-4 font-medium text-gray-900"><?php echo $dados_mes['mes_nome']; ?></td>
                            <td class="py-3 px-4"><?php echo $dados_mes['civeis']; ?></td>
                            <td class="py-3 px-4"><?php echo $dados_mes['trabalhistas']; ?></td>
                            <td class="py-3 px-4"><?php echo $dados_mes['outros']; ?></td>
                            <td class="py-3 px-4 font-semibold"><?php echo $dados_mes['total']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script src="../js/script.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('graficoVolumeEntradas').getContext('2d');
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo $chart_labels; ?>,
                datasets: [
                    {
                        label: 'Processos Cíveis',
                        data: <?php echo $chart_civeis_data; ?>,
                        backgroundColor: 'rgba(59, 130, 246, 0.7)',
                        borderColor: 'rgba(59, 130, 246, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Processos Trabalhistas',
                        data: <?php echo $chart_trabalhistas_data; ?>,
                        backgroundColor: 'rgba(249, 115, 22, 0.7)',
                        borderColor: 'rgba(249, 115, 22, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Outros',
                        data: <?php echo $chart_outros_data; ?>,
                        backgroundColor: 'rgba(107, 114, 128, 0.7)', // Cor cinza para "Outros"
                        borderColor: 'rgba(107, 114, 128, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } },
                    x: { grid: { display: false } } // Removido o stacked: true
                },
                plugins: {
                    legend: { position: 'top' },
                    tooltip: { mode: 'index', intersect: false }
                },
                interaction: { mode: 'index', intersect: false }
            }
        });
    });
    const BASE_URL = 'http://localhost/juridico'; 
    </script>
</body>
</html>