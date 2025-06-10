<?php
session_start();
require 'config.php'; // Conexão com o banco

// 1. Verificar se um ID foi passado pela URL
if (!isset($_GET['id'])) {
    die("ID do serviço não informado.");
}
$id = intval($_GET['id']);
if ($id <= 0) {
    die("ID inválido.");
}

// 2. Buscar dados do serviço
$sql_serv = "
    SELECT s.id, s.numero_servico, s.cliente_id, s.tecnico_id, s.responsavel,
           s.data_servico, s.hora_servico, s.prazo_entrega, s.aos_cuidados,
           s.validade, s.introducao, s.status, s.forma_pagamento, s.observacoes
    FROM servicos s
    WHERE s.id = ?
";
$stmt = $conn->prepare($sql_serv);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$servico = $result->fetch_assoc();

if (!$servico) {
    die("Serviço não encontrado.");
}

// 3. Buscar itens do serviço
$sql_itens = "SELECT * FROM servico_itens WHERE servico_id = ?";
$stmt_itens = $conn->prepare($sql_itens);
$stmt_itens->bind_param("i", $id);
$stmt_itens->execute();
$result_itens = $stmt_itens->get_result();
$lista_itens = $result_itens->fetch_all(MYSQLI_ASSOC);

// 4. Se formulário for submetido, atualizar serviço e itens
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'editar_servico') {
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

    // 4.1 Atualizar dados do serviço
    $sql_update = "
        UPDATE servicos
        SET 
            numero_servico  = ?,
            cliente_id      = ?,
            tecnico_id      = ?,
            responsavel     = ?,
            data_servico    = ?,
            hora_servico    = ?,
            prazo_entrega   = ?,
            aos_cuidados    = ?,
            validade        = ?,
            introducao      = ?,
            status          = ?,
            forma_pagamento = ?,
            observacoes     = ?
        WHERE id = ?
    ";
    $stmt_up = $conn->prepare($sql_update);
    $stmt_up->bind_param(
        "siissssssssssi",
        $numero_servico, $cliente_id, $tecnico_id, $responsavel,
        $data_servico, $hora_servico, $prazo_entrega, $aos_cuidados,
        $validade, $introducao, $status, $forma_pagamento,
        $observacoes, $id
    );
    $stmt_up->execute();

    // 4.2 Apagar itens antigos e inserir novos
    $sql_del = "DELETE FROM servico_itens WHERE servico_id = ?";
    $stmt_del = $conn->prepare($sql_del);
    $stmt_del->bind_param("i", $id);
    $stmt_del->execute();

    if (isset($_POST['item_descricao']) && is_array($_POST['item_descricao'])) {
        $sql_item = "INSERT INTO servico_itens (servico_id, descricao, quantidade, valor_unitario, valor_total) VALUES (?, ?, ?, ?, ?)";
        $stmt_item = $conn->prepare($sql_item);

        foreach ($_POST['item_descricao'] as $key => $desc) {
            $descricao  = $desc ?? '';
            $qtd        = floatval($_POST['item_quantidade'][$key] ?? 1);
            $vunit      = floatval($_POST['item_valor_unitario'][$key] ?? 0.00);
            $vtotal     = $qtd * $vunit;

            $stmt_item->bind_param("isidd", $id, $descricao, $qtd, $vunit, $vtotal);
            $stmt_item->execute();
        }
    }

    header("Location: servicos.php?msg=Serviço atualizado com sucesso");
    exit();
}

// 5. Buscar clientes e técnicos para dropdown
$sql_cli = "SELECT id, nome FROM clientes ORDER BY nome ASC";
$res_cli = $conn->query($sql_cli);

$sql_tec = "SELECT id, nome FROM tecnicos ORDER BY nome ASC";
$res_tec = $conn->query($sql_tec);

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Editar Serviço - UltraLimp</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script>
    // Adicionar, remover itens e calcular subtotais
    function adicionarItem() {
      let container = document.getElementById('itens-container');
      let div = document.createElement('div');
      div.classList.add('row','mb-2','item-row');
      div.innerHTML = `
        <div class="col-md-4">
          <input type="text" name="item_descricao[]" class="form-control" placeholder="Descrição" required>
        </div>
        <div class="col-md-2">
          <input type="number" name="item_quantidade[]" class="form-control" value="1" min="1" required oninput="calcularTotal()">
        </div>
        <div class="col-md-2">
          <input type="number" name="item_valor_unitario[]" class="form-control" step="0.01" value="0" required oninput="calcularTotal()">
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
      button.closest('.item-row').remove();
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
</head>
<body>

<?php include 'menu.php'; ?> <!-- Menu Superior -->

<div class="container mt-4">
    <h3>Editar Serviço (ID: <?= $servico['id'] ?>)</h3>
    <form method="POST">
      <input type="hidden" name="acao" value="editar_servico">

      <div class="row mb-3">
        <div class="col-md-4">
          <label>Número do Serviço</label>
          <input type="text" name="numero_servico" class="form-control" 
                 value="<?= htmlspecialchars($servico['numero_servico']) ?>" required>
        </div>
        <div class="col-md-4">
          <label>Cliente</label>
          <select name="cliente_id" class="form-select" required>
            <?php while($c = $res_cli->fetch_assoc()): 
              $selected = ($c['id'] == $servico['cliente_id']) ? 'selected' : '';
            ?>
              <option value="<?= $c['id'] ?>" <?= $selected ?>>
                <?= htmlspecialchars($c['nome']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label>Técnico</label>
          <select name="tecnico_id" class="form-select">
            <option value="">Nenhum</option>
            <?php while($t = $res_tec->fetch_assoc()):
              $selected = ($t['id'] == $servico['tecnico_id']) ? 'selected' : '';
            ?>
              <option value="<?= $t['id'] ?>" <?= $selected ?>>
                <?= htmlspecialchars($t['nome']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>
      </div>

      <div class="row mb-3">
        <div class="col-md-4">
          <label>Responsável</label>
          <input type="text" name="responsavel" class="form-control"
                 value="<?= htmlspecialchars($servico['responsavel']) ?>">
        </div>
        <div class="col-md-4">
          <label>Data do Serviço</label>
          <input type="date" name="data_servico" class="form-control" required
                 value="<?= $servico['data_servico'] ?>">
        </div>
        <div class="col-md-4">
          <label>Hora do Serviço</label>
          <input type="time" name="hora_servico" class="form-control" required
                 value="<?= $servico['hora_servico'] ?>">
        </div>
      </div>

      <div class="row mb-3">
        <div class="col-md-4">
          <label>Prazo de Entrega</label>
          <input type="text" name="prazo_entrega" class="form-control"
                 value="<?= htmlspecialchars($servico['prazo_entrega']) ?>">
        </div>
        <div class="col-md-4">
          <label>Aos Cuidados de</label>
          <input type="text" name="aos_cuidados" class="form-control"
                 value="<?= htmlspecialchars($servico['aos_cuidados']) ?>">
        </div>
        <div class="col-md-4">
          <label>Validade</label>
          <input type="text" name="validade" class="form-control"
                 value="<?= htmlspecialchars($servico['validade']) ?>">
        </div>
      </div>

      <div class="mb-3">
        <label>Introdução</label>
        <textarea name="introducao" class="form-control"><?= htmlspecialchars($servico['introducao']) ?></textarea>
      </div>

      <div class="mb-3">
        <label>Status</label>
        <select name="status" class="form-select">
          <option value="pendente"     <?php if($servico['status']==='pendente') echo 'selected'; ?>>Pendente</option>
          <option value="em andamento" <?php if($servico['status']==='em andamento') echo 'selected'; ?>>Em Andamento</option>
          <option value="concluído"    <?php if($servico['status']==='concluído')  echo 'selected'; ?>>Concluído</option>
        </select>
      </div>

      <div class="mb-3">
        <label>Forma de Pagamento</label>
        <input type="text" name="forma_pagamento" class="form-control"
               value="<?= htmlspecialchars($servico['forma_pagamento']) ?>">
      </div>

      <div class="mb-3">
        <label>Observações</label>
        <textarea name="observacoes" class="form-control"><?= htmlspecialchars($servico['observacoes']) ?></textarea>
      </div>

      <hr>
      <h4>Itens do Serviço</h4>
      <div id="itens-container">
        <?php if($lista_itens): ?>
          <?php foreach($lista_itens as $idx => $item): ?>
            <div class="row mb-2 item-row">
              <div class="col-md-4">
                <input type="text" name="item_descricao[]" class="form-control" placeholder="Descrição" required
                       value="<?= htmlspecialchars($item['descricao']) ?>">
              </div>
              <div class="col-md-2">
                <input type="number" name="item_quantidade[]" class="form-control" value="<?= $item['quantidade'] ?>" min="1" required oninput="calcularTotal()">
              </div>
              <div class="col-md-2">
                <input type="number" name="item_valor_unitario[]" class="form-control" step="0.01" required oninput="calcularTotal()"
                       value="<?= $item['valor_unitario'] ?>">
              </div>
              <div class="col-md-2">
                <input type="text" class="form-control subtotal-item" value="<?= number_format($item['valor_total'],2) ?>" readonly>
              </div>
              <div class="col-md-2 d-flex align-items-end">
                <button type="button" class="btn btn-danger" onclick="removerItem(this)">Remover</button>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <button type="button" class="btn btn-success mb-3" onclick="adicionarItem()">+ Adicionar Item</button>

      <div class="row mb-3">
        <div class="col-md-4">
          <label>Valor Total</label>
          <input type="text" id="valor_total" class="form-control" value="0.00" readonly>
        </div>
      </div>

      <button type="submit" class="btn btn-primary">Salvar Alterações</button>
    </form>
</div>

<script>
function adicionarItem() {
  let container = document.getElementById('itens-container');
  let div = document.createElement('div');
  div.classList.add('row','mb-2','item-row');
  div.innerHTML = `
    <div class="col-md-4">
      <input type="text" name="item_descricao[]" class="form-control" placeholder="Descrição" required>
    </div>
    <div class="col-md-2">
      <input type="number" name="item_quantidade[]" class="form-control" value="1" min="1" required oninput="calcularTotal()">
    </div>
    <div class="col-md-2">
      <input type="number" name="item_valor_unitario[]" class="form-control" step="0.01" value="0" required oninput="calcularTotal()">
    </div>
    <div class="col-md-2">
      <input type="text" class="form-control subtotal-item" value="0.00" readonly>
    </div>
    <div class="col-md-2 d-flex align-items-end">
      <button type="button" class="btn btn-danger" onclick="removerItem(this)">Remover</button>
    </div>
  `;
  container.appendChild(div);
  calcularTotal();
}
function removerItem(button) {
  button.closest('.item-row').remove();
  calcularTotal();
}
function calcularTotal() {
  let total = 0;
  document.querySelectorAll('.item-row').forEach(row => {
    const qtd = parseFloat(row.querySelector('[name=\"item_quantidade[]\"]').value) || 0;
    const unit = parseFloat(row.querySelector('[name=\"item_valor_unitario[]\"]').value) || 0;
    const st = qtd*unit;
    row.querySelector('.subtotal-item').value = st.toFixed(2);
    total += st;
  });
  document.getElementById('valor_total').value = total.toFixed(2);
}
</script>
</body>
</html>
