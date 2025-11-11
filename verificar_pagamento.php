<?php
// MODO DE DEBUG ATIVADO
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

try {
    if (!function_exists('curl_init')) {
        throw new Exception('Erro de servidor: A extensão cURL do PHP não está habilitada.');
    }

    // --- ETAPA 1: Obter o ID da transação enviado pelo JavaScript ---

    $request_body = file_get_contents('php://input');
    $data = json_decode($request_body, true);

    // O script.js envia 'transaction_id' (que agora é o ID da Payevo)
    if (!$data || !isset($data['transaction_id'])) {
        throw new Exception("'transaction_id' (Payevo ID) não foi fornecido na requisição.");
    }
    $id_payevo = $data['transaction_id']; // Ex: "847616a5-0b1c-..."


    // --- ETAPA 2: Configurar a autenticação Payevo (Basic Auth) ---
    
    // !! IMPORTANTE !!
    // Coloque aqui A MESMA chave secreta que você usou no 'pagamento.php'
    $secretKey = 'sk_like_VLNcnLpdTr8LBry4XLd15ZBGJkXuaVpxjDkRNJ0XM8sGyMr4'; // <--- COLOQUE SUA CHAVE AQUI
    
    $base64Key = base64_encode($secretKey);


    // --- ETAPA 3: Preparar e executar a chamada cURL para a Payevo ---

    // URL CORRETA, conforme a documentação:
    $apiUrl = "https://apiv2.payevo.com.br/functions/v1/transactions/" . urlencode($id_payevo);

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "GET", // É uma requisição GET
        CURLOPT_HTTPHEADER => [
            "Accept: application/json",
            "Content-Type: application/json",
            // Autenticação Basic Auth da Payevo (NÃO mais X-API-Key)
            "Authorization: Basic " . $base64Key 
        ],
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        throw new Exception('Erro de conexão com a API de pagamento: ' . $curl_error);
    }

    if ($httpcode >= 400) {
        // Se a API retornar 404 (Not Found), pode ser que o PIX ainda não foi processado
        // Tratamos como pendente para o script.js tentar de novo
        if ($httpcode == 404) {
             echo json_encode(['status' => 'PENDENTE']);
             exit;
        }
        throw new Exception("A API de pagamento retornou um erro ($httpcode): " . $response);
    }
    
    // --- ETAPA 4: Interpretar a resposta da API e enviar para o front-end ---

    $responseData = json_decode($response, true);

    // Pelos logs, o campo de status se chama 'status'
    $apiStatus = $responseData['status'] ?? 'pending'; // ex: "waiting_payment" ou "paid"

    $frontendStatus = 'PENDENTE';
    
    // Assumindo que o status de sucesso da Payevo é 'paid'
    // (Oposto de 'waiting_payment' que vimos no log de criação)
    $successStatus = ['paid', 'approved', 'completed']; // Status de sucesso comuns
    
    if (in_array(strtolower($apiStatus), $successStatus)) {
        $frontendStatus = 'PAGO';
    }

    echo json_encode(['status' => $frontendStatus]);

} catch (Exception $e) {
    http_response_code(500); 
    echo json_encode([
        'status' => 'ERRO',
        'message' => 'Ocorreu um erro no servidor ao verificar o pagamento.',
        'details' => $e->getMessage()
    ]);
}
?>