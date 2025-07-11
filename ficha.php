<?php
session_start();
if (!isset($_SESSION['user'])) {
    $_SESSION['error_message'] = 'Por favor, faça login novamente.';
    header("Location: index.php");
    exit();
}

$idFicha = $_GET['id'] ?? null;
if (!$idFicha || !is_numeric($idFicha)) {
    die('ID da ficha inválido.');
}

$userLogin = $_SESSION['user'];

$url = ($_SERVER['SERVER_NAME'] == "localhost")
    ? "http://localhost/portal-deck/api/v1/index.php"
    : "https://portal.vemprodeck.com.br/api/v1/index.php";

// Obter dados do usuário
$responseUser = file_get_contents($url, false, stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-type: application/json",
        'content' => json_encode([
            "method" => "getUserDetails",
            "data"   => ["user" => $userLogin]
        ])
    ]
]));

$dadosUsuario = json_decode($responseUser, true)['userDetails'] ?? [];

$userId = $dadosUsuario['id'] ?? null;
$unitId = $_SESSION['system_unit_id'] ?? $dadosUsuario['system_unit_id'] ?? null;
$token  = $dadosUsuario['token'] ?? null;

if (!$userId || !$unitId || !$token) {
    die('Erro ao obter dados do usuário.');
}

// Verificar se o usuário pode acessar a ficha
$responseFichas = file_get_contents($url, false, stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-type: application/json\r\nAuthorization: {$token}",
        'content' => json_encode([
            "method" => "getFichasByUser",
            "data"   => [
                "user_id" => $userId,
                "system_unit_id" => $unitId
            ]
        ])
    ]
]));

$fichas = json_decode($responseFichas, true)['data'] ?? [];
$idsPermitidos = array_column($fichas, 'id');
if (!in_array((int)$idFicha, $idsPermitidos)) {
    die('<h2 style="color: red; text-align: center; margin-top: 2rem;">⚠️ Acesso negado à ficha solicitada.</h2>');
}

// Buscar nome do produto da ficha
$responseFicha = file_get_contents($url, false, stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-type: application/json\r\nAuthorization: {$token}",
        'content' => json_encode([
            "method" => "getFichaDetalhada",
            "token"  => $token,
            "data"   => [
                "id_ficha" => (int)$idFicha,
                "system_unit_id" => (int)$unitId
            ]
        ])
    ]
]));

$nomeFicha = json_decode($responseFicha, true)['data']['nome_produto'] ?? 'Ficha desconhecida';
$codigo_produto = json_decode($responseFicha, true)['data']['codigo_produto'] ?? null;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Ficha #<?php echo $idFicha; ?></title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- FilePond CSS e JS -->
    <link href="https://unpkg.com/filepond/dist/filepond.min.css" rel="stylesheet">
    <script src="https://unpkg.com/filepond/dist/filepond.min.js"></script>
    <style>
        /* Container principal do FilePond */
        .filepond--root {
            max-width: 100% !important;
            width: 100% !important;
        }

        /* Itens individuais */
        .filepond--item {
            width: 100% !important;
            max-height: 80px;
        }

        /* Previews de imagem */
        .filepond--file {
            max-height: 80px;
            overflow: hidden;
        }

        .filepond--image-preview {
            height: 80px !important;
            width: auto !important;
            object-fit: cover;
            border-radius: 0.5rem;
        }

        .filepond--image-preview-wrapper {
            height: 80px !important;
            overflow: hidden;
        }

        /* Para remover largura fixa dos previews verticais */
        .filepond--file-wrapper {
            max-width: 100% !important;
        }
    </style>



</head>
<body class="bg-gray-50 text-gray-800 text-sm">

  <header class="bg-yellow-500 text-white px-4 py-3 flex justify-between items-center shadow-md">
    <span class="font-semibold text-sm">Olá, <?php echo htmlspecialchars($dadosUsuario['name']); ?></span>
    <a href="home.php" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded text-sm font-semibold">
      Voltar ao Início
    </a>
    <form method="POST" action="logout.php">
      <button class="bg-white text-yellow-600 text-xs px-3 py-1 rounded font-semibold shadow hover:bg-yellow-100 transition">Sair</button>
    </form>
  </header>

  <main class="p-4 space-y-4">
    <div class="bg-white p-4 rounded-lg shadow">
      <h1 class="text-yellow-600 font-bold text-sm">Ficha de Manipulação</h1>
      <p class="text-xs text-gray-600"><?php echo $nomeFicha; ?></p>
    </div>

    <div class="bg-white p-4 rounded-lg shadow space-y-4">
      <div>
        <label class="block font-medium text-gray-700 mb-1">Peso Bruto do Produto</label>
        <input inputmode="numeric" id="peso-bruto" type="text" class="w-full border rounded p-2 text-right" placeholder="Ex: 2.500" />
      </div>

      <div id="itens-container" class="space-y-3"></div>

      <div>
        <label class="block font-medium text-gray-700 mb-1">Descarte</label>
        <input id="descarte"  type="text" readonly class="w-full border rounded p-2 text-right bg-gray-100" />
      </div>

      <div>
        <label class="block font-medium text-gray-700 mb-1">Fotos (opcional)</label>
          <input id="fotos" type="file" multiple class="filepond" name="fotos[]" />
      </div>

      <div>
        <label class="block font-medium text-gray-700 mb-1">Observações</label>
        <textarea id="observacao" rows="3" class="w-full border rounded p-2"></textarea>
      </div>

      <button id="btn-enviar" class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600 w-full font-semibold">Enviar Dados</button>
    </div>
  </main>

  <script>
    const baseUrl = location.hostname === 'localhost'
      ? 'http://localhost/portal-deck/api/v1/index.php'
      : 'https://portal.vemprodeck.com.br/api/v1/index.php';


    const baseUrlredirect = window.location.hostname !== 'localhost' ?
        'https://vemprodeck.com.br/manipulacao' :
        'http://localhost/manipulacao-deck';

    const fichaId = <?php echo json_encode($idFicha); ?>;
    const codigoProduto = <?php echo json_encode($codigo_produto); ?>;
    const systemUnitId = <?php echo json_encode($dadosUsuario['system_unit_id']); ?>;
    const operador = <?php echo json_encode($dadosUsuario['id']); ?>;
    const token = <?php echo json_encode($dadosUsuario['token']); ?>;

    function mascararParaTresDecimais(event) {
        let valor = event.target.value.replace(/[^\d]/g, '');
        if (valor.length < 4) {
            valor = valor.padStart(4, '0');
        }
        const inteiros = valor.slice(0, -3);
        const decimais = valor.slice(-3);
        event.target.value = `${parseInt(inteiros)}.${decimais}`;
        atualizarDescarte();
    }


    function limparFormulario() {
        pesoBrutoField.value = '';
        descarteField.value = '';
        document.getElementById('observacao').value = '';

        // Limpa campos de peso dos itens
        itensFicha.forEach(item => {
            const input = document.getElementById(`peso-item-${item.id}`);
            if (input) input.value = '';
        });

        // Limpa FilePond
        const pondInstance = FilePond.find(document.querySelector('#fotos'));
        if (pondInstance) {
            pondInstance.removeFiles();
        }
    }



    let itensFicha = [];
    const descarteField = document.getElementById('descarte');
    const pesoBrutoField = document.getElementById('peso-bruto');
    pesoBrutoField.addEventListener('input', mascararParaTresDecimais);
    const btnEnviar = document.getElementById('btn-enviar');

    function formatarNumero(num) {
      return parseFloat(num).toFixed(3);
    }

    function atualizarDescarte() {
      const pesoBruto = parseFloat(pesoBrutoField.value.replace(',', '.')) || 0;
      const somaItens = itensFicha.reduce((soma, item) => {
        const input = document.getElementById(`peso-item-${item.id}`);
        return soma + (parseFloat(input.value.replace(',', '.')) || 0);
      }, 0);
      const descarte = pesoBruto - somaItens;
      descarteField.value = formatarNumero(descarte);

      if (descarte < 0) {
        descarteField.classList.add('text-red-600', 'font-bold');
      } else {
        descarteField.classList.remove('text-red-600', 'font-bold');
      }
    }

    async function carregarItensFicha() {
      Swal.fire({ title: 'Carregando ficha...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

      try {
        const res = await axios.post(baseUrl, {
          method: 'listarItensFicha',
          data: { id_ficha: fichaId, system_unit_id: systemUnitId }
        }, {
          headers: { 'Authorization': token, 'Content-Type': 'application/json' }
        });

        Swal.close();

        if (res.data.success) {
          itensFicha = res.data.data;
          const container = document.getElementById('itens-container');
          container.innerHTML = '';

          itensFicha.forEach(item => {
            const div = document.createElement('div');
            div.innerHTML = `
              <label class="block text-xs font-medium text-gray-600 mb-1">${item.produto_nome}</label>
              <input inputmode="numeric" id="peso-item-${item.id}" type="text" class="w-full border rounded p-2 text-right" placeholder="Peso em kg" />
            `;
            container.appendChild(div);
            const inputEl = document.getElementById(`peso-item-${item.id}`);
            inputEl.addEventListener('input', mascararParaTresDecimais);
          });
        }
      } catch (err) {
        Swal.close();
        Swal.fire('Erro', 'Não foi possível carregar a ficha.', 'error');
      }
    }

    let numeroDocumento = null;

btnEnviar.addEventListener('click', async () => {
    const pesoBruto = parseFloat(pesoBrutoField.value.replace(',', '.')) || 0;
    const descarte = parseFloat(descarteField.value) || 0;
    const obs = document.getElementById('observacao').value;

    if (descarte < 0) {
        await Swal.fire({
            icon: 'warning',
            title: 'Descarte inválido',
            text: 'O valor de descarte está negativo. Verifique os pesos informados nos insumos.',
            confirmButtonText: 'Corrigir',
        });
        return; // impede envio
    }

  const itens = itensFicha.map(item => {
  const peso = parseFloat(document.getElementById(`peso-item-${item.id}`).value.replace(',', '.')) || 0;
  return {
    'codigo_insumo': item.codigo_insumo,
    'unidade': 'KG',
    'quantidade': (peso * 1000).toFixed(0) 
  };
});


  try {
    Swal.fire({ title: 'Enviando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    const res = await axios.post(baseUrl, {
      method: 'registrarMovimentacao',
      data: {
        system_unit_id: systemUnitId,
        id_ficha: fichaId,
        codigo_produto: codigoProduto,
        operador: operador,
        peso_bruto: pesoBruto,
        descarte: descarte,
        observacao: obs,
        itens
      }
    }, {
      headers: { 'Authorization': token, 'Content-Type': 'application/json' }
    });

    if (res.data.success && res.data.documento) {
      numeroDocumento = res.data.documento;
      Swal.fire('Sucesso', `Movimentação registrada com sucesso!<br><strong>Doc: ${numeroDocumento}</strong>`, 'success');
        // Upload de arquivos após salvar movimentação
        const pondFiles = FilePond.find(document.querySelector('#fotos')).getFiles();
        if (pondFiles.length > 0 && numeroDocumento) {
            const uploadData = new FormData();
            uploadData.append('documento', numeroDocumento);
            uploadData.append('user_id', operador);
            uploadData.append('system_unit_id', systemUnitId);

            pondFiles.forEach(fileItem => {
                uploadData.append('fotos[]', fileItem.file, fileItem.file.name);
            });

            try {
                console.log('Enviando arquivos para upload...');

                const uploadRes = await axios.post(`${baseUrlredirect}/upload.php`, uploadData);

                Swal.close();

                if (uploadRes.data.success) {
                    console.log('Arquivos enviados com sucesso');
                } else {
                    console.warn('Erro ao enviar arquivos:', uploadRes.data.message);
                }

            } catch (e) {
                Swal.close();
                console.error('Erro no envio de arquivos:', e);
                Swal.fire('Erro', 'Erro ao enviar arquivos.', 'error');
                return;
            }
        }


        const fichaPayload = {
            system_unit_id: systemUnitId,
            documento: numeroDocumento
        };

        localStorage.setItem('fichaPdfData', JSON.stringify(fichaPayload));
        limparFormulario();
        window.location.href = `${baseUrlredirect}/ficha.html`;

    } else {
      Swal.fire('Aviso', 'Movimentação registrada, mas documento não retornado.', 'warning');
    }

  } catch (err) {
    Swal.fire('Erro', 'Erro ao registrar movimentação.', 'error');
  }
});


    document.addEventListener('DOMContentLoaded', carregarItensFicha);

    // Inicializa FilePond
    FilePond.create(document.querySelector('#fotos'), {
        allowMultiple: true,
        labelIdle: 'Arraste ou clique para selecionar',
        maxFiles: 5,
        acceptedFileTypes: ['image/*'],
        allowImagePreview: false, // Desativa o preview
    });

  </script>
</body>
</html>
