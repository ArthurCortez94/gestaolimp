<?php
declare(strict_types=1);

/**
 * Funções utilitárias para o Sistema UltraLimp
 */

/**
 * Formatar valor monetário
 */
function formatarMoeda(float $valor): string {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

/**
 * Formatar data brasileira
 */
function formatarData(string $data): string {
    try {
        $date = new DateTime($data);
        return $date->format('d/m/Y');
    } catch (Exception $e) {
        return $data;
    }
}

/**
 * Formatar hora
 */
function formatarHora(string $hora): string {
    try {
        $time = new DateTime($hora);
        return $time->format('H:i');
    } catch (Exception $e) {
        return $hora;
    }
}

/**
 * Calcular percentual de crescimento
 */
function calcularPercentualCrescimento(float $atual, float $anterior): float {
    if ($anterior == 0) {
        return $atual > 0 ? 100 : 0;
    }
    return (($atual - $anterior) / $anterior) * 100;
}

/**
 * Verificar autenticação do usuário
 */
function verificarAutenticacao(): void {
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
 * Classe para dados do dashboard
 */
class DashboardData {
    private PDO $pdo;
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Obter receita mensal atual
     */
    public function getReceitaMensal(): float {
        $sql = "SELECT COALESCE(SUM(valor_total), 0) as receita 
                FROM agendamentos 
                WHERE MONTH(data_agendamento) = MONTH(CURRENT_DATE()) 
                AND YEAR(data_agendamento) = YEAR(CURRENT_DATE())
                AND status IN ('concluido', 'pago')";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch();
        
        return (float)($result['receita'] ?? 0);
    }
    
    /**
     * Obter receita do mês anterior
     */
    public function getReceitaMesAnterior(): float {
        $sql = "SELECT COALESCE(SUM(valor_total), 0) as receita 
                FROM agendamentos 
                WHERE MONTH(data_agendamento) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) 
                AND YEAR(data_agendamento) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)
                AND status IN ('concluido', 'pago')";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch();
        
        return (float)($result['receita'] ?? 0);
    }
    
    /**
     * Obter agendamentos do dia
     */
    public function getAgendamentosDia(): array {
        $sql = "SELECT 
                    COUNT(*) as total_agendamentos,
                    SUM(CASE WHEN status = 'em_andamento' THEN 1 ELSE 0 END) as em_andamento,
                    SUM(CASE WHEN status = 'concluido' THEN 1 ELSE 0 END) as concluidos
                FROM agendamentos 
                WHERE DATE(data_agendamento) = CURRENT_DATE()";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch();
        
        return [
            'total_agendamentos' => (int)($result['total_agendamentos'] ?? 0),
            'em_andamento' => (int)($result['em_andamento'] ?? 0),
            'concluidos' => (int)($result['concluidos'] ?? 0)
        ];
    }
    
    /**
     * Obter dados dos funcionários
     */
    public function getFuncionarios(): array {
        $sql = "SELECT 
                    COUNT(*) as total_funcionarios,
                    SUM(CASE WHEN ativo = 1 THEN 1 ELSE 0 END) as funcionarios_ativos,
                    SUM(CASE WHEN ativo = 1 AND disponivel = 1 THEN 1 ELSE 0 END) as em_trabalho
                FROM usuarios 
                WHERE tipo_usuario = 'tecnico'";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch();
        
        return [
            'total_funcionarios' => (int)($result['total_funcionarios'] ?? 0),
            'funcionarios_ativos' => (int)($result['funcionarios_ativos'] ?? 0),
            'em_trabalho' => (int)($result['em_trabalho'] ?? 0)
        ];
    }
    
    /**
     * Obter dados da lavanderia
     */
    public function getLavanderia(): array {
        $sql = "SELECT 
                    COUNT(*) as total_itens,
                    SUM(CASE WHEN status = 'pronto' THEN 1 ELSE 0 END) as prontos,
                    SUM(CASE WHEN status = 'lavando' THEN 1 ELSE 0 END) as lavando
                FROM lavanderia 
                WHERE data_entrega IS NULL";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch();
        
        return [
            'total_itens' => (int)($result['total_itens'] ?? 0),
            'prontos' => (int)($result['prontos'] ?? 0),
            'lavando' => (int)($result['lavando'] ?? 0)
        ];
    }
    
    /**
     * Obter próximos serviços
     */
    public function getProximosServicos(int $limit = 5): array {
        $sql = "SELECT 
                    a.id,
                    c.nome as cliente,
                    a.data_agendamento,
                    a.hora_agendamento,
                    a.status,
                    a.valor_total
                FROM agendamentos a
                JOIN clientes c ON a.cliente_id = c.id
                WHERE a.data_agendamento >= CURRENT_DATE()
                AND a.status IN ('agendado', 'confirmado', 'em_andamento')
                ORDER BY a.data_agendamento ASC, a.hora_agendamento ASC
                LIMIT ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$limit]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Obter próximas entregas de tapetes
     */
    public function getProximasEntregas(int $limit = 5): array {
        $sql = "SELECT 
                    l.id,
                    c.nome as cliente,
                    l.data_prevista_entrega,
                    l.valor_total_geral,
                    l.status
                FROM lavanderia l
                JOIN clientes c ON l.cliente_id = c.id
                WHERE l.data_prevista_entrega >= CURRENT_DATE()
                AND l.status IN ('pronto', 'lavando')
                ORDER BY l.data_prevista_entrega ASC
                LIMIT ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$limit]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Obter distribuição de serviços
     */
    public function getDistribuicaoServicos(): array {
        $sql = "SELECT 
                    CASE 
                        WHEN tipo_servico LIKE '%residencial%' THEN 'Residencial'
                        WHEN tipo_servico LIKE '%comercial%' THEN 'Comercial'
                        WHEN tipo_servico LIKE '%tapete%' THEN 'Tapetes'
                        ELSE 'Outros'
                    END as categoria,
                    COUNT(*) as quantidade
                FROM agendamentos 
                WHERE MONTH(data_agendamento) = MONTH(CURRENT_DATE())
                AND YEAR(data_agendamento) = YEAR(CURRENT_DATE())
                GROUP BY categoria";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll();
        
        $labels = [];
        $valores = [];
        $total = array_sum(array_column($results, 'quantidade'));
        
        foreach ($results as $result) {
            $labels[] = $result['categoria'];
            $valores[] = $total > 0 ? round(($result['quantidade'] / $total) * 100, 1) : 0;
        }
        
        return [
            'labels' => $labels,
            'valores' => $valores
        ];
    }
    
    /**
     * Obter dados de performance
     */
    public function getPerformance(): array {
        // Meta mensal padrão
        $meta_mensal = 50000.00;
        
        // Satisfação do cliente (simulado - implementar com sistema de avaliações)
        $satisfacao_cliente = 95;
        
        return [
            'meta_mensal' => $meta_mensal,
            'satisfacao_cliente' => $satisfacao_cliente
        ];
    }
}