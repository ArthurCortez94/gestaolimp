<?php
session_start();
require 'config.php'; // Conexão com o banco
$msg = $_GET['msg'] ?? '';

// 1) Cadastrar novo serviço
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'cadastrar_servico') {
    $numero_servico  = $_POST['numero_servico']  ?? '';
    $cliente_id      = $_POST['cliente_id']      ?? null;
    $tecnico_id      = $_POST['tecnico_id']      ?? null;
    $responsavel     = $_POST['responsavel']     ?? '';
    $data_servico    = $_POST['data_servico']    ?? date('Y-m-d');
    $hora_servico    = $_POST['hora_servico']    ?? '00:00:00';
    $prazo_entrega   = $_POST['prazo_entrega']   ?? '';
    $aos_cuidados    = $_POST['aos_cuidados']    ?? '';
    $validade        = $_POST['validade']        ?? '';
    $introducao      = $_POST['introducao']      ?? '';
    $status          = $_POST['status']          ?? 'pendente';
    $forma_pagamento = $_POST['forma_pagamento'] ?? 'À vista';
    $observacoes     = $_POST['observacoes']     ?? '';

    // Inserir serviço
    $sql_insert = "
        INSERT INTO servicos (
            numero_servico, cliente_id, tecnico_id, responsavel,
            data_servico, hora_servico, prazo_entrega, aos_cuidados,
            validade, introducao, status, forma_pagamento,
            observacoes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";
    $stmt = $conn->prepare($sql_insert);
    $stmt->bind_param(
        "siissssssssss",
        $numero_servico, $cliente_id, $tecnico_id, $responsavel,
        $data_servico, $hora_servico, $prazo_entrega, $aos_cuidados,
        $validade, $introducao, $status, $forma_pagamento,
        $observacoes
    );
    $stmt->execute();
    $servico_id = $stmt->insert_id;

    // 2) Inserir itens do serviço
    if (isset($_POST['item_descricao']) && is_array($_POST['item_descricao'])) {
        $sql_item = "INSERT INTO servico_itens (servico_id, descricao, quantidade, valor_unitario, valor_total) VALUES (?, ?, ?, ?, ?)";
        $stmt_item = $conn->prepare($sql_item);

        foreach ($_POST['item_descricao'] as $i => $desc) {
            $desc_item     = $desc ?? '';
            $qtd_item      = floatval($_POST['item_quantidade'][$i] ?? 1);
            $vunit_item    = floatval($_POST['item_valor_unitario'][$i] ?? 0.00);
            $vtotal_item   = $qtd_item * $vunit_item;

            $stmt_item->bind_param("isidd", $servico_id, $desc_item, $qtd_item, $vunit_item, $vtotal_item);
            $stmt_item->execute();
        }
    }

    header("Location: servicos.php?msg=Serviço cadastrado com sucesso");
    exit();
}

// 3) Excluir serviço (opcional)
if (isset($_GET['excluir'])) {
    $excluir_id = intval($_GET['excluir']);
    $sql_del = "DELETE FROM servicos WHERE id = ?";
    $stmt_del = $conn->prepare($sql_del);
    $stmt_del->bind_param("i", $excluir_id);
    $stmt_del->execute();

    header("Location: servicos.php?msg=Serviço excluído");
    exit();
}

// 4) Buscar lista de serviços
$sql_servicos = "
    SELECT s.id, s.numero_servico, c.nome AS cliente_nome, t.nome AS tecnico_nome,
           s.responsavel, s.data_servico, s.hora_servico, s.prazo_entrega,
           s.status, s.forma_pagamento, s.observacoes, s.created_at
    FROM servicos s
    JOIN clientes c ON s.cliente_id = c.id
    LEFT JOIN tecnicos t ON s.tecnico_id = t.id
    ORDER BY s.id DESC
";
$result_servicos = $conn->query($sql_servicos);

// 5) Buscar clientes e técnicos para dropdown
$sql_clientes   = "SELECT id, nome FROM clientes ORDER BY nome ASC";
$res_cli        = $conn->query($sql_clientes);

$sql_tecnicos   = "SELECT id, nome FROM tecnicos ORDER BY nome ASC";
$res_tecnicos   = $conn->query($sql_tecnicos);

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Serviços - UltraLimp</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <script>
    // JS para adicionar/remover itens e calcular total
    function adicionarItem() {
      const container = document.getElementById('itens-container');
      const div = document.createElement('div');
      div.classList.add('row','mb-2','item-row');
      div.innerHTML = `
        <div class="col-md-4">
          <input type="text" name="item_descricao[]" class="form-control" placeholder="Descrição" required>
        </div>
        <div class="col-md-2">
          <input type="number" name="item_quantidade[]" class="form-control" value="1" min="1" required oninput="calcularTotal()">
        </div>
        <div class="col-md-2">
          <input type="number" name="item_valor_unitario[]" class="form-control" step="0.01" required oninput="calcularTotal()">
        </div>
        <div class="col-md-2">
          <input type="text" class="form-control subtotal-item" readonly>
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <button type="button" class="btn btn-danger" onclick="removerItem(this)">Remover</button>
        </div>
      `;
      container.appendChild(div);
    }

    function removerItem(button) {
      button.parentElement.parentElement.remove();
      calcularTotal();
    }

    function calcularTotal() {
      let total = 0;
      document.querySelectorAll('.item-row').forEach(row => {
        const qtd = parseFloat(row.querySelector('[name="item_quantidade[]"]').value) || 0;
        const unit = parseFloat(row.querySelector('[name="item_valor_unitario[]"]').value) || 0;
        const subtotal = qtd * unit;
        row.querySelector('.subtotal-item').value = subtotal.toFixed(2);
        total += subtotal;
      });
      document.getElementById('valor_total').value = total.toFixed(2);
    }
  </script>
</head>
<body>

<?php include 'menu.php'; ?> <!-- Menu Superior -->

<div class="container mt-4">
  <!-- Mensagem de feedback -->
  <?php if(!empty($msg)): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div>
  <?php endif; ?>

  <h3>Cadastrar Novo Serviço</h3>
  <form method="POST">
    <input type="hidden" name="acao" value="cadastrar_servico">

    <!-- 1) Campos principais -->
    <div class="row mb-3">
      <div class="col-md-4">
        <label>Número do Serviço</label>
        <input type="text" name="numero_servico" class="form-control" required>
      </div>
      <div class="col-md-4">
        <label>Cliente</label>
        <select name="cliente_id" class="form-select" required>
          <option value="">Selecione um cliente</option>
          <?php while($cli = $res_cli->fetch_assoc()): ?>
            <option value="<?= $cli['id'] ?>"><?= htmlspecialchars($cli['nome']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label>Técnico</label>
        <select name="tecnico_id" class="form-select">
          <option value="">Selecione um técnico</option>
          <?php while($tec = $res_tecnicos->fetch_assoc()): ?>
            <option value="<?= $tec['id'] ?>"><?= htmlspecialchars($tec['nome']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>
    </div>

    <div class="row mb-3">
      <div class="col-md-4">
        <label>Responsável</label>
        <input type="text" name="responsavel" class="form-control">
      </div>
      <div class="col-md-4">
        <label>Data do Serviço</label>
        <input type="date" name="data_servico" class="form-control" required>
      </div>
      <div class="col-md-4">
        <label>Hora do Serviço</label>
        <input type="time" name="hora_servico" class="form-control" required>
      </div>
    </div>

    <div class="row mb-3">
      <div class="col-md-4">
        <label>Prazo de Entrega</label>
        <input type="text" name="prazo_entrega" class="form-control">
      </div>
      <div class="col-md-4">
        <label>Aos Cuidados de</label>
        <input type="text" name="aos_cuidados" class="form-control">
      </div>
      <div class="col-md-4">
        <label>Validade</label>
        <input type="text" name="validade" class="form-control">
      </div>
    </div>

    <div class="mb-3">
      <label>Introdução</label>
      <textarea name="introducao" class="form-control"></textarea>
    </div>

    <div class="mb-3">
      <label>Status</label>
      <select name="status" class="form-select">
        <option value="pendente">Pendente</option>
        <option value="em andamento">Em Andamento</option>
        <option value="concluído">Concluído</option>
      </select>
    </div>

    <div class="mb-3">
      <label>Forma de Pagamento</label>
      <input type="text" name="forma_pagamento" class="form-control" value="À vista">
    </div>

    <div class="mb-3">
      <label>Observações</label>
      <textarea name="observacoes" class="form-control"></textarea>
    </div>

    <hr>
    <h4>Itens do Serviço</h4>
    <div id="itens-container"></div>
    <button type="button" class="btn btn-success mb-3" onclick="adicionarItem()">+ Adicionar Item</button>

    <div class="row mb-3">
      <div class="col-md-4">
        <label>Valor Total</label>
        <input type="text" id="valor_total" class="form-control" value="0.00" readonly>
      </div>
    </div>

    <button type="submit" class="btn btn-primary">Cadastrar Serviço</button>
  </form>

  <hr>
  <h3>Serviços Cadastrados</h3>
  <table class="table table-striped table-bordered">
    <thead>
      <tr>
        <th>ID</th>
        <th>Número</th>
        <th>Cliente</th>
        <th>Técnico</th>
        <th>Responsável</th>
        <th>Data</th>
        <th>Hora</th>
        <th>Prazo</th>
        <th>Status</th>
        <th>Pagamento</th>
        <th>Observações</th>
        <th>Ações</th>
      </tr>
    </thead>
    <tbody>
    <?php while($srv = $result_servicos->fetch_assoc()): ?>
      <tr>
        <td><?= $srv['id'] ?></td>
        <td><?= htmlspecialchars($srv['numero_servico']) ?></td>
        <td><?= htmlspecialchars($srv['cliente_nome']) ?></td>
        <td><?= htmlspecialchars($srv['tecnico_nome']) ?></td>
        <td><?= htmlspecialchars($srv['responsavel']) ?></td>
        <td><?= $srv['data_servico'] ?></td>
        <td><?= $srv['hora_servico'] ?></td>
        <td><?= htmlspecialchars($srv['prazo_entrega']) ?></td>
        <td><?= $srv['status'] ?></td>
        <td><?= $srv['forma_pagamento'] ?></td>
        <td><?= htmlspecialchars($srv['observacoes']) ?></td>
        <td>
          <a href="editar_servico.php?id=<?= $srv['id'] ?>" class="btn btn-warning btn-sm">Editar</a>
          <a href="servicos.php?excluir=<?= $srv['id'] ?>" class="btn btn-danger btn-sm"
             onclick="return confirm('Deseja realmente excluir este serviço?')">Excluir</a>
        </td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
</div>

<script>
// Lógica de adicionar/remover itens e calcular total
function adicionarItem() {
  let container = document.getElementById('itens-container');
  let div = document.createElement('div');
  div.classList.add('row','mb-2','item-row');
  div.innerHTML = `
    <div class="col-md-4">
      <input type="text" name="item_descricao[]" class="form-control" placeholder="Descrição do item" required>
    </div>
    <div class="col-md-2">
      <input type="number" name="item_quantidade[]" class="form-control" value="1" min="1" required oninput="calcularTotal()">
    </div>
    <div class="col-md-2">
      <input type="number" name="item_valor_unitario[]" class="form-control" step="0.01" value="0" required oninput="calcularTotal()">
    </div>
    <div class="col-md-2">
      <input type="text" class="form-control subtotal-item" value="0" readonly>
    </div>
    <div class="col-md-2 d-flex align-items-end">
      <button type="button" class="btn btn-danger" onclick="removerItem(this)">Remover</button>
    </div>
  `;
  container.appendChild(div);
}

function removerItem(button) {
  button.parentNode.parentNode.remove();
  calcularTotal();
}

function calcularTotal() {
  let total = 0;
  document.querySelectorAll('.item-row').forEach(row => {
    let qtd = parseFloat(row.querySelector('[name=\"item_quantidade[]\"]').value) || 0;
    let unit = parseFloat(row.querySelector('[name=\"item_valor_unitario[]\"]').value) || 0;
    let subtotal = qtd * unit;
    row.querySelector('.subtotal-item').value = subtotal.toFixed(2);
    total += subtotal;
  });
  document.getElementById('valor_total').value = total.toFixed(2);
}
</script>

</body>
</html>
