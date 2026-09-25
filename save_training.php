<?php
// save_training.php
header('Content-Type: application/json; charset=utf-8');

// Ontvang de JSON payload vanuit de fetch applicatie
$rawInput = file_get_contents('php://input');
$requestData = json_decode($rawInput, true);

if (!$requestData || !isset($requestData['data']) || !isset($requestData['format'])) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Ongeldige gegevens ontvangen.'
    ]);
    exit;
}

$format = strtolower($requestData['format']); // 'json' of 'yaml'
$training = $requestData['data'];
$datum = isset($training['datum']) ? preg_replace('/[^0-9\-]/', '', $training['datum']) : date('Y-m-d');

// Zorg dat de map 'trainingen' bestaat
$dir = __DIR__ . '/trainingen';
if (!is_dir($dir)) {
    if (!mkdir($dir, 0755, true)) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Kan de map /trainingen niet aanmaken op de server.'
        ]);
        exit;
    }
}

// Bepaal de bestandsnaam op basis van de datum
$extension = ($format === 'yaml') ? 'yaml' : 'json';
$filepath = $dir . '/' . $datum . '.' . $extension;

// Converteer naar gewenste indeling
if ($format === 'yaml') {
    // Eenvoudige, schone YAML generator in PHP
    function arrayToYaml($array, $indent = 0) {
        $yaml = '';
        $prefix = str_repeat('  ', $indent);
        
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                // Controleer of het een numerieke lijst is
                if (array_keys($value) === range(0, count($value) - 1)) {
                    $yaml .= "{$prefix}{$key}:\n";
                    foreach ($value as $item) {
                        if (is_array($item)) {
                            $yaml .= "{$prefix}  -\n";
                            foreach ($item as $subKey => $subVal) {
                                $yaml .= "{$prefix}    {$subKey}: " . (is_numeric($subVal) ? $subVal : "\"{$subVal}\"") . "\n";
                            }
                        } else {
                            $yaml .= "{$prefix}  - \"{$item}\"\n";
                        }
                    }
                } else {
                    $yaml .= "{$prefix}{$key}:\n" . arrayToYaml($value, $indent + 1);
                }
            } else {
                $yaml .= "{$prefix}{$key}: " . (is_numeric($value) ? $value : "\"{$value}\"") . "\n";
            }
        }
        return $yaml;
    }
    
    $content = arrayToYaml($training);
} else {
    // Mooi geformatteerde JSON
    $content = json_encode($training, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

// Sla het bestand op
if (file_put_contents($filepath, $content) !== false) {
    echo json_encode([
        'status' => 'success',
        'message' => "Training succesvol opgeslagen op de server!",
        'filename' => 'trainingen/' . $datum . '.' . $extension,
        'format' => strtoupper($format)
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Fout bij het schrijven van het bestand naar de server.'
    ]);
}
?>