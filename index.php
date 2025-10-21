<?php
// 1. INCLUIR GUARDIÃO DE LOGIN E CONEXÃO
require_once 'includes/verifica_login.php';
require_once 'includes/conexao.php';
require_once 'includes/carregar_tema.php'; // Define $themeClass

// Pega o ano do filtro para os KPIs e Tabela, ou usa o ano atual como padrão
$ano_selecionado = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT) ?: date('Y');
$anos_disponiveis = range(date('Y'), 2020);

try {
    // 2. BUSCAR DADOS PARA OS CARDS (KPIs) FILTRADOS POR ANO
    $stmt_total_processos = $pdo->prepare("SELECT COUNT(id) AS total FROM processos_judiciais WHERE ano = :ano");
    $stmt_total_processos->execute([':ano' => $ano_selecionado]);
    $total_processos = $stmt_total_processos->fetchColumn();

    $stmt_valor_total = $pdo->prepare("SELECT SUM(valor_causa) AS total FROM processos_judiciais WHERE ano = :ano");
    $stmt_valor_total->execute([':ano' => $ano_selecionado]);
    $valor_total_causas = $stmt_valor_total->fetchColumn();

    $stmt_economia_total = $pdo->prepare("SELECT SUM(economia) AS total FROM processos_judiciais WHERE ano = :ano");
    $stmt_economia_total->execute([':ano' => $ano_selecionado]);
    $economia_total = $stmt_economia_total->fetchColumn();

    // 3. BUSCAR DADOS PARA O GRÁFICO (ÚLTIMOS 6 MESES - IGNORA O FILTRO DE ANO)
    $meses_nomes = [1=>'Jan', 2=>'Fev', 3=>'Mar', 4=>'Abr', 5=>'Mai', 6=>'Jun', 7=>'Jul', 8=>'Ago', 9=>'Set', 10=>'Out', 11=>'Nov', 12=>'Dez'];
    $chart_labels_map = [];
    $chart_data_map = [];

    for ($i = 5; $i >= 0; $i--) {
        $date = new DateTime("first day of -$i month");
        $month_key = $date->format('Y-m');
        $chart_labels_map[$month_key] = $meses_nomes[(int)$date->format('n')] . '/' . $date->format('y');
        $chart_data_map[$month_key] = ['civeis' => 0, 'trabalhistas' => 0];
    }

    $stmt_grafico = $pdo->query("SELECT ano, mes, tipo_vara, quantidade FROM relatorio_mensal_processos WHERE STR_TO_DATE(CONCAT(ano, '-', mes, '-01'), '%Y-%m-%d') >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)");
    $resultados_grafico = $stmt_grafico->fetchAll(PDO::FETCH_ASSOC);

    foreach ($resultados_grafico as $row) {
        $month_key = $row['ano'] . '-' . str_pad($row['mes'], 2, '0', STR_PAD_LEFT);
        if (array_key_exists($month_key, $chart_data_map)) {
            $quantidade = (int)$row['quantidade'];
            $tipo_vara_lower = strtolower($row['tipo_vara']);
            if (stripos($row['tipo_vara'], 'CÍVEIS') !== false || stripos($row['tipo_vara'], 'CIVEIS') !== false) {
                $chart_data_map[$month_key]['civeis'] += $quantidade;
            } elseif (strpos($tipo_vara_lower, 'trabalhista') !== false) {
                $chart_data_map[$month_key]['trabalhistas'] += $quantidade;
            }
        }
    }
    $chart_labels = array_values($chart_labels_map);
    $chart_civeis_data = array_column($chart_data_map, 'civeis');
    $chart_trabalhistas_data = array_column($chart_data_map, 'trabalhistas');

    // 4. BUSCAR DADOS PARA A TABELA DE PROCESSOS RECENTES (FILTRADO POR ANO)
    $stmt_recentes = $pdo->prepare("SELECT numero_processo, autor, data_recebimento FROM processos_judiciais WHERE ano = :ano ORDER BY data_recebimento DESC LIMIT 5");
    $stmt_recentes->execute([':ano' => $ano_selecionado]);
    $processos_recentes = $stmt_recentes->fetchAll();

} catch (PDOException $e) {
    die("Erro ao buscar dados para o dashboard: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="pt-br" class="<?php echo $themeClass; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema Jurídico</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .kpi-card { background-color: #FFFFFF; padding: 1.5rem; border-radius: 0.5rem; box-shadow: var(--sombra-caixa); display: flex; align-items: center; justify-content: space-between; }
        .kpi-icon { font-size: 3rem; }
        .kpi-text .kpi-title { color: var(--cor-texto-secundario); font-size: 0.875rem; font-weight: 500; }
        .kpi-text .kpi-value { color: var(--cor-texto-principal); font-size: 2rem; font-weight: 700; }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php require_once 'includes/sidebar.php'; ?>
        <main class="main-content">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-800">Dashboard</h1>
                <h2 class="text-lg text-gray-600">Bem-vindo(a), <?php echo htmlspecialchars($_SESSION['nome_usuario'] ?? 'Usuário'); ?>!</h2>
            </div>

            <div class="bg-white p-4 rounded-lg shadow-sm mb-6">
                <form action="index.php" method="GET" class="flex items-center gap-4">
                    <label for="ano" class="text-sm font-medium text-gray-700">Filtrar por Ano:</label>
                    <select name="ano" id="ano" class="p-2 border-gray-300 rounded-md shadow-sm">
                        <?php foreach($anos_disponiveis as $ano_opcao): ?>
                            <option value="<?php echo $ano_opcao; ?>" <?php echo ($ano_opcao == $ano_selecionado) ? 'selected' : ''; ?>><?php echo $ano_opcao; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Filtrar</button>
                </form>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
                <div class="kpi-card"><div class="kpi-text"><p class="kpi-title">Total de Processos (<?php echo $ano_selecionado; ?>)</p><p class="kpi-value"><?php echo $total_processos; ?></p></div><i class="fas fa-gavel kpi-icon" style="color: #2563eb;"></i></div>
                <div class="kpi-card"><div class="kpi-text"><p class="kpi-title">Risco Provisionado (<?php echo $ano_selecionado; ?>)</p><p class="kpi-value">R$ <?php echo number_format($valor_total_causas, 2, ',', '.'); ?></p></div><i class="fas fa-dollar-sign kpi-icon" style="color: #dc2626;"></i></div>
                <div class="kpi-card"><div class="kpi-text"><p class="kpi-title">Economia Total (<?php echo $ano_selecionado; ?>)</p><p class="kpi-value">R$ <?php echo number_format($economia_total, 2, ',', '.'); ?></p></div><i class="fas fa-piggy-bank kpi-icon" style="color: #16a34a;"></i></div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow">
                    <h3 class="font-semibold text-gray-800 mb-4">Volume de Entrada (Últimos 6 Meses)</h3>
                    <div style="position: relative; height: 400px;"><canvas id="graficoVolumeEntrada"></canvas></div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow">
    
    <h3 class="font-semibold text-gray-800 dark:text-gray-200 mb-4">
        Últimos Processos Adicionados em <?php echo $ano_selecionado; ?>
    </h3>
    
    <table class="w-full text-sm text-left">
        <thead class="text-xs text-gray-700 dark:text-gray-300 uppercase bg-gray-50 dark:bg-gray-700">
            <tr>
                <th class="py-2 px-4">Nº Processo</th>
                <th class="py-2 px-4">Autor</th>
            </tr>
        </thead>
        
        <tbody class="text-gray-900 dark:text-gray-300">
            <?php if(empty($processos_recentes)): ?>
                <tr>
                    <td colspan="2" class="text-center py-4">Nenhum processo para este ano.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($processos_recentes as $processo): ?>
                    <tr class="border-b dark:border-gray-700">
                        <td class="py-3 px-4"><?php echo htmlspecialchars($processo['numero_processo']); ?></td>
                        <td class="py-3 px-4"><?php echo htmlspecialchars($processo['autor']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
            </div>
        </main>
    </div>

    <script src="js/script.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        let graficoVolumeEntrada = null; // Variável para armazenar a instância do gráfico
        const ctx = document.getElementById('graficoVolumeEntrada').getContext('2d');
        
        // Dados do gráfico (fora da função para não recarregar)
        const chartData = {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [
                { label: 'Processos Cíveis', data: <?php echo json_encode($chart_civeis_data); ?>, backgroundColor: 'rgba(59, 130, 246, 0.7)' },
                { label: 'Processos Trabalhistas', data: <?php echo json_encode($chart_trabalhistas_data); ?>, backgroundColor: 'rgba(249, 115, 22, 0.7)' }
            ]
        };

        // Função para obter as opções de cor com base no tema ATUAL
        function getChartOptions() {
            const isDarkMode = document.documentElement.classList.contains('dark');
            const textColor = isDarkMode ? '#E5E7EB' : '#374151'; // Texto claro no dark, escuro no light
            const gridColor = isDarkMode ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)'; // Grade clara no dark, escura no light

            return {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        stacked: false,
                        ticks: { color: textColor },
                        grid: { color: gridColor }
                    },
                    x: {
                        stacked: false,
                        ticks: { color: textColor },
                        grid: { display: false }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { color: textColor } // Cor da legenda
                    },
                    tooltip: { mode: 'index', intersect: false }
                },
                interaction: { mode: 'index', intersect: false }
            };
        }

        // Função para desenhar ou ATUALIZAR o gráfico
        function renderChart() {
            const options = getChartOptions();
            if (graficoVolumeEntrada) {
                // Se o gráfico já existe, atualiza as opções
                graficoVolumeEntrada.options = options;
                graficoVolumeEntrada.update();
            } else {
                // Se não existe, cria um novo
                graficoVolumeEntrada = new Chart(ctx, {
                    type: 'bar',
                    data: chartData,
                    options: options
                });
            }
        }

        // 1. Desenha o gráfico no carregamento inicial da página
        renderChart();

        // 2. Cria um "observador" que vigia a tag <html>
        const observer = new MutationObserver((mutationsList) => {
            for (const mutation of mutationsList) {
                // Se o atributo 'class' da tag <html> mudar...
                if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                    // ...redesenha o gráfico com as novas cores
                    renderChart();
                }
            }
        });

        // 3. Inicia o observador
        observer.observe(document.documentElement, { attributes: true });
    });
    const BASE_URL = 'http://localhost/juridico'
</script>
</body>
</html>