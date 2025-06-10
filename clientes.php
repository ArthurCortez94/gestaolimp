<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Configuração do MySQLi
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$servername = "localhost";
$username   = "admin";
$password   = "251213@bC";
$dbname     = "ultralimp";

$conn = new mysqli($servername, $username, $password, $dbname);

// Cadastrar cliente (CREATE)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'cadastrar_cliente') {
    $tipo_cliente  = $_POST['tipo_cliente'] ?? 'Pessoa Física';
    $nome          = $_POST['nome'] ?? '';
    $razao_social  = $_POST['razao_social'] ?? null;
    $nome_fantasia = $_POST['nome_fantasia'] ?? null;
    $cnpj_cpf      = $_POST['cnpj_cpf'] ?? '';
    $endereco      = $_POST['endereco'] ?? '';
    $numero        = $_POST['numero'] ?? '';
    $complemento   = $_POST['complemento'] ?? '';
    $cidade        = $_POST['cidade'] ?? '';
    $uf            = $_POST['uf'] ?? '';
    $cep           = $_POST['cep'] ?? '';
    $telefone      = $_POST['telefone'] ?? '';
    $email         = $_POST['email'] ?? '';

    $sql_insert = "INSERT INTO clientes (
        tipo_cliente, nome, razao_social, nome_fantasia, 
        cnpj_cpf, endereco, numero, complemento, 
        cidade, uf, cep, telefone, email
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql_insert);
    $stmt->bind_param(
        "sssssssssssss",
        $tipo_cliente, $nome, $razao_social, $nome_fantasia, 
        $cnpj_cpf, $endereco, $numero, $complemento, 
        $cidade, $uf, $cep, $telefone, $email
    );
    $stmt->execute();

    header("Location: clientes.php?msg=Cliente cadastrado com sucesso");
    exit();
}

// Listar clientes (READ)
$sql_clientes = "SELECT * FROM clientes ORDER BY id DESC";
$result_clientes = $conn->query($sql_clientes);

// Editar cliente (UPDATE)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'editar_cliente') {
    $id            = $_POST['id'];
    $tipo_cliente  = $_POST['tipo_cliente'];
    $nome          = $_POST['nome'];
    $razao_social  = $_POST['razao_social'] ?? null;
    $nome_fantasia = $_POST['nome_fantasia'] ?? null;
    $cnpj_cpf      = $_POST['cnpj_cpf'];
    $endereco      = $_POST['endereco'];
    $numero        = $_POST['numero'];
    $complemento   = $_POST['complemento'];
    $cidade        = $_POST['cidade'];
    $uf            = $_POST['uf'];
    $cep           = $_POST['cep'];
    $telefone      = $_POST['telefone'];
    $email         = $_POST['email'];

    $sql_update = "UPDATE clientes SET 
        tipo_cliente=?, nome=?, razao_social=?, nome_fantasia=?, 
        cnpj_cpf=?, endereco=?, numero=?, complemento=?, 
        cidade=?, uf=?, cep=?, telefone=?, email=? 
        WHERE id=?";
    
    $stmt = $conn->prepare($sql_update);
    $stmt->bind_param(
        "sssssssssssssi",
        $tipo_cliente, $nome, $razao_social, $nome_fantasia, 
        $cnpj_cpf, $endereco, $numero, $complemento, 
        $cidade, $uf, $cep, $telefone, $email, $id
    );
    $stmt->execute();

    header("Location: clientes.php?msg=Cliente atualizado com sucesso");
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Clientes - UltraLimp</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<nav class="navbar navbar-dark bg-dark p-3">
    <span class="navbar-brand">UltraLimp - Clientes</span>
    <div>
        <a href="dashboard.php" class="btn btn-outline-light">Dashboard</a>
        <a href="logout.php" class="btn btn-danger">Sair</a>
    </div>
</nav>

<div class="container mt-4">
    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <h3>Cadastrar Novo Cliente</h3>
    <form method="POST" class="mb-4">
        <input type="hidden" name="acao" value="cadastrar_cliente">

        <div class="mb-3">
            <label>Tipo de Cliente</label>
            <select name="tipo_cliente" class="form-select">
                <option value="Pessoa Física">Pessoa Física</option>
                <option value="Pessoa Jurídica">Pessoa Jurídica</option>
            </select>
        </div>

        <div class="mb-3">
            <label>Nome</label>
            <input type="text" name="nome" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>CPF/CNPJ</label>
            <input type="text" name="cnpj_cpf" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Endereço</label>
            <input type="text" name="endereco" class="form-control">
        </div>

        <div class="mb-3">
            <label>Telefone</label>
            <input type="text" name="telefone" class="form-control">
        </div>

        <div class="mb-3">
            <label>Email</label>
            <input type="email" name="email" class="form-control">
        </div>

        <button type="submit" class="btn btn-primary">Cadastrar Cliente</button>
    </form>

    <h3>Clientes Cadastrados</h3>
    <table class="table table-striped table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Tipo</th>
                <th>Nome</th>
                <th>CPF/CNPJ</th>
                <th>Telefone</th>
                <th>Email</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($cli = $result_clientes->fetch_assoc()): ?>
            <tr>
                <td><?php echo $cli['id']; ?></td>
                <td><?php echo $cli['tipo_cliente']; ?></td>
                <td><?php echo htmlspecialchars($cli['nome']); ?></td>
                <td><?php echo $cli['cnpj_cpf']; ?></td>
                <td><?php echo $cli['telefone']; ?></td>
                <td><?php echo $cli['email']; ?></td>
                <td>
                    <a href="editar_cliente.php?id=<?php echo $cli['id']; ?>" class="btn btn-sm btn-warning">Editar</a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>
</body>
</html>
