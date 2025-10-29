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
            <h1 id="main-page-title" class="text-2xl font-bold text-gray-800 mb-6">Relatório de Volume de Entradas de Processos</h1>
            
            <div id="filtro-card" class="bg-white p-4 rounded-lg shadow mb-6">
                <form action="volume_entrada.php" method="GET" class="flex items-center gap-4">
                    <div>
                        <label id="filtro-label" for="ano" class="block text-sm font-medium text-gray-700">Ano</label>
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

            <div id="graph-card" class="bg-white p-6 rounded-lg shadow mb-6">
                <h2 id="graph-card-titulo" class="text-lg font-semibold text-gray-700 mb-4">Total de Novas Ações por Mês</h2>
                <div>
                    <canvas id="graficoVolumeEntradas" style="height: 400px;"></canvas>
                </div>
            </div>

            <div id="table-card" class="bg-white p-6 rounded-lg shadow overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead id="table-header" class="text-xs text-gray-700 uppercase bg-gray-50">
                        <tr>
                            <th class="py-3 px-4">Mês</th>
                            <th class="py-3 px-4">Processos Cíveis</th>
                            <th class="py-3 px-4">Processos Trabalhistas</th>
                            <th class="py-3 px-4">Outros</th>
                            <th class="py-3 px-4">Total Mensal</th>
                        </tr>
                    </thead>
                    <tbody id="table-body">
                        <?php foreach ($dados_mensais as $dados_mes): ?>
                        <tr class="border-b">
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

    <script src="../js/app.js"></script> <script src="../js/mobile.js"></script> <script>
    document.addEventListener('DOMContentLoaded', function() {
        
        // --- 1. INSTÂNCIAS E DADOS DO GRÁFICO ---
        let graficoVolumeEntradas = null;
        const ctx = document.getElementById('graficoVolumeEntradas').getContext('2d');
        
        // Dados do PHP
        const chartLabels = <?php echo $chart_labels; ?>;
        const chartCiveisData = <?php echo $chart_civeis_data; ?>;
        const chartTrabalhistasData = <?php echo $chart_trabalhistas_data; ?>;
        const chartOutrosData = <?php echo $chart_outros_data; ?>;

        // --- 2. FUNÇÕES DE OPÇÕES DO GRÁFICO ---
        function getChartOptions() {
            const isDarkMode = document.documentElement.classList.contains('dark');
            const textColor = isDarkMode ? '#E5E7EB' : '#374151';
            const gridColor = isDarkMode ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
            
            return {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { 
                        beginAtZero: true, 
                        ticks: { precision: 0, color: textColor }, // Cor eixo Y
                        grid: { color: gridColor } // Cor grid Y
                    },
                    x: { 
                        grid: { display: false },
                        ticks: { color: textColor } // Cor eixo X
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

        // --- 3. FUNÇÃO DE RENDERIZAÇÃO DO GRÁFICO ---
        function renderBarChart() {
             const options = getChartOptions();
             const data = {
                labels: chartLabels,
                datasets: [
                    {
                        label: 'Processos Cíveis',
                        data: chartCiveisData,
                        backgroundColor: 'rgba(59, 130, 246, 0.7)', // blue-500
                        borderColor: 'rgba(59, 130, 246, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Processos Trabalhistas',
                        data: chartTrabalhistasData,
                        backgroundColor: 'rgba(249, 115, 22, 0.7)', // orange-500
                        borderColor: 'rgba(249, 115, 22, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Outros',
                        data: chartOutrosData,
                        backgroundColor: 'rgba(107, 114, 128, 0.7)', // gray-500
                        borderColor: 'rgba(107, 114, 128, 1)',
                        borderWidth: 1
                    }
                ]
            };

            if (graficoVolumeEntradas) {
                graficoVolumeEntradas.options = options;
                graficoVolumeEntradas.update();
            } else {
                 graficoVolumeEntradas = new Chart(ctx, { type: 'bar', data: data, options: options });
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
        
        // (Target 1 e 2: Card e Título do Gráfico)
        function atualizarTemaGraphCard() {
            const isDarkMode = document.documentElement.classList.contains('dark');
            const card = document.getElementById('graph-card');
            const titulo = document.getElementById('graph-card-titulo');
            
            const corFundo = isDarkMode ? '#2D3748' : '#FFFFFF';
            const corTitulo = isDarkMode ? '#A0AEC0' : '#4B5563'; // gray-400 / gray-600 (text-lg font-semibold text-gray-700)

            if(card) card.style.backgroundColor = corFundo;
            if(titulo) titulo.style.color = corTitulo;
        }

        // (Target 3: Tabela e Hover)
        function atualizarTemaTabela() {
            const isDarkMode = document.documentElement.classList.contains('dark');
            const card = document.getElementById('table-card');
            const header = document.getElementById('table-header');
            const allRows = document.querySelectorAll('#table-body tr');

            const corFundoCard = isDarkMode ? '#2D3748' : '#FFFFFF';
            const corFundoHeader = isDarkMode ? '#4A5568' : '#F9FAFB';
            const corTextoHeader = isDarkMode ? '#A0AEC0' : '#374151';
            const corBordaRow = isDarkMode ? '#4A5568' : '#E5E7EB'; 
            const corTextoPrincipal = isDarkMode ? '#CBD5E0' : '#374151'; // Para Cíveis, Trabalhistas, Outros
            const corTextoMes = isDarkMode ? '#FFFFFF' : '#111827';      // Para Mês (font-medium text-gray-900)
            const corTextoTotal = isDarkMode ? '#E5E7EB' : '#1F2937';    // Para Total (font-semibold)
            
            const corFundoRowNormal = 'transparent';
            const corFundoRowHover = isDarkMode ? '#4A5568' : '#F9FAFB';

            if (card) card.style.backgroundColor = corFundoCard;
            if (header) {
                header.style.backgroundColor = corFundoHeader;
                header.style.color = corTextoHeader;
            }

            if (allRows.length > 0) {
                allRows.forEach(row => {
                    // Não há célula de fallback nesta tabela
                    row.style.borderColor = corBordaRow; 
                    row.style.backgroundColor = corFundoRowNormal;

                    row.addEventListener('mouseenter', () => { row.style.backgroundColor = corFundoRowHover; });
                    row.addEventListener('mouseleave', () => { row.style.backgroundColor = corFundoRowNormal; });

                    const tds = row.querySelectorAll('td');
                    tds.forEach((td, index) => {
                        if (index === 0) { // Coluna Mês
                            td.style.color = corTextoMes;
                        } else if (index === 4) { // Coluna Total Mensal
                             td.style.color = corTextoTotal;
                        } else { // Colunas Cíveis, Trabalhistas, Outros
                            td.style.color = corTextoPrincipal;
                        }
                    });
                });
            }
        }
        
        // Target 4 (Paginação) não se aplica aqui.

        // --- 5. EXECUÇÃO E OBSERVADOR ---

        function atualizarTudo() {
            renderBarChart(); // Atualiza o gráfico (com cores)
            
            // Atualiza o HTML
            atualizarTemaTituloPrincipal(); 
            atualizarTemaFiltro();
            atualizarTemaGraphCard();
            atualizarTemaTabela();
            // atualizarTemaPaginacao(); // Não tem nesta página
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

        // Verifique se esta URL base está correta
        const BASE_URL = 'http://localhost/juridico'; 
    });
    </script>
</body>
</html>