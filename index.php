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

    // =========================================================================
    // ✅ INÍCIO DA LÓGICA DO GRÁFICO ATUALIZADA
    // =========================================================================
    
    // 3. BUSCAR DADOS PARA O GRÁFICO (FILTRADO PELO ANO SELECIONADO)
    $meses_nomes = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
    
    // Inicializa o array de 12 meses com dados zerados
    $chart_data_map = [];
    foreach ($meses_nomes as $index => $nome_mes) {
        $mes_num = $index + 1; // Mês 1 a 12
        $chart_data_map[$mes_num] = ['civeis' => 0, 'trabalhistas' => 0];
    }
    
    // Os labels do gráfico agora são fixos (Jan-Dez)
    $chart_labels = $meses_nomes;

    // Busca os dados do banco AGRUPADOS POR MÊS para o ano selecionado
    $stmt_grafico = $pdo->prepare("SELECT mes, tipo_vara, quantidade FROM relatorio_mensal_processos WHERE ano = :ano");
    $stmt_grafico->execute([':ano' => $ano_selecionado]);
    $resultados_grafico = $stmt_grafico->fetchAll(PDO::FETCH_ASSOC);

    // Preenche o array de 12 meses com os dados do banco
    foreach ($resultados_grafico as $row) {
        $mes_num = (int)$row['mes'];
        if (array_key_exists($mes_num, $chart_data_map)) {
            $quantidade = (int)$row['quantidade'];
            $tipo_vara_lower = strtolower($row['tipo_vara']); // Para o 'trabalhista'

            if (stripos($row['tipo_vara'], 'CÍVEIS') !== false || stripos($row['tipo_vara'], 'CIVEIS') !== false) {
                $chart_data_map[$mes_num]['civeis'] += $quantidade;
            } elseif (strpos($tipo_vara_lower, 'trabalhista') !== false) {
                $chart_data_map[$mes_num]['trabalhistas'] += $quantidade;
            }
        }
    }
    
    // Extrai os dados finais para o Chart.js
    $chart_civeis_data = array_column($chart_data_map, 'civeis');
    $chart_trabalhistas_data = array_column($chart_data_map, 'trabalhistas');

    // =========================================================================
    // ✅ FIM DA LÓGICA DO GRÁFICO ATUALIZADA
    // =========================================================================

    // 4. BUSCAR DADOS PARA A TABELA DE PROCESSOS RECENTES (FILTRADO POR ANO)
    $stmt_recentes = $pdo->prepare("SELECT numero_processo, autor, data_recebimento FROM processos_judiciais WHERE ano = :ano ORDER BY data_recebimento DESC LIMIT 7");
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
    <link rel="stylesheet" href="css/mobile.css">
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
        <main class="main-content bg-gray-100">
            <div class="mb-6 dashboard-header-container"> 
                
                <div class="dashboard-header-text">
                    <h1 class="text-2xl font-bold text-gray-800">Dashboard</h1>
                    <h2 class="text-lg text-gray-700">Bem-vindo(a), <?php echo htmlspecialchars($_SESSION['nome_usuario'] ?? 'Usuário'); ?>!</h2>
                </div>

                <div class="filtro-header-mobile">
                    <select name="ano" id="filtro-ano-mobile" class="p-2 rounded-md shadow-sm">
                        <?php foreach($anos_disponiveis as $ano_opcao): ?>
                            <option value="<?php echo $ano_opcao; ?>" <?php echo ($ano_opcao == $ano_selecionado) ? 'selected' : ''; ?>><?php echo $ano_opcao; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>

            <div id="filtro-card" class="p-4 rounded-lg shadow-sm mb-6">
                <form action="index.php" method="GET" class="flex items-center gap-4">
                    <label id="filtro-label" for="ano" class="font-semibold text-gray-700">Filtrar por Ano:</label>
                    <select name="ano" id="ano" class="p-2 rounded-md shadow-sm">
                        <?php foreach($anos_disponiveis as $ano_opcao): ?>
                            <option value="<?php echo $ano_opcao; ?>" <?php echo ($ano_opcao == $ano_selecionado) ? 'selected' : ''; ?>><?php echo $ano_opcao; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Filtrar</button>
                </form>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
                <div id="kpi-card-1" class="kpi-card"><div class="kpi-text"><p class="kpi-title">Total de Processos (<?php echo $ano_selecionado; ?>)</p><p class="kpi-value"><?php echo $total_processos; ?></p></div><i class="fas fa-gavel kpi-icon" style="color: #2563eb;"></i></div>
                <div id="kpi-card-2" class="kpi-card"><div class="kpi-text"><p class="kpi-title">Risco Provisionado (<?php echo $ano_selecionado; ?>)</p><p class="kpi-value">R$ <?php echo number_format($valor_total_causas, 2, ',', '.'); ?></p></div><i class="fas fa-dollar-sign kpi-icon" style="color: #dc2626;"></i></div>
                <div id="kpi-card-3" class="kpi-card"><div class="kpi-text"><p class="kpi-title">Economia Total (<?php echo $ano_selecionado; ?>)</p><p class="kpi-value">R$ <?php echo number_format($economia_total, 2, ',', '.'); ?></p></div><i class="fas fa-piggy-bank kpi-icon" style="color: #16a34a;"></i></div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow">
                    
                    <h3 class="font-semibold text-gray-700 mb-4">Volume de Entrada (<?php echo $ano_selecionado; ?>)</h3>
                    
                    <div style="position: relative; height: 25rem;"><canvas id="graficoVolumeEntrada"></canvas></div>
            </div>
               <div id="processos-recentes-card" class="p-6 rounded-lg shadow">
                <h3 id="processos-recentes-titulo" class="font-semibold mb-6">
                    Últimos Processos Adicionados em <?php echo $ano_selecionado; ?>
                </h3>
                
                <div class="table-container">
                    <table class="w-full text-sm text-left table-fixed">
                        <thead id="processos-recentes-header" class="text-xs uppercase">
                            <tr>
                                <th class="py-2 px-4 w-2/5">Nº Processo</th>
                                <th class="py-2 px-4 w-3/5">Autor</th>
                            </tr>
                        </thead>
                        <tbody id="processos-recentes-corpo">
                            <?php if(empty($processos_recentes)): ?>
                                <tr>
                                    <td colspan="2" class="text-center py-4">Nenhum processo para este ano.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($processos_recentes as $processo): ?>
                                    <tr class="border-b dark:border-gray-300">
                                        <td class="py-4 px-4 whitespace-nowrap" data-label="Nº PROCESSO">
                                            <?php echo htmlspecialchars($processo['numero_processo']); ?>
                                        </td>
                                        
                                        <td class="py-4 px-4 truncate" title="<?php echo htmlspecialchars($processo['autor']); ?>" data-label="AUTOR">
                                            <?php echo htmlspecialchars($processo['autor']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="js/mobile.js"></script>
    <script src="js/app.js"></script>   

<script>
    document.addEventListener('DOMContentLoaded', function() {

        let graficoVolumeEntrada = null;
        const ctx = document.getElementById('graficoVolumeEntrada').getContext('2d');
        
        // Os dados do gráfico agora são injetados pela nova lógica PHP
        const chartData = {
            labels: <?php echo json_encode($chart_labels); ?>, // <- Vem do PHP (Jan, Fev, ...)
            datasets: [
                { label: 'Processos Cíveis', data: <?php echo json_encode($chart_civeis_data); ?>, backgroundColor: 'rgba(59, 130, 246, 0.7)' },
                { label: 'Processos Trabalhistas', data: <?php echo json_encode($chart_trabalhistas_data); ?>, backgroundColor: 'rgba(249, 115, 22, 0.7)' }
            ]
        };

        // --- FUNÇÃO DO GRÁFICO (Sem alteração) ---
        function getChartOptions() {
            const isDarkMode = document.documentElement.classList.contains('dark');
            const textColor = isDarkMode ? '#E5E7EB' : '#374151';
            const gridColor = isDarkMode ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
            return {
                responsive: true, maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, stacked: false, ticks: { color: textColor }, grid: { color: gridColor } },
                    x: { stacked: false, ticks: { color: textColor }, grid: { display: false } }
                },
                plugins: {
                    legend: { position: 'top', labels: { color: textColor } },
                    tooltip: { mode: 'index', intersect: false }
                },
                interaction: { mode: 'index', intersect: false }
            };
        }

        function renderChart() {
            const options = getChartOptions();
            if (graficoVolumeEntrada) {
                graficoVolumeEntrada.options = options;
                graficoVolumeEntrada.update();
            } else {
                graficoVolumeEntrada = new Chart(ctx, { type: 'bar', data: chartData, options: options });
            }
        }

        // --- ✅ FUNÇÃO DA TABELA (ESTA É A VERSÃO CORRIGIDA) ---
        function atualizarTemaTabela() {
            const isDarkMode = document.documentElement.classList.contains('dark');
            
            // --- Elementos ---
            const card = document.getElementById('processos-recentes-card');
            const titulo = document.getElementById('processos-recentes-titulo');
            const header = document.getElementById('processos-recentes-header'); // Desktop <thead>
            const allRows = document.querySelectorAll('#processos-recentes-corpo tr'); // Todas as linhas <tr>
            
            // --- Cores ---
            // Wrapper
            const corFundoCard = isDarkMode ? '#2D3748' : '#FFFFFF';
            const corTitulo = isDarkMode ? '#A0AEC0' : '#374151';
            
            // Desktop <thead>
            const corFundoHeader = isDarkMode ? '#4A5568' : '#F9FAFB';
            const corTextoHeader = isDarkMode ? '#A0AEC0' : '#374151';
            
            // Cores para Mobile Cards <tr>
            const corFundoRowCard = isDarkMode ? '#2D3748' : '#FFFFFF';
            const corBordaRowCard = isDarkMode ? '#5A6578' : '#E5E7EB'; 

            // Cores para texto <td>
            const corTextoPrincipal = isDarkMode ? '#E2E8F0' : '#111827'; 
            const corTextoInfo = isDarkMode ? '#60A5FA' : '#2563EB'; 
            
            
            if (card) {
                // 1. Estiliza o Wrapper (comum)
                card.style.backgroundColor = corFundoCard;
                titulo.style.color = corTitulo;

                // 2. Estiliza o Header (Desktop) - O CSS o esconde no mobile
                if (header) {
                    header.style.backgroundColor = corFundoHeader;
                    header.style.color = corTextoHeader;
                }

                // 3. Estiliza CADA LINHA (seja desktop row ou mobile card)
                if (allRows.length > 0) {
                    allRows.forEach(row => {
                        
                        // Pula a linha de "Nenhum processo"
                        const fallbackCell = row.querySelector('td[colspan="2"]');
                        if (fallbackCell) {
                            row.style.backgroundColor = 'transparent';
                            row.style.border = 'none';
                            fallbackCell.style.color = corTitulo; // Reusa a cor do título
                            return;
                        }

                        // Aplica estilo de card (só será visível no mobile, via CSS)
                        row.style.backgroundColor = corFundoRowCard;
                        row.style.borderColor = corBordaRowCard; 
                        row.style.borderWidth = '1px'; // Garante que a borda apareça

                        // Aplica estilo aos <td> (valores)
                        const tds = row.querySelectorAll('td');
                        tds.forEach((td, index) => {
                            // O data-label="Nº PROCESSO" ou o primeiro <td> recebe a cor de info
                            if (td.getAttribute('data-label') === 'Nº PROCESSO' || index === 0) {
                                td.style.color = corTextoInfo; 
                            } else {
                                td.style.color = corTextoPrincipal;
                            }
                        });
                    });
                }
            }
        }

        // --- FUNÇÃO DO FILTRO (Desktop) ---
        function atualizarTemaFiltro() {
            const isDarkMode = document.documentElement.classList.contains('dark');
            const card = document.getElementById('filtro-card');
            const label = document.getElementById('filtro-label');
            const select = document.getElementById('ano');

            // Cores do seu CSS
            const corFundoCard = isDarkMode ? '#2D3748' : '#FFFFFF';
            const corTextoLabel = isDarkMode ? '#A0AEC0' : '#374151';
            const corFundoSelect = isDarkMode ? '#4A5568' : '#FFFFFF';
            const corTextoSelect = isDarkMode ? '#E2E8F0' : '#111827';
            const corBordaSelect = isDarkMode ? '#4A5568' : '#D1D5DB';

            if (card) {
                card.style.backgroundColor = corFundoCard;
                label.style.color = corTextoLabel;
                select.style.backgroundColor = corFundoSelect;
                select.style.color = corTextoSelect;
                select.style.borderColor = corBordaSelect;
                select.style.borderWidth = '1px';
            }
        }

        // --- FUNÇÃO DO FILTRO (Mobile) ---
        function atualizarTemaFiltroMobile() {
            const isDarkMode = document.documentElement.classList.contains('dark');
            const selectMobile = document.getElementById('filtro-ano-mobile');

            // Cores baseadas na sua outra função de filtro
            const corFundoSelect = isDarkMode ? '#4A5568' : '#FFFFFF';
            const corTextoSelect = isDarkMode ? '#E2E8F0' : '#111827';
            const corBordaSelect = isDarkMode ? '#4A5568' : '#D1D5DB';

            if (selectMobile) {
                selectMobile.style.backgroundColor = corFundoSelect;
                selectMobile.style.color = corTextoSelect;
                selectMobile.style.borderColor = corBordaSelect;
                selectMobile.style.borderWidth = '1px';
            }
        }

        // --- FUNÇÃO DOS KPIs E GRÁFICO (Cores atualizadas) ---
        function atualizarTemaCardsRestantes() {
            const isDarkMode = document.documentElement.classList.contains('dark');
            const kpi1 = document.getElementById('kpi-card-1');
            const kpi2 = document.getElementById('kpi-card-2');
            const kpi3 = document.getElementById('kpi-card-3');
            
            // Cores do seu CSS
            const corFundoCard = isDarkMode ? '#2D3748' : '#FFFFFF';
            const corKpiTitulo = isDarkMode ? '#A0AEC0' : '#374151'; 
            const corKpiValor = isDarkMode ? '#ffffffff' : '#000000ff'; 

            if (kpi1) {
                kpi1.style.backgroundColor = corFundoCard;
                kpi2.style.backgroundColor = corFundoCard;
                kpi3.style.backgroundColor = corFundoCard;
                
                document.querySelectorAll('.kpi-title').forEach(el => el.style.color = corKpiTitulo);
                document.querySelectorAll('.kpi-value').forEach(el => el.style.color = corKpiValor);
            }
        }

        // --- EXECUÇÃO ---

        // 1. Roda tudo no carregamento inicial
        renderChart();
        atualizarTemaTabela();
        atualizarTemaFiltro();
        atualizarTemaFiltroMobile();
        atualizarTemaCardsRestantes();

        // 2. Cria o observador para mudar o tema
        const observer = new MutationObserver((mutationsList) => {
            for (const mutation of mutationsList) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                    // Roda tudo de novo quando o tema mudar
                    renderChart();
                    atualizarTemaTabela();
                    atualizarTemaFiltro();
                    atualizarTemaFiltroMobile();
                    atualizarTemaCardsRestantes();
                }
            }
        });

        // 3. Inicia o observador
        observer.observe(document.documentElement, { attributes: true });

    }); // Fim do DOMContentLoaded
</script>
</body>
</html>