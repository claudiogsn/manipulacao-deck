document.addEventListener("DOMContentLoaded", function () {

  
  const storeFilterElem = document.getElementById('store-filter');
  const integrationFilterElem = document.getElementById('integration-filter');
  const startDateElem = document.getElementById('start-date');
  const endDateElem = document.getElementById('end-date');
  const fetchDataBtn = document.getElementById('fetch-data-btn');
  const dateWarning = document.getElementById('date-warning');
  const searchOrderElem = document.getElementById('search-order');
  const totalOrdersElem = document.getElementById('total-orders');
  const lateOrdersElem = document.getElementById('late-orders');
  const avgDispatchTimeElem = document.getElementById('avg-dispatch-time');
  const avgPrepTimeElem = document.getElementById('avg-prep-time');
  const avgWaitingTimeElem = document.getElementById('avg-waiting-time');
  const preparingOrdersElem = document.getElementById('preparing-orders');
  const readyOrdersElem = document.getElementById('ready-orders');
  const dispatchedOrdersElem = document.getElementById('dispatched-orders');
  const toggleChartBtn = document.getElementById('toggle-chart-btn');
  const chartContainer = document.getElementById('chart-container');


  const now = new Date();
  startDateElem.value = `${now.getFullYear()}-${(now.getMonth() + 1).toString().padStart(2, '0')}-${now.getDate().toString().padStart(2, '0')}T00:00`;
  endDateElem.value = `${now.getFullYear()}-${(now.getMonth() + 1).toString().padStart(2, '0')}-${now.getDate().toString().padStart(2, '0')}T23:59`;

  function fetchData() {
    const start = startDateElem.value ? startDateElem.value.replace('T', ' ') + ":00" : `${now.getFullYear()}-${(now.getMonth() + 1).toString().padStart(2, '0')}-${now.getDate().toString().padStart(2, '0')} 00:00:00`;
    const end = endDateElem.value ? endDateElem.value.replace('T', ' ') + ":00" : `${now.getFullYear()}-${(now.getMonth() + 1).toString().padStart(2, '0')}-${now.getDate().toString().padStart(2, '0')} 23:59:59`;
    
    const selectedStartDate = new Date(start);
    const selectedEndDate = new Date(end);
    const isToday = selectedStartDate.toDateString() === now.toDateString() && selectedEndDate.toDateString() === now.toDateString();
    dateWarning.classList.toggle('hidden', isToday);
    
    axios.post('https://vemprodeck.com.br/dispatch-bot/api/index.php', {
      method: 'getOrdersDeliveryByPeriod',
      data: { start: start, end: end }
    }).then(response => {
      updateDashboard(response.data);
    }).catch(error => {
      console.error('Error fetching data:', error);
    });
  }

  function updateDashboard(data) {
    const storeCnpjs = Array.from(storeFilterElem.selectedOptions).map(option => option.value);
    if (!storeCnpjs.includes("all")) {
      data = data.filter(order => storeCnpjs.includes(order.cnpj));
    }
    const integrationTypes = Array.from(integrationFilterElem.selectedOptions).map(option => option.value);
    if (!integrationTypes.includes("all")) {
      data = data.filter(order => integrationTypes.includes(order.intg_tipo));
    }
    const searchQuery = searchOrderElem.value.toLowerCase();
    if (searchQuery) {
      data = data.filter(order => order.identificador_conta.toLowerCase().includes(searchQuery) || order.num_controle.toLowerCase().includes(searchQuery));
    }

    totalOrdersElem.textContent = data.length;
    const lateOrders = data.filter(order => order.hora_saida === "0000-00-00 00:00:00" && (new Date() - new Date(order.hora_abertura)) / 60000 > 30);
    lateOrdersElem.textContent = lateOrders.length;
    if (lateOrders.length > 0) {
      lateOrdersElem.classList.add('text-red-600');
      document.getElementById('late-orders-card').classList.add('late');
    } else {
      lateOrdersElem.classList.remove('text-red-600');
      document.getElementById('late-orders-card').classList.remove('late');
    }
    avgDispatchTimeElem.textContent = calculateAverageDispatchTime(data) + ' min';
    avgPrepTimeElem.textContent = calculateAveragePrepTime(data) + ' min';
    avgWaitingTimeElem.textContent = calculateAverageWaitingTime(data) + ' min';
    updateKanbanLists(data);
  }

  function calculateAverageDispatchTime(data) {
    const dispatchTimes = data.filter(order => order.hora_saida !== "0000-00-00 00:00:00").map(order => (new Date(order.hora_saida) - new Date(order.hora_abertura)) / 60000);
    const totalDispatchTime = dispatchTimes.reduce((a, b) => a + b, 0);
    return Math.round(totalDispatchTime / dispatchTimes.length);
  }

  function calculateAveragePrepTime(data) {
    const prepTimes = data.filter(order => order.tempo_preparo !== "0000-00-00 00:00:00").map(order => (new Date(order.tempo_preparo) - new Date(order.hora_abertura)) / 60000);
    const totalPrepTime = prepTimes.reduce((a, b) => a + b, 0);
    return Math.round(totalPrepTime / prepTimes.length);
  }

  function calculateAverageWaitingTime(data) {
    const waitingTimes = data.filter(order => order.hora_saida !== "0000-00-00 00:00:00" && order.tempo_preparo !== "0000-00-00 00:00:00").map(order => (new Date(order.hora_saida) - new Date(order.tempo_preparo)) / 60000);
    const totalWaitingTime = waitingTimes.reduce((a, b) => a + b, 0);
    return Math.round(totalWaitingTime / waitingTimes.length);
  }

  function updateKanbanLists(data) {
    const preparingOrders = data.filter(order => 
      order.status_pedido.includes('INICIANDO PREPARO') || 
      order.status_pedido.includes('PARCIALMENTE PRONTO')
    );
    
    const readyOrders = data.filter(order => 
      order.status_pedido.includes('PRONTO PARA DESPACHO') 
    );
    
    const dispatchedOrders = data.filter(order => 
      order.status_pedido.includes('PEDIDO DESPACHADO') ||
      order.status_pedido.includes('NOTA EMITIDA')
    );
    
  
    preparingOrdersElem.innerHTML = '';
    readyOrdersElem.innerHTML = '';
    dispatchedOrdersElem.innerHTML = '';
  
    preparingOrders.forEach(order => {
      const timeOpen = calculateTimeOpen(order.hora_abertura);
      let statusClass = 'bg-success text-success';
      if (timeOpen >= 20 && timeOpen < 30) {
        statusClass = 'bg-warning text-warning';
      } else if (timeOpen >= 30) {
        statusClass = 'bg-danger text-danger';
      }
      const formattedHoraAbertura = formatDateTime(order.hora_abertura);
      const orderElem = document.createElement('tr');
      orderElem.innerHTML = `
        <td class="border-b border-[#eee] ${statusClass} bg-opacity-10 px-4 py-5 pl-9 dark:border-strokedark xl:pl-11">
          <h5 class="font-medium line-break">${order.identificador_conta}</h5>
          <p class="text-sm">${order.modo_de_conta}</p>
          <p class="text-sm">${order.status_pedido} - ${order.quantidade_produzida} / ${order.quantidade_producao} </p>
          <p class="text-sm">${order.intg_tipo} - ${order.num_controle}</p>
          <p class="text-sm">Abertura: ${formattedHoraAbertura}</p>
        </td>
        <td class="border-b border-[#eee] ${statusClass} bg-opacity-10 px-4 py-5 dark:border-strokedark">
          <p class="inline-flex px-3 py-1 text-sm font-medium">
            ${timeOpen} min
          </p>
        </td>
      `;
      preparingOrdersElem.appendChild(orderElem);
    });
  
    readyOrders.forEach(order => {
      const timeOpen = calculateTimeOpen(order.hora_abertura);
      let statusClass = 'bg-success text-success';
      if (timeOpen >= 20 && timeOpen < 30) {
        statusClass = 'bg-warning text-warning';
      } else if (timeOpen >= 30) {
        statusClass = 'bg-danger text-danger';
      }
      const formattedHoraAbertura = formatDateTime(order.hora_abertura);
      const orderElem = document.createElement('tr');
      const forceDispatchButton = order.status_pedido.includes('NOTA EMITIDA')
        ? `<button class="force-dispatch-btn border-b border-[#eee] ${statusClass} bg-opacity-10 px-4 py-2 dark:border-strokedark rounded" data-order-hash="${order.hash}" data-order-cnpj="${order.cnpj}" data-order-num="${order.num_controle}">Forçar Despacho</button>`
        : '';
      orderElem.innerHTML = `
        <td class="border-b border-[#eee] ${statusClass} bg-opacity-10  px-4 py-5 pl-9 dark:border-strokedark xl:pl-11">
          <h5 class="font-medium line-break">${order.identificador_conta}</h5>
          <p class="text-sm">${order.modo_de_conta}</p>
          <p class="text-sm">${order.status_pedido} - ${order.quantidade_produzida} / ${order.quantidade_producao} </p>
          <p class="text-sm">${order.intg_tipo} - ${order.num_controle}</p>
          <p class="text-sm">Abertura: ${formattedHoraAbertura}</p>
          ${forceDispatchButton}
        </td>
        <td class="border-b border-[#eee] ${statusClass} bg-opacity-10  px-4 py-5 dark:border-strokedark">
          <p class="inline-flex px-3 py-1 text-sm font-medium">${timeOpen} min</p>
        </td>
      `;
      readyOrdersElem.appendChild(orderElem);
    });    



    dispatchedOrders.sort((a, b) => new Date(b.hora_saida) - new Date(a.hora_saida)).forEach(order => {
      const timeOpen = Math.round((new Date(order.hora_saida) - new Date(order.hora_abertura)) / 60000);
      const isLate = timeOpen > 30;
      let statusClass = 'bg-success text-success';
      if (isLate) {
        statusClass = 'bg-danger text-danger';
      }
      const formattedHoraSaida = formatDateTime(order.hora_saida);
      const formattedHoraAbertura = formatDateTime(order.hora_abertura);
      const formattedTempoPreparo = order.tempo_preparo === '0000-00-00 00:00:00' ? 'Não informado' : formatDateTime(order.tempo_preparo);
      
      const orderElem = document.createElement('tr');
      orderElem.innerHTML = `
        <td class="border-b border-[#eee] ${statusClass} bg-opacity-10 px-4 py-5 pl-9 dark:border-strokedark xl:pl-11">
          <h5 class="font-medium line-break">${order.identificador_conta}</h5>
          <p class="text-sm">${order.intg_tipo} - ${order.num_controle}</p>
          <p class="text-sm">Abertura: ${formattedHoraAbertura}</p>
          <p class="text-sm ">Preparo: ${formattedTempoPreparo}</p>
          <p class="text-sm">Saida: ${formattedHoraSaida}</p>
          <p class="text-sm font-medium">Tempo: ${timeOpen} min</p>
          <p class="text-sm"><a href="${order.link_rastreio_pedido}" target="_blank">${order.link_rastreio_pedido}</a></p>
          <button class="view-details-btn border-b border-[#eee] ${statusClass} bg-opacity-10 px-4 py-2 dark:border-strokedark rounded" data-order-id="${order.num_controle}">Ver Detalhes</button>
        </td>
      `;
      dispatchedOrdersElem.appendChild(orderElem);
    });


    document.addEventListener('click', function (event) {
      if (event.target && event.target.classList.contains('view-details-btn')) {
        const orderId = event.target.getAttribute('data-order-id');
        const order = dispatchedOrders.find(o => o.num_controle === orderId);
        
        if (order) {
          // Preparar o conteúdo para o SweetAlert
          let orderContent = `
            <strong>Identificador:</strong> ${order.identificador_conta} <br>
            <strong>Data de Abertura:</strong> ${formatDateTime(order.hora_abertura)} <br>
            <strong>Data de Saída:</strong> ${formatDateTime(order.hora_saida)} <br>
          `;
          
          // Se houver parada_id, buscar dados adicionais
          if (order.id_parada) {
            axios.post('https://vemprodeck.com.br/dispatch-bot/api/index.php', {
              method: 'getDeliveryInfoByNumeroParada',
              data: {
                numero_parada: order.id_parada
              }
            }).then(response => {
              const logisticsData = response.data;
              orderContent += `
                <strong>Endereço:</strong> ${logisticsData.endereco}, ${logisticsData.complemento} <br>
                <strong>Taxista:</strong> ${logisticsData.nome_taxista} <br>
                <strong>Veículo:</strong> ${logisticsData.veiculo} - ${logisticsData.placa_veiculo} (${logisticsData.cor_veiculo}) <br>
                <strong>Link de Rastreio:</strong> <span id="order-rastreio-link">${logisticsData.link_rastreio_pedido}</span>
              `;
              
              // Exibir o SweetAlert com os detalhes do pedido
              Swal.fire({
                title: 'Detalhes do Pedido',
                html: orderContent,
                icon: 'info',
                showConfirmButton: false,  // Desabilitar o botão de confirmação (Fechar)
                width: '600px',  // Opcional: ajuste o tamanho da janela
                padding: '20px', // Opcional: adicione algum preenchimento
                footer: `
                  <button class="swal2-footer-button swal2-styled" onclick="copyLink()">Copiar Link <i class="fa fa-copy"></i></button>
                  <button class="swal2-footer-button swal2-styled" onclick="trackOrder()">Rastrear Pedido</button>
                  <button class="swal2-footer-button swal2-styled" onclick="closeModal()">Fechar</button>
                `  // Todos os botões no rodapé
              });
            }).catch(error => {
              console.error("Erro ao buscar dados adicionais:", error);
              Swal.fire({
                title: 'Erro',
                text: 'Não foi possível carregar os dados adicionais.',
                icon: 'error',
                confirmButtonText: 'Ok',
                confirmButtonColor: '#d33' // Define a cor do botão como vermelho
              });
            });
          } else {
            // Exibir o SweetAlert com os detalhes básicos, caso não haja parada_id
            Swal.fire({
              title: 'Detalhes do Pedido',
              html: orderContent,
              icon: 'info',
              showConfirmButton: false,  // Desabilitar o botão de confirmação (Fechar)
              width: '600px',  // Opcional: ajuste o tamanho da janela
              padding: '20px', // Opcional: adicione algum preenchimento
              footer: `
                <button class="swal2-footer-button swal2-styled" onclick="copyLink()">Copiar Link <i class="fa fa-copy"></i></button>
                <button class="swal2-footer-button swal2-styled" onclick="trackOrder()">Rastrear Pedido</button>
                <button class="swal2-footer-button swal2-styled" onclick="closeModal()">Fechar</button>
              `  // Todos os botões no rodapé
            });
          }
        }
      }
    });

    document.addEventListener('click', function (event) {
      if (event.target && event.target.classList.contains('force-dispatch-btn')) {
        const orderHash = event.target.getAttribute('data-order-hash');
        const orderCnpj = event.target.getAttribute('data-order-cnpj');
        const orderNum = event.target.getAttribute('data-order-num');
    
        Swal.fire({
          title: 'Confirmação',
          text: 'Tem certeza de que deseja forçar o despacho deste pedido?',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#d33',
          confirmButtonText: 'Sim, despachar!',
          cancelButtonText: 'Cancelar'
        }).then((result) => {
          if (result.isConfirmed) {
            axios.post('https://vemprodeck.com.br/dispatch-bot/api/index.php', {
              method: 'changeStatusPedido',
              data: {
                hash: orderHash,
                cnpj: orderCnpj,
                num_controle: orderNum,
                status: 'PEDIDO DESPACHADO'
              }
            }).then(response => {
              Swal.fire({
                title: 'Sucesso!',
                text: 'O pedido foi despachado com sucesso.',
                icon: 'success',
                confirmButtonText: 'Ok',
                confirmButtonColor: '#28a745' // Define a cor do botão como verde
              }).then(() => {
                fetchData();
              });
              
            }).catch(error => {
              console.error('Erro ao despachar o pedido:', error);
              Swal.fire({
                title: 'Erro',
                text: 'Não foi possível despachar o pedido. Tente novamente mais tarde.',
                icon: 'error',
                confirmButtonText: 'Ok',
                confirmButtonColor: '#d33' // Define a cor do botão como vermelho
              });
            });
          }
        });
      }
    });
    
  
    // Função chamada quando o botão do rodapé "Fechar" é clicado
    function closeModal() {
      Swal.close();
    }
  
    // Função chamada quando o botão do rodapé "Rastrear Pedido" é clicado
    function trackOrder() {
      const rastreioLink = document.getElementById("order-rastreio-link").textContent;
      if (rastreioLink) {
        window.open(rastreioLink, '_blank');
      } else {
        Swal.fire('Erro', 'Link de rastreio não disponível.', 'error');
      }
    }
  
    // Função para copiar o link de rastreio para a área de transferência
    function copyLink() {
      const linkElement = document.getElementById("order-rastreio-link");
      const range = document.createRange();
      range.selectNode(linkElement);
      window.getSelection().removeAllRanges();  // Clear any previous selections
      window.getSelection().addRange(range);
      document.execCommand("copy");
      window.getSelection().removeAllRanges();  // Deselect the text
      Swal.fire({
        icon: 'success',
        title: 'Link copiado!',
        text: 'O link de rastreio foi copiado para a área de transferência.',
      });
    };
    
    
    
    
    
    
  }

  
  

  function formatDateTime(dateString) {
    return new Date(dateString).toLocaleString('pt-BR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  }

  function calculateTimeOpen(horaAbertura) {
    return Math.round((new Date() - new Date(horaAbertura)) / 60000);
  }

  searchOrderElem.addEventListener('input', fetchData);
  fetchDataBtn.addEventListener('click', fetchData);
  storeFilterElem.addEventListener('change', fetchData);
  integrationFilterElem.addEventListener('change', fetchData);
  fetchData();
  setInterval(fetchData, 15000);
});
