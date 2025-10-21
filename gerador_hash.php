<?php
// Inicializa as variáveis para não dar erro na primeira carga da página
$senha_original = '';
$hash_gerado = '';
$erro = '';

// Verifica se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $senha_original = $_POST['senha'];

    // Garante que a senha não está vazia antes de gerar o hash
    if (!empty($senha_original)) {
        // Gera o hash da senha usando o algoritmo padrão e mais seguro (atualmente bcrypt)
        $hash_gerado = password_hash($senha_original, PASSWORD_DEFAULT);
    } else {
        $erro = 'Por favor, digite uma senha para gerar o hash.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerador de Hash de Senha</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f4f4f9;
            color: #333;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .container {
            background-color: #fff;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 600px;
        }
        h1 {
            color: #005A9C;
            text-align: center;
            margin-bottom: 1.5rem;
        }
        form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        label {
            font-weight: 600;
        }
        input[type="text"] {
            padding: 0.75rem;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 1rem;
        }
        button {
            padding: 0.75rem;
            border: none;
            border-radius: 4px;
            background-color: #005A9C;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        button:hover {
            background-color: #004a80;
        }
        .resultado {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #eee;
        }
        textarea {
            width: 100%;
            padding: 0.5rem;
            font-family: monospace;
            font-size: 0.9rem;
            border: 1px solid #ccc;
            border-radius: 4px;
            background-color: #e9ecef;
            resize: vertical;
        }
        .erro {
            color: #DC3545;
            text-align: center;
            margin-bottom: 1rem;
        }
        .aviso {
            background-color: #fff3cd;
            color: #856404;
            padding: 1rem;
            border: 1px solid #ffeeba;
            border-radius: 4px;
            margin-top: 2rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Gerador de Hash de Senha (Bcrypt)</h1>
        
        <form action="gerador_hash.php" method="POST">
            <label for="senha">Digite a Senha:</label>
            <input type="text" id="senha" name="senha" value="<?php echo htmlspecialchars($senha_original); ?>" required>
            <button type="submit">Gerar Hash</button>
        </form>

        <?php if ($erro): ?>
            <p class="erro"><?php echo $erro; ?></p>
        <?php endif; ?>

        <?php if ($hash_gerado): ?>
            <div class="resultado">
                <h2>Resultado Gerado</h2>
                <p><strong>Senha Original:</strong> <?php echo htmlspecialchars($senha_original); ?></p>
                <label for="hash_resultado"><strong>Hash (copie e cole no banco de dados):</strong></label>
                <textarea id="hash_resultado" readonly rows="3" onclick="this.select();"><?php echo htmlspecialchars($hash_gerado); ?></textarea>
            </div>
        <?php endif; ?>

        <div class="aviso">
            <strong>Atenção:</strong> Esta é uma ferramenta de desenvolvimento. Nunca a disponibilize em um servidor de produção público.
        </div>
    </div>
</body>
</html>