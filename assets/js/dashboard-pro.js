'use strict';

(function(){
  const data = (window.__DASH_DATA__ || {});
  const statusCounts = data.statusCounts || {};
  const valoresRecebidos = Number(data.valoresRecebidos || 0);
  const valoresAReceber = Number(data.valoresAReceber || 0);

  // Theme setup
  const root = document.documentElement;
  function setTheme(theme){ root.setAttribute('data-theme', theme); localStorage.setItem('ultra.theme', theme); }
  function initTheme(){ const saved = localStorage.getItem('ultra.theme'); if(saved){ setTheme(saved); } }

  // Charts
  let statusChart;
  function initStatusChart(){
    const el = document.getElementById('statusPieChart');
    if(!el) return;
    const ctx = el.getContext('2d');
    const labels = ['Abertos', 'Agendados', 'Em Andamento', 'Concluídos', 'Atrasados', 'Cancelados'];
    const values = [
      Number(statusCounts.abertos||0),
      Number(statusCounts.agendados||0),
      Number(statusCounts.em_andamento||0),
      Number(statusCounts.concluidos||0),
      Number(statusCounts.atrasados||0),
      Number(statusCounts.cancelados||0)
    ];
    statusChart = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels,
        datasets: [{
          data: values,
          backgroundColor: ['#3B82F6','#2563EB','#F59E0B','#10B981','#EF4444','#374151'],
          borderWidth: 2,
          borderColor: root.getAttribute('data-theme') === 'dark' ? 'rgba(15,23,42,.6)' : '#fff',
          hoverOffset: 8
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { padding: 18, usePointStyle: true } } },
        cutout: '68%'
      }
    });
  }

  function initFinanceiroChart(){
    const el = document.getElementById('financeiroChart');
    if(!el) return;
    const ctx = el.getContext('2d');
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4'],
        datasets: [
          { label: 'Recebidos', data: [valoresRecebidos*0.2, valoresRecebidos*0.3, valoresRecebidos*0.25, valoresRecebidos*0.25], borderColor: '#10B981', backgroundColor: 'rgba(16,185,129,0.12)', tension: 0.4, fill: true },
          { label: 'A Receber', data: [valoresAReceber*0.3, valoresAReceber*0.2, valoresAReceber*0.3, valoresAReceber*0.2], borderColor: '#F59E0B', backgroundColor: 'rgba(245,158,11,0.12)', tension: 0.4, fill: true }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'top' } },
        scales: { y: { beginAtZero: true, ticks: { callback: (v)=> 'R$ ' + Number(v).toLocaleString('pt-BR') } } }
      }
    });
  }

  // Toggle chart view exposed globally for button onclick
  window.toggleChartView = function(){
    const cardsDiv = document.getElementById('statusCards');
    const chartDiv = document.getElementById('statusChart');
    if(!cardsDiv || !chartDiv) return;
    const toChart = chartDiv.style.display === 'none';
    cardsDiv.style.display = toChart ? 'none' : 'flex';
    chartDiv.style.display = toChart ? 'block' : 'none';
    if(toChart && !statusChart) initStatusChart();
  }

  // Intersection animations
  function initReveal(){
    const els = document.querySelectorAll('.metric-card, .dashboard-card, .dashboard-item');
    const io = new IntersectionObserver((entries)=>{
      for(const e of entries){ if(e.isIntersecting){ e.target.classList.add('animate-slide-up'); io.unobserve(e.target); } }
    },{ threshold: 0.08 });
    els.forEach(el=> io.observe(el));
  }

  // Notifications (simulated)
  function showNotification(message, type='info'){
    const n = document.createElement('div');
    n.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    n.style.cssText = 'top: 100px; right: 20px; z-index: 9999; min-width: 300px;';
    n.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.body.appendChild(n); setTimeout(()=> n.remove(), 5000);
  }

  // Theme toggle button
  function initThemeToggle(){
    const btn = document.getElementById('themeToggle');
    if(!btn) return;
    btn.addEventListener('click', ()=>{
      const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      setTheme(next);
      if(statusChart){ // redraw border contrast
        try { statusChart.destroy(); } catch(e){}
        initStatusChart();
      }
    });
  }

  document.addEventListener('DOMContentLoaded', ()=>{
    initTheme();
    initFinanceiroChart();
    initReveal();
    initThemeToggle();

    // Auto-refresh every 5 min
    setInterval(()=>{
      const refreshBtn = document.querySelector('[title="Atualizar Dados"]');
      if(refreshBtn){ refreshBtn.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i>'; setTimeout(()=> location.reload(), 1000); }
    }, 300000);

    setTimeout(()=> showNotification('<i class="fas fa-bell me-2"></i>Novo orçamento recebido!', 'success'), 10000);
    setTimeout(()=> showNotification('<i class="fas fa-clock me-2"></i>Lembrete: Reunião em 30 minutos', 'warning'), 20000);

    // Initialize chart if already visible
    const chartDiv = document.getElementById('statusChart');
    if(chartDiv && chartDiv.style.display !== 'none'){ initStatusChart(); }
  });
})();