# Ultra Limp - Dashboard Executivo

## Visão Geral

O Dashboard Executivo do Ultra Limp é uma interface moderna e profissional desenvolvida para fornecer insights em tempo real sobre o desempenho da empresa de limpeza. O sistema apresenta métricas chave, gráficos interativos e alertas importantes em uma interface limpa e responsiva.

## Características Principais

### 🎯 KPIs Principais
- **Receita Mensal**: Acompanhamento da receita com comparação ao mês anterior
- **Agendamentos**: Visão geral dos serviços agendados para o dia
- **Equipe**: Status da equipe e funcionários ativos
- **Lavanderia**: Controle dos itens em processamento

### 📊 Visualizações
- Gráfico de distribuição de serviços (doughnut chart)
- Timeline de próximos serviços agendados
- Lista de entregas de tapetes pendentes
- Métricas de performance e insights executivos

### 🚨 Sistema de Alertas
- Tapetes com prazo próximo de entrega
- Serviços em atraso
- Notificações de metas atingidas

## Arquivos Criados

### 1. `executive_dashboard.php`
Dashboard principal com interface moderna e responsiva.

### 2. `DashboardData.php`
Classe responsável por buscar e processar todos os dados do dashboard:
- Consultas otimizadas ao banco de dados
- Tratamento de erros robusto
- Métodos para diferentes métricas

### 3. `functions.php`
Funções utilitárias para formatação e validação:
- Formatação de moeda e datas
- Cálculos de percentuais
- Sanitização de dados
- Validações diversas

### 4. `styles.css`
CSS moderno e profissional com:
- Variáveis CSS organizadas
- Design system consistente
- Responsividade completa
- Animações sutis
- Dark mode preparado

## Estrutura do Banco de Dados

O sistema espera as seguintes tabelas principais:

### Tabelas Principais
- `ordens_servico` - Serviços agendados e realizados
- `clientes` - Cadastro de clientes
- `usuarios` - Funcionários e usuários do sistema
- `tapetes_lavanderia` - Controle da lavanderia
- `avaliacoes_servicos` - Avaliações dos clientes

### Campos Importantes
```sql
-- ordens_servico
- data_agendamento, hora_agendamento
- status (pendente, em_andamento, concluido, cancelado)
- valor_total_geral
- tipo_servico
- cliente_id

-- tapetes_lavanderia
- status (pronto, lavando, processamento, entregue)
- data_prevista_entrega
- valor_total_geral
- cliente_id

-- usuarios
- status (ativo, inativo)
- tipo_usuario (funcionario, tecnico, atendente)
- ultimo_login
```

## Configuração

### 1. Banco de Dados
Certifique-se de que o arquivo `config.php` está configurado corretamente com as credenciais do banco de dados.

### 2. Dependências
- PHP 7.4+
- MySQL/MariaDB
- Extensões: PDO, PDO_MySQL

### 3. Arquivos Necessários
- `config.php` - Configuração do banco de dados (já existe)
- `navbar.php` - Componente de navegação (já existe)

## Uso

1. Acesse `executive_dashboard.php` através do navegador
2. O sistema verificará automaticamente a autenticação
3. Os dados serão carregados dinamicamente do banco de dados
4. Em caso de erro, valores padrão serão exibidos

## Características Técnicas

### Performance
- Consultas otimizadas com prepared statements
- Cache de dados quando apropriado
- Carregamento assíncrono de componentes

### Segurança
- Sanitização de todos os dados de saída
- Prepared statements para prevenir SQL injection
- Verificação de autenticação em todas as páginas
- Controle de sessão com timeout

### Responsividade
- Design mobile-first
- Breakpoints otimizados para tablets e desktop
- Componentes adaptáveis
- Touch-friendly em dispositivos móveis

## Customização

### Cores e Temas
As cores podem ser facilmente customizadas através das variáveis CSS no arquivo `styles.css`:

```css
:root {
    --primary: #2563eb;
    --success: #059669;
    --warning: #d97706;
    --danger: #dc2626;
}
```

### Métricas
Para adicionar novas métricas, edite a classe `DashboardData.php` e adicione novos métodos.

### Alertas
Os alertas são gerados dinamicamente baseados nos dados do banco. Customize a lógica no método `getAlertas()`.

## Manutenção

### Logs
Todos os erros são registrados no log do PHP. Verifique regularmente:
- Erros de conexão com banco
- Consultas que falharam
- Problemas de autenticação

### Monitoramento
- Monitore o desempenho das consultas SQL
- Verifique o uso de memória em picos de acesso
- Acompanhe os logs de erro

## Suporte

Para dúvidas ou problemas:
1. Verifique os logs de erro do PHP
2. Confirme as configurações do banco de dados
3. Teste as consultas SQL diretamente
4. Verifique as permissões de arquivo

## Versão
- **Versão**: 2.0
- **Data**: Janeiro 2025
- **Compatibilidade**: PHP 7.4+, MySQL 5.7+