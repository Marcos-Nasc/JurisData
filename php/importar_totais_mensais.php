<?php
session_start(); // <-- ADICIONE ESTA LINHA BEM AQUI NO TOP
// 1. INCLUIR ARQUIVOS ESSENCIAIS
require_once '../includes/verifica_login.php';
require_once '../includes/conexao.php';
require_once '../includes/funcoes.php';
require_once '../includes/carregar_tema.php'; // Define $themeClass

// Carrega a biblioteca PhpSpreadsheet
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Variáveis de feedback
$mensagem_sucesso = ''; $mensagem_erro = ''; $preview_data = null; $ano_selecionado = null;

// LÓGICA DE PRÉ-VISUALIZAÇÃO (UPLOAD)
if (isset($_POST['preview'])) {
    $ano_selecionado = filter_input(INPUT_POST, 'ano_referencia', FILTER_VALIDATE_INT);

    if (!$ano_selecionado) {
        $mensagem_erro = "Por favor, selecione um ano de referência válido.";
    } elseif (isset($_FILES['import_file']) && $_FILES['import_file']['error'] == UPLOAD_ERR_OK) {
        $file = $_FILES['import_file']['tmp_name'];
        try {
            $spreadsheet = IOFactory::load($file);
            $sheet = $spreadsheet->getActiveSheet();
            // Pega os dados da planilha como um array para o preview
            // O range aqui é só para visualização, então pode ser mais simples
            $preview_data = $sheet->toArray(null, true, true, true);
            registrar_log($pdo, 'Pré-visualização de importação de totais mensais', 'Arquivo: ' . $_FILES['import_file']['name'] . ', Ano: ' . $ano_selecionado);
        } catch (Exception $e) {
            $mensagem_erro = "Erro ao ler o arquivo: " . $e->getMessage();
            registrar_log($pdo, 'Erro na pré-visualização de importação de totais mensais', 'Arquivo: ' . ($_FILES['import_file']['name'] ?? 'N/A') . ', Erro: ' . $e->getMessage());
        }
    } else {
        registrar_log($pdo, 'Erro no upload do arquivo para pré-visualização de totais mensais', 'Código: ' . ($_FILES['import_file']['error'] ?? 'N/A'));
        $mensagem_erro = "Erro no upload do arquivo. Código do erro: " . ($_FILES['import_file']['error'] ?? 'N/A');
    }
}

// LÓGICA DE IMPORTAÇÃO FINAL
if (isset($_POST['import'])) {
    $dados_codificados = $_POST['import_data'];
    $ano_importacao = filter_input(INPUT_POST, 'ano_importacao', FILTER_VALIDATE_INT);
    
    // CORREÇÃO: Mapa de Colunas para Meses, para ler o formato pivotado
    $coluna_para_mes = [
        'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4, 'F' => 5, 'G' => 6,
        'H' => 7, 'I' => 8, 'J' => 9, 'K' => 10, 'L' => 11, 'M' => 12
    ];
    
    // Os dados do preview são decodificados para serem processados
    $dados_para_importar = json_decode(base64_decode($dados_codificados), true);

    if ($ano_importacao && !empty($dados_para_importar)) {
        $pdo->beginTransaction();
        try {
            $sql = "INSERT INTO relatorio_mensal_processos (ano, mes, tipo_vara, quantidade, usuario_id) 
                    VALUES (:ano, :mes, :tipo_vara, :quantidade, :usuario_id)
                    ON DUPLICATE KEY UPDATE quantidade = VALUES(quantidade), usuario_id = VALUES(usuario_id)";
            $stmt = $pdo->prepare($sql);
            $registros_afetados = 0;

            // Pula a primeira linha (cabeçalho dos meses: JANEIRO, FEVEREIRO...)
            array_shift($dados_para_importar);
            // Pula a segunda linha (cabeçalho dos meses que pode estar duplicado)
            array_shift($dados_para_importar);

            // Itera sobre cada linha da planilha (cada linha é um TIPO DE VARA)
            foreach ($dados_para_importar as $index => $row) {
                // A coluna 'A' contém o nome do Tipo de Vara
                $tipo_vara_nome = trim($row['A'] ?? '');

                // ✅ CORREÇÃO: Pula linhas vazias ou que contenham "TOTAL"
                if (empty($tipo_vara_nome) || stripos($tipo_vara_nome, 'TOTAL') !== false) {
                    continue; // Pula para a próxima iteração do loop
                }

                // Agora, itera sobre as colunas de B a M para pegar os valores de cada mês
                foreach ($coluna_para_mes as $coluna => $mes_num) {
                    $quantidade = 0;
                    if (isset($row[$coluna]) && is_numeric($row[$coluna])) {
                        $quantidade = (int)$row[$coluna];
                    }

                    // Executa o insert para cada mês daquela linha
                    $stmt->execute([
                        'ano' => $ano_importacao,
                        'mes' => $mes_num,
                        'tipo_vara' => $tipo_vara_nome,
                        'quantidade' => $quantidade,
                        'usuario_id' => $_SESSION['user_id'] // <-- CORRIGIDO
                    ]);
                    $registros_afetados += $stmt->rowCount();
                }
            }
            $pdo->commit();
            registrar_log($pdo, 'Importação de totais mensais concluída', 'Ano: ' . $ano_importacao . ', Registros afetados: ' . $registros_afetados);
            $mensagem_sucesso = "Importação concluída! Registros afetados (inseridos/atualizados): " . $registros_afetados;
        } catch (Exception $e) {
            $pdo->rollBack();
            registrar_log($pdo, 'Erro na importação de totais mensais', 'Ano: ' . $ano_importacao . ', Erro: ' . $e->getMessage());
            $mensagem_erro = "Erro na importação: " . $e->getMessage();
        }
    } else {
        registrar_log($pdo, 'Dados para importação de totais mensais ausentes', 'Ano: ' . ($ano_importacao ?? 'N/A'));
        $mensagem_erro = "Dados para importação ou ano de referência ausentes.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br" class="<?php echo $themeClass; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importação de Totais Mensais</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="app-layout">
        <?php require_once '../includes/sidebar.php'; ?>
        <main class="main-content">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800">Importação de Totais Mensais</h1>
                <a href="totais_mensais.php" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Voltar
                </a>
            </div>

            <?php if ($mensagem_sucesso): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert"><p><?php echo $mensagem_sucesso; ?></p></div>
            <?php endif; ?>
            <?php if ($mensagem_erro): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert"><p><?php echo $mensagem_erro; ?></p></div>
            <?php endif; ?>

            <div class="bg-white p-6 rounded-lg shadow">
                
                <?php if ($preview_data === null): // PASSO 1: UPLOAD ?>
                    <h3 class="font-semibold text-gray-800 mb-4">Passo 1: Selecione o Ano e o Arquivo</h3>
                    <p class="text-sm text-gray-600 mb-4">Envie o arquivo Excel (.xlsx) no formato de planilha (varas nas linhas, meses nas colunas).</p>
                    
                    <form action="importar_totais_mensais.php" method="POST" enctype="multipart/form-data">
                        <div class="mb-4">
                            <label for="ano_referencia" class="block text-sm font-medium text-gray-700 mb-1">Ano de Referência:</label>
                            <select name="ano_referencia" id="ano_referencia" required class="block w-full max-w-xs p-2 border border-gray-300 rounded-md shadow-sm">
                                <?php for ($ano = date('Y'); $ano >= 2020; $ano--): ?>
                                    <option value="<?php echo $ano; ?>"><?php echo $ano; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label for="import_file" class="block text-sm font-medium text-gray-700 mb-1">Arquivo:</label>
                            <input type="file" name="import_file" id="import_file" required class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"/>
                        </div>
                        
                        <div class="mt-6">
                            <button type="submit" name="preview" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                <i class="fas fa-eye mr-2"></i>Pré-visualizar
                            </button>
                        </div>
                    </form>
                
                <?php else: // PASSO 2: PREVIEW E CONFIRMAÇÃO ?>
                    <h3 class="font-semibold text-gray-800 mb-4">Passo 2: Pré-visualização para o Ano de <?php echo htmlspecialchars($ano_selecionado); ?></h3>
                    <p class="text-sm text-gray-600 mb-4">Confira os dados brutos da planilha abaixo. O sistema irá processar este formato automaticamente.</p>

                    <div class="max-h-96 overflow-y-auto border rounded-lg mb-4">
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50 sticky top-0">
                                <tr>
                                    <?php foreach (array_keys(reset($preview_data)) as $header): ?>
                                        <th class="py-3 px-4"><?php echo htmlspecialchars($header); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($preview_data as $row): ?>
                                <tr class="border-b">
                                    <?php foreach ($row as $cell): ?>
                                        <td class="py-2 px-4"><?php echo htmlspecialchars($cell); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <form action="importar_totais_mensais.php" method="POST">
                        <input type="hidden" name="import_data" value="<?php echo base64_encode(json_encode($preview_data)); ?>">
                        <input type="hidden" name="ano_importacao" value="<?php echo htmlspecialchars($ano_selecionado); ?>">
                        
                        <div class="flex items-center gap-4">
                            <button type="submit" name="import" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                <i class="fas fa-check mr-2"></i>Confirmar Importação
                            </button>
                            <a href="importar_totais_mensais.php" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                                Cancelar
                            </a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <script>
    const BASE_URL = 'http://localhost/juridico'; 
</script>
    <script src="../js/script.js"></script>
</body>
</html>