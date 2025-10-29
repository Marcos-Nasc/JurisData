<?php
// Inicia a sessão e inclui arquivos essenciais
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/verifica_login.php';
require_once '../includes/conexao.php';
require_once '../includes/carregar_tema.php'; // Define $themeClass

// --- LÓGICA DE FILTRO E BUSCA DE DADOS ---
$ano_selecionado = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT) ?: date('Y');
$anos_disponiveis = range(date('Y'), 2020);

// Paginação
$registros_por_pagina = 20;
$pagina_selecionada = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$offset = ($pagina_selecionada - 1) * $registros_por_pagina;

try {
    // Query para os KPIs e Gráfico de Rosquinha
    $sql_kpis = "SELECT SUM(valor_causa - (despesas_processuais_1 + despesas_processuais_2 + valor_pago)) as total_economia, SUM(despesas_processuais_1 + despesas_processuais_2) as total_despesas, SUM(valor_pago) as total_pago FROM processos_judiciais WHERE ano = :ano";
    $stmt_kpis = $pdo->prepare($sql_kpis);
    $stmt_kpis->execute([':ano' => $ano_selecionado]);
    $kpis = $stmt_kpis->fetch(PDO::FETCH_ASSOC);
    $total_economia = $kpis['total_economia'] ?? 0;
    $total_despesas = $kpis['total_despesas'] ?? 0;
    $total_pago = $kpis['total_pago'] ?? 0;
    $doughnut_data = [max(0, $total_economia), $total_despesas, $total_pago];

    // Query para o Gráfico de Linha (Economia Mensal)
    $sql_line_chart = "SELECT mes, SUM(valor_causa - (despesas_processuais_1 + despesas_processuais_2 + valor_pago)) as economia_mensal FROM processos_judiciais WHERE ano = :ano GROUP BY mes ORDER BY mes ASC";
    $stmt_line_chart = $pdo->prepare($sql_line_chart);
    $stmt_line_chart->execute([':ano' => $ano_selecionado]);
    $economia_mensal_raw = $stmt_line_chart->fetchAll(PDO::FETCH_KEY_PAIR);
    $economia_mensal_data = array_fill(0, 12, 0);
    foreach ($economia_mensal_raw as $mes => $valor) {
        if ($mes >= 1 && $mes <= 12) {
            $economia_mensal_data[$mes - 1] = $valor;
        }
    }

    // Query para a tabela de processos com paginação
    $stmt_total_reg = $pdo->prepare("SELECT COUNT(id) FROM processos_judiciais WHERE ano = :ano");
    $stmt_total_reg->execute([':ano' => $ano_selecionado]);
    $total_registros = $stmt_total_reg->fetchColumn();
    $total_paginas = ceil($total_registros / $registros_por_pagina);

    $sql_tabela = "SELECT numero_processo, autor, valor_causa, (despesas_processuais_1 + despesas_processuais_2) as total_despesas_proc, valor_pago, (valor_causa - (despesas_processuais_1 + despesas_processuais_2 + valor_pago)) as economia_proc FROM processos_judiciais WHERE ano = :ano ORDER BY id DESC LIMIT :limit OFFSET :offset";
    $stmt_tabela = $pdo->prepare($sql_tabela);
    $stmt_tabela->bindValue(':ano', $ano_selecionado, PDO::PARAM_INT);
    $stmt_tabela->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
    $stmt_tabela->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt_tabela->execute();
    $processos = $stmt_tabela->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao consultar o banco de dados: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="pt-br" class="<?php echo $themeClass; ?>">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Performance Financeira</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/mobile.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .kpi-card { display: flex; align-items: center; justify-content: space-between; }
        .kpi-icon { font-size: 2.5rem; } 
        .pagination { display: flex; justify-content: center; align-items: center; gap: 0.5rem; margin-top: 1.5rem; }
        .pagination a, .pagination span { padding: 0.5rem 0.8rem; border: 1px solid #ddd; text-decoration: none; color: var(--cor-primaria); border-radius: 0.375rem; }
        .pagination a:hover { background-color: #f1f1f1; }
        .pagination .active { background-color: var(--cor-primaria); color: white; border-color: var(--cor-primaria); }
        .pagination .disabled { color: #aaa; background-color: #f9f9f9; }
        .text-success { color: #15803d; }
        .text-danger { color: #b91c1c; }
        .text-warning { color: #b45309; }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php require_once '../includes/sidebar.php'; ?>
        <main class="main-content">
            <h1 id="main-page-title" class="text-2xl font-bold text-gray-800 mb-6">Relatório de Performance Financeira</h1>

            <div id="filtro-card" class="bg-white p-4 rounded-lg shadow-sm mb-6 hidden md:block">
                <form action="" method="GET" class="flex items-center gap-4">
                    <label id="filtro-label" for="ano" class="text-sm font-medium text-gray-700">Filtrar por Ano:</label>
                    <select name="ano" id="ano" class="p-2 border-gray-300 rounded-md shadow-sm">
                        <?php foreach($anos_disponiveis as $ano_opcao): ?>
                            <option value="<?php echo $ano_opcao; ?>" <?php echo ($ano_opcao == $ano_selecionado) ? 'selected' : ''; ?>><?php echo $ano_opcao; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Filtrar</button>
                </form>
            </div>

            <div class="mobile-filter-bar md:hidden mb-4">
                 <label for="filtro-ano-mobile" class="text-sm font-medium text-gray-700 dark:text-gray-300 sr-only">Filtrar por Ano:</label>
                 <select name="ano_mobile" id="filtro-ano-mobile" class="w-full p-2 border-gray-300 rounded-md shadow-sm" onchange="window.location.href='?ano='+this.value+'&page=1'">
                     <?php foreach($anos_disponiveis as $ano_opcao): ?>
                         <option value="<?php echo $ano_opcao; ?>" <?php echo ($ano_opcao == $ano_selecionado) ? 'selected' : ''; ?>>
                              <?php echo $ano_opcao; ?>
                         </option>
                     <?php endforeach; ?>
                 </select>
            </div>


            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div id="kpi-card-1" class="bg-white p-6 rounded-lg shadow kpi-card"><div><p class="text-sm text-gray-500 kpi-title">Economia Total</p><p class="text-3xl font-bold text-success kpi-value">R$ <?php echo number_format($total_economia, 2, ',', '.'); ?></p></div><i class="fas fa-piggy-bank kpi-icon text-green-600"></i></div>
                <div id="kpi-card-2" class="bg-white p-6 rounded-lg shadow kpi-card"><div><p class="text-sm text-gray-500 kpi-title">Total de Despesas</p><p class="text-3xl font-bold text-warning kpi-value">R$ <?php echo number_format($total_despesas, 2, ',', '.'); ?></p></div><i class="fas fa-hand-holding-usd kpi-icon text-orange-600"></i></div>
                <div id="kpi-card-3" class="bg-white p-6 rounded-lg shadow kpi-card"><div><p class="text-sm text-gray-500 kpi-title">Valor Pago</p><p class="text-3xl font-bold text-danger kpi-value">R$ <?php echo number_format($total_pago, 2, ',', '.'); ?></p></div><i class="fas fa-dollar-sign kpi-icon text-red-600"></i></div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <div id="linha-chart-card" class="lg:col-span-2 bg-white p-6 rounded-lg shadow">
                    <h3 id="linha-chart-titulo" class="font-semibold text-gray-800 mb-4">Economia Mensal em <?php echo $ano_selecionado; ?></h3>
                    <div class="relative h-80">
                        <canvas id="graficoLinhaEconomia"></canvas>
                    </div>
                </div>
                <div id="rosquinha-chart-card" class="bg-white p-6 rounded-lg shadow">
                    <h3 id="rosquinha-chart-titulo" class="font-semibold text-gray-800 mb-4 text-center">Composição Financeira</h3>
                    <div class="relative h-80 flex justify-center">
                        <canvas id="graficoRosquinha"></canvas>
                    </div>
                </div>
            </div>

            <div id="processos-card" class="bg-white p-6 rounded-lg shadow overflow-x-auto responsive-table-wrapper">
                <table class="w-full text-sm text-left responsive-table">
                    <thead id="processos-header" class="text-xs text-gray-700 uppercase bg-gray-50 responsive-table-header">
                        <tr>
                            <th class="py-3 px-4 font-semibold">Nº do Processo</th>
                            <th class="py-3 px-4 font-semibold">Autor</th>
                            <th class="py-3 px-4 font-semibold">Valor da Causa</th>
                            <th class="py-3 px-4 font-semibold">Despesas</th>
                            <th class="py-3 px-4 font-semibold">Valor Pago</th>
                            <th class="py-3 px-4 font-semibold">Economia</th>
                        </tr>
                    </thead>
                    <tbody id="processos-corpo" class="responsive-table-body">
                        <?php if (empty($processos)): ?>
                            <tr><td colspan="6" class="text-center py-10 text-gray-500">Nenhum processo encontrado para o ano de <?php echo $ano_selecionado; ?>.</td></tr>
                        <?php else: ?>
                            <?php foreach ($processos as $proc): ?>
                            <tr class="border-b">
                                <td data-label="Nº Processo" class="py-3 px-4 font-bold text-gray-900"><?php echo htmlspecialchars($proc['numero_processo']); ?></td>
                                <td data-label="Autor" class="py-3 px-4"><?php echo htmlspecialchars($proc['autor']); ?></td>
                                <td data-label="Valor da Causa" class="py-3 px-4">R$ <?php echo number_format($proc['valor_causa'], 2, ',', '.'); ?></td>
                                <td data-label="Despesas" class="py-3 px-4">R$ <?php echo number_format($proc['total_despesas_proc'], 2, ',', '.'); ?></td>
                                <td data-label="Valor Pago" class="py-3 px-4">R$ <?php echo number_format($proc['valor_pago'], 2, ',', '.'); ?></td>
                                <td data-label="Economia" class="py-3 px-4 font-bold <?php echo ($proc['economia_proc'] >= 0) ? 'text-success' : 'text-danger'; ?>">R$ <?php echo number_format($proc['economia_proc'], 2, ',', '.'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_paginas > 1): ?>
            <div id="paginacao-wrapper" class="pagination">
                <?php if ($pagina_selecionada > 1): ?>
                    <a href="?page=<?php echo $pagina_selecionada - 1; ?>&ano=<?php echo $ano_selecionado; ?>">Anterior</a>
                <?php endif; ?>

                <?php 
                $window = 1;
                if ($pagina_selecionada > $window + 2) {
                    echo '<a href="?page=1&ano='.$ano_selecionado.'">1</a>';
                    echo '<span class="disabled">...</span>';
                }
                for ($i = max(1, $pagina_selecionada - $window); $i <= min($total_paginas, $pagina_selecionada + $window); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&ano=<?php echo $ano_selecionado; ?>" class="<?php echo ($i == $pagina_selecionada) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php
                if ($pagina_selecionada < $total_paginas - $window - 1) {
                    echo '<span class="disabled">...</span>';
                    echo '<a href="?page='.$total_paginas.'&ano='.$ano_selecionado.'"> '.$total_paginas.'</a>';
                }
                ?>

                <?php if ($pagina_selecionada < $total_paginas): ?>
                    <a href="?page=<?php echo $pagina_selecionada + 1; ?>&ano=<?php echo $ano_selecionado; ?>">Próxima</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </main>
    </div>
    <script src="../js/app.js"></script>
    <script src="../js/mobile.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        
        // --- 1. INSTÂNCIAS E DADOS DOS GRÁFICOS ---
        let graficoLinhaEconomia = null;
        let graficoRosquinha = null;
        
        const ctxLine = document.getElementById('graficoLinhaEconomia').getContext('2d');
        const ctxDoughnut = document.getElementById('graficoRosquinha').getContext('2d');

        const lineChartLabels = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        const lineChartData = <?php echo json_encode($economia_mensal_data); ?>;
        const doughnutChartData = <?php echo json_encode($doughnut_data); ?>;
        const doughnutChartLabels = ['Economia', 'Despesas', 'Valor Pago'];


        // --- 2. FUNÇÕES DE OPÇÕES DOS GRÁFICOS (PARA MUDAR COR DO TEXTO) ---
        
        function getLineChartOptions() {
            const isDarkMode = document.documentElement.classList.contains('dark');
            const textColor = isDarkMode ? '#E5E7EB' : '#374151'; 
            const gridColor = isDarkMode ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
            
            return {
                responsive: true, maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, ticks: { color: textColor }, grid: { color: gridColor } },
                    x: { ticks: { color: textColor }, grid: { display: false } }
                },
                plugins: { legend: { position: 'top', labels: { color: textColor } } }
            };
        }

        function getDoughnutChartOptions() {
            const isDarkMode = document.documentElement.classList.contains('dark');
            const textColor = isDarkMode ? '#E5E7EB' : '#374151';
            
            return {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', display: true, labels: { color: textColor } } }
            };
        }

        // --- 3. FUNÇÕES DE RENDERIZAÇÃO DOS GRÁFICOS ---
        
        function renderLineChart() {
            const options = getLineChartOptions();
            const data = {
                labels: lineChartLabels,
                datasets: [{
                    label: 'Economia Mensal',
                    data: lineChartData,
                    borderColor: '#16a34a',
                    backgroundColor: 'rgba(22, 163, 74, 0.1)',
                    fill: true,
                    tension: 0.1 
                }]
            };
            
            if (graficoLinhaEconomia) {
                graficoLinhaEconomia.options = options;
                graficoLinhaEconomia.update();
            } else {
                graficoLinhaEconomia = new Chart(ctxLine, { type: 'line', data: data, options: options });
            }
        }

        function renderDoughnutChart() {
            const options = getDoughnutChartOptions();
            const data = {
                labels: doughnutChartLabels,
                datasets: [{
                    data: doughnutChartData,
                    backgroundColor: ['#41b46bd7', '#fc8e40ff', '#fc2828d2'],
                    hoverOffset: 4
                }]
            };

            if (graficoRosquinha) {
                graficoRosquinha.options = options;
                graficoRosquinha.update();
            } else {
                graficoRosquinha = new Chart(ctxDoughnut, { type: 'doughnut', data: data, options: options });
            }
        }

        // --- 4. FUNÇÕES DE ATUALIZAÇÃO DE TEMA (HTML) ---
        
        function atualizarTemaTituloPrincipal() {
            const isDarkMode = document.documentElement.classList.contains('dark');
            const el = document.getElementById('main-page-title');
            const cor = isDarkMode ? '#E5E7EB' : '#374151'; // gray-200 / gray-800
            if (el) el.style.color = cor;
        }

        // (Filtro Desktop)
        function atualizarTemaFiltro() {
            const isDarkMode = document.documentElement.classList.contains('dark');
            const card = document.getElementById('filtro-card');
            const label = document.getElementById('filtro-label');
            const select = document.getElementById('ano');

            const corFundoCard = isDarkMode ? '#2D3748' : '#FFFFFF';
            const corTextoLabel = isDarkMode ? '#A0AEC0' : '#374151';
            const corFundoSelect = isDarkMode ? '#4A5568' : '#FFFFFF';
            const corTextoSelect = isDarkMode ? '#E2E8F0' : '#111827';
            const corBordaSelect = isDarkMode ? '#4A5568' : '#D1D5DB';

            if (card) {
                card.style.backgroundColor = corFundoCard;
                if (label) label.style.color = corTextoLabel;
                if (select) {
                    select.style.backgroundColor = corFundoSelect;
                    select.style.color = corTextoSelect;
                    select.style.borderColor = corBordaSelect;
                }
            }
        }

        // ✅ NOVO: (Filtro Mobile)
        function atualizarTemaFiltroMobile() {
            const isDarkMode = document.documentElement.classList.contains('dark');
            const selectMobile = document.getElementById('filtro-ano-mobile');

            const corFundoSelect = isDarkMode ? '#4A5568' : '#FFFFFF';
            const corTextoSelect = isDarkMode ? '#E2E8F0' : '#111827';
            const corBordaSelect = isDarkMode ? '#4A5568' : '#D1D5DB';

            if (selectMobile) {
                selectMobile.style.backgroundColor = corFundoSelect;
                selectMobile.style.color = corTextoSelect;
                selectMobile.style.borderColor = corBordaSelect;
            }
        }

        function atualizarTemaKPIs() {
            const isDarkMode = document.documentElement.classList.contains('dark');
            const kpi1 = document.getElementById('kpi-card-1');
            const kpi2 = document.getElementById('kpi-card-2');
            const kpi3 = document.getElementById('kpi-card-3');
            
            const corFundoCard = isDarkMode ? '#2D3748' : '#FFFFFF';
            const corKpiTitulo = isDarkMode ? '#A0AEC0' : '#6B7280'; 

            if (kpi1) {
                kpi1.style.backgroundColor = corFundoCard;
                kpi2.style.backgroundColor = corFundoCard;
                kpi3.style.backgroundColor = corFundoCard;
                document.querySelectorAll('.kpi-title').forEach(el => el.style.color = corKpiTitulo);
            }
        }

        function atualizarTemaChartWrappers() {
            const isDarkMode = document.documentElement.classList.contains('dark');
            const cardLinha = document.getElementById('linha-chart-card');
            const tituloLinha = document.getElementById('linha-chart-titulo');
            const cardRosquinha = document.getElementById('rosquinha-chart-card');
            const tituloRosquinha = document.getElementById('rosquinha-chart-titulo');

            const corFundoCard = isDarkMode ? '#2D3748' : '#FFFFFF';
            const corTitulo = isDarkMode ? '#E5E7EB' : '#374151'; 

            if (cardLinha) {
                cardLinha.style.backgroundColor = corFundoCard;
                tituloLinha.style.color = corTitulo;
            }
            if (cardRosquinha) {
                cardRosquinha.style.backgroundColor = corFundoCard;
                tituloRosquinha.style.color = corTitulo;
            }
        }
        
        // (Tabela com Hover manual)
        function atualizarTemaTabela() {
            const isDarkMode = document.documentElement.classList.contains('dark');
            const card = document.getElementById('processos-card');
            const header = document.getElementById('processos-header');
            const allRows = document.querySelectorAll('#processos-corpo tr');

            const corFundoCard = isDarkMode ? '#2D3748' : '#FFFFFF';
            const corFundoHeader = isDarkMode ? '#4A5568' : '#F9FAFB';
            const corTextoHeader = isDarkMode ? '#A0AEC0' : '#374151';
            const corBordaRow = isDarkMode ? '#4A5568' : '#E5E7EB'; 
            const corTextoPrincipal = isDarkMode ? '#CBD5E0' : '#374151'; 
            const corTextoBold = isDarkMode ? '#FFFFFF' : '#111827';     
            const corTextoFallback = isDarkMode ? '#A0AEC0' : '#6B7280'; 
            
            const corFundoRowNormal = 'transparent';
            const corFundoRowHover = isDarkMode ? '#4A5568' : '#F9FAFB';

            if (card) {
                card.style.backgroundColor = corFundoCard;
            }
            if (header) {
                header.style.backgroundColor = corFundoHeader;
                header.style.color = corTextoHeader;
            }

            if (allRows.length > 0) {
                allRows.forEach(row => {
                    const fallbackCell = row.querySelector('td[colspan="6"]');
                    if (fallbackCell) {
                        row.style.border = 'none';
                        fallbackCell.style.color = corTextoFallback;
                        return;
                    }
                    
                    row.style.borderColor = corBordaRow; 
                    row.style.backgroundColor = corFundoRowNormal;

                    row.addEventListener('mouseenter', () => {
                        row.style.backgroundColor = corFundoRowHover;
                    });
                    row.addEventListener('mouseleave', () => {
                        row.style.backgroundColor = corFundoRowNormal;
                    });

                    const tds = row.querySelectorAll('td');
                    tds.forEach((td, index) => {
                        if (td.classList.contains('text-success') || td.classList.contains('text-danger')) {
                            return;
                        }
                        if (index === 0) {
                            td.style.color = corTextoBold;
                        } else {
                            td.style.color = corTextoPrincipal;
                        }
                    });
                });
            }
        }
        
        function atualizarTemaPaginacao() {
            const isDarkMode = document.documentElement.classList.contains('dark');
            const links = document.querySelectorAll('#paginacao-wrapper a');
            const disabledSpans = document.querySelectorAll('#paginacao-wrapper .disabled');
            
            const corBorda = isDarkMode ? '#4A5568' : '#ddd';
            const corTextoLink = isDarkMode ? '#A0AEC0' : 'var(--cor-primaria)';
            const corTextoDisabled = isDarkMode ? '#718096' : '#aaa';
            const corFundoDisabled = isDarkMode ? '#2D3748' : '#f9f9f9';
            const corTextoActive = '#FFFFFF';
            const corFundoActive = 'var(--cor-primaria)';
            
            links.forEach(link => {
                if (link.classList.contains('active')) {
                    link.style.backgroundColor = corFundoActive;
                    link.style.color = corTextoActive;
                    link.style.borderColor = corFundoActive;
                } else {
                    link.style.color = corTextoLink;
                    link.style.borderColor = corBorda;
                    link.style.backgroundColor = 'transparent';
                }
            });
            
            disabledSpans.forEach(span => {
                span.style.color = corTextoDisabled;
                span.style.backgroundColor = corFundoDisabled;
                span.style.borderColor = corBorda;
            });
        }


        // --- 5. EXECUÇÃO E OBSERVADOR ---

        function atualizarTudo() {
            renderLineChart();
            renderDoughnutChart();
            
            atualizarTemaTituloPrincipal(); 
            atualizarTemaFiltro();
            atualizarTemaFiltroMobile(); // ✅ ADICIONADO
            atualizarTemaKPIs();
            atualizarTemaChartWrappers();
            atualizarTemaTabela();
            atualizarTemaPaginacao();
        }

        // 1. Roda tudo no carregamento inicial
        atualizarTudo();

        // 2. Cria o observador para mudar o tema
        const observer = new MutationObserver((mutationsList) => {
            for (const mutation of mutationsList) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                    atualizarTudo();
                }
            }
        });

        // 3. Inicia o observador
        observer.observe(document.documentElement, { attributes: true });

        const BASE_URL = '<?php echo BASE_URL; ?>';
    });
</script>
</body>
</html>
