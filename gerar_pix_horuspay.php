<?php // <<-- GARANTA QUE NÃO HÁ NADA ANTES DISSO (espaços, linhas, etc.)

// --- Credenciais (Mantenha seguras!) ---
$secretKey = "sk_live_hnbc3f0NapdAlbVgubpMOcFxwcvScSSVh2amRxjlEmfyLWWw"; // SUA CHAVE SECRETA AQUI
$companyId = "9fb84a6a-4059-4e58-ab5c-764825b49dda"; // SEU COMPANY ID AQUI

// --- Configuração da Resposta ---
header('Content-Type: application/json');
error_reporting(0); // Suprime warnings/notices que quebrariam o JSON. Use logs em produção.

// --- Receber Dados do JavaScript ---
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);

// --- Validação Mínima ---
if (json_last_error() !== JSON_ERROR_NONE || !$input || !isset($input['valor']) || !is_numeric($input['valor']) || $input['valor'] < 1 || !isset($input['cliente'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Dados inválidos recebidos. Verifique o valor e os dados do cliente.']);
    exit;
}

// --- Dados do Pagamento ---
$valorDecimal = (float)$input['valor'];
$valorEmCentavos = (int)round($valorDecimal * 100);

// --- **MUDANÇA AQUI**: Pegando dados do cliente do JSON ---
$clienteInput = $input['cliente'];
$clienteInfoDescricao = $clienteInput['name'] ?? 'Cliente';

// Remove caracteres não numéricos do CPF, CEP e Telefone
$cpfLimpo = preg_replace('/\D/', '', $clienteInput['cpf'] ?? '00000000000');
$cepLimpo = preg_replace('/\D/', '', $clienteInput['cep'] ?? '00000000');
$telefoneLimpo = preg_replace('/\D/', '', $clienteInput['phone'] ?? '00000000000');


// --- Dados do Cliente (Agora dinâmicos) ---
$customerData = [
    "name" => $clienteInput['name'] ?? 'Nome não informado',
    "email" => $clienteInput['email'] ?? 'email@naoinformado.com',
    "document" => [
        "number" => $cpfLimpo,
        "type" => "CPF"
    ],
    "address" => [
        "street" => $clienteInput['address'] ?? 'Rua não informada',
        "zipCode" => $cepLimpo,
        "neighborhood" => $clienteInput['neighborhood'] ?? 'Bairro não informado',
        "city" => $clienteInput['city'] ?? 'Cidade não informada',
        "state" => $clienteInput['state'] ?? 'XX', // Estado (UF)
        "country" => "BR",
        "number" => $clienteInput['number'] ?? 'S/N', // Adicionando o número
        "complement" => $clienteInput['complement'] ?? '' // Adicionando complemento
    ]
    // Verifique a documentação da HorusPay se 'phone' deve ir aqui ou em outro lugar.
];

// --- Itens da Transação ---
$itemsData = [
    [
        "title" => "Pedido Loja Cinho Imports", // Nome mais descritivo
        "unitPrice" => $valorEmCentavos,
        "quantity" => 1,
    ]
];

// --- Monta o Payload FINAL para a HorusPay ---
$payloadData = [
    "customer" => $customerData,
    "paymentMethod" => "PIX",
    "items" => $itemsData,
    "amount" => $valorEmCentavos,
    "postbackUrl" => "https://seudominio.com/notificacao_horus.php", // <<< SUBSTITUA PELA SUA URL REAL!
    "description" => substr("Pedido de: " . $clienteInfoDescricao, 0, 140), 
    "externalRef" => "CINHO-" . uniqid() . "-" . time() 
];

// --- Lógica cURL (sem alterações) ---
$credentials = base64_encode($secretKey . ':' . $companyId);
$headers = [
    'Authorization: Basic ' . $credentials,
    'Content-Type: application/json'
];
$data = json_encode($payloadData); 

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => "https://api.gateway.horuspay.co/functions/v1/transactions",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "", 
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 45, 
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "POST", 
    CURLOPT_POSTFIELDS => $data,
    CURLOPT_HTTPHEADER => $headers,
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$curlError = curl_error($curl);
curl_close($curl);

// --- Tratamento da Resposta (sem alterações) ---
if ($response === false) { 
    http_response_code(500);
    echo json_encode([
        'error' => 'Falha na comunicação com o gateway de pagamento.',
        'curlError' => $curlError
    ]);

} elseif ($httpCode >= 200 && $httpCode < 300) { 
    $result = json_decode($response, true);

    if (json_last_error() === JSON_ERROR_NONE && isset($result['pix']['qrcode'])) {
        http_response_code($httpCode); 

        $pixCopiaCola = $result['pix']['qrcode'];
        $transactionId = $result['id'] ?? null;

        $qrCodeUrl = 'https://gerarqrcodepix.com.br/api/v1?brcode=' . urlencode($pixCopiaCola);

        $pixData = [
            'pix_code' => $pixCopiaCola,
            'qr_code_url' => $qrCodeUrl,
            'transaction_id' => $transactionId
        ];
        echo json_encode($pixData);

    } else {
        http_response_code(500);
        echo json_encode([
            'error' => 'Resposta da API recebida, mas dados PIX (qrcode) não encontrados ou formato inválido.',
            'jsonError' => json_last_error_msg(),
            'apiResponseRecebida' => $result ?? $response
        ]);
    }

} else { 
    http_response_code($httpCode); 
    $errorData = json_decode($response, true);
    echo json_encode([
        'error' => 'API de pagamento retornou um erro.',
        'httpCode' => $httpCode,
        'apiDetails' => $errorData ?? ['rawResponse' => $response] 
    ]);
}

exit; 
?>