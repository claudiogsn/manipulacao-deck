<?php
session_start();
if (!isset($_SESSION['user'])) {
    $_SESSION['error_message'] = 'Por favor, faça login novamente.';
    header("Location: index.php");
    exit();
}

$userLogin = $_SESSION['user'];

$url = ($_SERVER['SERVER_NAME'] == "localhost")
    ? "http://localhost/portal-deck/api/v1/index.php"
    : "https://portal.vemprodeck.com.br/api/v1/index.php";

$response = file_get_contents($url, false, stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-type: application/json",
        'content' => json_encode([
            "method" => "getUserDetails",
            "data"   => ["user" => $userLogin]
        ])
    ]
]));

$dadosUsuario = json_decode($response, true)['userDetails'] ?? [];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Manipulação Deck</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    body {
      font-size: 13px;
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-800">
  <!-- Cabeçalho -->
  <header class="bg-yellow-500 text-white px-4 py-3 flex justify-between items-center shadow-md">
    <span class="font-semibold text-sm">Olá, <?php echo htmlspecialchars($dadosUsuario['name']); ?></span>
    <form method="POST" action="logout.php">
      <button class="bg-white text-yellow-600 text-xs px-3 py-1 rounded font-semibold shadow hover:bg-yellow-100 transition">Sair</button>
    </form>
  </header>

  <!-- Conteúdo principal -->
  <main class="p-4 space-y-4">
    <!-- Informações do usuário -->
    <div class="bg-white p-3 rounded-lg shadow text-sm">
       <div class="mb-2">
        <span class="block text-gray-400 text-xs">Unidade</span>
        <span class="font-medium"><?php echo $dadosUsuario['system_unit_id']; ?> - <?php echo $dadosUsuario['unit_name'] ?? ''; ?></span>
      </div>
      <div class="mb-2">
        <span class="block text-gray-400 text-xs">Função</span>
        <span class="font-medium"><?php echo $dadosUsuario['function_name']; ?></span>
      </div>
    </div>

    <!-- Fichas (serão renderizadas aqui) -->
    <div>
      <h2 class="text-yellow-600 font-bold text-sm mb-2">📋 Suas Fichas</h2>
      <section id="fichas-container" class="grid grid-cols-2 gap-3"></section>
    </div>
  </main>

  <!-- Script para buscar e exibir fichas -->
  <script>
   
    document.addEventListener('DOMContentLoaded', async () => {
      
      const fichasContainer = document.getElementById('fichas-container');

      const userId = <?php echo json_encode($dadosUsuario['id']); ?>;
      const systemUnitId = <?php echo json_encode($dadosUsuario['system_unit_id']); ?>;
      const token = <?php echo json_encode($dadosUsuario['token']); ?>;

      const baseUrl = location.hostname === 'localhost'
        ? 'http://localhost/portal-deck/api/v1/index.php'
        : 'https://portal.vemprodeck.com.br/api/v1/index.php';

      Swal.fire({
        title: 'Carregando fichas...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
      });

      try {
        const response = await axios.post(baseUrl, {
          method: 'getFichasByUser',
          data: {
            user_id: userId,
            system_unit_id: systemUnitId
          }
        }, {
          headers: {
            'Authorization': token,
            'Content-Type': 'application/json'
          }
        });

        Swal.close();

        const result = response.data;

        if (result.success && Array.isArray(result.data)) {
          if (result.data.length === 0) {
            fichasContainer.innerHTML = '<p class="col-span-2 text-center text-sm text-gray-500">Nenhuma ficha encontrada.</p>';
            return;
          }

          result.data.forEach(ficha => {
            const card = document.createElement('div');
            card.className = 'bg-white rounded-lg shadow p-3 flex flex-col justify-between hover:ring-2 hover:ring-yellow-400 transition';

            card.innerHTML = `
              <h3 class="text-xs font-bold text-yellow-600">${ficha.nome_produto}</h3>
              <p class="text-[11px] text-gray-500 mt-1">Cód: ${ficha.codigo_produto}</p>
              <p class="text-[11px] text-gray-400">Criado em: ${new Date(ficha.created_at).toLocaleDateString()}</p>
            `;

            card.addEventListener('click', () => {
              window.location.href = `ficha.php?id=${ficha.id}`;
            });


            fichasContainer.appendChild(card);
          });
        } else {
          fichasContainer.innerHTML = '<p class="col-span-2 text-center text-red-500 text-sm">Erro ao carregar fichas.</p>';
        }
      } catch (error) {
        Swal.close();
        console.error(error);
        fichasContainer.innerHTML = '<p class="col-span-2 text-center text-red-500 text-sm">Erro de conexão com o servidor.</p>';
      }
    });
  </script>
</body>
</html>
