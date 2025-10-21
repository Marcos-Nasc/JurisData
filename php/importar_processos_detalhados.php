<?php
// Aumenta os limites de execução para importações grandes
ini_set('max_execution_time', 300); // 5 minutos
ini_set('memory_limit', '256M');

// Inicia a sessão e inclui arquivos essenciais
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/verifica_login.php';
require_once '../includes/conexao.php';
require_once '../vendor/autoload.php';
require_once '../includes/carregar_tema.php'; // Define $themeClass

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

// =============== FUNÇÕES AUXILIARES ===============
function formatar_moeda($valor): ?float {
    if (empty($valor)) return 0.0;
    $s = (string) $valor;
    $s = preg_replace('/[^\d,.]/', '', $s);
    $ultimo_ponto = strrpos($s, '.');
    $ultima_virgula = strrpos($s, ',');
    if ($ultimo_ponto !== false && $ultima_virgula !== false && $ultimo_ponto < $ultima_virgula) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } else if ($ultimo_ponto !== false && $ultima_virgula !== false && $ultima_virgula < $ultimo_ponto) {
        $s = str_replace(',', '', $s);
    } else if ($ultima_virgula !== false) {
        $s = str_replace(',', '.', $s);
    }
    return (float)$s;
}

function formatar_data($valor): ?string {
    if (empty($valor)) return null;
    if (is_numeric($valor)) {
        try {
            return Date::excelToDateTimeObject($valor)->format('Y-m-d');
        } catch (Exception $e) {
            return null;
        }
    } else {
        try {
            $data = DateTime::createFromFormat('d/m/Y', $valor);
            return $data ? $data->format('Y-m-d') : (new DateTime($valor))->format('Y-m-d');
        } catch (Exception $e) {
            return null;
        }
    }
}

// =============== LÓGICA PRINCIPAL ===============
$mensagem_sucesso = '';
$mensagem_erro = '';
$preview_data = null;
$ano_selecionado = null;
$mes_selecionado = null;
$meses = ["Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho", "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"];

// --- PASSO 1: PRÉ-VISUALIZAÇÃO ---
if (isset($_POST['preview'])) {
    $ano_selecionado = filter_input(INPUT_POST, 'ano_referencia', FILTER_VALIDATE_INT);
    $mes_selecionado = filter_input(INPUT_POST, 'mes_referencia', FILTER_VALIDATE_INT);

    if (!$ano_selecionado || !$mes_selecionado) {
        $mensagem_erro = "Por favor, selecione um ano e mês de referência válidos.";
    } elseif (isset($_FILES['import_file']) && $_FILES['import_file']['error'] == UPLOAD_ERR_OK) {
        $file = $_FILES['import_file']['tmp_name'];
        try {
            $spreadsheet = IOFactory::load($file);
            $sheet = $spreadsheet->getActiveSheet();
            $preview_data = $sheet->toArray(null, true, true, true);
            if (count($preview_data) < 2) {
                $mensagem_erro = "A planilha parece estar vazia ou não contém dados após o cabeçalho.";
                $preview_data = null;
            }
        } catch (Exception $e) {
            $mensagem_erro = "Erro ao ler o arquivo: " . $e->getMessage();
        }
    } else {
        $mensagem_erro = "Erro no upload do arquivo. Código: " . ($_FILES['import_file']['error'] ?? 'N/A');
    }
}

// --- PASSO 2: IMPORTAÇÃO FINAL ---
if (isset($_POST['import'])) {
    $dados_codificados = $_POST['import_data'];
    $ano_importacao = filter_input(INPUT_POST, 'ano_importacao', FILTER_VALIDATE_INT);
    $mes_importacao = filter_input(INPUT_POST, 'mes_importacao', FILTER_VALIDATE_INT);
    $dados_para_importar = json_decode(base64_decode($dados_codificados), true);
    $row_num = 1;

    if ($ano_importacao && $mes_importacao && !empty($dados_para_importar)) {
        $pdo->beginTransaction();
        try {
            $sql = ""
            . "INSERT INTO processos_judiciais (numero_processo, autor, data_recebimento, ano, mes, materia, valor_causa, sentenca_1_instancia, recurso, despesas_processuais_2, valor_pago, economia, usuario_id) "
            . "VALUES (:numero_processo, :autor, :data_recebimento, :ano, :mes, :materia, :valor_causa, :sentenca_1_instancia, :recurso, :despesas_processuais_2, :valor_pago, :economia, :usuario_id) "
            . "ON DUPLICATE KEY UPDATE "
            . "autor = VALUES(autor), data_recebimento = VALUES(data_recebimento), ano = VALUES(ano), mes = VALUES(mes), materia = VALUES(materia), valor_causa = VALUES(valor_causa), sentenca_1_instancia = VALUES(sentenca_1_instancia), recurso = VALUES(recurso), despesas_processuais_2 = VALUES(despesas_processuais_2), valor_pago = VALUES(valor_pago), economia = VALUES(economia), usuario_id = VALUES(usuario_id)";

            $stmt = $pdo->prepare($sql);
            $usuario_id = $_SESSION['user_id'] ?? null;
            $inseridos = 0;
            $atualizados = 0;

            array_shift($dados_para_importar); // Remove o cabeçalho (Linha 1)
            array_shift($dados_para_importar); // Remove a segunda linha de cabeçalho (Linha 2)

            foreach ($dados_para_importar as $row) {
                $row_num++;
                $numero_processo = trim($row['A'] ?? '');
                if (empty($numero_processo)) continue;

                $data_recebimento_raw = trim($row['C'] ?? '');
                $data_recebimento = formatar_data($data_recebimento_raw);

                if ($data_recebimento === null && !empty($data_recebimento_raw)) {
                    throw new Exception("Não foi possível processar a data na coluna C: '{$data_recebimento_raw}'. Verifique se o formato é válido (ex: DD/MM/AAAA).");
                }

                $params = [
                    ':numero_processo' => $numero_processo,
                    ':autor' => trim($row['B'] ?? ''),
                    ':data_recebimento' => $data_recebimento,
                    ':ano' => $ano_importacao,
                    ':mes' => $mes_importacao,
                    ':materia' => trim($row['D'] ?? ''),
                    ':valor_causa' => formatar_moeda($row['F'] ?? '0'),
                    ':sentenca_1_instancia' => trim($row['G'] ?? ''),
                    ':recurso' => trim($row['I'] ?? ''),
                    ':despesas_processuais_2' => formatar_moeda($row['J'] ?? '0'),
                    ':valor_pago' => formatar_moeda($row['K'] ?? '0'),
                    ':economia' => formatar_moeda($row['L'] ?? '0'),
                    ':usuario_id' => $usuario_id
                ];

                $stmt->execute($params);
                if ($stmt->rowCount() == 1) $inseridos++;
                elseif ($stmt->rowCount() == 2) $atualizados++;
            }

            $pdo->commit();
            $mensagem_sucesso = "Importação concluída! Processos inseridos: {$inseridos}. Processos atualizados: {$atualizados}.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $mensagem_erro = "Erro na importação na linha {$row_num} da planilha: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br" class="<?php echo $themeClass; ?>">
<head>
    <meta charset="UTF-8">
    <title>Importar Processos Detalhados</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="app-layout">
        <?php require_once '../includes/sidebar.php'; ?>
        <main class="main-content">
            <div class="flex justify-between items-center mb-6">
                 <h1 class="text-2xl font-bold text-gray-800">Importar Processos Detalhados</h1>
                 <a href="processos_detalhados.php" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Voltar
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
                    <h3 class="font-semibold text-gray-800 mb-4">Passo 1: Selecione o Período e o Arquivo</h3>
                    <p class="text-sm text-gray-600 mb-4">Escolha o ano e o mês de referência para esta importação. Em seguida, envie a planilha com os processos.</p>
                    
                    <form action="" method="POST" enctype="multipart/form-data">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div>
                                <label for="ano_referencia" class="block text-sm font-medium text-gray-700 mb-1">Ano de Referência:</label>
                                <select name="ano_referencia" id="ano_referencia" required class="block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                                    <?php for ($ano = date('Y'); $ano >= 2020; $ano--): ?>
                                        <option value="<?php echo $ano; ?>"><?php echo $ano; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div>
                                <label for="mes_referencia" class="block text-sm font-medium text-gray-700 mb-1">Mês de Referência:</label>
                                <select name="mes_referencia" id="mes_referencia" required class="block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                                    <?php foreach ($meses as $i => $nome_mes): ?>
                                        <option value="<?php echo $i + 1; ?>"><?php echo $nome_mes; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="import_file" class="block text-sm font-medium text-gray-700 mb-1">Arquivo (.xlsx, .xls):</label>
                            <input type="file" name="import_file" id="import_file" required class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"/>
                        </div>
                        <div class="mt-6">
                            <button type="submit" name="preview" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                <i class="fas fa-eye mr-2"></i>Pré-visualizar Dados
                            </button>
                        </div>
                    </form>
                <?php else: // PASSO 2: PREVIEW E CONFIRMAÇÃO ?>
                    <h3 class="font-semibold text-gray-800 mb-4">Passo 2: Pré-visualização e Confirmação</h3>
                    <p class="text-sm text-gray-600 mb-4">Os dados abaixo serão importados para o período de <strong class="text-gray-900"><?php echo $meses[$mes_selecionado-1] . '/' . $ano_selecionado; ?></strong>. Se estiverem corretos, clique em "Confirmar Importação".</p>

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

                    <form action="" method="POST">
                        <input type="hidden" name="import_data" value="<?php echo base64_encode(json_encode($preview_data)); ?>">
                        <input type="hidden" name="ano_importacao" value="<?php echo htmlspecialchars($ano_selecionado); ?>">
                        <input type="hidden" name="mes_importacao" value="<?php echo htmlspecialchars($mes_selecionado); ?>">
                        <div class="flex items-center gap-4">
                            <button type="submit" name="import" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                <i class="fas fa-check mr-2"></i>Confirmar Importação
                            </button>
                            <a href="" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">Cancelar</a>
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