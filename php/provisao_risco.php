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

$total_provisionado = 0;
$dados_tabela = [];
$line_chart_labels = '[]';
$line_chart_data = '[]';

$where_clause = "WHERE ano = :ano";

try {
    // 1. Query para o valor total provisionado (KPI)
    $sql_total = "SELECT SUM(valor_causa) as total_provisionado FROM processos_judiciais $where_clause";
    $stmt_total = $pdo->prepare($sql_total);
    $stmt_total->execute([':ano' => $ano_selecionado]);
    $resultado_total = $stmt_total->fetch(PDO::FETCH_ASSOC);
    $total_provisionado = $resultado_total['total_provisionado'] ?? 0;

    // 2. Query para o Gráfico de Linha (Evolução do Risco Mensal)
    $sql_line_chart = "SELECT CONCAT(ano, '-', LPAD(mes, 2, '0')) as ano_mes, SUM(valor_causa) as risco_mensal FROM processos_judiciais WHERE ano = :ano AND mes IS NOT NULL GROUP BY ano, mes ORDER BY ano, mes ASC";
    $stmt_line_chart = $pdo->prepare($sql_line_chart);
    $stmt_line_chart->execute([':ano' => $ano_selecionado]);
    $risco_mensal_raw = $stmt_line_chart->fetchAll(PDO::FETCH_KEY_PAIR);
    if ($risco_mensal_raw) {
        $line_chart_labels = json_encode(array_keys($risco_mensal_raw));
        $line_chart_data = json_encode(array_values($risco_mensal_raw));
    }

    // 3. Contagem para paginação
    $stmt_total_reg = $pdo->prepare("SELECT COUNT(id) FROM processos_judiciais $where_clause");
    $stmt_total_reg->execute([':ano' => $ano_selecionado]);
    $total_registros = $stmt_total_reg->fetchColumn();
    $total_paginas = ceil($total_registros / $registros_por_pagina);

    // 4. Query para a tabela de processos detalhada com paginação
    $sql_tabela = "SELECT numero_processo, autor, materia, valor_causa FROM processos_judiciais $where_clause ORDER BY valor_causa DESC LIMIT :limit OFFSET :offset";
    $stmt_tabela = $pdo->prepare($sql_tabela);
    $stmt_tabela->bindValue(':ano', $ano_selecionado, PDO::PARAM_INT);
    $stmt_tabela->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
    $stmt_tabela->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt_tabela->execute();
    $dados_tabela = $stmt_tabela->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao consultar o banco de dados: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="pt-br" class="<?php echo $themeClass; ?>">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Provisão de Riscos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .card-provisao {
        background-color: white;
        border-radius: 12px; /* Bordas um pouco menos arredondadas para um visual mais 'sharp' */
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06); /* Sombra um pouco mais definida */
        padding: 24px;
        margin-bottom: 32px;
        text-align: center;
        position: relative; /* Necessário para o posicionamento da linha se usássemos um pseudo-elemento, mas com border-left é mais simples */
        /* 2. Borda esquerda grossa e escura que sobrepõe a borda fina no lado esquerdo */
        border-left: 5px solid #f13e3eff; 
    }

    /* Estilo para o título (ex: "RISCO TOTAL PROVISIONADO") */
    .card-provisao .titulo {
        color: #a0a0a0;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        margin-bottom: 8px;
    }

    /* Estilo para o valor em R$ */
    .card-provisao .valor {
        color: #f13e3eff; /* Cor correspondente à borda esquerda */
        font-size: 44px;
        font-weight: 700;
        line-height: 1.2;
    }

        .pagination { display: flex; justify-content: center; align-items: center; gap: 0.5rem; margin-top: 1.5rem; }
        .pagination a, .pagination span { padding: 0.5rem 0.8rem; border: 1px solid #ddd; text-decoration: none; color: var(--cor-primaria); border-radius: 0.375rem; }
        .pagination a:hover { background-color: #f1f1f1; }
        .pagination .active { background-color: var(--cor-primaria); color: white; border-color: var(--cor-primaria); }
        .pagination .disabled { color: #aaa; background-color: #f9f9f9; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="app-layout">
        <?php require_once '../includes/sidebar.php'; ?>
        <main class="main-content">

            <h1 id="main-page-title" class="text-2xl font-bold text-gray-800 mb-6">Relatório de Provisão de Riscos</h1>

            <div id="filtro-card" class="bg-white p-4 rounded-lg shadow-sm mb-6">
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

            <div id="card-provisao" class="card-provisao">
                <h2 id="card-provisao-titulo" class="titulo">
                    Risco Total Provisionado (<?php echo $ano_selecionado; ?>)
                </h2>
                <p id="card-provisao-valor" class="valor">
                    R$ <?php echo number_format($total_provisionado, 2, ',', '.'); ?>
                </p>
            </div>

            <div id="graph-card" class="bg-white p-6 rounded-xl shadow-md mb-8">
                <h3 id="graph-card-titulo" class="font-semibold text-gray-800 mb-4">Evolução do Risco Total em <?php echo $ano_selecionado; ?></h3>
                <div class="relative h-80">
                    <canvas id="graficoLinhaRisco"></canvas>
                </div>
            </div>

            <div id="table-card" class="bg-white p-6 rounded-lg shadow overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-600">
                    <thead id="table-header" class="text-xs text-gray-700 uppercase bg-gray-50 border-b">
                        <tr>
                            <th class="py-3 px-6 font-semibold">Nº do Processo</th>
                            <th class="py-3 px-6 font-semibold">Autor</th>
                            <th class="py-3 px-6 font-semibold">Matéria</th>
                            <th class="py-3 px-6 font-semibold text-right">Valor da Causa</th>
                        </tr>
                    </thead>
                    <tbody id="table-body">
                        <?php if (empty($dados_tabela)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-10 text-gray-500">Nenhum processo encontrado para o ano de <?php echo $ano_selecionado; ?>.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($dados_tabela as $linha): ?>
                            <tr class="border-b">
                                <td class="py-4 px-6 font-medium text-gray-900"><?php echo htmlspecialchars($linha['numero_processo']); ?></td>
                                <td class="py-4 px-6"><?php echo htmlspecialchars($linha['autor']); ?></td>
                                <td class="py-4 px-6"><?php echo htmlspecialchars($linha['materia']); ?></td>
                                <td class="py-4 px-6 text-right font-medium text-red-700">R$ <?php echo number_format($linha['valor_causa'], 2, ',', '.'); ?></td>
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
    
    <script src="../js/app.js"></script> <script src="../js/mobile.js"></script> <script>
    document.addEventListener('DOMContentLoaded', function() {
        
        // --- 1. INSTÂNCIAS E DADOS DO GRÁFICO ---
        let graficoLinhaRisco = null;
        const ctxLineRisco = document.getElementById('graficoLinhaRisco').getContext('2d');
        
        // Dados do PHP
        const chartLabels = <?php echo $line_chart_labels; ?>;
        const chartData = <?php echo $line_chart_data; ?>;

        // --- 2. FUNÇÕES DE OPÇÕES DO GRÁFICO ---
        function getLineChartOptions() {
            const isDarkMode = document.documentElement.classList.contains('dark');
            const textColor = isDarkMode ? '#E5E7EB' : '#374151';
            const gridColor = isDarkMode ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
            
            return {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        display: true,
                        labels: { color: textColor } // Cor da legenda
                    }
                },
                scales: {
                    y: {
                        ticks: {
                            color: textColor, // Cor dos labels do eixo Y
                            callback: function(value, index, values) {
                                // Preserva sua formatação de moeda
                                return 'R$ ' + value.toLocaleString('pt-BR');
                            }
                        },
                        grid: { color: gridColor } // Cor das linhas do grid Y
                    },
                    x: {
                         ticks: { color: textColor }, // Cor dos labels do eixo X
                         grid: { display: false }
                    }
                }
            };
        }

        // --- 3. FUNÇÃO DE RENDERIZAÇÃO DO GRÁFICO ---
        function renderLineChart() {
            const options = getLineChartOptions();
            const data = {
                labels: chartLabels,
                datasets: [{
                    label: 'Risco Mensal',
                    data: chartData,
                    borderColor: '#dc2626', // red-600
                    backgroundColor: 'rgba(220, 38, 38, 0.1)',
                    fill: true,
                    tension: 0.1
                }]
            };

            if (graficoLinhaRisco) {
                graficoLinhaRisco.options = options;
                graficoLinhaRisco.update();
            } else {
                graficoLinhaRisco = new Chart(ctxLineRisco, { type: 'line', data: data, options: options });
            }
        }

        // --- 4. FUNÇÕES DE ATUALIZAÇÃO DE TEMA (HTML) ---

        function atualizarTemaTituloPrincipal() {
            const isDarkMode = document.documentElement.classList.contains('dark');
            const el = document.getElementById('main-page-title');
            const cor = isDarkMode ? '#E5E7EB' : '#374151'; // gray-200 / gray-800
            if (el) el.style.color = cor;
        }

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
        
        // (Target 1: Card Risco Total)
        function atualizarTemaCardProvisao() {
            const isDarkMode = document.documentElement.classList.contains('dark');
            const card = document.getElementById('card-provisao');
            const titulo = document.getElementById('card-provisao-titulo');
            const valor = document.getElementById('card-provisao-valor');

            const corFundo = isDarkMode ? '#2D3748' : '#FFFFFF';
            const corTitulo = isDarkMode ? '#A0AEC0' : '#a0a0a0'; // cinza claro / cinza (do seu CSS)
            const corValor = isDarkMode ? '#f87171' : '#f13e3eff'; // red-400 / red (do seu CSS)
            
            if(card) {
                card.style.backgroundColor = corFundo;
                card.style.borderColor = corValor; // A borda usa a mesma cor do valor
            }
            if(titulo) titulo.style.color = corTitulo;
            if(valor) valor.style.color = corValor;
        }

        // (Target 2: Título do card do gráfico)
        function atualizarTemaGraphCard() {
            const isDarkMode = document.documentElement.classList.contains('dark');
            const card = document.getElementById('graph-card');
            const titulo = document.getElementById('graph-card-titulo');
            
            const corFundo = isDarkMode ? '#2D3748' : '#FFFFFF';
            const corTitulo = isDarkMode ? '#E5E7EB' : '#374151'; // gray-200 / gray-800

            if(card) card.style.backgroundColor = corFundo;
            if(titulo) titulo.style.color = corTitulo;
        }

        // (Target 3: Hover da tabela)
        function atualizarTemaTabela() {
            const isDarkMode = document.documentElement.classList.contains('dark');
            const card = document.getElementById('table-card');
            const header = document.getElementById('table-header');
            const allRows = document.querySelectorAll('#table-body tr');

            // Cores
            const corFundoCard = isDarkMode ? '#2D3748' : '#FFFFFF';
            const corFundoHeader = isDarkMode ? '#4A5568' : '#F9FAFB';
            const corTextoHeader = isDarkMode ? '#A0AEC0' : '#374151';
            const corBordaRow = isDarkMode ? '#4A5568' : '#E5E7EB'; 
            const corTextoPrincipal = isDarkMode ? '#CBD5E0' : '#374151'; 
            const corTextoBold = isDarkMode ? '#FFFFFF' : '#111827';     
            const corTextoFallback = isDarkMode ? '#A0AEC0' : '#6B7280'; 
            
            const corFundoRowNormal = 'transparent';
            const corFundoRowHover = isDarkMode ? '#4A5568' : '#F9FAFB'; // bg-gray-600 / bg-gray-50

            if (card) card.style.backgroundColor = corFundoCard;
            if (header) {
                header.style.backgroundColor = corFundoHeader;
                header.style.color = corTextoHeader;
            }

            if (allRows.length > 0) {
                allRows.forEach(row => {
                    const fallbackCell = row.querySelector('td[colspan="4"]'); // Colspan desta tabela é 4
                    if (fallbackCell) {
                        row.style.border = 'none';
                        fallbackCell.style.color = corTextoFallback;
                        return;
                    }
                    
                    row.style.borderColor = corBordaRow; 
                    row.style.backgroundColor = corFundoRowNormal;

                    row.addEventListener('mouseenter', () => { row.style.backgroundColor = corFundoRowHover; });
                    row.addEventListener('mouseleave', () => { row.style.backgroundColor = corFundoRowNormal; });

                    // Atualiza as cores dos textos (TD)
                    const tds = row.querySelectorAll('td');
                    tds.forEach((td, index) => {
                        // Ignora a última coluna (Valor da Causa) que tem cor vermelha própria
                        if (index === 3) return; 
                        
                        if (index === 0) { // Coluna "Nº Processo" (bold)
                            td.style.color = corTextoBold;
                        } else { // Colunas "Autor" e "Matéria"
                            td.style.color = corTextoPrincipal;
                        }
                    });
                });
            }
        }
        
        // (Target 4: Paginação)
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
            renderLineChart(); // Atualiza o gráfico (com cores)
            
            // Atualiza o HTML
            atualizarTemaTituloPrincipal(); 
            atualizarTemaFiltro();
            atualizarTemaCardProvisao();
            atualizarTemaGraphCard();
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

        // Verifique se esta URL base está correta para seu ambiente
        const BASE_URL = 'http://localhost/juridico'; 
    });
    </script>
</body>
</html>
