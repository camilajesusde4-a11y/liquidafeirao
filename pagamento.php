<?php
// ** MODO DE DEBUG ATIVADO ** ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// !! MUDANÇA: 'gerador_dados.php' não é mais necessário para o cliente !!
// require_once 'gerador_dados.php';

// Define que a resposta será SEMPRE em formato JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

try {
    // Validação da extensão cURL
    if (!function_exists('curl_init')) {
        throw new Exception('Erro de servidor: A extensão cURL do PHP não está habilitada.');
    }

    // --- DEBUG 1: Logar o corpo da requisição bruta ---
    $request_body = file_get_contents('php://input');
    file_put_contents('debug_log.txt', "--- INÍCIO DA REQUISIÇÃO ---\nCorpo Bruto: " . $request_body . "\n", FILE_APPEND);
    // --- FIM DEBUG ---

    // Configurar a autenticação da Payevo
    $secretKey = 'sk_like_VLNcnLpdTr8LBry4XLd15ZBGJkXuaVpxjDkRNJ0XM8sGyMr4'; // Sua chave Payevo
    $base64Key = base64_encode($secretKey); 

    // Pega os dados enviados pelo JavaScript (front-end)
    $data = json_decode($request_body, true);

    // --- DEBUG 2: Logar o array PHP decodificado ---
    file_put_contents('debug_log.txt', "Array \$data: " . print_r($data, true) . "\n", FILE_APPEND);
    // --- FIM DEBUG ---


    // Validação dos dados recebidos
    if (!$data || !isset($data['amount'])) {
        // --- DEBUG 3: Logar a falha na validação ---
        file_put_contents('debug_log.txt', "ERRO: Validação falhou. 'amount' não está setado.\n--- FIM ---\n\n", FILE_APPEND);
        // --- FIM DEBUG ---
        throw new Exception('Dados da requisição inválidos ou faltando (amount).');
    }
    
    // Se chegou aqui, a validação passou!
    file_put_contents('debug_log.txt', "Validação OK. 'amount' encontrado: " . $data['amount'] . "\n", FILE_APPEND);

    // Pega o valor em centavos vindo do front-end
    $amountInCents = $data['amount'];

    // !! MUDANÇA: Usando os dados fixos que você forneceu !!
    $customerName = "Usuario";
    $customerCpf = "41452770824";
    $customerEmail = "usuario@gmail.com";

    // Construir o corpo (payload) para a API Payevo
   $payload = [
        'paymentMethod' => 'PIX', 
        'amount' => $amountInCents,
        
        // !! MUDANÇA AQUI !!
        // O 'document' precisa ser um objeto (array) contendo 'number'
        'customer' => [
            'name' => $customerName,
            'email' => $customerEmail,
            'document' => [
                'type' => 'CPF', // É uma boa prática já enviar o tipo
                'number' => $customerCpf
            ]
        ],
        // !! FIM DA MUDANÇA !!
        
        'items' => [
            [
                'description' => 'Produto da Loja',
                'amount' => $amountInCents, 
                'quantity' => 1
            ]
        ],
        
        'description' => 'Pedido da Loja',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ];
    
    file_put_contents('debug_log.txt', "Payload Payevo: " . json_encode($payload) . "\n", FILE_APPEND);

    // Inicia a chamada cURL
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => "https://apiv2.payevo.com.br/functions/v1/transactions",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode($payload),
        
        CURLOPT_HTTPHEADER => [
            "Accept: application/json",
            "Content-Type: application/json",
            "Authorization: Basic " . $base64Key 
        ],
    ]);

    // Executa a chamada
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    file_put_contents('debug_log.txt', "Resposta Payevo (HTTP $httpcode): " . $response . "\n--- FIM ---\n\n", FILE_APPEND);


    if ($curl_error) {
        throw new Exception('Erro de conexão com a API de pagamento: ' . $curl_error);
    }

    http_response_code($httpcode);
    echo $response;

} catch (Exception $e) {
    http_response_code(500); 
    echo json_encode([
        'error' => true,
        'message' => 'Ocorreu um erro no servidor.',
        'details' => $e->getMessage()
    ]);
}
?>