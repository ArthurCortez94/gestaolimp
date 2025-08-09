# Executive Dashboard - UltraLimp System

## Visão Geral

O Executive Dashboard é uma interface executiva moderna e responsiva para o sistema UltraLimp, projetada para fornecer insights de alto nível sobre o desempenho da empresa de limpeza.

## Arquivos Criados

### 1. `functions.php`
- Funções utilitárias para formatação de dados
- Classe `DashboardData` para consultas ao banco de dados
- Funções de autenticação e segurança

### 2. `executive_dashboard.php`
- Interface principal do dashboard executivo
- Integração com o sistema existente
- Visualização de KPIs e métricas importantes

### 3. `assets/css/executive-dashboard.css`
- Estilos modernos e responsivos
- Design system profissional
- Suporte para dispositivos móveis

## Funcionalidades

### KPIs Principais
- **Receita Mensal**: Acompanhamento da receita atual vs. meta
- **Agendamentos**: Serviços do dia e status
- **Equipe**: Funcionários ativos e disponíveis
- **Lavanderia**: Itens em processamento

### Seções de Conteúdo
- **Timeline de Serviços**: Próximos agendamentos
- **Entregas de Tapetes**: Prazos e valores
- **Performance**: Métricas de satisfação e eficiência
- **Gráficos**: Distribuição de tipos de serviço

### Alertas Inteligentes
- Alertas de prazos próximos
- Notificações de metas atingidas
- Sistema de ações rápidas

## Requisitos Técnicos

### Dependências
- PHP 7.4 ou superior
- MySQL/MariaDB
- Extensões: PDO, JSON
- Font Awesome 6.4.0
- Chart.js
- Inter Font

### Tabelas do Banco
- `agendamentos`
- `clientes`
- `usuarios`
- `lavanderia`

## Instalação

1. **Copiar arquivos**:
   ```bash
   # Já incluídos no projeto
   - functions.php
   - executive_dashboard.php
   - assets/css/executive-dashboard.css
   ```

2. **Configurar permissões**:
   ```bash
   chmod 644 functions.php executive_dashboard.php
   chmod -R 644 assets/
   ```

3. **Acessar o dashboard**:
   ```
   https://seudominio.com/executive_dashboard.php
   ```

## Personalização

### Cores e Temas
As variáveis CSS podem ser customizadas em `executive-dashboard.css`:

```css
:root {
    --primary: #2563eb;        /* Cor principal */
    --success: #059669;        /* Cor de sucesso */
    --warning: #d97706;        /* Cor de aviso */
    --danger: #dc2626;         /* Cor de perigo */
}
```

### Métricas Personalizadas
Edite a classe `DashboardData` em `functions.php` para adicionar novas métricas:

```php
public function getMinhaMetrica(): array {
    // Sua consulta personalizada
}
```

## Responsividade

O dashboard é totalmente responsivo e se adapta a:
- **Desktop**: Layout completo com todas as funcionalidades
- **Tablet**: Reorganização em coluna única
- **Mobile**: Interface otimizada para toque

## Segurança

### Autenticação
- Verificação de sessão ativa
- Controle de inatividade (30 minutos)
- Redirecionamento automático para login

### Proteções
- Prepared statements para consultas SQL
- Escape de dados de saída (XSS)
- Validação de tipos (strict_types)

## Performance

### Otimizações
- Consultas SQL otimizadas
- Carregamento assíncrono de gráficos
- CSS minimalista
- Animações suaves com CSS

### Cache (Futuro)
- Implementar cache Redis para métricas
- Cache de consultas complexas
- Invalidação automática

## Manutenção

### Logs
Erros são registrados automaticamente:
```php
error_log("Erro no dashboard executivo: " . $e->getMessage());
```

### Monitoramento
- Verificar logs regularmente
- Monitorar performance das consultas
- Atualizar dependências

## Futuras Melhorias

### Funcionalidades Planejadas
- [ ] Filtros por período
- [ ] Exportação de relatórios
- [ ] Notificações push
- [ ] Dashboard mobile app
- [ ] Integração com WhatsApp Business
- [ ] Relatórios automáticos por email

### Integrações
- [ ] API REST para dados
- [ ] Webhook para atualizações em tempo real
- [ ] Integração com Google Analytics
- [ ] Sincronização com calendário

## Suporte

Para dúvidas ou problemas:
1. Verificar logs do PHP
2. Validar conexão com banco de dados
3. Confirmar permissões de arquivo
4. Testar em ambiente de desenvolvimento

## Changelog

### v1.0.0 (2024-01-XX)
- Implementação inicial
- KPIs básicos
- Interface responsiva
- Integração com sistema existente