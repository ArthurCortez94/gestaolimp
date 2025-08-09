<?php
/**
 * Ultra Limp - Funções Utilitárias
 * Versão: 2.0
 */

/**
 * Formatar valor monetário para exibição
 */
function formatarMoeda($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

/**
 * Formatar data para exibição
 */
function formatarData($data) {
    if (empty($data)) return '-';
    
    $timestamp = is_string($data) ? strtotime($data) : $data;
    return date('d/m/Y', $timestamp);
}

/**
 * Formatar hora para exibição
 */
function formatarHora($hora) {
    if (empty($hora)) return '-';
    
    $timestamp = is_string($hora) ? strtotime($hora) : $hora;
    return date('H:i', $timestamp);
}

/**
 * Formatar data e hora completa
 */
function formatarDataHora($dataHora) {
    if (empty($dataHora)) return '-';
    
    $timestamp = is_string($dataHora) ? strtotime($dataHora) : $dataHora;
    return date('d/m/Y H:i', $timestamp);
}

/**
 * Calcular percentual de crescimento
 */
function calcularPercentualCrescimento($valorAtual, $valorAnterior) {
    if ($valorAnterior == 0) {
        return $valorAtual > 0 ? 100 : 0;
    }
    
    return (($valorAtual - $valorAnterior) / $valorAnterior) * 100;
}

/**
 * Verificar autenticação do usuário
 */
function verificarAutenticacao() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: /login.php");
        exit();
    }
    
    // Controle de inatividade (30 minutos)
    $_SESSION['user_last_active'] = $_SESSION['user_last_active'] ?? time();
    if ((time() - $_SESSION['user_last_active']) > 1800) {
        session_unset();
        session_destroy();
        header("Location: /login.php?expired=1");
        exit();
    }
    $_SESSION['user_last_active'] = time();
}

/**
 * Obter status formatado
 */
function formatarStatus($status) {
    $status = strtolower(trim($status));
    
    $statusMap = [
        'pendente' => 'Pendente',
        'confirmado' => 'Confirmado', 
        'em andamento' => 'Em Andamento',
        'em_andamento' => 'Em Andamento',
        'concluido' => 'Concluído',
        'concluído' => 'Concluído',
        'cancelado' => 'Cancelado',
        'agendado' => 'Agendado',
        'lavado' => 'Lavado',
        'pronto' => 'Pronto',
        'processamento' => 'Processamento',
        'espera' => 'Em Espera'
    ];
    
    return $statusMap[$status] ?? ucfirst($status);
}

/**
 * Gerar cor para status
 */
function getStatusClass($status) {
    $status = strtolower(trim($status));
    
    $classMap = [
        'pendente' => 'status-pendente',
        'espera' => 'status-espera',
        'em espera' => 'status-espera',
        'confirmado' => 'status-confirmado',
        'em andamento' => 'status-em-andamento',
        'em_andamento' => 'status-em-andamento',
        'concluido' => 'status-concluido',
        'concluído' => 'status-concluido',
        'cancelado' => 'status-cancelado',
        'agendado' => 'status-confirmado',
        'lavado' => 'status-lavado',
        'pronto' => 'status-pronto',
        'processamento' => 'status-processamento'
    ];
    
    return $classMap[$status] ?? 'status-default';
}

/**
 * Formatar número com separadores
 */
function formatarNumero($numero, $decimais = 0) {
    return number_format($numero, $decimais, ',', '.');
}

/**
 * Calcular porcentagem
 */
function calcularPorcentagem($parte, $total) {
    if ($total == 0) return 0;
    return round(($parte / $total) * 100, 1);
}

/**
 * Sanitizar string para exibição
 */
function sanitizar($string) {
    return htmlspecialchars(trim($string), ENT_QUOTES, 'UTF-8');
}

/**
 * Gerar ID único
 */
function gerarId($prefixo = '') {
    return $prefixo . uniqid() . mt_rand(1000, 9999);
}

/**
 * Validar email
 */
function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validar telefone brasileiro
 */
function validarTelefone($telefone) {
    $telefone = preg_replace('/\D/', '', $telefone);
    return strlen($telefone) >= 10 && strlen($telefone) <= 11;
}

/**
 * Formatar telefone
 */
function formatarTelefone($telefone) {
    $telefone = preg_replace('/\D/', '', $telefone);
    
    if (strlen($telefone) == 11) {
        return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 5) . '-' . substr($telefone, 7);
    } elseif (strlen($telefone) == 10) {
        return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 4) . '-' . substr($telefone, 6);
    }
    
    return $telefone;
}

/**
 * Calcular idade
 */
function calcularIdade($dataNascimento) {
    $nascimento = new DateTime($dataNascimento);
    $hoje = new DateTime();
    return $hoje->diff($nascimento)->y;
}

/**
 * Converter data para MySQL
 */
function dataParaMySQL($data) {
    $timestamp = strtotime(str_replace('/', '-', $data));
    return date('Y-m-d', $timestamp);
}

/**
 * Converter data do MySQL para exibição
 */
function dataDoMySQL($data) {
    if (empty($data) || $data == '0000-00-00') return '-';
    
    $timestamp = strtotime($data);
    return date('d/m/Y', $timestamp);
}

/**
 * Obter primeiro nome
 */
function obterPrimeiroNome($nomeCompleto) {
    $partes = explode(' ', trim($nomeCompleto));
    return $partes[0];
}

/**
 * Truncar texto
 */
function truncarTexto($texto, $limite = 50) {
    if (strlen($texto) <= $limite) return $texto;
    return substr($texto, 0, $limite) . '...';
}

/**
 * Debug - Log para desenvolvimento
 */
function debugLog($message, $data = null) {
    if (defined('DEBUG') && DEBUG) {
        error_log("[DEBUG] " . $message . ($data ? ' - ' . json_encode($data) : ''));
    }
}
?>